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
	public const META_DATE            = 'event_date';
	public const META_ROUTES          = 'event_routes';
	public const META_PLACE_NAME      = 'event_place_name';
	public const META_ADDRESS         = 'event_address';
	public const META_LAT             = 'event_lat';
	public const META_LNG             = 'event_lng';
	public const META_MUNICIPALITY    = 'event_municipality';
	public const META_ORGANISER_NAME  = 'event_organiser_name';
	public const META_ORGANISER_URL   = 'event_organiser_url';
	public const META_ORGANISER_EMAIL = 'event_organiser_email';

	// Source / scraping meta.
	public const META_SOURCE      = 'event_source';
	public const META_SOURCE_URL  = 'event_source_url';
	public const META_SOURCE_NAME = 'event_source_name';
	public const META_SCRAPED_AT  = 'event_scraped_at';
	public const META_CLAIMED     = 'event_claimed';

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
		add_filter( 'rest_prepare_' . self::CUSTOMPOSTTYPE, [ $this, 'hide_organiser_email_in_rest' ], 10, 2 );
		// Hook directly into meta saves — fires at the exact moment each value is
		// written to the database, regardless of whether the save comes from the
		// block editor REST API, a scraper, or wp-cli.
		add_action( 'added_post_meta', [ $this, 'on_event_meta_saved' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'on_event_meta_saved' ], 10, 4 );
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

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_PLACE_NAME,
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '',
			]
		);

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_ADDRESS,
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '',
			]
		);

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_LAT,
			[
				'type'         => 'number',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => 0,
			]
		);

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_LNG,
			[
				'type'         => 'number',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => 0,
			]
		);

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_MUNICIPALITY,
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '',
			]
		);

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_ORGANISER_NAME,
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '',
			]
		);

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_ORGANISER_URL,
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '',
			]
		);

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_ORGANISER_EMAIL,
			[
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true, // Exposed to block editor but stripped for non-admins via rest_prepare filter.
				'auth_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'default'       => '',
			]
		);

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_SOURCE,
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => 'manual',
			]
		);

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_SOURCE_URL,
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '',
			]
		);

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_SOURCE_NAME,
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '',
			]
		);

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_SCRAPED_AT,
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '',
			]
		);

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_CLAIMED,
			[
				'type'         => 'boolean',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => false,
			]
		);
	}

	/**
	 * React to individual meta saves for event posts.
	 * Fires on both added_post_meta and updated_post_meta.
	 * $meta_value is the raw (unserialized) value passed to update_post_meta.
	 *
	 * @param int    $meta_id    Meta row ID (unused).
	 * @param int    $object_id  Post ID.
	 * @param string $meta_key   Meta key that was saved.
	 * @param mixed  $meta_value The value that was just written.
	 * @return void
	 */
	public function on_event_meta_saved( int $meta_id, int $object_id, string $meta_key, mixed $meta_value ): void {
		if ( get_post_type( $object_id ) !== self::CUSTOMPOSTTYPE ) {
			return;
		}

		if ( self::META_MUNICIPALITY === $meta_key ) {
			$this->assign_region_from_municipality( $object_id, (string) $meta_value );
		}

		if ( self::META_ROUTES === $meta_key ) {
			$this->assign_length_from_routes( $object_id, is_array( $meta_value ) ? $meta_value : [] );
		}
	}

	/**
	 * Assign event_region from a municipality name.
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $municipality Municipality name as returned by DAWA.
	 * @return void
	 */
	private function assign_region_from_municipality( int $post_id, string $municipality ): void {
		if ( empty( $municipality ) ) {
			wp_set_object_terms( $post_id, [], self::TAX_REGION );
			return;
		}

		$map = self::municipality_region_map();
		$key = mb_strtolower( trim( $municipality ) );

		if ( isset( $map[ $key ] ) ) {
			wp_set_object_terms( $post_id, [ $map[ $key ] ], self::TAX_REGION );
		}
	}

	/**
	 * Assign event_length terms from a routes array.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $routes  Raw routes array from meta.
	 * @return void
	 */
	private function assign_length_from_routes( int $post_id, array $routes ): void {
		if ( empty( $routes ) ) {
			wp_set_object_terms( $post_id, [], self::TAX_LENGTH );
			return;
		}

		$terms = [];

		foreach ( $routes as $route ) {
			$km = isset( $route['distance_km'] ) ? (float) $route['distance_km'] : 0;

			if ( $km > 0 && $km <= 10 ) {
				$terms[] = 'short';
			} elseif ( $km > 10 && $km <= 25 ) {
				$terms[] = 'medium';
			} elseif ( $km > 25 ) {
				$terms[] = 'long';
			}
		}

		wp_set_object_terms( $post_id, array_unique( $terms ), self::TAX_LENGTH );
	}

	/**
	 * Map of Danish municipality names (lowercase) to region slugs.
	 *
	 * @return array<string, string>
	 */
	private static function municipality_region_map(): array {
		return [
			// Hovedstaden.
			'albertslund'       => 'hovedstaden',
			'allerød'           => 'hovedstaden',
			'ballerup'          => 'hovedstaden',
			'bornholm'          => 'hovedstaden',
			'brøndby'           => 'hovedstaden',
			'christiansø'       => 'hovedstaden',
			'dragør'            => 'hovedstaden',
			'egedal'            => 'hovedstaden',
			'fredensborg'       => 'hovedstaden',
			'frederiksberg'     => 'hovedstaden',
			'frederikssund'     => 'hovedstaden',
			'furesø'            => 'hovedstaden',
			'gentofte'          => 'hovedstaden',
			'gladsaxe'          => 'hovedstaden',
			'glostrup'          => 'hovedstaden',
			'gribskov'          => 'hovedstaden',
			'halsnæs'           => 'hovedstaden',
			'helsingør'         => 'hovedstaden',
			'herlev'            => 'hovedstaden',
			'hillerød'          => 'hovedstaden',
			'hvidovre'          => 'hovedstaden',
			'høje-taastrup'     => 'hovedstaden',
			'hørsholm'          => 'hovedstaden',
			'ishøj'             => 'hovedstaden',
			'københavn'         => 'hovedstaden',
			'lyngby-taarbæk'    => 'hovedstaden',
			'rudersdal'         => 'hovedstaden',
			'rødovre'           => 'hovedstaden',
			'tårnby'            => 'hovedstaden',
			'vallensbæk'        => 'hovedstaden',
			// Sjælland.
			'faxe'              => 'sjaelland',
			'greve'             => 'sjaelland',
			'guldborgsund'      => 'sjaelland',
			'holbæk'            => 'sjaelland',
			'kalundborg'        => 'sjaelland',
			'køge'              => 'sjaelland',
			'lejre'             => 'sjaelland',
			'lolland'           => 'sjaelland',
			'næstved'           => 'sjaelland',
			'odsherred'         => 'sjaelland',
			'ringsted'          => 'sjaelland',
			'roskilde'          => 'sjaelland',
			'slagelse'          => 'sjaelland',
			'solrød'            => 'sjaelland',
			'sorø'              => 'sjaelland',
			'stevns'            => 'sjaelland',
			'vordingborg'       => 'sjaelland',
			// Syddanmark.
			'assens'            => 'syddanmark',
			'billund'           => 'syddanmark',
			'esbjerg'           => 'syddanmark',
			'faaborg-midtfyn'   => 'syddanmark',
			'fanø'              => 'syddanmark',
			'fredericia'        => 'syddanmark',
			'haderslev'         => 'syddanmark',
			'kerteminde'        => 'syddanmark',
			'kolding'           => 'syddanmark',
			'langeland'         => 'syddanmark',
			'middelfart'        => 'syddanmark',
			'nordfyns'          => 'syddanmark',
			'nyborg'            => 'syddanmark',
			'odense'            => 'syddanmark',
			'svendborg'         => 'syddanmark',
			'sønderborg'        => 'syddanmark',
			'tønder'            => 'syddanmark',
			'varde'             => 'syddanmark',
			'vejen'             => 'syddanmark',
			'vejle'             => 'syddanmark',
			'ærø'               => 'syddanmark',
			'aabenraa'          => 'syddanmark',
			// Midtjylland.
			'favrskov'          => 'midtjylland',
			'hedensted'         => 'midtjylland',
			'herning'           => 'midtjylland',
			'holstebro'         => 'midtjylland',
			'horsens'           => 'midtjylland',
			'ikast-brande'      => 'midtjylland',
			'lemvig'            => 'midtjylland',
			'norddjurs'         => 'midtjylland',
			'odder'             => 'midtjylland',
			'randers'           => 'midtjylland',
			'ringkøbing-skjern' => 'midtjylland',
			'samsø'             => 'midtjylland',
			'silkeborg'         => 'midtjylland',
			'skanderborg'       => 'midtjylland',
			'skive'             => 'midtjylland',
			'struer'            => 'midtjylland',
			'syddjurs'          => 'midtjylland',
			'viborg'            => 'midtjylland',
			'aarhus'            => 'midtjylland',
			// Nordjylland.
			'brønderslev'       => 'nordjylland',
			'frederikshavn'     => 'nordjylland',
			'hjørring'          => 'nordjylland',
			'jammerbugt'        => 'nordjylland',
			'læsø'              => 'nordjylland',
			'mariagerfjord'     => 'nordjylland',
			'morsø'             => 'nordjylland',
			'rebild'            => 'nordjylland',
			'thisted'           => 'nordjylland',
			'vesthimmerlands'   => 'nordjylland',
			'aalborg'           => 'nordjylland',
		];
	}

	/**
	 * Strip event_organiser_email from REST responses for non-admin users.
	 * Admins can read and write it via the block editor; everyone else sees nothing.
	 *
	 * @param \WP_REST_Response $response The REST response.
	 * @param \WP_Post          $_post    The post object (unused — required by filter signature).
	 * @return \WP_REST_Response
	 */
	public function hide_organiser_email_in_rest( \WP_REST_Response $response, \WP_Post $_post ): \WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- required by filter signature.
		if ( current_user_can( 'manage_options' ) ) {
			return $response;
		}

		$data = $response->get_data();

		if ( isset( $data['meta'][ self::META_ORGANISER_EMAIL ] ) ) {
			unset( $data['meta'][ self::META_ORGANISER_EMAIL ] );
			$response->set_data( $data );
		}

		return $response;
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
				'rewrite'           => [
					'slug'       => 'region',
					'with_front' => false,
				],
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
				'rewrite'           => [
					'slug'       => 'laengde',
					'with_front' => false,
				],
			]
		);
	}

	/**
	 * Register Gutenberg blocks from the build directory.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		foreach ( glob( VANDREKALENDER_EVENTS_DIR . 'build/blocks/*/block.json' ) as $block_json ) {
			register_block_type( dirname( $block_json ) );
		}
	}

	/**
	 * Enqueue the event meta fields sidebar script in the block editor.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		$asset_file = VANDREKALENDER_EVENTS_DIR . 'build/resources/event-meta-fields/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'vandrekalender-event-meta-fields',
			VANDREKALENDER_EVENTS_URL . 'build/resources/event-meta-fields/index.js',
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
