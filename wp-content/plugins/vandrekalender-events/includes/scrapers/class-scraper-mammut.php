<?php

defined( 'ABSPATH' ) || exit;

/**
 * Scraper for walking events from mammutmarch.dk.
 *
 * The site is a WPBakery-built WordPress site. Events are listed at /shop/ and
 * each event has its own page under /<distance>-km-marsch/<slug>/. The event
 * pages are template-based with consistent text markers (Dato, Distance,
 * "Start: <time> – <address>", and a "<n> KM STANDARD ... <price> DKK" price
 * table), so this scraper parses by those markers rather than CSS selectors.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Scraper_Mammut extends Vandrekalender_Scraper_Base {

	const SOURCE_URL    = 'https://mammutmarch.dk/shop/';
	const SOURCE_NAME   = 'Mammutmarch';
	const ORGANISER_URL = 'https://mammutmarch.dk/';

	/**
	 * Fetch the shop listing page.
	 *
	 * @return string HTML body.
	 */
	protected function fetch(): string {
		return $this->remote_get( self::SOURCE_URL );
	}

	/**
	 * Parse the shop page: find event URLs, fetch each, and build event arrays.
	 *
	 * @param string $html Raw HTML from fetch().
	 * @return array
	 */
	protected function parse( string $html ): array {
		if ( ! preg_match_all( '#https://mammutmarch\.dk/\d+-km-marsch/[a-z0-9\-]+/#', $html, $matches ) ) {
			return [];
		}

		$urls   = array_unique( $matches[0] );
		$events = [];

		foreach ( $urls as $url ) {
			// v1 scope is Denmark only — skip the Berlin march.
			if ( false !== strpos( $url, 'berlin' ) ) {
				continue;
			}

			$this->mark_source_url_seen( $url );

			$page = $this->remote_get( $url );
			if ( '' === $page ) {
				continue;
			}

			$event = $this->parse_event( $page, $url );
			if ( null !== $event ) {
				$events[] = $event;
			}
		}

		return $events;
	}

	/**
	 * Parse a single event page into a canonical event array.
	 *
	 * @param string $html Raw HTML of the event page.
	 * @param string $url  The event page URL (dedup key).
	 * @return array|null Event array, or null if it lacks a title.
	 */
	private function parse_event( string $html, string $url ): ?array {
		// Decode entities so an HTML-entity dash (e.g. &#8211;) becomes a real
		// character before the "Start: <time> – <address>" regex runs.
		$text = html_entity_decode( wp_strip_all_tags( $html, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Title from og:title, minus the trailing " - Mammutmarch".
		$title = '';
		if ( preg_match( '#<meta property="og:title" content="([^"]+)"#', $html, $title_match ) ) {
			$title = html_entity_decode( $title_match[1], ENT_QUOTES, 'UTF-8' );
			$title = trim( (string) preg_replace( '/\s*-\s*Mammutmarch\s*$/u', '', $title ) );
		}
		if ( '' === $title ) {
			return null;
		}

		// Date: "Dato 15.08.2026" -> YYYY-MM-DD.
		$date = '';
		if ( preg_match( '/Dato\s+(\d{2})\.(\d{2})\.(\d{4})/u', $text, $date_match ) ) {
			$date = sprintf( '%s-%s-%s', $date_match[3], $date_match[2], $date_match[1] );
		}

		// Price table: "<n> KM STANDARD ... <price> DKK" -> distance => price.
		$prices = [];
		if ( preg_match_all( '/(\d+)\s*KM\s+STANDARD.*?([\d.]+),\d{2}\s*DKK/us', $text, $price_rows, PREG_SET_ORDER ) ) {
			foreach ( $price_rows as $row ) {
				$prices[ (int) $row[1] ] = (string) (int) str_replace( '.', '', $row[2] );
			}
		}

		// "Start: <time> – <address>" lines, in document order. Two page formats
		// exist: a single time then the address (København), and a time range
		// then a pipe then the address (Aarhus: "08:30 – 10:00 | <address>").
		// Capture the first time as the start; tolerate an optional second time
		// and a dash or pipe before the address.
		$starts = [];
		if ( preg_match_all( '/Start:\s*(\d{1,2})[:.](\d{2})(?:\s*[-–—]\s*\d{1,2}[:.]\d{2})?\s*[|–—-]?\s*(.+?,\s*\d{4}\s+[A-Za-zÆØÅæøå]+)/us', $text, $start_rows, PREG_SET_ORDER ) ) {
			foreach ( $start_rows as $row ) {
				$starts[] = [
					'time'    => sprintf( '%02d:%s', (int) $row[1], $row[2] ),
					'address' => trim( $row[3] ),
				];
			}
		}

		// Distances come from the price table; fall back to the "Distance" line.
		$distances = array_keys( $prices );
		if ( empty( $distances ) && preg_match( '/Distance\s+([0-9\/ km]+)/u', $text, $dist_line ) ) {
			preg_match_all( '/(\d+)\s*km/u', $dist_line[1], $dist_nums );
			$distances = array_map( 'intval', $dist_nums[1] );
		}
		sort( $distances );

		// One event_routes entry per distance.
		$routes = [];
		foreach ( array_values( $distances ) as $index => $km ) {
			$routes[] = [
				'id'          => 'route-' . $km,
				'distance_km' => (string) $km,
				'start_time'  => isset( $starts[ $index ] ) ? $starts[ $index ]['time'] : '',
				'cutoff_time' => '',
				'price'       => isset( $prices[ $km ] ) ? $prices[ $km ] : '',
			];
		}

		// Start address (first route) drives geocoding and the place name. The raw
		// value may be prefixed with a meeting-point name (Aarhus: "Tankrogen,
		// Marselisborg Havnevej 1, 8000 Aarhus"). Split into a clean street
		// address for geocoding and a place name (the prefix, or the town).
		$raw_address = ! empty( $starts ) ? $starts[0]['address'] : '';
		$address     = '';
		$place       = '';
		if ( '' !== $raw_address ) {
			$parts     = array_map( 'trim', explode( ',', $raw_address ) );
			$city_part = (string) array_pop( $parts );
			$street    = ! empty( $parts ) ? (string) array_pop( $parts ) : '';
			$prefix    = implode( ', ', $parts );
			$address   = trim( $street . ', ' . $city_part, ', ' );
			$city      = trim( (string) preg_replace( '/^\s*\d{4}\s+/u', '', $city_part ) );
			$place     = '' !== $prefix ? $prefix : $city;
		}

		// Source has no meeting point yet ("Start: Vil blive annonceret senere").
		// Publish anyway, but label the location so the card is not blank. The
		// next scrape overwrites this once the start point is announced.
		if ( '' === $address && preg_match( '/annonceres?\s+senere|annonceret\s+senere/iu', $text ) ) {
			$place = 'Annonceres senere';
		}

		// Description (first substantial paragraphs) and featured image URL.
		$description = $this->extract_description( $html );

		// Pick the event-specific og:image. The pages carry several: generic
		// share images on the German mammutmarsch.de domain, then the real
		// event image (a 1080x1080 branded square) on the same domain as the
		// event page. Select the one hosted on the event's own host.
		$image_url = '';
		$host      = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( '' !== $host && preg_match_all( '#<meta property="og:image" content="([^"]+)"#', $html, $image_matches ) ) {
			foreach ( $image_matches[1] as $candidate ) {
				$candidate = html_entity_decode( $candidate, ENT_QUOTES, 'UTF-8' );
				if ( false !== stripos( $candidate, $host ) ) {
					$image_url = $candidate;
				}
			}
		}

		$event = [
			'post_title'                               => $title,
			'post_content'                             => $description['content'],
			'post_excerpt'                             => $description['excerpt'],
			'featured_image_url'                       => $image_url,
			\Vandrekalender\Event::META_DATE           => $date,
			\Vandrekalender\Event::META_ROUTES         => $routes,
			\Vandrekalender\Event::META_PLACE_NAME     => $place,
			\Vandrekalender\Event::META_ADDRESS        => $address,
			\Vandrekalender\Event::META_ORGANISER_NAME => self::SOURCE_NAME,
			\Vandrekalender\Event::META_ORGANISER_URL  => self::ORGANISER_URL,
			\Vandrekalender\Event::META_SOURCE_URL     => $url,
			\Vandrekalender\Event::META_SOURCE_NAME    => self::SOURCE_NAME,
		];

		// Geocode the start address server-side via DAWA.
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
	 * Extract the first substantial paragraphs as content, plus a short excerpt.
	 *
	 * Best-effort: takes the first two paragraphs of 80+ characters, which on
	 * the Mammut event pages is the event intro. Boilerplate further down the
	 * page (testimonials, FAQ) is skipped by stopping after two paragraphs.
	 *
	 * @param string $html Raw HTML of the event page.
	 * @return array Array of { content: string, excerpt: string }.
	 */
	private function extract_description( string $html ): array {
		$paragraphs = [];

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
		libxml_clear_errors();

		foreach ( $dom->getElementsByTagName( 'p' ) as $node ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$paragraph = trim( (string) preg_replace( '/\s+/u', ' ', $node->textContent ) );
			if ( mb_strlen( $paragraph ) >= 80 ) {
				$paragraphs[] = $paragraph;
			}
			if ( count( $paragraphs ) >= 2 ) {
				break;
			}
		}

		$content = '';
		foreach ( $paragraphs as $paragraph ) {
			$content .= '<p>' . esc_html( $paragraph ) . '</p>';
		}

		$excerpt = ! empty( $paragraphs ) ? wp_trim_words( $paragraphs[0], 40, '…' ) : '';

		return [
			'content' => $content,
			'excerpt' => $excerpt,
		];
	}
}
