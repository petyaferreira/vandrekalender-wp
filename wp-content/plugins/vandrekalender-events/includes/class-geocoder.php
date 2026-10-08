<?php

defined( 'ABSPATH' ) || exit;

/**
 * Server-side geocoding via Adressevælger (adressevaelger.dk, Klimadatastyrelsen).
 *
 * Turns a free-text Danish address into coordinates and a municipality name.
 * Scrapers run in PHP with no browser, so they cannot reuse the block editor's
 * client-side address search; this is the server-side equivalent and follows
 * the same steps: search `/husnumre/soeg`, look the first house number up by
 * ID, convert its EPSG:25832 point with Vandrekalender_Utm_Converter and map
 * the municipality code through data/municipalities.json.
 *
 * The search is phonetic and forgiving, but it ignores a town name written
 * without a postcode: "Skovvejen 26, Brædstrup" ranks Skovvejen 26 in
 * Slagelse first. So the hit is chosen by the input's locality (see
 * pick_hit()) — a hit in the wrong town is rejected rather than pinned
 * hundreds of kilometres away. A house number that does not exist ("Marselisborg
 * Havnevej 1", the street only has even numbers) falls back to the nearest
 * house on the same street and postcode. Results are cached as transients so
 * repeat scrapes do not re-hit the API. Failed requests are never cached, so
 * an outage does not hide addresses until the cache expires.
 *
 * Adressevælger has no place-name search and no reverse geocoding. Both need
 * Datafordeler (PR 3 of docs/dawa-migration-plan.md); until then
 * geocode_place() and municipality_from_coords() return nothing and say so in
 * the Scraper Log.
 *
 * Problems are collected per run and attached to the scraper's row in the
 * Scraper Log (see take_issues()), so a broken lookup is visible instead of
 * silently leaving events without a map pin.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Geocoder {

	const ADRESSEVAELGER = 'https://adressevaelger.dk';
	const CACHE_PREFIX   = 'vk_geocode_';
	const CACHE_TTL      = MONTH_IN_SECONDS;
	// A genuine "no match" is cached briefly, so one scrape does not ask
	// twice for the same unresolvable address.
	const NO_MATCH_TTL = HOUR_IN_SECONDS;

	/**
	 * Problems seen since the last take_issues(), message => count.
	 *
	 * @var array<string, int>
	 */
	private static $issues = [];

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

		$locality = self::locality( $address );

		// A town without a postcode does not steer the search, so the right
		// town can rank far down ("Skolevej 5, Fanø" is hit 56): fetch more.
		$town_only = '' === $locality['postcode'] && '' !== $locality['town'];
		$hits      = $this->search( $address, $town_only ? 200 : 50 );
		if ( null === $hits ) {
			return null;
		}
		$hit = self::pick_hit( $hits, $locality );

		// The house number may not exist ("Marselisborg Havnevej 1"): with a
		// postcode, the nearest house on the same street and postcode is
		// still the right place to within a few hundred metres.
		if ( null === $hit && '' !== $locality['postcode'] ) {
			$street = self::street_name( $address );
			if ( '' !== $street ) {
				$hits = $this->search( $street . ', ' . $locality['postcode'], 50 );
				if ( null === $hits ) {
					return null;
				}
				$hit = self::pick_hit( $hits, $locality );
			}
		}

		if ( null === $hit ) {
			set_transient( $cache_key, 'none', self::NO_MATCH_TTL );
			return null;
		}

		$lookup = $this->request( '/husnumre/' . rawurlencode( (string) $hit['id'] ) );
		if ( null === $lookup ) {
			return null;
		}

		$point = $lookup['husnummer']['adgangspunkt']['koordinater'] ?? null;
		if ( ! isset( $point['x'], $point['y'] ) || ! is_numeric( $point['x'] ) || ! is_numeric( $point['y'] ) ) {
			self::record_issue( __( 'Address lookup returned no coordinates.', 'vandrekalender-events' ) );
			return null;
		}

		$code         = (string) ( $lookup['husnummer']['navngivenvejkommunedel']['kommune'] ?? '' );
		$municipality = Vandrekalender_Municipalities::name( $code );
		if ( '' === $municipality ) {
			self::record_issue(
				sprintf(
					/* translators: %s: four-digit municipality code, e.g. 0101. */
					__( 'Municipality code "%s" is not in data/municipalities.json, so the event gets no region. Add it to the file.', 'vandrekalender-events' ),
					$code
				)
			);
		}

		$coords = Vandrekalender_Utm_Converter::to_lat_lng( (float) $point['x'], (float) $point['y'] );
		$result = [
			'lat'          => $coords['lat'],
			'lng'          => $coords['lng'],
			'municipality' => $municipality,
		];

		set_transient( $cache_key, $result, self::CACHE_TTL );
		return $result;
	}

	/**
	 * Run an address search and return its house number hits.
	 *
	 * Streets without a house number are not a usable point and are left out.
	 *
	 * @param string $text Search text.
	 * @param int    $max  Number of results to ask for (the API rejects 500; 200 works).
	 * @return array|null House number hits in ranking order, or null on failure.
	 */
	private function search( string $text, int $max ): ?array {
		$search = $this->request(
			'/husnumre/soeg',
			[
				'tekst'    => $text,
				'maksimum' => $max,
			]
		);
		if ( null === $search ) {
			return null;
		}
		if ( ! isset( $search['fund'] ) || ! is_array( $search['fund'] ) ) {
			self::record_issue( __( 'Address search returned an unexpected response.', 'vandrekalender-events' ) );
			return null;
		}

		return array_values(
			array_filter(
				$search['fund'],
				fn( $hit ) => isset( $hit['type'], $hit['id'], $hit['titel'] ) && 'husnummer' === $hit['type']
			)
		);
	}

	/**
	 * Choose the hit that matches the input's locality.
	 *
	 * - Postcode given: the first hit with that postcode.
	 * - Only a town given: the first hit whose locality part contains it.
	 * - Neither (a landmark like "Æbelø"): the first hit, but only when all
	 *   hits share one postcode — otherwise the input is ambiguous and a
	 *   guess could be anywhere in Denmark.
	 *
	 * @param array                                 $hits     House number hits, in ranking order.
	 * @param array{postcode: string, town: string} $locality From locality().
	 * @return array|null The chosen hit, or null when none fits.
	 */
	private static function pick_hit( array $hits, array $locality ): ?array {
		if ( '' !== $locality['postcode'] ) {
			foreach ( $hits as $hit ) {
				if ( self::postcode_of( $hit['titel'] ) === $locality['postcode'] ) {
					return $hit;
				}
			}
			return null;
		}

		if ( '' !== $locality['town'] ) {
			$town = '/(^|[^\p{L}])' . preg_quote( mb_strtolower( $locality['town'] ), '/' ) . '($|[^\p{L}])/u';
			foreach ( $hits as $hit ) {
				// Only the part after the street, so a town name inside a
				// street name ("Koldingvej") does not count.
				$after_street = mb_strtolower( (string) strstr( $hit['titel'], ',' ) );
				if ( preg_match( $town, $after_street ) ) {
					return $hit;
				}
			}
			return null;
		}

		$postcodes = array_unique( array_map( fn( $hit ) => self::postcode_of( $hit['titel'] ), $hits ) );

		return 1 === count( $postcodes ) ? $hits[0] : null;
	}

	/**
	 * The postcode and town an address names, e.g. "Skovvejen 26, 8740
	 * Brædstrup" → 8740 / Brædstrup, "Holbergsvej 50, Kolding" → '' / Kolding.
	 *
	 * The town is the last comma-separated part without the postcode, and
	 * only when there is more than one part ("Æbelø" has no town).
	 *
	 * @param string $address Free-text address.
	 * @return array{postcode: string, town: string}
	 */
	private static function locality( string $address ): array {
		$postcode = preg_match_all( '/(?<!\d)\d{4}(?!\d)/', $address, $m ) ? (string) end( $m[0] ) : '';

		$parts = array_map( 'trim', explode( ',', $address ) );
		$town  = '';
		if ( count( $parts ) > 1 ) {
			$last = trim( (string) preg_replace( '/(?<!\d)\d{4}(?!\d)/', '', (string) end( $parts ) ) );
			$town = trim( $last, " .\t" );
		}

		return [
			'postcode' => $postcode,
			'town'     => $town,
		];
	}

	/**
	 * The street name of an address without its house number, e.g.
	 * "Marselisborg Havnevej 1, 8000 Aarhus" → "Marselisborg Havnevej".
	 *
	 * @param string $address Free-text address.
	 * @return string Empty string when the first part has no house number.
	 */
	private static function street_name( string $address ): string {
		$first = trim( explode( ',', $address )[0] );

		return preg_match( '/^(.*\D)\s+\d+\s*[a-zA-Z]?$/u', $first, $m ) ? trim( $m[1] ) : '';
	}

	/**
	 * The postcode in a hit's title, e.g. "Skovvejen 26, 8740 Brædstrup" → 8740.
	 *
	 * @param string $titel Hit title.
	 * @return string
	 */
	private static function postcode_of( string $titel ): string {
		return preg_match( '/,\s*(\d{4})\s+[^,]*$/u', $titel, $m ) ? $m[1] : '';
	}

	/**
	 * Geocode a Danish landmark or place name ("Stevns Klint", "Mols Bjerge").
	 *
	 * Not available yet: Adressevælger only searches addresses, and the
	 * place-name register needs Datafordeler (PR 3 of
	 * docs/dawa-migration-plan.md). Callers fall back to geocode().
	 *
	 * @param string $name Place name, e.g. "Stevns Klint".
	 * @return array|null Always null for now.
	 */
	public function geocode_place( string $name ): ?array {
		if ( '' !== trim( $name ) ) {
			self::record_issue( __( 'Place-name lookup is not available until Datafordeler is set up (DAWA migration PR 3); tried the address search instead.', 'vandrekalender-events' ) );
		}

		return null;
	}

	/**
	 * Find the municipality containing a coordinate (reverse geocoding).
	 *
	 * Not available yet: Adressevælger has no reverse geocoding, it needs
	 * Datafordeler (PR 3 of docs/dawa-migration-plan.md). Events keep their
	 * coordinates but get no municipality or region from this.
	 *
	 * @param float $lat Latitude (WGS84).
	 * @param float $lng Longitude (WGS84).
	 * @return string Always an empty string for now.
	 */
	public function municipality_from_coords( float $lat, float $lng ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- public signature kept for PR 3.
		self::record_issue( __( 'Municipality from coordinates is not available until Datafordeler is set up (DAWA migration PR 3); events keep their coordinates but get no municipality or region.', 'vandrekalender-events' ) );

		return '';
	}

	/**
	 * Return the problems seen since the last call and start a new list.
	 *
	 * The scraper scheduler calls this after each scraper, so every problem
	 * lands on the right scraper's row in the Scraper Log.
	 *
	 * @return string[] One line per distinct problem, with a count when repeated.
	 */
	public static function take_issues(): array {
		$lines = [];

		foreach ( self::$issues as $message => $count ) {
			$lines[] = $count > 1 ? sprintf( '%s (%d×)', $message, $count ) : $message;
		}

		self::$issues = [];
		return $lines;
	}

	/**
	 * Remember a problem for the Scraper Log.
	 *
	 * @param string $message What went wrong, for an admin reading the log.
	 * @return void
	 */
	private static function record_issue( string $message ): void {
		self::$issues[ $message ] = ( self::$issues[ $message ] ?? 0 ) + 1;
	}

	/**
	 * Call an Adressevælger endpoint and decode its JSON.
	 *
	 * Failures are recorded as issues and never cached.
	 *
	 * @param string $path   Endpoint path, e.g. "/husnumre/soeg".
	 * @param array  $params Query parameters, without the token.
	 * @return array|null Decoded response, or null on any failure.
	 */
	private function request( string $path, array $params = [] ): ?array {
		$token = defined( 'VANDREKALENDER_ADRESSEVAELGER_TOKEN' ) ? (string) VANDREKALENDER_ADRESSEVAELGER_TOKEN : '';
		if ( '' === $token ) {
			self::record_issue( __( 'Address search is not configured (VANDREKALENDER_ADRESSEVAELGER_TOKEN is missing), so no addresses were geocoded.', 'vandrekalender-events' ) );
			return null;
		}

		$params['token'] = $token;

		$response = wp_remote_get(
			self::ADRESSEVAELGER . $path . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 ),
			[
				'timeout'    => 10,
				'user-agent' => 'Vandrekalender/1.0 (+https://allevandreture.dk)',
				'headers'    => [ 'Accept' => 'application/json' ],
			]
		);

		if ( is_wp_error( $response ) ) {
			self::record_issue(
				sprintf(
					/* translators: %s: error message from the HTTP request. */
					__( 'Address search request failed: %s', 'vandrekalender-events' ),
					$response->get_error_message()
				)
			);
			return null;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			self::record_issue(
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Address search returned HTTP %d.', 'vandrekalender-events' ),
					$status
				)
			);
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			self::record_issue( __( 'Address search returned an unexpected response.', 'vandrekalender-events' ) );
			return null;
		}

		return $data;
	}
}
