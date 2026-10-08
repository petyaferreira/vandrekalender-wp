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
 * Adressevælger has no reverse geocoding, so reverse() and
 * municipality_from_coords() ask Datafordeler's DAR register (GraphQL, API
 * key in VANDREKALENDER_DATAFORDELER_API_KEY) for the nearest address point.
 * It has no place-name search either; geocode_place() returns nothing and
 * says so in the Scraper Log.
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
	const DATAFORDELER = 'https://graphql.datafordeler.dk/';
	// Square half-sides tried in turn, in metres: a street, a village, open
	// countryside. Stops at the first that contains any address point.
	const REVERSE_RADII = [ 150, 750, 3000 ];
	// DAR's `in` filter accepts at most 100 values.
	const DAR_IN_LIMIT = 100;

	/**
	 * Problems seen since the last take_issues(), message => count.
	 *
	 * @var array<string, int>
	 */
	private static $issues = [];

	/**
	 * Set when the service itself is down or overloaded (network error, 429
	 * or 5xx), so the rest of the scraper's run skips it instead of waiting
	 * on a 10 s timeout per address. Other 4xx answers (one bad address, or a
	 * bad token) come back at once and only fail that address. Reset by
	 * take_issues(), so the next scraper tries again.
	 *
	 * @var bool
	 */
	private static $unavailable = false;

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
	 * Not built yet: Adressevælger only searches addresses, and Datafordeler's
	 * place-name register (DS/v2) only matches exact spellings and keeps the
	 * geometry in ~30 category types (see docs/dawa-migration-plan.md →
	 * Place names). Callers fall back to geocode().
	 *
	 * @param string $name Place name, e.g. "Stevns Klint".
	 * @return array|null Always null for now.
	 */
	public function geocode_place( string $name ): ?array {
		if ( '' !== trim( $name ) ) {
			self::record_issue( __( 'Place-name lookup is not built yet (see docs/dawa-migration-plan.md); tried the address search instead.', 'vandrekalender-events' ) );
		}

		return null;
	}

	/**
	 * Find the municipality containing a coordinate (reverse geocoding).
	 *
	 * For sources that carry exact coordinates but whose meeting points are
	 * landmark names an address search cannot geocode forward ("Birkerød
	 * St."). The municipality is the nearest address's — see reverse().
	 *
	 * @param float $lat Latitude (WGS84).
	 * @param float $lng Longitude (WGS84).
	 * @return string Municipality name, or empty string on failure.
	 */
	public function municipality_from_coords( float $lat, float $lng ): string {
		$result = $this->reverse( $lat, $lng );

		return null !== $result ? $result['municipality'] : '';
	}

	/**
	 * The nearest address to a coordinate, with its municipality.
	 *
	 * Adressevælger has no reverse lookup, so this asks Datafordeler's DAR
	 * register for the address points inside a small square around the
	 * point (widening it when empty), takes the nearest, and looks its house
	 * number up in Adressevælger for the address text and municipality code.
	 * Points far out in nature still get the nearest address within a few
	 * kilometres, so the municipality (and region) is right in practice even
	 * though the address is only "near".
	 *
	 * @param float $lat Latitude (WGS84).
	 * @param float $lng Longitude (WGS84).
	 * @return array|null Array of { address: string, municipality: string, distance: float (metres) }, or null.
	 */
	public function reverse( float $lat, float $lng ): ?array {
		// Round to ~10 m so nearby lookups share a cache entry.
		$cache_key = self::CACHE_PREFIX . 'rev_' . round( $lat, 4 ) . '_' . round( $lng, 4 );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$point   = Vandrekalender_Utm_Converter::from_lat_lng( $lat, $lng );
		$nearest = null;

		foreach ( self::REVERSE_RADII as $radius ) {
			$candidates = $this->address_points_near( $point['x'], $point['y'], $radius );
			if ( null === $candidates ) {
				return null;
			}
			foreach ( $candidates as &$candidate ) {
				$candidate['distance'] = hypot( $candidate['x'] - $point['x'], $candidate['y'] - $point['y'] );
			}
			unset( $candidate );
			usort( $candidates, fn( $a, $b ) => $a['distance'] <=> $b['distance'] );

			// Not every address point is a current house number's access
			// point (road points, retired house numbers), so look the house
			// numbers up for the nearest batch and take the nearest that has one.
			$candidates = array_slice( $candidates, 0, self::DAR_IN_LIMIT );
			$husnumre   = $this->current_husnumre_at( array_column( $candidates, 'id' ) );
			if ( null === $husnumre ) {
				return null;
			}
			foreach ( $candidates as $candidate ) {
				if ( isset( $husnumre[ $candidate['id'] ] ) ) {
					$nearest = $candidate + [ 'husnummer' => $husnumre[ $candidate['id'] ] ];
					break 2;
				}
			}
		}

		if ( null === $nearest ) {
			set_transient( $cache_key, 'none', self::NO_MATCH_TTL );
			return null;
		}

		$husnummer_id = $nearest['husnummer'];
		$lookup       = $this->request( '/husnumre/' . rawurlencode( $husnummer_id ) );
		if ( null === $lookup ) {
			return null;
		}

		$code   = (string) ( $lookup['husnummer']['navngivenvejkommunedel']['kommune'] ?? '' );
		$result = [
			'address'      => (string) ( $lookup['husnummer']['adgangsadressebetegnelse'] ?? '' ),
			'municipality' => Vandrekalender_Municipalities::name( $code ),
			'distance'     => round( $nearest['distance'] ),
		];

		set_transient( $cache_key, $result, self::CACHE_TTL );
		return $result;
	}

	/**
	 * Current DAR address points inside a square around a UTM 32N point.
	 *
	 * @param float $x      Easting in metres.
	 * @param float $y      Northing in metres.
	 * @param int   $radius Half the square's side, in metres.
	 * @return array|null List of { id: string, x: float, y: float }, or null on failure.
	 */
	private function address_points_near( float $x, float $y, int $radius ): ?array {
		$wkt = sprintf(
			'POLYGON((%1$.2f %2$.2f, %3$.2f %2$.2f, %3$.2f %4$.2f, %1$.2f %4$.2f, %1$.2f %2$.2f))',
			$x - $radius,
			$y - $radius,
			$x + $radius,
			$y + $radius
		);

		$data = $this->request_datafordeler(
			'DAR/v3',
			sprintf(
				'{ DAR_Adressepunkt(first: 200, virkningstid: "%1$s", registreringstid: "%1$s", where: { position: { within: { wkt: "%2$s", crs: 25832 } } }) { nodes { id_lokalId status position { wkt } } } }',
				gmdate( 'Y-m-d\TH:i:s\Z' ),
				$wkt
			)
		);
		if ( null === $data ) {
			return null;
		}

		$points = [];
		foreach ( $data['DAR_Adressepunkt']['nodes'] ?? [] as $node ) {
			// Status 8 is a current point; 9 is retired.
			if ( isset( $node['id_lokalId'], $node['position']['wkt'] )
				&& '8' === (string) ( $node['status'] ?? '' )
				&& preg_match( '/POINT\s*\(\s*([\d.]+)\s+([\d.]+)/i', (string) $node['position']['wkt'], $m ) ) {
				$points[] = [
					'id' => (string) $node['id_lokalId'],
					'x'  => (float) $m[1],
					'y'  => (float) $m[2],
				];
			}
		}

		return $points;
	}

	/**
	 * Current house numbers (adgangsadresser) whose access points are among
	 * these address points.
	 *
	 * @param string[] $address_point_ids DAR_Adressepunkt id_lokalId values, at most DAR_IN_LIMIT.
	 * @return array<string, string>|null House number id keyed by its access point id, or null on failure.
	 */
	private function current_husnumre_at( array $address_point_ids ): ?array {
		if ( empty( $address_point_ids ) ) {
			return [];
		}

		$ids  = array_map( fn( $id ) => preg_replace( '/[^0-9a-f-]/i', '', (string) $id ), $address_point_ids );
		$data = $this->request_datafordeler(
			'DAR/v3',
			sprintf(
				'{ DAR_Husnummer(first: %1$d, virkningstid: "%2$s", registreringstid: "%2$s", where: { adgangspunkt: { in: %3$s } }) { nodes { id_lokalId adgangspunkt status } } }',
				count( $ids ) * 2,
				gmdate( 'Y-m-d\TH:i:s\Z' ),
				wp_json_encode( array_values( $ids ) )
			)
		);
		if ( null === $data ) {
			return null;
		}

		$map = [];
		foreach ( $data['DAR_Husnummer']['nodes'] ?? [] as $node ) {
			// Status 3 is a current house number (the ones Adressevælger returns).
			if ( isset( $node['id_lokalId'], $node['adgangspunkt'] ) && '3' === (string) ( $node['status'] ?? '' ) ) {
				$map[ (string) $node['adgangspunkt'] ] = (string) $node['id_lokalId'];
			}
		}

		return $map;
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

		self::$issues      = [];
		self::$unavailable = false;
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
	 * Send an HTTP request, retrying once on a network error.
	 *
	 * A single dropped connection is common enough that it should not trip
	 * the "service unavailable" switch for the rest of the run; two in a
	 * row is treated as a real outage by the callers.
	 *
	 * @param string $method 'GET' or 'POST'.
	 * @param string $url    Full URL.
	 * @param array  $args   wp_remote_request() arguments.
	 * @return array|WP_Error
	 */
	private static function http( string $method, string $url, array $args ) {
		$args['method'] = $method;
		$response       = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$response = wp_remote_request( $url, $args );
		}

		return $response;
	}

	/**
	 * Run a GraphQL query against a Datafordeler register.
	 *
	 * Failures are recorded as issues and never cached; network errors, 429
	 * and 5xx also stop further lookups for the run, like request().
	 *
	 * @param string $register Register and version, e.g. "DAR/v3".
	 * @param string $query    GraphQL query.
	 * @return array|null The response's `data`, or null on any failure.
	 */
	private function request_datafordeler( string $register, string $query ): ?array {
		$key = defined( 'VANDREKALENDER_DATAFORDELER_API_KEY' ) ? (string) VANDREKALENDER_DATAFORDELER_API_KEY : '';
		if ( '' === $key ) {
			self::record_issue( __( 'Datafordeler is not configured (VANDREKALENDER_DATAFORDELER_API_KEY is missing), so no municipality or address could be found from coordinates.', 'vandrekalender-events' ) );
			return null;
		}
		if ( self::$unavailable ) {
			self::record_issue( __( 'Skipped an address lookup because the address search failed earlier in this run.', 'vandrekalender-events' ) );
			return null;
		}

		$response = self::http(
			'POST',
			self::DATAFORDELER . $register . '?apiKey=' . rawurlencode( $key ),
			[
				'timeout'    => 15,
				'user-agent' => 'Vandrekalender/1.0 (+https://allevandreture.dk)',
				'headers'    => [
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				],
				'body'       => wp_json_encode( [ 'query' => $query ] ),
			]
		);

		if ( is_wp_error( $response ) ) {
			self::record_issue(
				sprintf(
					/* translators: %s: error message from the HTTP request. */
					__( 'Datafordeler request failed: %s', 'vandrekalender-events' ),
					// The key is in the URL; never let it reach the Scraper Log.
					str_replace( $key, '***', $response->get_error_message() )
				)
			);
			self::$unavailable = true;
			return null;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $status || 403 === $status ) {
			self::record_issue( __( 'Datafordeler rejected the API key (expired, wrong, or created less than 15 minutes ago). See docs/deployment.md → Datafordeler API key.', 'vandrekalender-events' ) );
			self::$unavailable = true;
			return null;
		}
		if ( 200 !== $status ) {
			self::record_issue(
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Datafordeler returned HTTP %d.', 'vandrekalender-events' ),
					$status
				)
			);
			if ( 429 === $status || $status >= 500 ) {
				self::$unavailable = true;
			}
			return null;
		}
		if ( ! empty( $body['errors'] ) || ! isset( $body['data'] ) || ! is_array( $body['data'] ) ) {
			self::record_issue(
				sprintf(
					/* translators: %s: first error message returned by the GraphQL API. */
					__( 'Datafordeler query failed: %s', 'vandrekalender-events' ),
					(string) ( $body['errors'][0]['message'] ?? __( 'unexpected response', 'vandrekalender-events' ) )
				)
			);
			return null;
		}

		return $body['data'];
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

		if ( self::$unavailable ) {
			self::record_issue( __( 'Skipped an address lookup because the address search failed earlier in this run.', 'vandrekalender-events' ) );
			return null;
		}

		$params['token'] = $token;

		$response = self::http(
			'GET',
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
			self::$unavailable = true;
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
			if ( 429 === $status || $status >= 500 ) {
				self::$unavailable = true;
			}
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
