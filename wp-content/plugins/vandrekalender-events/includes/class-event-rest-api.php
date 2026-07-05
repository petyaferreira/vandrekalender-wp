<?php

defined( 'ABSPATH' ) || exit;

/**
 * REST API endpoints for reading walking events.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Event_Rest_Api {

	const NAMESPACE = 'vandrekalender/v1';

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
		register_rest_route(
			self::NAMESPACE,
			'/events',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_events' ],
				'permission_callback' => '__return_true',
				'args'                => $this->get_collection_params(),
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
			'/events/days',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_events_days' ],
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

		register_rest_route(
			self::NAMESPACE,
			'/events/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_event' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'id' => [
						'validate_callback' => fn( $v ) => is_numeric( $v ),
					],
				],
			]
		);
	}

	/**
	 * Return a filtered, paginated list of published events.
	 *
	 * Queries matching IDs first (cheap even for thousands of events), applies
	 * the PHP-side free/paid filter to the full ID list, and only then slices
	 * the requested page. That keeps page boundaries and the X-WP-Total /
	 * X-WP-TotalPages headers exact even though the price lives inside the
	 * routes JSON and cannot be filtered in SQL.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_events( WP_REST_Request $request ) {
		$per_page = (int) $request->get_param( 'per_page' );
		$per_page = $per_page > 0 ? min( $per_page, 100 ) : 50;
		$page     = max( 1, (int) $request->get_param( 'page' ) );

		$args = self::build_query_args( $this->extract_filters( $request ) );

		$args['posts_per_page'] = -1;
		$args['fields']         = 'ids';
		$args['orderby']        = 'meta_value';
		$args['meta_key']       = \Vandrekalender\Event::META_DATE;
		$args['order']          = 'ASC';

		$ids = get_posts( $args );

		// Price lives inside the routes JSON, so the free/paid filter runs in PHP.
		if ( null !== $request->get_param( 'is_free' ) && '' !== $request->get_param( 'is_free' ) ) {
			update_meta_cache( 'post', $ids );
			$want_free = rest_sanitize_boolean( $request->get_param( 'is_free' ) );
			$ids       = array_values(
				array_filter(
					$ids,
					fn( $id ) => self::is_event_free( $id ) === $want_free
				)
			);
		}

		$total    = count( $ids );
		$page_ids = array_slice( $ids, ( $page - 1 ) * $per_page, $per_page );

		$events = array_map(
			fn( $id ) => $this->format_event( get_post( $id ) ),
			$page_ids
		);

		$response = rest_ensure_response( $events );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $total / $per_page ) );

		return $response;
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
	 * Return per-day event counts for the given filters.
	 *
	 * Powers the calendar's month grid: the dots only need how many events
	 * fall on each date, so this stays a ~1 KB payload no matter how many
	 * events exist. Response shape: { "2026-08-01": 12, "2026-08-02": 7 }.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_events_days( WP_REST_Request $request ) {
		// Cast so an empty result serialises as {} rather than [].
		return rest_ensure_response( (object) self::count_events_by_day( $this->extract_filters( $request ) ) );
	}

	/**
	 * Count matching events per day.
	 *
	 * Shared by the /events/days endpoint and the server-rendered Event
	 * Calendar block.
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

			$locations[] = [
				'id'           => $id,
				'title'        => get_the_title( $id ),
				'permalink'    => get_permalink( $id ),
				'date'         => get_post_meta( $id, \Vandrekalender\Event::META_DATE, true ),
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

		foreach ( [ 'date_from', 'date_to', 'region', 'length', 'is_free' ] as $key ) {
			if ( isset( $_GET[ $key ] ) && '' !== $_GET[ $key ] ) {
				$filters[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $filters;
	}

	/**
	 * Pull the recognised filter params out of a REST request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array Filter values keyed by REST param name.
	 */
	private function extract_filters( WP_REST_Request $request ) {
		$filters = [];

		foreach ( [ 'date_from', 'date_to', 'region', 'length' ] as $key ) {
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
	 * Whether an event is free, using the same rule as format_event():
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
	 * Return a single event by ID.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_event( WP_REST_Request $request ) {
		$post = get_post( (int) $request->get_param( 'id' ) );

		if ( ! $post || $post->post_type !== \Vandrekalender\Event::CUSTOMPOSTTYPE ) {
			return new WP_Error( 'not_found', __( 'Event not found.', 'vandrekalender-events' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $this->format_event( $post ) );
	}

	/**
	 * Format a post into an event response array.
	 *
	 * @param WP_Post $post The event post.
	 * @return array
	 */
	private function format_event( WP_Post $post ) {
		$date   = get_post_meta( $post->ID, \Vandrekalender\Event::META_DATE, true );
		$routes = get_post_meta( $post->ID, \Vandrekalender\Event::META_ROUTES, true );
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

		$lat = get_post_meta( $post->ID, \Vandrekalender\Event::META_LAT, true );
		$lng = get_post_meta( $post->ID, \Vandrekalender\Event::META_LNG, true );

		$thumbnail_url = get_the_post_thumbnail_url( $post->ID, 'large' );

		return [
			'id'                 => $post->ID,
			'title'              => $post->post_title,
			'description'        => apply_filters( 'the_content', $post->post_content ),
			'permalink'          => get_permalink( $post->ID ),
			'featured_image_url' => $thumbnail_url ? $thumbnail_url : null,
			'date'               => $date ? $date : null,
			'place_name'         => get_post_meta( $post->ID, \Vandrekalender\Event::META_PLACE_NAME, true ),
			'municipality'       => get_post_meta( $post->ID, \Vandrekalender\Event::META_MUNICIPALITY, true ),
			'organiser'          => get_post_meta( $post->ID, \Vandrekalender\Event::META_ORGANISER_NAME, true ),
			'lat'                => '' !== $lat ? (float) $lat : null,
			'lng'                => '' !== $lng ? (float) $lng : null,
			'routes'             => $routes,
			'distances_km'       => $distances,
			'price_from'         => $price_from,
			// Free when no route records a real price, or when the cheapest
			// recorded price is 0. Only a route priced above 0 makes it paid.
			'is_free'            => null === $price_from || 0.0 === (float) $price_from,
			'taxonomies'         => [
				'region' => wp_get_post_terms( $post->ID, \Vandrekalender\Event::TAX_REGION, [ 'fields' => 'names' ] ),
				'length' => wp_get_post_terms( $post->ID, \Vandrekalender\Event::TAX_LENGTH, [ 'fields' => 'slugs' ] ),
			],
		];
	}

	/**
	 * Return the query param definitions for the events collection endpoint.
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
			'length'    => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'is_free'   => [
				'type' => 'boolean',
			],
			'per_page'  => [
				'type' => 'integer',
			],
			'page'      => [
				'type' => 'integer',
			],
		];
	}
}
