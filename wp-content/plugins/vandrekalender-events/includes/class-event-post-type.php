<?php
/**
 * Event Post Type Class
 *
 * Handles registration of the vandrekalender_event custom post type,
 * its taxonomies, and computed taxonomy sync on save.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages the vandrekalender_event CPT and all its taxonomies.
 */
class Vandrekalender_Event_Post_Type {

	/**
	 * Singleton instance.
	 *
	 * @var Vandrekalender_Event_Post_Type|null
	 */
	private static $instance = null;

	// Post type slug.
	public const POST_TYPE = 'vandrekalender_event';

	// Taxonomy slugs.
	public const TAX_ACTIVITY       = 'vandrekalender_activity';
	public const TAX_REGION         = 'vandrekalender_region';
	public const TAX_DISTANCE_RANGE = 'vandrekalender_distance_range';
	public const TAX_ORGANISER_TYPE = 'vandrekalender_organiser_type';

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
		// Sync distance range from routes: REST API save (Gutenberg) and classic save.
		add_action( 'rest_after_insert_' . self::POST_TYPE, [ $this, 'sync_distance_range' ] );
		add_action( 'save_post_' . self::POST_TYPE, [ $this, 'sync_distance_range_on_save' ] );
	}

	/**
	 * Register the vandrekalender_event post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
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
				'has_archive'   => false,
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
	 * Register all taxonomies.
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		$this->register_activity_taxonomy();
		$this->register_region_taxonomy();
		$this->register_distance_range_taxonomy();
		$this->register_organiser_type_taxonomy();
	}

	/**
	 * Register the Activity Type taxonomy.
	 *
	 * User-assigned. Flat list. Public so activity archive pages are possible.
	 * Example terms: naturvandring, nattevandring, familietur, motionsvandring.
	 *
	 * @return void
	 */
	private function register_activity_taxonomy(): void {
		register_taxonomy(
			self::TAX_ACTIVITY,
			self::POST_TYPE,
			[
				'labels'            => [
					'name'          => __( 'Activity Types', 'vandrekalender-events' ),
					'singular_name' => __( 'Activity Type', 'vandrekalender-events' ),
					'search_items'  => __( 'Search Activity Types', 'vandrekalender-events' ),
					'all_items'     => __( 'All Activity Types', 'vandrekalender-events' ),
					'edit_item'     => __( 'Edit Activity Type', 'vandrekalender-events' ),
					'update_item'   => __( 'Update Activity Type', 'vandrekalender-events' ),
					'add_new_item'  => __( 'Add New Activity Type', 'vandrekalender-events' ),
					'new_item_name' => __( 'New Activity Type Name', 'vandrekalender-events' ),
					'menu_name'     => __( 'Activity Types', 'vandrekalender-events' ),
				],
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => [
					'slug'       => 'activity',
					'with_front' => false,
				],
			]
		);
	}

	/**
	 * Register the Region taxonomy.
	 *
	 * Auto-derived from event coordinates via reverse geocoding on save.
	 * Editors should not need to assign this manually.
	 * Example terms: Nordjylland, Vestjylland, Sjælland, København.
	 *
	 * @return void
	 */
	private function register_region_taxonomy(): void {
		register_taxonomy(
			self::TAX_REGION,
			self::POST_TYPE,
			[
				'labels'             => [
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
				'hierarchical'       => true,
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'show_admin_column'  => true,
				'rewrite'            => [
					'slug'       => 'region',
					'with_front' => false,
				],
			]
		);
	}

	/**
	 * Register the Distance Range taxonomy.
	 *
	 * Auto-assigned on save from all km values in _event_routes.
	 * An event with routes [10, 50] km gets both 'short' and 'long' terms
	 * so it is findable regardless of which distance a visitor wants to walk.
	 *
	 * Terms: short (0–10 km), medium (10–25 km), long (25+ km).
	 *
	 * @return void
	 */
	private function register_distance_range_taxonomy(): void {
		register_taxonomy(
			self::TAX_DISTANCE_RANGE,
			self::POST_TYPE,
			[
				'labels'             => [
					'name'          => __( 'Distance Ranges', 'vandrekalender-events' ),
					'singular_name' => __( 'Distance Range', 'vandrekalender-events' ),
					'search_items'  => __( 'Search Distance Ranges', 'vandrekalender-events' ),
					'all_items'     => __( 'All Distance Ranges', 'vandrekalender-events' ),
					'edit_item'     => __( 'Edit Distance Range', 'vandrekalender-events' ),
					'update_item'   => __( 'Update Distance Range', 'vandrekalender-events' ),
					'add_new_item'  => __( 'Add New Distance Range', 'vandrekalender-events' ),
					'new_item_name' => __( 'New Distance Range Name', 'vandrekalender-events' ),
					'menu_name'     => __( 'Distance Ranges', 'vandrekalender-events' ),
				],
				'hierarchical'       => false,
				'public'             => false,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'show_admin_column'  => true,
				'rewrite'            => false,
			]
		);
	}

	/**
	 * Register the Organiser Type taxonomy.
	 *
	 * User-assigned. Terms: klub, forening, individuel.
	 *
	 * @return void
	 */
	private function register_organiser_type_taxonomy(): void {
		register_taxonomy(
			self::TAX_ORGANISER_TYPE,
			self::POST_TYPE,
			[
				'labels'             => [
					'name'          => __( 'Organiser Types', 'vandrekalender-events' ),
					'singular_name' => __( 'Organiser Type', 'vandrekalender-events' ),
					'search_items'  => __( 'Search Organiser Types', 'vandrekalender-events' ),
					'all_items'     => __( 'All Organiser Types', 'vandrekalender-events' ),
					'edit_item'     => __( 'Edit Organiser Type', 'vandrekalender-events' ),
					'update_item'   => __( 'Update Organiser Type', 'vandrekalender-events' ),
					'add_new_item'  => __( 'Add New Organiser Type', 'vandrekalender-events' ),
					'new_item_name' => __( 'New Organiser Type Name', 'vandrekalender-events' ),
					'menu_name'     => __( 'Organiser Types', 'vandrekalender-events' ),
				],
				'hierarchical'       => false,
				'public'             => false,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'show_admin_column'  => false,
				'rewrite'            => false,
			]
		);
	}

	/**
	 * Wrapper for the save_post hook — receives a post ID, not a post object.
	 *
	 * @param int $post_id The event post ID.
	 * @return void
	 */
	public function sync_distance_range_on_save( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$post = get_post( $post_id );
		if ( $post ) {
			$this->sync_distance_range( $post );
		}
	}

	/**
	 * Auto-assign distance range taxonomy terms from the _event_routes meta.
	 *
	 * Called after save via both REST API (rest_after_insert) and classic editor (save_post).
	 * Reads all route km values and maps each to its range slug:
	 *   short  : 0–10 km
	 *   medium : 10–25 km
	 *   long   : 25+ km
	 *
	 * @param \WP_Post $post The event post object.
	 * @return void
	 */
	public function sync_distance_range( \WP_Post $post ): void {
		$raw    = get_post_meta( $post->ID, '_event_routes', true );
		$routes = is_string( $raw ) ? json_decode( $raw, true ) : [];

		if ( ! is_array( $routes ) || empty( $routes ) ) {
			return;
		}

		$slugs = [];
		foreach ( $routes as $route ) {
			$km = isset( $route['km'] ) ? (float) $route['km'] : 0.0;
			if ( $km <= 0 ) {
				continue;
			}
			if ( $km <= 10 ) {
				$slugs[] = 'short';
			} elseif ( $km <= 25 ) {
				$slugs[] = 'medium';
			} else {
				$slugs[] = 'long';
			}
		}

		if ( ! empty( $slugs ) ) {
			wp_set_object_terms( $post->ID, array_unique( $slugs ), self::TAX_DISTANCE_RANGE );
		}
	}
}
