<?php

defined( 'ABSPATH' ) || exit;

/**
 * Registers the vandrekalender_event custom post type and activity taxonomy.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Event_Post_Type {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'init', [ $this, 'register_taxonomy' ] );
	}

	/**
	 * Register the vandrekalender_event post type.
	 */
	public function register_post_type() {
		$labels = [
			'name'               => __( 'Events', 'vandrekalender-events' ),
			'singular_name'      => __( 'Event', 'vandrekalender-events' ),
			'add_new_item'       => __( 'Add New Event', 'vandrekalender-events' ),
			'edit_item'          => __( 'Edit Event', 'vandrekalender-events' ),
			'view_item'          => __( 'View Event', 'vandrekalender-events' ),
			'search_items'       => __( 'Search Events', 'vandrekalender-events' ),
			'not_found'          => __( 'No events found.', 'vandrekalender-events' ),
			'not_found_in_trash' => __( 'No events found in trash.', 'vandrekalender-events' ),
		];

		register_post_type(
			'vandrekalender_event',
			[
				'labels'       => $labels,
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => [ 'title', 'editor', 'thumbnail', 'author' ],
				'has_archive'  => false,
				'rewrite'      => [ 'slug' => 'tur' ],
				'menu_icon'    => 'dashicons-location-alt',
			]
		);
	}

	/**
	 * Register the vandrekalender_activity taxonomy.
	 */
	public function register_taxonomy() {
		register_taxonomy(
			'vandrekalender_activity',
			'vandrekalender_event',
			[
				'label'        => __( 'Activity Type', 'vandrekalender-events' ),
				'public'       => true,
				'show_in_rest' => true,
				'rewrite'      => [ 'slug' => 'aktivitet' ],
				'hierarchical' => false,
			]
		);
	}
}
