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
				'q'    => $address,
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
