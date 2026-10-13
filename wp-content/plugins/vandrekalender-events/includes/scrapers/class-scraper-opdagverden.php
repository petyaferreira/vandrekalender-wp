<?php

defined( 'ABSPATH' ) || exit;

/**
 * Scraper for day walks from opdagverden.dk (Opdag Verden).
 *
 * The site is a Laravel app with Inertia (since autumn 2026): every page
 * carries its data as JSON in the `data-page` attribute of the app element.
 * Day walks are listed at /faellesture/dagsvandringer (paginated). Each trip
 * has a detail page /faellesture/<slug> with the description, the meeting
 * point (`location`) and its upcoming dates, each with its own price. A trip
 * walked on several dates becomes one event per date, keyed by
 * /faellesture/<slug>#<date>.
 *
 * Meeting points are landmark names ("Stevns klint", "Æbelø"); the
 * Adressevælger address search resolves them to the nearest real address,
 * giving approximate coordinates and the correct municipality.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Scraper_Opdagverden extends Vandrekalender_Scraper_Base {

	const BASE_URL      = 'https://www.opdagverden.dk';
	const SOURCE_URL    = 'https://www.opdagverden.dk/faellesture/dagsvandringer';
	const SOURCE_NAME   = 'Opdag Verden';
	const ORGANISER_URL = 'https://www.opdagverden.dk/';

	/**
	 * Fetch every page of the day-walk listing and return its trips as JSON.
	 *
	 * @return string JSON array of trip objects, or empty string on failure.
	 */
	protected function fetch(): string {
		$trips = [];
		$page  = 1;

		do {
			$props = $this->page_props( $this->remote_get( self::SOURCE_URL . '?page=' . $page ) );
			if ( ! isset( $props['trips']['data'] ) || ! is_array( $props['trips']['data'] ) ) {
				// A failed page must not look like a short list, or the
				// stale sweep would draft the trips on the missing pages.
				return '';
			}

			$trips     = array_merge( $trips, $props['trips']['data'] );
			$last_page = (int) ( $props['trips']['last_page'] ?? 1 );
			++$page;
		} while ( $page <= $last_page && $page <= 10 );

		return (string) wp_json_encode( $trips );
	}

	/**
	 * Build one event per upcoming date of every listed trip.
	 *
	 * @param string $html JSON from fetch().
	 * @return array
	 */
	protected function parse( string $html ): array {
		$trips = json_decode( $html, true );
		if ( ! is_array( $trips ) ) {
			return [];
		}

		$events = [];

		foreach ( $trips as $listed ) {
			if ( ! is_array( $listed ) || empty( $listed['slug'] ) || empty( $listed['upcoming_dates'] ) ) {
				continue;
			}

			$trip_url = self::BASE_URL . '/faellesture/' . $listed['slug'];

			// Mark the listed dates first, so a detail page that fails to
			// load is not mistaken for removed events.
			foreach ( $listed['upcoming_dates'] as $date ) {
				$this->mark_source_url_seen( $trip_url . '#' . ( $date['start_date'] ?? '' ) );
			}

			$props = $this->page_props( $this->remote_get( $trip_url ) );
			if ( ! isset( $props['trip'] ) || ! is_array( $props['trip'] ) ) {
				continue;
			}

			foreach ( $this->parse_trip( $props['trip'], $trip_url ) as $event ) {
				$events[] = $event;
			}
		}

		return $events;
	}

	/**
	 * Read the Inertia page data (the `data-page` JSON) of a page.
	 *
	 * @param string $html Raw HTML of the page.
	 * @return array The page's props, or empty array if missing.
	 */
	private function page_props( string $html ): array {
		if ( ! preg_match( '/\sdata-page="([^"]*)"/', $html, $match ) ) {
			return [];
		}

		$page = json_decode( html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), true );

		return is_array( $page['props'] ?? null ) ? $page['props'] : [];
	}

	/**
	 * Build the events for a trip, one per upcoming date.
	 *
	 * @param array  $trip     The `trip` prop of the detail page.
	 * @param string $trip_url The trip's page URL.
	 * @return array Event arrays.
	 */
	private function parse_trip( array $trip, string $trip_url ): array {
		// Source titles carry a stray comma before the distance, e.g.
		// "Svanninge Bakker, (18 km)" — tidy it to "Svanninge Bakker (18 km)".
		$title = html_entity_decode( (string) ( $trip['title'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$title = (string) preg_replace( '/\s*,\s*\(/u', ' (', $title );
		$title = trim( (string) preg_replace( '/\s+/u', ' ', $title ), " \t\n\r\0\x0B," );
		if ( '' === $title ) {
			return [];
		}

		$description = (string) ( $trip['description'] ?? '' );
		$text        = html_entity_decode( wp_strip_all_tags( $description ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Distance from the title, e.g. "(21 km)" / "(25km)" -> 21 / 25.
		$distance = '';
		if ( preg_match( '/(\d+)\s*km/iu', $title, $dist_match ) ) {
			$distance = (string) (int) $dist_match[1];
		}

		// Start time from the description: "Start: Kl.10 ved ..." / "Start: kl. 9.30".
		$start_time = '';
		if ( preg_match( '/Start\s*:?\s*kl\.?\s*(\d{1,2})(?:[.:](\d{2}))?/iu', $text, $time_match ) ) {
			$start_time = sprintf( '%02d:%s', (int) $time_match[1], $time_match[2] ?? '00' );
		}

		// Meeting point / location name.
		$place = trim( html_entity_decode( (string) ( $trip['location']['name'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		$excerpt = trim( (string) ( $trip['hero']['description'] ?? '' ) );
		if ( '' === $excerpt ) {
			$excerpt = wp_trim_words( $text, 40, '…' );
		}

		// Resolved once per trip: every date meets at the same place.
		$geo    = $this->resolve_location( $title, $place );
		$region = $this->region_from_categories( is_array( $trip['categories'] ?? null ) ? $trip['categories'] : [] );
		$slug   = (string) ( $trip['slug'] ?? '' );
		$events = [];

		foreach ( is_array( $trip['upcoming_dates'] ?? null ) ? $trip['upcoming_dates'] : [] as $date_row ) {
			$date = (string) ( $date_row['start_date'] ?? '' );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || 'cancelled' === ( $date_row['status'] ?? '' ) ) {
				continue;
			}

			// Non-member price of this date; a free walk becomes "0".
			$price = (string) (int) round( (float) ( $date_row['price'] ?? $trip['price'] ?? 0 ) );

			$url = $trip_url . '#' . $date;

			$event = [
				'post_title'                               => $title,
				'post_content'                             => $description,
				'post_excerpt'                             => $excerpt,
				'featured_image_url'                       => (string) ( $trip['image'] ?? '' ),
				\Vandrekalender\Event::META_DATE           => $date,
				// Single route per event: distance and time live here, not as flat meta.
				\Vandrekalender\Event::META_ROUTES         => [
					[
						'id'          => '' !== $distance ? 'route-' . $distance : 'route-1',
						'distance_km' => $distance,
						'start_time'  => $start_time,
						'cutoff_time' => '',
						'price'       => $price,
					],
				],
				\Vandrekalender\Event::META_PLACE_NAME     => $place,
				\Vandrekalender\Event::META_ADDRESS        => $place,
				\Vandrekalender\Event::META_ORGANISER_NAME => self::SOURCE_NAME,
				\Vandrekalender\Event::META_ORGANISER_URL  => self::ORGANISER_URL,
				\Vandrekalender\Event::META_SOURCE_URL     => $url,
				\Vandrekalender\Event::META_SOURCE_NAME    => self::SOURCE_NAME,
			];

			if ( null !== $geo ) {
				// Coordinates are usable even when the point does not fall
				// inside a kommune polygon (coastal/island landmarks), so keep
				// them regardless of whether a municipality was resolved.
				$event[ \Vandrekalender\Event::META_LAT ] = $geo['lat'];
				$event[ \Vandrekalender\Event::META_LNG ] = $geo['lng'];
			}

			if ( null !== $geo && '' !== $geo['municipality'] ) {
				$event[ \Vandrekalender\Event::META_MUNICIPALITY ] = $geo['municipality'];
			} elseif ( '' !== $region ) {
				// No municipality. Region normally derives from it, so writing
				// an empty value would clear the region term; instead use the
				// trip's region category so the event stays in the filters.
				$event['tax_terms'] = [ \Vandrekalender\Event::TAX_REGION => [ $region ] ];
			}

			// Each date used to be its own page at
			// /ture/dagsvandring/<region>/<slug>-<id>.
			$this->adopt_legacy_source_url(
				$url,
				$date,
				'#^https://www\.opdagverden\.dk/ture/dagsvandring/[a-z]+/' . preg_quote( $slug, '#' ) . '-\d+$#'
			);

			$events[] = $event;
		}

		return $events;
	}

	/**
	 * Resolve coordinates and municipality for an event's landmark meeting point.
	 *
	 * Builds an ordered list of query candidates from the event title and the
	 * meeting point, then tries the place-name register for each, and
	 * finally the address search. The first hit wins.
	 *
	 * @param string $title Event title, e.g. "Møns Klint: Klintekongens Rige (15 km)".
	 * @param string $place The meeting point (the trip's location name).
	 * @return array|null Array of { lat, lng, municipality }, or null if unresolved.
	 */
	private function resolve_location( string $title, string $place ): ?array {
		$geocoder   = new Vandrekalender_Geocoder();
		$candidates = $this->geocode_candidates( $title, $place );

		foreach ( $candidates as $candidate ) {
			$geo = $geocoder->geocode_place( $candidate );
			if ( null !== $geo ) {
				return $geo;
			}
		}

		foreach ( $candidates as $candidate ) {
			$geo = $geocoder->geocode( $candidate );
			if ( null !== $geo ) {
				return $geo;
			}
		}

		return null;
	}

	/**
	 * Build an ordered, de-duplicated list of landmark query strings to geocode.
	 *
	 * The title's leading segment (before a colon, with a trailing "(21 km)"
	 * removed) is the most reliable landmark; the meeting point is a fallback.
	 * Each is also offered in a cleaned form with tour words ("rundt",
	 * "dagstur") and secondary segments stripped.
	 *
	 * @param string $title Event title.
	 * @param string $place The meeting point (the trip's location name).
	 * @return string[] Ordered unique candidate strings.
	 */
	private function geocode_candidates( string $title, string $place ): array {
		$title_base = (string) preg_replace( '/\s*\([^)]*\)\s*/u', ' ', $title );
		$title_base = trim( (string) preg_split( '/:/u', $title_base )[0] );

		$candidates = [
			$title_base,
			$this->clean_place( $title_base ),
			$this->clean_place( $place ),
			$place,
		];

		$candidates = array_filter( array_map( 'trim', $candidates ) );

		return array_values( array_unique( $candidates ) );
	}

	/**
	 * Reduce a location string to its leading landmark name for geocoding.
	 *
	 * Drops parentheticals, keeps only the first segment before a comma, ampersand,
	 * slash or " og ", and strips trailing tour words like "rundt" or "dagstur".
	 *
	 * @param string $value Raw location or title string.
	 * @return string Cleaned landmark name.
	 */
	private function clean_place( string $value ): string {
		$value = (string) preg_replace( '/\([^)]*\)/u', ' ', $value );
		$value = (string) preg_split( '/[,&\/:]| og /u', $value )[0];
		$value = (string) preg_replace( '/\b(rundtur|rundt|rundsted|weekendtur|dagstur|tur)\b/iu', ' ', $value );

		return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
	}

	/**
	 * Derive a region taxonomy slug from the trip's region category.
	 *
	 * Trips carry Opdag Verden's own geographic grouping as a category
	 * ("storkobenhavn", "midtjylland", ...). This maps those to the site's
	 * five official regions. Used only as a fallback when geocoding finds no
	 * municipality.
	 *
	 * @param array $categories The trip's categories, each with a `slug`.
	 * @return string Region slug (e.g. "syddanmark"), or empty string if unknown.
	 */
	private function region_from_categories( array $categories ): string {
		$map = [
			'storkobenhavn' => 'hovedstaden',
			'kobenhavn'     => 'hovedstaden',
			'nordsjaelland' => 'hovedstaden',
			'bornholm'      => 'hovedstaden',
			'sjaelland'     => 'sjaelland',
			'fyn'           => 'syddanmark',
			'sydjylland'    => 'syddanmark',
			'jylland'       => 'midtjylland',
			'midtjylland'   => 'midtjylland',
			'vestjylland'   => 'midtjylland',
			'nordjylland'   => 'nordjylland',
		];

		foreach ( $categories as $category ) {
			$slug = (string) ( $category['slug'] ?? '' );
			if ( isset( $map[ $slug ] ) ) {
				return $map[ $slug ];
			}
		}

		return '';
	}
}
