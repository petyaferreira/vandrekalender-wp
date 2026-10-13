<?php

defined( 'ABSPATH' ) || exit;

/**
 * Scraper for walking events from mammutmarch.dk.
 *
 * The site is a Next.js app (since autumn 2026) backed by a public JSON API,
 * the same one its own pages call from the browser. The event list
 * (`/events?country=dk`) gives name, date, address, city, distances and the
 * image; the variants of an event (`/events/{id}/variants`, one per distance
 * and package) give the price per distance. Both need an `x-locale` header.
 * The API has no public single-event endpoint, so the full description is
 * read from the event page itself (`/events/<slug>`).
 *
 * @package Vandrekalender
 */
class Vandrekalender_Scraper_Mammut extends Vandrekalender_Scraper_Base {

	const API_URL       = 'https://mm-backend-production-f4af.up.railway.app/api';
	const EVENT_URL     = 'https://mammutmarch.dk/events/';
	const SOURCE_NAME   = 'Mammutmarch';
	const ORGANISER_URL = 'https://mammutmarch.dk/';

	/**
	 * Locale header the API requires; 'dk' gives Danish texts and DKK prices.
	 */
	const API_HEADERS = [ 'x-locale' => 'dk' ];

	/**
	 * Fetch every page of the Danish event list and return the events as JSON.
	 *
	 * @return string JSON array of event objects, or empty string on failure.
	 */
	protected function fetch(): string {
		$events = [];
		$page   = 1;

		do {
			$body = $this->remote_get( self::API_URL . '/events?country=dk&page=' . $page, self::API_HEADERS );
			$data = json_decode( $body, true );
			if ( ! is_array( $data ) || ! isset( $data['data']['events'] ) || ! is_array( $data['data']['events'] ) ) {
				// A failed page must not look like a short list, or the
				// stale sweep would draft the events on the missing pages.
				return '';
			}

			$events = array_merge( $events, $data['data']['events'] );
			$more   = ! empty( $data['data']['pagination']['hasNextPage'] );
			++$page;
		} while ( $more && $page <= 10 );

		return (string) wp_json_encode( $events );
	}

	/**
	 * Build event arrays from the API's event list.
	 *
	 * @param string $html JSON from fetch().
	 * @return array
	 */
	protected function parse( string $html ): array {
		$list = json_decode( $html, true );
		if ( ! is_array( $list ) ) {
			return [];
		}

		$events = [];

		foreach ( $list as $item ) {
			// v1 scope is Denmark only; the Danish list also carries the
			// Berlin, Stockholm and Göteborg marches.
			if ( ! is_array( $item ) || 'DK' !== ( $item['eventCountry'] ?? '' ) || empty( $item['slug'] ) ) {
				continue;
			}

			$url = self::EVENT_URL . $item['slug'];

			// A cancelled march is left unseen, so the stale sweep drafts it.
			if ( 'CANCELLED' === ( $item['effectiveStatus'] ?? $item['status'] ?? '' ) ) {
				continue;
			}

			$this->mark_source_url_seen( $url );

			$event = $this->build_event( $item, $url );
			if ( null === $event ) {
				continue;
			}

			// Event pages used to live at /<n>-km-marsch/<slug>/.
			$this->adopt_legacy_source_url(
				$url,
				$event[ \Vandrekalender\Event::META_DATE ],
				'#^https://mammutmarch\.dk/\d+-km-marsch/' . preg_quote( (string) $item['slug'], '#' ) . '/$#'
			);

			$events[] = $event;
		}

		return $events;
	}

	/**
	 * Build a canonical event array from one event of the API list.
	 *
	 * @param array  $item Event object from the API.
	 * @param string $url  The event page URL (dedup key).
	 * @return array|null Event array, or null if it lacks a title or date, or a
	 *                    request for its prices or description failed (the
	 *                    event is then left as stored until the next run).
	 */
	private function build_event( array $item, string $url ): ?array {
		$title = trim( (string) ( $item['name'] ?? '' ) );
		$date  = substr( (string) ( $item['date'] ?? '' ), 0, 10 );
		if ( '' === $title || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return null;
		}

		$variants    = $this->fetch_variants( (int) ( $item['id'] ?? 0 ) );
		$description = $this->fetch_description( $url );
		if ( null === $variants || null === $description ) {
			return null;
		}

		// One event_routes entry per distance.
		$distances = array_map( 'intval', is_array( $item['distances'] ?? null ) ? $item['distances'] : [] );
		$distances = array_values( array_unique( array_filter( $distances ) ) );
		sort( $distances );

		$routes = [];
		foreach ( $distances as $km ) {
			$routes[] = [
				'id'          => 'route-' . $km,
				'distance_km' => (string) $km,
				'start_time'  => $variants[ $km ]['start_time'] ?? '',
				'cutoff_time' => '',
				'price'       => $variants[ $km ]['price'] ?? '',
			];
		}

		// "Tankrogen, Marselisborg Havnevej 1, 8000 Aarhus, Danmark": drop the
		// country, then split into a street address for geocoding and a place
		// name (a meeting-point prefix, or the town). Without a postcode the
		// address is not announced yet ("Vil blive annonceret senere.").
		$raw_address = trim( (string) preg_replace( '/,\s*Danmark\s*$/iu', '', (string) ( $item['address'] ?? '' ) ) );
		$address     = '';
		$place       = trim( (string) ( $item['city'] ?? '' ) );
		if ( preg_match( '/\b\d{4}\s+\S/u', $raw_address ) ) {
			$parts     = array_map( 'trim', explode( ',', $raw_address ) );
			$city_part = (string) array_pop( $parts );
			$street    = ! empty( $parts ) ? (string) array_pop( $parts ) : '';
			$prefix    = implode( ', ', $parts );
			$address   = trim( $street . ', ' . $city_part, ', ' );
			$place     = '' !== $prefix ? $prefix : trim( (string) preg_replace( '/^\s*\d{4}\s+/u', '', $city_part ) );
		} elseif ( preg_match( '/annonce|offentliggø/iu', $raw_address ) ) {
			// Publish anyway, but label the location so the card is not
			// blank. The next scrape overwrites this once it is announced.
			$place = 'Annonceres senere';
		}

		$event = [
			'post_title'                               => $title,
			'post_content'                             => $description['content'],
			'post_excerpt'                             => $description['excerpt'],
			'featured_image_url'                       => (string) ( $item['image'] ?? '' ),
			\Vandrekalender\Event::META_DATE           => $date,
			\Vandrekalender\Event::META_ROUTES         => $routes,
			\Vandrekalender\Event::META_PLACE_NAME     => $place,
			\Vandrekalender\Event::META_ADDRESS        => $address,
			\Vandrekalender\Event::META_ORGANISER_NAME => self::SOURCE_NAME,
			\Vandrekalender\Event::META_ORGANISER_URL  => self::ORGANISER_URL,
			\Vandrekalender\Event::META_SOURCE_URL     => $url,
			\Vandrekalender\Event::META_SOURCE_NAME    => self::SOURCE_NAME,
		];

		// Geocode the start address server-side via Adressevælger.
		if ( '' !== $address ) {
			$geo = ( new Vandrekalender_Geocoder() )->geocode( $address );
			if ( null !== $geo ) {
				$event[ \Vandrekalender\Event::META_LAT ]          = $geo['lat'];
				$event[ \Vandrekalender\Event::META_LNG ]          = $geo['lng'];
				$event[ \Vandrekalender\Event::META_MUNICIPALITY ] = $geo['municipality'];
			}
		}

		return $event;
	}

	/**
	 * Price and start time per distance, from the event's variants.
	 *
	 * Each distance comes as several variants: participation only, or with an
	 * event shirt and other extras. `basePrice` (in øre) is the participation
	 * price, the same for every variant of a distance, so the lowest one is
	 * taken. Start times are only set once Mammut announces the start groups.
	 *
	 * @param int $event_id API event ID.
	 * @return array|null Price and start time keyed by distance in km, or null
	 *                    if the request failed.
	 */
	private function fetch_variants( int $event_id ): ?array {
		if ( $event_id <= 0 ) {
			return null;
		}

		$data = json_decode( $this->remote_get( self::API_URL . '/events/' . $event_id . '/variants', self::API_HEADERS ), true );
		if ( ! is_array( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return null;
		}

		$by_distance = [];
		foreach ( $data['data'] as $variant ) {
			$km = (int) ( $variant['distance'] ?? 0 );
			if ( $km <= 0 || 'DKK' !== ( $variant['price']['currency'] ?? '' ) ) {
				continue;
			}

			$by_distance[ $km ] = $by_distance[ $km ] ?? [
				'price'      => '',
				'start_time' => '',
			];

			$price = (int) round( (int) ( $variant['price']['basePrice'] ?? $variant['basePrice'] ?? 0 ) / 100 );
			if ( $price > 0 && ( '' === $by_distance[ $km ]['price'] || $price < (int) $by_distance[ $km ]['price'] ) ) {
				$by_distance[ $km ]['price'] = (string) $price;
			}

			if ( '' === $by_distance[ $km ]['start_time'] && preg_match( '/(\d{1,2})[:.](\d{2})/', (string) ( $variant['startTime'] ?? '' ), $time ) ) {
				$by_distance[ $km ]['start_time'] = sprintf( '%02d:%s', (int) $time[1], $time[2] );
			}
		}

		return $by_distance;
	}

	/**
	 * Read the description from the event page, plus a short excerpt.
	 *
	 * The page shows it under "Om dette Arrangement", in the first element
	 * with the class `tiptap-content` (the site's rich-text output).
	 *
	 * @param string $url Event page URL.
	 * @return array|null Array of { content: string, excerpt: string }, or null
	 *                    if the page could not be fetched.
	 */
	private function fetch_description( string $url ): ?array {
		$html = $this->remote_get( $url );
		if ( '' === $html ) {
			return null;
		}

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();

		$xpath      = new DOMXPath( $dom );
		$nodes      = $xpath->query( "(//*[contains(concat(' ', normalize-space(@class), ' '), ' tiptap-content ')])[1]//p" );
		$paragraphs = [];

		foreach ( $nodes ? $nodes : [] as $node ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$paragraph = trim( (string) preg_replace( '/\s+/u', ' ', $node->textContent ) );
			if ( '' !== $paragraph ) {
				$paragraphs[] = $paragraph;
			}
		}

		$content = '';
		foreach ( $paragraphs as $paragraph ) {
			$content .= '<p>' . esc_html( $paragraph ) . '</p>';
		}

		$excerpt = ! empty( $paragraphs ) ? wp_trim_words( implode( ' ', $paragraphs ), 40, '…' ) : '';

		return [
			'content' => $content,
			'excerpt' => $excerpt,
		];
	}
}
