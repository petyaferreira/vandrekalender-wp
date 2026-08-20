<?php

defined( 'ABSPATH' ) || exit;

/**
 * Server-side geocoding via DAWA (Danmarks Adressers Web API).
 *
 * Turns a free-text Danish address into coordinates and a municipality name.
 * Scrapers run in PHP with no browser, so they cannot reuse the block editor's
 * client-side DAWA integration; this is the server-side equivalent.
 *
 * Uses the DAWA autocomplete endpoint (the same one the editor uses) rather
 * than a strict address lookup, because scraped meeting-point addresses are
 * often approximate (e.g. a house number that does not exist exactly), and
 * autocomplete returns the nearest real address with coordinates. Results are
 * cached as transients so repeat scrapes do not re-hit the API.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Geocoder {

	const AUTOCOMPLETE = 'https://api.dataforsyningen.dk/autocomplete';
	const STEDNAVNE    = 'https://api.dataforsyningen.dk/stednavne2';
	const KOMMUNE      = 'https://api.dataforsyningen.dk/kommuner/';
	const CACHE_PREFIX = 'vk_geocode_';
	const CACHE_TTL    = MONTH_IN_SECONDS;

	/**
	 * Geocode a Danish address string.
	 *
	 * @param string $address Free-text address, e.g. "Marselisborg Havnevej 1, 8000 Aarhus".
	 * @return array|null Array of { lat: float, lng: float, municipality: string }, or null on failure.
	 */
	public function geocode( string $address ): ?array {
		$address = trim( $address );
		if ( '' === $address ) {
			return null;
		}

		$cache_key = self::CACHE_PREFIX . md5( $address );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$url = add_query_arg(
			[
				'type' => 'adresse',
				'q'    => rawurlencode( $address ),
			],
			self::AUTOCOMPLETE
		);

		$response = wp_remote_get(
			$url,
			[
				'timeout'    => 10,
				'user-agent' => 'Vandrekalender/1.0 (+https://vandrekalender.dk)',
				'headers'    => [ 'Accept' => 'application/json' ],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Brief negative cache so a transient outage does not stall the scrape.
			set_transient( $cache_key, 'none', HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $data[0]['data']['x'], $data[0]['data']['y'] ) ) {
			set_transient( $cache_key, 'none', HOUR_IN_SECONDS );
			return null;
		}

		$match  = $data[0]['data'];
		$result = [
			'lat'          => (float) $match['y'],
			'lng'          => (float) $match['x'],
			'municipality' => $this->kommune_name( (string) ( $match['kommunekode'] ?? '' ) ),
		];

		set_transient( $cache_key, $result, self::CACHE_TTL );
		return $result;
	}

	/**
	 * Geocode a Danish landmark or place name via DAWA's place-name register.
	 *
	 * For sources whose meeting point is a natural feature or area rather than a
	 * street address ("Stevns Klint", "Mols Bjerge", "Tisvilde Hegn") — which the
	 * address autocomplete cannot resolve. Returns the feature's representative
	 * point (visueltcenter) and the municipality that point falls in.
	 *
	 * @param string $name Place name, e.g. "Stevns Klint".
	 * @return array|null Array of { lat: float, lng: float, municipality: string }, or null on failure.
	 */
	public function geocode_place( string $name ): ?array {
		$name = trim( $name );
		if ( '' === $name ) {
			return null;
		}

		$cache_key = self::CACHE_PREFIX . 'sted_' . md5( $name );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$url = add_query_arg(
			[
				'q'        => rawurlencode( $name ),
				'per_side' => 1,
				'struktur' => 'flad',
			],
			self::STEDNAVNE
		);

		$response = wp_remote_get(
			$url,
			[
				'timeout'    => 10,
				'user-agent' => 'Vandrekalender/1.0 (+https://vandrekalender.dk)',
				'headers'    => [ 'Accept' => 'application/json' ],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $cache_key, 'none', HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $data[0]['sted_visueltcenter_x'], $data[0]['sted_visueltcenter_y'] ) ) {
			set_transient( $cache_key, 'none', HOUR_IN_SECONDS );
			return null;
		}

		$lat    = (float) $data[0]['sted_visueltcenter_y'];
		$lng    = (float) $data[0]['sted_visueltcenter_x'];
		$result = [
			'lat'          => $lat,
			'lng'          => $lng,
			'municipality' => $this->municipality_from_coords( $lat, $lng ),
		];

		set_transient( $cache_key, $result, self::CACHE_TTL );
		return $result;
	}

	/**
	 * Find the municipality containing a coordinate (reverse geocoding).
	 *
	 * For sources that already provide exact coordinates but whose meeting
	 * points are landmark names DAWA cannot geocode forward ("Birkerød St.").
	 *
	 * @param float $lat Latitude (WGS84).
	 * @param float $lng Longitude (WGS84).
	 * @return string Municipality name, or empty string on failure.
	 */
	public function municipality_from_coords( float $lat, float $lng ): string {
		// Round to ~100 m so nearby lookups share a cache entry.
		$cache_key = self::CACHE_PREFIX . 'rev_' . round( $lat, 3 ) . '_' . round( $lng, 3 );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return is_string( $cached ) && 'none' !== $cached ? $cached : '';
		}

		$url = add_query_arg(
			[
				'x' => rawurlencode( (string) $lng ),
				'y' => rawurlencode( (string) $lat ),
			],
			self::KOMMUNE . 'reverse'
		);

		$response = wp_remote_get(
			$url,
			[
				'timeout'    => 10,
				'user-agent' => 'Vandrekalender/1.0 (+https://vandrekalender.dk)',
				'headers'    => [ 'Accept' => 'application/json' ],
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $cache_key, 'none', HOUR_IN_SECONDS );
			return '';
		}

		$kommune = json_decode( wp_remote_retrieve_body( $response ), true );
		$name    = isset( $kommune['navn'] ) ? (string) $kommune['navn'] : '';

		if ( '' !== $name ) {
			set_transient( $cache_key, $name, self::CACHE_TTL );
		} else {
			set_transient( $cache_key, 'none', HOUR_IN_SECONDS );
		}

		return $name;
	}

	/**
	 * Resolve a DAWA municipality code to its name, cached.
	 *
	 * @param string $kode Four-digit municipality code, e.g. "0751".
	 * @return string Municipality name, or empty string on failure.
	 */
	private function kommune_name( string $kode ): string {
		if ( '' === $kode ) {
			return '';
		}

		$cache_key = self::CACHE_PREFIX . 'kommune_' . $kode;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (string) $cached;
		}

		$response = wp_remote_get(
			self::KOMMUNE . rawurlencode( $kode ),
			[
				'timeout'    => 10,
				'user-agent' => 'Vandrekalender/1.0 (+https://vandrekalender.dk)',
				'headers'    => [ 'Accept' => 'application/json' ],
			]
		);

		$name = '';
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$kommune = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( isset( $kommune['navn'] ) ) {
				$name = (string) $kommune['navn'];
			}
		}

		if ( '' !== $name ) {
			set_transient( $cache_key, $name, YEAR_IN_SECONDS );
		}

		return $name;
	}
}
