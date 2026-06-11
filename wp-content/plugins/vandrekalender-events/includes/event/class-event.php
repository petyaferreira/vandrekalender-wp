<?php
/**
 * Event Class
 *
 * Handles registration of the Event custom post type,
 * its taxonomies, and event meta fields.
 *
 * @package Vandrekalender
 */

namespace Vandrekalender;

/**
 * Event class to manage Event functionalities.
 */
class Event {
	/**
	 * Singleton instance.
	 *
	 * @var Event|null
	 */
	private static $instance = null;

	public const CUSTOMPOSTTYPE = 'event';

	// Taxonomies.
	public const TAX_REGION = 'event_region';
	public const TAX_LENGTH = 'event_length';

	// Meta keys.
	public const META_DATE   = 'event_date';
	public const META_ROUTES = 'event_routes';

	/**
	 * Get the singleton instance.
	 *
	 * @return Vandrekalender_Event_Post_Type
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — registers all hooks.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'init', [ $this, 'register_taxonomies' ] );
		add_action( 'init', [ $this, 'register_meta' ] );
		add_action( 'init', [ $this, 'register_blocks' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Register the event post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			self::CUSTOMPOSTTYPE,
			[
				'labels'        => [
					'name'               => __( 'Events', 'vandrekalender-events' ),
					'singular_name'      => __( 'Event', 'vandrekalender-events' ),
					'menu_name'          => __( 'Events', 'vandrekalender-events' ),
					'add_new_item'       => __( 'Add New Event', 'vandrekalender-events' ),
					'edit_item'          => __( 'Edit Event', 'vandrekalender-events' ),
					'view_item'          => __( 'View Event', 'vandrekalender-events' ),
					'view_items'         => __( 'View Events', 'vandrekalender-events' ),
					'update_item'        => __( 'Update Event', 'vandrekalender-events' ),
					'all_items'          => __( 'All Events', 'vandrekalender-events' ),
					'search_items'       => __( 'Search Events', 'vandrekalender-events' ),
					'not_found'          => __( 'No events found.', 'vandrekalender-events' ),
					'not_found_in_trash' => __( 'No events found in trash.', 'vandrekalender-events' ),
				],
				// 'custom-fields' enables the meta box panel in the editor.
				'supports'      => [ 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'custom-fields' ],
				'public'        => true,
				'show_in_rest'  => true,
				'has_archive'   => true,
				'rewrite'       => [
					'slug'       => 'event',
					'with_front' => false,
				],
				'menu_icon'     => 'dashicons-location-alt',
				'menu_position' => 5,
			]
		);
	}

	/**
	 * Register Event taxonomies.
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		$this->register_region_taxonomy();
		$this->register_length_taxonomy();
	}

	/**
	 * Register Event meta fields.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_DATE,
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '',
			]
		);

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_ROUTES,
			[
				'type'         => 'array',
				'single'       => true,
				'show_in_rest' => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => [
								'id'          => [ 'type' => 'string' ],
								'distance_km' => [ 'type' => 'string' ],
								'start_time'  => [ 'type' => 'string' ],
								'cutoff_time' => [ 'type' => 'string' ],
								'price'       => [ 'type' => 'string' ],
							],
						],
					],
				],
				'default'      => [],
			]
		);
	}

	/**
	 * Register the region taxonomy (5 Danish regions).
	 * Terms are auto-assigned on save — not manually editable.
	 *
	 * @return void
	 */
	private function register_region_taxonomy(): void {
		register_taxonomy(
			self::TAX_REGION,
			self::CUSTOMPOSTTYPE,
			[
				'labels'            => [
					'name'          => __( 'Regions', 'vandrekalender-events' ),
					'singular_name' => __( 'Region', 'vandrekalender-events' ),
					'search_items'  => __( 'Search Regions', 'vandrekalender-events' ),
					'all_items'     => __( 'All Regions', 'vandrekalender-events' ),
					'edit_item'     => __( 'Edit Region', 'vandrekalender-events' ),
					'update_item'   => __( 'Update Region', 'vandrekalender-events' ),
					'add_new_item'  => __( 'Add New Region', 'vandrekalender-events' ),
					'new_item_name' => __( 'New Region Name', 'vandrekalender-events' ),
					'menu_name'     => __( 'Regions', 'vandrekalender-events' ),
				],
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'meta_box_cb'       => false,
			]
		);
	}

	/**
	 * Register the length taxonomy (short / medium / long).
	 * Terms are auto-assigned on save — not manually editable.
	 *
	 * @return void
	 */
	private function register_length_taxonomy(): void {
		register_taxonomy(
			self::TAX_LENGTH,
			self::CUSTOMPOSTTYPE,
			[
				'labels'            => [
					'name'          => __( 'Lengths', 'vandrekalender-events' ),
					'singular_name' => __( 'Length', 'vandrekalender-events' ),
					'search_items'  => __( 'Search Lengths', 'vandrekalender-events' ),
					'all_items'     => __( 'All Lengths', 'vandrekalender-events' ),
					'edit_item'     => __( 'Edit Length', 'vandrekalender-events' ),
					'update_item'   => __( 'Update Length', 'vandrekalender-events' ),
					'add_new_item'  => __( 'Add New Length', 'vandrekalender-events' ),
					'new_item_name' => __( 'New Length Name', 'vandrekalender-events' ),
					'menu_name'     => __( 'Lengths', 'vandrekalender-events' ),
				],
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'meta_box_cb'       => false,
			]
		);
	}

	/**
	 * Register Gutenberg blocks from the build directory.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		foreach ( glob( VANDREKALENDER_EVENTS_DIR . 'build/*/block.json' ) as $block_json ) {
			register_block_type( dirname( $block_json ) );
		}
	}

	/**
	 * Enqueue the event meta fields sidebar script in the block editor.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		$asset_file = VANDREKALENDER_EVENTS_DIR . 'build/event-meta-fields/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'vandrekalender-event-meta-fields',
			VANDREKALENDER_EVENTS_URL . 'build/event-meta-fields/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations(
			'vandrekalender-event-meta-fields',
			'vandrekalender-events'
		);
	}
}
