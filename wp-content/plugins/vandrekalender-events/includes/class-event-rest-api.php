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

		$args = [
			'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'meta_value',
			'meta_key'       => \Vandrekalender\Event::META_DATE,
			'order'          => 'ASC',
		];

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = array_merge( $args['meta_query'] ?? [], $meta_query );
		}

		$posts = get_posts( $args );

		return rest_ensure_response( array_map( [ $this, 'format_event' ], $posts ) );
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

		return [
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'description' => apply_filters( 'the_content', $post->post_content ),
			'permalink'   => get_permalink( $post->ID ),
			'date'        => $date ? $date : null,
			'routes'      => is_array( $routes ) ? $routes : [],
			'taxonomies'  => [
				'location' => wp_get_post_terms( $post->ID, \Vandrekalender\Event::TAX_LOCATION, [ 'fields' => 'names' ] ),
				'format'   => wp_get_post_terms( $post->ID, \Vandrekalender\Event::TAX_FORMAT, [ 'fields' => 'names' ] ),
				'length'   => wp_get_post_terms( $post->ID, \Vandrekalender\Event::TAX_LENGTH, [ 'fields' => 'names' ] ),
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
			'location'  => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'format'    => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'length'    => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
		];
	}
}
