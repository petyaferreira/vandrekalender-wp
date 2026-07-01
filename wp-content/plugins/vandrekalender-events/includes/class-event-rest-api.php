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
	 * Return a filtered list of published events.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_events( WP_REST_Request $request ) {
		$meta_query = [];

		if ( $request->get_param( 'date_from' ) ) {
			$meta_query[] = [
				'key'     => \Vandrekalender\Event::META_DATE,
				'value'   => sanitize_text_field( $request->get_param( 'date_from' ) ),
				'compare' => '>=',
			];
		}

		if ( $request->get_param( 'date_to' ) ) {
			$meta_query[] = [
				'key'     => \Vandrekalender\Event::META_DATE,
				'value'   => sanitize_text_field( $request->get_param( 'date_to' ) ),
				'compare' => '<=',
			];
		}

		$tax_query = [];

		if ( $request->get_param( 'region' ) ) {
			$tax_query[] = [
				'taxonomy' => \Vandrekalender\Event::TAX_REGION,
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_title', explode( ',', $request->get_param( 'region' ) ) ),
			];
		}

		if ( $request->get_param( 'length' ) ) {
			$tax_query[] = [
				'taxonomy' => \Vandrekalender\Event::TAX_LENGTH,
				'field'    => 'slug',
				'terms'    => array_map( 'sanitize_title', explode( ',', $request->get_param( 'length' ) ) ),
			];
		}

		$per_page = (int) $request->get_param( 'per_page' );
		$per_page = $per_page > 0 ? min( $per_page, 100 ) : 50;

		$args = [
			'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'orderby'        => 'meta_value',
			'meta_key'       => \Vandrekalender\Event::META_DATE,
			'order'          => 'ASC',
		];

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = array_merge( $args['meta_query'] ?? [], $meta_query );
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		$events = array_map( [ $this, 'format_event' ], get_posts( $args ) );

		// Price lives inside the routes JSON, so the free/paid filter runs in PHP.
		if ( null !== $request->get_param( 'is_free' ) && '' !== $request->get_param( 'is_free' ) ) {
			$want_free = rest_sanitize_boolean( $request->get_param( 'is_free' ) );
			$events    = array_values(
				array_filter(
					$events,
					fn( $event ) => $event['is_free'] === $want_free
				)
			);
		}

		return rest_ensure_response( $events );
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
		];
	}
}
