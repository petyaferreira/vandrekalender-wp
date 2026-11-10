<?php

defined( 'ABSPATH' ) || exit;

/**
 * REST API endpoints for reading walking events.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Event_Rest_Api {

	const NAMESPACE = 'vandrekalender/v1';

	// Organiser filter value that matches no walk: sanitize_title() makes it
	// '', and a tax_query for no terms finds nothing. See presets_for().
	const NO_ORGANISER = '-';

	// Reverse lookups allowed per user per window (see reverse_geocode()).
	const REVERSE_RATE_LIMIT  = 60;
	const REVERSE_RATE_WINDOW = 10 * MINUTE_IN_SECONDS;

	// Event Cards shown per "Vis flere" step (#204). The card grid
	// (event-cards/style.scss: minmax(280px, 1fr) inside the theme's 1376px
	// wideSize) has 1 to 4 columns, and 12 divides by each, so every step
	// ends on a full row. If the grid can get a 5th column (wider wideSize
	// or smaller minmax), pick a number that fills those rows too.
	const CARDS_PER_PAGE = 12;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		// Nearest address and municipality for coordinates pasted in the editor.
		// Goes through the server so the Datafordeler key never reaches a
		// browser. Only for people who can edit events (administrators and
		// event organisers; organisers deliberately lack edit_posts), and
		// rate limited per user, since anyone can sign up as an organiser and
		// every call spends our Datafordeler quota.
		register_rest_route(
			self::NAMESPACE,
			'/geocode/reverse',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'reverse_geocode' ],
				'permission_callback' => fn() => current_user_can( 'edit_events' ),
				'args'                => [
					'lat' => [
						'required' => true,
						'type'     => 'number',
						'minimum'  => -90,
						'maximum'  => 90,
					],
					'lng' => [
						'required' => true,
						'type'     => 'number',
						'minimum'  => -180,
						'maximum'  => 180,
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/events/count',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_events_count' ],
				'permission_callback' => '__return_true',
				'args'                => $this->get_collection_params(),
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/events/locations',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_events_locations' ],
				'permission_callback' => '__return_true',
				'args'                => $this->get_collection_params(),
			]
		);
	}

	/**
	 * Nearest address and municipality for a coordinate pair.
	 *
	 * @param WP_REST_Request $request Request with lat and lng.
	 * @return WP_REST_Response|WP_Error { found, address, municipality }, or a
	 *                                   502 error when the lookup service failed.
	 */
	public function reverse_geocode( WP_REST_Request $request ) {
		// Far more than an editor pasting coordinates needs, far less than a
		// script draining the quota would want.
		$limit_key = 'vk_reverse_rate_' . get_current_user_id();
		$calls     = (int) get_transient( $limit_key );
		if ( $calls >= self::REVERSE_RATE_LIMIT ) {
			return new WP_Error(
				'vandrekalender_reverse_rate_limited',
				__( 'Too many address lookups. Wait a few minutes and try again.', 'vandrekalender-events' ),
				[ 'status' => 429 ]
			);
		}
		set_transient( $limit_key, $calls + 1, self::REVERSE_RATE_WINDOW );

		$result = ( new Vandrekalender_Geocoder() )->reverse( (float) $request['lat'], (float) $request['lng'] );
		$issues = Vandrekalender_Geocoder::take_issues();

		if ( null === $result && $issues ) {
			return new WP_Error( 'vandrekalender_reverse_unavailable', implode( ' ', $issues ), [ 'status' => 502 ] );
		}

		return rest_ensure_response(
			[
				'found'        => null !== $result,
				'address'      => $result['address'] ?? '',
				'municipality' => $result['municipality'] ?? '',
			]
		);
	}

	/**
	 * Return the number of published events matching the given filters.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_events_count( WP_REST_Request $request ) {
		return rest_ensure_response( [ 'count' => self::count_events( $this->extract_filters( $request ) ) ] );
	}

	/**
	 * Count matching events per day.
	 *
	 * Powers the server-rendered Event Calendar block: the dots only need how
	 * many events fall on each date.
	 *
	 * @param array $filters Filter values keyed by REST param name.
	 * @return array Date key (Y-m-d) => event count, sorted by date.
	 */
	public static function count_events_by_day( array $filters ) {
		$args = self::build_query_args( $filters );

		$args['posts_per_page'] = -1;
		$args['fields']         = 'ids';

		$ids = get_posts( $args );

		// Price lives inside the routes JSON, so the free/paid filter runs in PHP.
		if ( isset( $filters['is_free'] ) && '' !== $filters['is_free'] ) {
			$want_free = rest_sanitize_boolean( $filters['is_free'] );
			$ids       = array_filter(
				$ids,
				fn( $id ) => self::is_event_free( $id ) === $want_free
			);
		}

		$days = [];
		foreach ( $ids as $id ) {
			$date = get_post_meta( $id, \Vandrekalender\Event::META_DATE, true );
			if ( $date ) {
				$days[ $date ] = ( $days[ $date ] ?? 0 ) + 1;
			}
		}
		ksort( $days );

		return $days;
	}

	/**
	 * Return every matching event as a slim map-pin payload.
	 *
	 * Powers the map view: a pin only needs coordinates and its popup line
	 * (title, date, distances, price, link), so this returns all matching
	 * events in one unpaginated response without rendering description HTML —
	 * a fraction of the size and cost of /events. Events without coordinates
	 * are skipped server-side; the map cannot place them anyway.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_events_locations( WP_REST_Request $request ) {
		return rest_ensure_response( self::locations_payload( $this->extract_filters( $request ) ) );
	}

	/**
	 * Build the slim map-pin payload for a set of filter values.
	 *
	 * Shared by the /events/locations endpoint and the server-rendered Event
	 * Map block, which embeds the initial pins so first paint needs no fetch.
	 *
	 * @param array $filters Filter values keyed by REST param name.
	 * @return array List of pin arrays (id, title, permalink, date, lat, lng,
	 *               distances_km, price_from, is_free).
	 */
	public static function locations_payload( array $filters ) {
		$args = self::build_query_args( $filters );

		$args['posts_per_page'] = -1;
		$args['fields']         = 'ids';
		$args['orderby']        = 'meta_value';
		$args['meta_key']       = \Vandrekalender\Event::META_DATE;
		$args['order']          = 'ASC';

		$ids = get_posts( $args );
		// One query each for posts and meta instead of two per event below.
		_prime_post_caches( $ids, false, true );

		$want_free = null;
		if ( isset( $filters['is_free'] ) && '' !== $filters['is_free'] ) {
			$want_free = rest_sanitize_boolean( $filters['is_free'] );
		}

		$locations = [];
		foreach ( $ids as $id ) {
			$lat = get_post_meta( $id, \Vandrekalender\Event::META_LAT, true );
			$lng = get_post_meta( $id, \Vandrekalender\Event::META_LNG, true );
			// Skip events without a usable position (unset, or stored as 0/0
			// when geocoding found no match) — the map cannot place them.
			if ( '' === $lat || '' === $lng || ( 0.0 === (float) $lat && 0.0 === (float) $lng ) ) {
				continue;
			}

			$routes = get_post_meta( $id, \Vandrekalender\Event::META_ROUTES, true );
			$routes = is_array( $routes ) ? $routes : [];

			$distances = [];
			$prices    = [];
			foreach ( $routes as $route ) {
				if ( isset( $route['distance_km'] ) && '' !== $route['distance_km'] ) {
					$distances[] = (float) $route['distance_km'];
				}
				if ( isset( $route['price'] ) && '' !== $route['price'] ) {
					$prices[] = (float) $route['price'];
				}
			}

			$price_from = ! empty( $prices ) ? min( $prices ) : null;
			$is_free    = null === $price_from || 0.0 === (float) $price_from;

			if ( null !== $want_free && $is_free !== $want_free ) {
				continue;
			}

			$date = get_post_meta( $id, \Vandrekalender\Event::META_DATE, true );

			$locations[] = [
				'id'           => $id,
				'title'        => get_the_title( $id ),
				'permalink'    => get_permalink( $id ),
				'date'         => $date,
				// Formatted here rather than in the map's view module: dates
				// belong to Settings → General, and a script module cannot read
				// that. 'date' stays raw for any client-side comparisons.
				'date_label'   => $date ? wp_date( get_option( 'date_format' ), strtotime( $date ), new DateTimeZone( 'UTC' ) ) : '',
				'lat'          => (float) $lat,
				'lng'          => (float) $lng,
				'distances_km' => $distances,
				'price_from'   => $price_from,
				'is_free'      => $is_free,
			];
		}

		return $locations;
	}

	/**
	 * Count published events matching a set of filter values.
	 *
	 * Shared by the /events/count endpoint and server-rendered count blocks.
	 *
	 * @param array $filters Filter values keyed by REST param name
	 *                       (date_from, date_to, region, length, is_free).
	 * @return int
	 */
	public static function count_events( array $filters ) {
		$args = self::build_query_args( $filters );

		$args['posts_per_page'] = -1;
		$args['fields']         = 'ids';

		$ids = get_posts( $args );

		// Price lives inside the routes JSON, so the free/paid filter runs in PHP.
		if ( isset( $filters['is_free'] ) && '' !== $filters['is_free'] ) {
			$want_free = rest_sanitize_boolean( $filters['is_free'] );
			$ids       = array_filter(
				$ids,
				fn( $id ) => self::is_event_free( $id ) === $want_free
			);
		}

		return count( $ids );
	}

	/**
	 * Read the recognised filter params from the current URL query string.
	 *
	 * Filter state lives in the URL (see event-filters/view.js). Used by
	 * server renders — the Filtered Events Count block and shortcode — so the
	 * number is correct on first paint before the view script takes over.
	 *
	 * @return array Filter values keyed by REST param name.
	 */
	public static function filters_from_query() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$filters = [];

		foreach ( [ 'date_from', 'date_to', 'region', 'organiser', 'length', 'is_free' ] as $key ) {
			if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] ) {
				$filters[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $filters;
	}

	/**
	 * How many of $total walks the Event Cards block shows after $page steps
	 * of CARDS_PER_PAGE (?side=N).
	 *
	 * @param int $total Number of matching walks.
	 * @param int $page  Steps shown (?side=N, at least 1).
	 * @return int
	 */
	public static function cards_shown_count( int $total, int $page ): int {
		return min( $total, max( 1, $page ) * self::CARDS_PER_PAGE );
	}

	/**
	 * Filters the page itself pins, whatever the URL says: the term on a
	 * region or length archive, the linked organiser on an organiser page.
	 * The cards, calendar, map and count blocks merge them over
	 * filters_from_query().
	 *
	 * @return array Filter values keyed by REST param name.
	 */
	public static function page_presets() {
		$taxonomy  = '';
		$term_slug = '';
		if ( is_tax( [ \Vandrekalender\Event::TAX_REGION, \Vandrekalender\Event::TAX_LENGTH ] ) ) {
			$taxonomy  = get_queried_object()->taxonomy;
			$term_slug = get_queried_object()->slug;
		}

		$organiser_slug = null;
		if ( is_singular( \Vandrekalender\Organiser::CUSTOMPOSTTYPE ) ) {
			$term           = \Vandrekalender\Organiser::term_for_post( get_queried_object_id() );
			$organiser_slug = $term ? $term->slug : '';
		}

		return self::presets_for( $taxonomy, $term_slug, $organiser_slug );
	}

	/**
	 * The pinned filters for a page (see page_presets()).
	 *
	 * @param string      $taxonomy       Taxonomy of the archive shown, '' when none.
	 * @param string      $term_slug      Its term's slug.
	 * @param string|null $organiser_slug On an organiser page, its organiser's
	 *                                    term slug ('' when it has none yet);
	 *                                    null on any other page.
	 * @return array Filter values keyed by REST param name.
	 */
	public static function presets_for( string $taxonomy, string $term_slug, ?string $organiser_slug ) {
		$keys    = [
			\Vandrekalender\Event::TAX_REGION => 'region',
			\Vandrekalender\Event::TAX_LENGTH => 'length',
		];
		$presets = [];

		if ( isset( $keys[ $taxonomy ] ) && '' !== $term_slug ) {
			$presets[ $keys[ $taxonomy ] ] = $term_slug;
		}

		// A page without an organiser (a draft not linked yet) must not show
		// every walk as if they were its own: pin a slug no term can have.
		if ( null !== $organiser_slug ) {
			$presets['organiser'] = '' !== $organiser_slug ? $organiser_slug : self::NO_ORGANISER;
		}

		return $presets;
	}

	/**
	 * Pull the recognised filter params out of a REST request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array Filter values keyed by REST param name.
	 */
	private function extract_filters( WP_REST_Request $request ) {
		$filters = [];

		foreach ( [ 'date_from', 'date_to', 'region', 'organiser', 'length' ] as $key ) {
			if ( $request->get_param( $key ) ) {
				$filters[ $key ] = $request->get_param( $key );
			}
		}

		if ( null !== $request->get_param( 'is_free' ) && '' !== $request->get_param( 'is_free' ) ) {
			$filters['is_free'] = $request->get_param( 'is_free' );
		}

		return $filters;
	}

	/**
	 * Build WP_Query args for published events from filter values.
	 *
	 * Sanitises every value, so raw query-string input is safe to pass in.
	 * The is_free filter is not handled here — it runs in PHP after the query.
	 * Public because server renders (the Event Cards block) reuse it.
	 *
	 * @param array $filters Filter values keyed by REST param name.
	 * @return array WP_Query args.
	 */
	public static function build_query_args( array $filters ) {
		// Default to upcoming events: with no date range at all, floor the
		// query at today. Explicit date params — including a past range —
		// behave exactly as given, so past events stay reachable on purpose.
		if ( empty( $filters['date_from'] ) && empty( $filters['date_to'] ) ) {
			$filters['date_from'] = current_time( 'Y-m-d' );
		}

		$meta_query = [];

		if ( ! empty( $filters['date_from'] ) ) {
			$meta_query[] = [
				'key'     => \Vandrekalender\Event::META_DATE,
				'value'   => sanitize_text_field( $filters['date_from'] ),
				'compare' => '>=',
			];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$meta_query[] = [
				'key'     => \Vandrekalender\Event::META_DATE,
				'value'   => sanitize_text_field( $filters['date_to'] ),
				'compare' => '<=',
			];
		}

		$tax_query = [];

		if ( ! empty( $filters['region'] ) ) {
			$tax_query[] = [
				'taxonomy' => \Vandrekalender\Event::TAX_REGION,
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_title', explode( ',', $filters['region'] ) ),
			];
		}

		if ( ! empty( $filters['organiser'] ) ) {
			$tax_query[] = [
				'taxonomy' => \Vandrekalender\Event::TAX_ORGANISER,
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_title', explode( ',', $filters['organiser'] ) ),
			];
		}

		if ( ! empty( $filters['length'] ) ) {
			$tax_query[] = [
				'taxonomy' => \Vandrekalender\Event::TAX_LENGTH,
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_title', explode( ',', $filters['length'] ) ),
			];
		}

		$args = [
			'post_type'   => \Vandrekalender\Event::CUSTOMPOSTTYPE,
			'post_status' => 'publish',
		];

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		return $args;
	}

	/**
	 * Whether an event is free, the rule for the free/paid filter and the cards:
	 * free when no route records a real price, or the cheapest price is 0.
	 * Public because server renders (the Event Cards block) reuse it.
	 *
	 * @param int $post_id The event post ID.
	 * @return bool
	 */
	public static function is_event_free( $post_id ) {
		$routes = get_post_meta( $post_id, \Vandrekalender\Event::META_ROUTES, true );
		$prices = [];

		foreach ( ( is_array( $routes ) ? $routes : [] ) as $route ) {
			if ( isset( $route['price'] ) && '' !== $route['price'] ) {
				$prices[] = (float) $route['price'];
			}
		}

		return empty( $prices ) || 0.0 === min( $prices );
	}

	/**
	 * Return the query param definitions shared by the count and locations endpoints.
	 *
	 * @return array
	 */
	private function get_collection_params() {
		return [
			'date_from' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'date_to'   => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'region'    => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'organiser' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'length'    => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'is_free'   => [
				'type' => 'boolean',
			],
		];
	}
}
