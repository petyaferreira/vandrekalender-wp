<?php

defined( 'ABSPATH' ) || exit;

/**
 * Scraper for walking events from sportstiming.dk.
 *
 * The events page is JavaScript-rendered, but the listing is backed by a JSON
 * endpoint (General/EventList/SearchEvents) that returns upcoming events for a
 * given activity. We request the "Walk" activity (which also covers "Hike"),
 * keep only events typed exactly Walk or Hike (excluding multi-sport entries
 * like "Run, Walk"), then read each event's detail page for its og: metadata.
 *
 * v1 scope: title, date, image, description, organiser and source link. Each
 * distance's length, start time and adult price come from the detail page's
 * distance accordion when present. The location is still unstructured, so these
 * events publish without a map pin.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Scraper_Sportstiming extends Vandrekalender_Scraper_Base {

	const BASE_URL    = 'https://www.sportstiming.dk';
	const API_URL     = 'https://www.sportstiming.dk/General/EventList/SearchEvents?type=Coming&eventTypes=Walk&maxResults=200&page=1&federation=&keyword=';
	const SOURCE_NAME = 'Sportstiming';

	/**
	 * Fetch the walking-events listing as JSON from the search endpoint.
	 *
	 * @return string JSON body, or empty string on failure.
	 */
	protected function fetch(): string {
		$response = wp_remote_get(
			self::API_URL,
			[
				'timeout'    => 20,
				'user-agent' => 'Vandrekalender/1.0 (+https://vandrekalender.dk)',
				'headers'    => [
					'Accept'           => 'application/json',
					'X-Requested-With' => 'XMLHttpRequest',
					'Referer'          => self::BASE_URL . '/events',
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Parse the listing JSON, keep Walk/Hike events, and build event arrays.
	 *
	 * @param string $json JSON body from fetch().
	 * @return array
	 */
	protected function parse( string $json ): array {
		$data = json_decode( $json, true );

		if ( empty( $data['Events'] ) || ! is_array( $data['Events'] ) ) {
			return [];
		}

		$events = [];
		$seen   = [];

		foreach ( $data['Events'] as $item ) {
			$type = trim( (string) ( $item['EventTypeName'] ?? '' ) );

			// Walk + Hike only. Multi-sport entries ("Run, Walk", etc.) contain a
			// comma and are excluded.
			if ( 'Walk' !== $type && 'Hike' !== $type ) {
				continue;
			}

			$link = (string) ( $item['LinkEvent'] ?? '' );
			$date = (string) ( $item['Date'] ?? '' );
			if ( '' === $link || '' === $date ) {
				continue;
			}

			$page_url = self::BASE_URL . $link;
			// Some events run on several dates under the same link; make the dedup
			// key unique per occurrence so the dates are not collapsed into one.
			$source_url = $page_url . '#' . $date;
			if ( isset( $seen[ $source_url ] ) ) {
				continue;
			}
			$seen[ $source_url ] = true;

			$event = $this->build_event( $page_url, $source_url, (string) ( $item['Name'] ?? '' ), $date );
			if ( null !== $event ) {
				$events[] = $event;
			}
		}

		return $events;
	}

	/**
	 * Build a canonical event array from the detail page's og: metadata.
	 *
	 * @param string $page_url   The event detail page URL.
	 * @param string $source_url Unique dedup key (page URL + date).
	 * @param string $name       Event name from the listing (fallback title).
	 * @param string $date       Event date (YYYY-MM-DD).
	 * @return array|null
	 */
	private function build_event( string $page_url, string $source_url, string $name, string $date ): ?array {
		$html        = $this->remote_get( $page_url );
		$title       = trim( $name );
		$description = '';
		$image       = '';
		$routes      = [];
		$place       = '';
		$address     = '';

		if ( '' !== $html ) {
			$og_title = $this->og( $html, 'title' );
			if ( '' !== $og_title ) {
				$title = $og_title;
			}

			$description = $this->og( $html, 'description' );

			$candidate = $this->og( $html, 'image' );
			// Skip the platform's generic logo/placeholder images.
			if ( '' !== $candidate && false === stripos( $candidate, '/images/sportstiming' ) && false === stripos( $candidate, 'logo' ) ) {
				$image = $candidate;
			}

			$routes = $this->extract_distances( $html );

			$location = $this->extract_location( $html );
			if ( null !== $location ) {
				$place   = $location['place'];
				$address = $location['address'];
			}
		}

		if ( '' === $title ) {
			return null;
		}

		$event = [
			'post_title'                               => $title,
			'post_content'                             => '' !== $description ? '<p>' . esc_html( $description ) . '</p>' : '',
			'post_excerpt'                             => $description,
			'featured_image_url'                       => $image,
			\Vandrekalender\Event::META_DATE           => $date,
			\Vandrekalender\Event::META_ROUTES         => $routes,
			\Vandrekalender\Event::META_PLACE_NAME     => $place,
			\Vandrekalender\Event::META_ADDRESS        => $address,
			\Vandrekalender\Event::META_ORGANISER_NAME => self::SOURCE_NAME,
			\Vandrekalender\Event::META_ORGANISER_URL  => $page_url,
			\Vandrekalender\Event::META_SOURCE_URL     => $source_url,
			\Vandrekalender\Event::META_SOURCE_NAME    => self::SOURCE_NAME,
		];

		// Geocode the meeting point server-side via DAWA so the event gets a map
		// pin and (through the municipality) a region. Danish addresses only;
		// bare city names or foreign venues simply return no match.
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
	 * Read an Open Graph meta value, tolerant of attribute order and quoting.
	 *
	 * @param string $html Page HTML.
	 * @param string $name og property name without the "og:" prefix.
	 * @return string Decoded value, or empty string.
	 */
	private function og( string $html, string $name ): string {
		if ( preg_match( '#<meta[^>]+(?:property|name)=["\']og:' . preg_quote( $name, '#' ) . '["\'][^>]*>#i', $html, $tag )
			&& preg_match( '#content=["\']([^"\']*)["\']#i', $tag[0], $content ) ) {
			return trim( html_entity_decode( $content[1], ENT_QUOTES, 'UTF-8' ) );
		}
		return '';
	}

	/**
	 * Extract the event's meeting point from the detail page.
	 *
	 * Prefers the page's schema.org Event JSON-LD (a structured Place with an
	 * address); falls back to the plain-text "Sted:" line. Returns a place name
	 * and a geocodable address, or null when neither is present.
	 *
	 * @param string $html Page HTML.
	 * @return array{place: string, address: string}|null
	 */
	private function extract_location( string $html ): ?array {
		$location = $this->location_from_jsonld( $html );
		if ( null !== $location ) {
			return $location;
		}

		return $this->location_from_text( $html );
	}

	/**
	 * Read the meeting point from the page's schema.org Event JSON-LD.
	 *
	 * @param string $html Page HTML.
	 * @return array{place: string, address: string}|null
	 */
	private function location_from_jsonld( string $html ): ?array {
		if ( ! preg_match_all( '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $blocks ) ) {
			return null;
		}

		foreach ( $blocks[1] as $raw ) {
			$data = json_decode( trim( $raw ), true );
			if ( ! is_array( $data ) || ! isset( $data['location'] ) || ! is_array( $data['location'] ) ) {
				continue;
			}

			$place_node = $data['location'];
			$address    = is_array( $place_node['address'] ?? null ) ? $place_node['address'] : [];
			$street     = trim( (string) ( $address['streetAddress'] ?? '' ) );
			$city       = trim( (string) ( $address['addressLocality'] ?? '' ) );
			$name       = trim( (string) ( $place_node['name'] ?? '' ) );

			$full = trim( implode( ', ', array_filter( [ $street, $city ] ) ), ', ' );
			if ( '' === $full && '' === $name ) {
				continue;
			}

			return [
				'place'   => '' !== $name ? $name : ( '' !== $city ? $city : $street ),
				'address' => '' !== $full ? $full : $name,
			];
		}

		return null;
	}

	/**
	 * Read the meeting point from a plain-text "Sted: <address>" line.
	 *
	 * @param string $html Page HTML.
	 * @return array{place: string, address: string}|null
	 */
	private function location_from_text( string $html ): ?array {
		$text = html_entity_decode( wp_strip_all_tags( $html, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = (string) preg_replace( '/\s+/u', ' ', $text );

		if ( ! preg_match( '/Sted:\s*(.+?)\s*(?:Tidsplan|Tilmelding|Praktisk|Ruterne)/u', $text, $match ) ) {
			return null;
		}

		$address = trim( $match[1] );
		if ( '' === $address ) {
			return null;
		}

		// Place name = the street part before the postal code and city.
		$parts = array_map( 'trim', explode( ',', $address ) );

		return [
			'place'   => '' !== $parts[0] ? $parts[0] : $address,
			'address' => $address,
		];
	}

	/**
	 * Extract each distance's length, start time and price from the detail page.
	 *
	 * Prefers the structured distance accordion (which carries per-distance
	 * prices); falls back to a plain comma-separated "N km" list when the page
	 * has no accordion.
	 *
	 * @param string $html Page HTML.
	 * @return array event_routes entries, or empty array.
	 */
	private function extract_distances( string $html ): array {
		$routes = $this->extract_from_accordion( $html );
		if ( ! empty( $routes ) ) {
			return $routes;
		}

		return $this->extract_distance_list( $html );
	}

	/**
	 * Parse the detail page's distance accordion into routes with prices.
	 *
	 * Each distance is a panel: the length lives in the panel body's "road" row,
	 * the start time in the "clock" row, and the adult price in the first price
	 * table. Distance headings can be labels ("Halvmaraton"), so the length is
	 * read from the body rather than the heading.
	 *
	 * @param string $html Page HTML.
	 * @return array event_routes entries, or empty array when no accordion.
	 */
	private function extract_from_accordion( string $html ): array {
		$pos = stripos( $html, 'distanceAccordion' );
		if ( false === $pos ) {
			return [];
		}

		// Each distance panel body starts at its own distanceCollapse id, so
		// splitting on those ids yields one segment per distance (the first
		// segment is the markup before the first panel and is dropped).
		$segments = preg_split( '#id=["\']distanceCollapse\d+["\']#i', substr( $html, $pos ) );
		if ( ! is_array( $segments ) || count( $segments ) < 2 ) {
			return [];
		}
		array_shift( $segments );

		$routes = [];
		$seen   = [];
		foreach ( $segments as $segment ) {
			$km = $this->panel_distance( $segment );
			if ( '' === $km || isset( $seen[ $km ] ) ) {
				continue;
			}
			$seen[ $km ] = true;
			$routes[]    = [
				'id'          => 'dist-' . $km,
				'distance_km' => $km,
				'start_time'  => $this->panel_start_time( $segment ),
				'cutoff_time' => '',
				'price'       => $this->panel_price( $segment ),
			];
		}

		return $routes;
	}

	/**
	 * Read a panel's distance in km from its "road" row.
	 *
	 * @param string $segment Panel body markup.
	 * @return string Normalised km, or empty string.
	 */
	private function panel_distance( string $segment ): string {
		if ( ! preg_match( '#fa fa-road.*?<td[^>]*>\s*([\d.,]+)\s*km#is', $segment, $match ) ) {
			return '';
		}
		$km = (string) (float) str_replace( ',', '.', $match[1] );

		return '0' === $km ? '' : $km;
	}

	/**
	 * Read a panel's start time from its "clock" row.
	 *
	 * @param string $segment Panel body markup.
	 * @return string Start time (HH:MM), or empty string.
	 */
	private function panel_start_time( string $segment ): string {
		if ( preg_match( '#fa fa-clock-o.*?<td[^>]*>\s*Starter\s*([\d:]+)#is', $segment, $match ) ) {
			return trim( $match[1] );
		}

		return '';
	}

	/**
	 * Read a panel's cheapest current adult price.
	 *
	 * Uses the first price table (the adult "Voksen" tier when split from a
	 * child price), ignoring struck-through past date tiers and the separate
	 * handling fee, then takes the lowest remaining amount.
	 *
	 * @param string $segment Panel body markup.
	 * @return string Normalised price, or empty string when none is listed.
	 */
	private function panel_price( string $segment ): string {
		if ( ! preg_match( '#<table[^>]*class="[^"]*table-condensed[^"]*"[^>]*>.*?</table>#is', $segment, $table ) ) {
			return '';
		}

		// Drop expired tiers (rendered struck-through) before reading amounts.
		$markup = preg_replace( '#<strike>.*?</strike>#is', '', $table[0] );

		if ( ! preg_match_all( '#([\d.]+(?:,\d+)?)\s*DKK#i', $markup, $amounts ) ) {
			return '';
		}

		$prices = [];
		foreach ( $amounts[1] as $raw ) {
			// Danish formatting: dots group thousands, comma is the decimal.
			$prices[] = (float) str_replace( ',', '.', str_replace( '.', '', $raw ) );
		}

		return empty( $prices ) ? '' : (string) min( $prices );
	}

	/**
	 * Fallback distances: a comma-separated "N km" list on the detail page.
	 *
	 * @param string $html Page HTML.
	 * @return array event_routes entries (distance only), or empty array.
	 */
	private function extract_distance_list( string $html ): array {
		$text = html_entity_decode( wp_strip_all_tags( $html, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Two or more "N km" values separated by commas (the distance list).
		if ( ! preg_match( '/(\d{1,3}(?:[.,]\d+)?\s*km(?:\s*,\s*\d{1,3}(?:[.,]\d+)?\s*km)+)/iu', $text, $list ) ) {
			return [];
		}

		preg_match_all( '/(\d{1,3}(?:[.,]\d+)?)\s*km/iu', $list[1], $nums );

		$routes = [];
		$seen   = [];
		foreach ( $nums[1] as $raw ) {
			$km = (string) (float) str_replace( ',', '.', $raw );
			if ( '0' === $km || isset( $seen[ $km ] ) ) {
				continue;
			}
			$seen[ $km ] = true;
			$routes[]    = [
				'id'          => 'dist-' . $km,
				'distance_km' => $km,
				'start_time'  => '',
				'cutoff_time' => '',
				'price'       => '',
			];
		}

		return $routes;
	}
}
