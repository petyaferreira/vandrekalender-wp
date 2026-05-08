<?php

defined( 'ABSPATH' ) || exit;

class Vandrekalender_Event_Rest_Api {

	const NAMESPACE = 'vandrekalender/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

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

	public function get_events( WP_REST_Request $request ) {
		$meta_query = [];

		if ( $request->get_param( 'date_from' ) ) {
			$meta_query[] = [
				'key'     => '_event_date',
				'value'   => sanitize_text_field( $request->get_param( 'date_from' ) ),
				'compare' => '>=',
			];
		}

		if ( $request->get_param( 'date_to' ) ) {
			$meta_query[] = [
				'key'     => '_event_date',
				'value'   => sanitize_text_field( $request->get_param( 'date_to' ) ),
				'compare' => '<=',
			];
		}

		if ( $request->get_param( 'difficulty' ) ) {
			$meta_query[] = [
				'key'   => '_event_difficulty',
				'value' => sanitize_text_field( $request->get_param( 'difficulty' ) ),
			];
		}

		if ( $request->get_param( 'distance_max' ) ) {
			$meta_query[] = [
				'key'     => '_event_distance_km',
				'value'   => floatval( $request->get_param( 'distance_max' ) ),
				'compare' => '<=',
				'type'    => 'DECIMAL',
			];
		}

		$args = [
			'post_type'      => 'vandrekalender_event',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'meta_value',
			'meta_key'       => '_event_date',
			'order'          => 'ASC',
		];

		if ( $request->get_param( 'region' ) ) {
			$args['meta_query'][] = [
				'key'   => '_event_region',
				'value' => sanitize_text_field( $request->get_param( 'region' ) ),
			];
		}

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = array_merge( $args['meta_query'] ?? [], $meta_query );
		}

		$posts = get_posts( $args );

		return rest_ensure_response( array_map( [ $this, 'format_event' ], $posts ) );
	}

	public function get_event( WP_REST_Request $request ) {
		$post = get_post( (int) $request->get_param( 'id' ) );

		if ( ! $post || $post->post_type !== 'vandrekalender_event' ) {
			return new WP_Error( 'not_found', __( 'Event not found.', 'vandrekalender-events' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $this->format_event( $post ) );
	}

	private function format_event( WP_Post $post ) {
		$meta = get_post_meta( $post->ID );

		return [
			'id'            => $post->ID,
			'title'         => $post->post_title,
			'description'   => apply_filters( 'the_content', $post->post_content ),
			'permalink'     => get_permalink( $post->ID ),
			'date'          => $meta['_event_date'][0] ?? null,
			'distance_km'   => isset( $meta['_event_distance_km'][0] ) ? (float) $meta['_event_distance_km'][0] : null,
			'difficulty'    => $meta['_event_difficulty'][0] ?? null,
			'location_name' => $meta['_event_location_name'][0] ?? null,
			'lat'           => isset( $meta['_event_lat'][0] ) ? (float) $meta['_event_lat'][0] : null,
			'lng'           => isset( $meta['_event_lng'][0] ) ? (float) $meta['_event_lng'][0] : null,
			'organiser'     => $meta['_event_organiser'][0] ?? null,
			'source_url'    => $meta['_event_source_url'][0] ?? null,
			'claim_status'  => $meta['_event_claim_status'][0] ?? 'unclaimed',
			'region'        => $meta['_event_region'][0] ?? null,
		];
	}

	private function get_collection_params() {
		return [
			'date_from'    => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'date_to'      => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'region'       => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'difficulty'   => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			'distance_max' => [ 'type' => 'number', 'sanitize_callback' => 'floatval' ],
		];
	}
}
