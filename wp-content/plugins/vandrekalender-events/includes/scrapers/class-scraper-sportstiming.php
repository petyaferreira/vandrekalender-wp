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
 * v1 scope: title, date, image, description, organiser and source link. The
 * location, distances and price are unstructured on Sportstiming and are left
 * for a later enrichment pass, so these events publish without a map pin.
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
		}

		if ( '' === $title ) {
			return null;
		}

		return [
			'post_title'                               => $title,
			'post_content'                             => '' !== $description ? '<p>' . esc_html( $description ) . '</p>' : '',
			'post_excerpt'                             => $description,
			'featured_image_url'                       => $image,
			\Vandrekalender\Event::META_DATE           => $date,
			\Vandrekalender\Event::META_ROUTES         => $routes,
			\Vandrekalender\Event::META_ORGANISER_NAME => self::SOURCE_NAME,
			\Vandrekalender\Event::META_ORGANISER_URL  => $page_url,
			\Vandrekalender\Event::META_SOURCE_URL     => $source_url,
			\Vandrekalender\Event::META_SOURCE_NAME    => self::SOURCE_NAME,
		];
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
	 * Best-effort distances: a comma-separated "N km" list on the detail page.
	 *
	 * @param string $html Page HTML.
	 * @return array event_routes entries (distance only), or empty array.
	 */
	private function extract_distances( string $html ): array {
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
