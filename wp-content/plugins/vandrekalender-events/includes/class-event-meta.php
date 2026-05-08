<?php

defined( 'ABSPATH' ) || exit;

class Vandrekalender_Event_Meta {

	const META_FIELDS = [
		'_event_date'          => [ 'type' => 'string', 'sanitize' => 'sanitize_text_field' ],
		'_event_distance_km'   => [ 'type' => 'number', 'sanitize' => 'floatval' ],
		'_event_difficulty'    => [ 'type' => 'string', 'sanitize' => 'sanitize_text_field' ],
		'_event_location_name' => [ 'type' => 'string', 'sanitize' => 'sanitize_text_field' ],
		'_event_lat'           => [ 'type' => 'number', 'sanitize' => 'floatval' ],
		'_event_lng'           => [ 'type' => 'number', 'sanitize' => 'floatval' ],
		'_event_organiser'     => [ 'type' => 'string', 'sanitize' => 'sanitize_text_field' ],
		'_event_source_url'    => [ 'type' => 'string', 'sanitize' => 'esc_url_raw' ],
		'_event_claim_status'  => [ 'type' => 'string', 'sanitize' => 'sanitize_text_field' ],
		'_event_region'        => [ 'type' => 'string', 'sanitize' => 'sanitize_text_field' ],
	];

	public function __construct() {
		add_action( 'init', [ $this, 'register_meta' ] );
	}

	public function register_meta() {
		foreach ( self::META_FIELDS as $key => $config ) {
			register_post_meta(
				'vandrekalender_event',
				$key,
				[
					'type'         => $config['type'],
					'single'       => true,
					'show_in_rest' => true,
				]
			);
		}
	}
}
