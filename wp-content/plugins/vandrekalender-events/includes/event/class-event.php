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
	public const META_SERIES_KEY  = 'event_series_key';

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
		add_filter( 'rest_prepare_' . self::CUSTOMPOSTTYPE, [ $this, 'hide_gpx_source_url_in_rest' ], 10, 3 );
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
				'labels'          => [
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
				'supports'        => [ 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'custom-fields' ],
				// Own capability set (edit_events, publish_events, …) so the
				// event_organizer role can work with events without gaining
				// access to regular posts. Granted in Vandrekalender_Roles.
				'capability_type' => [ 'event', 'events' ],
				'map_meta_cap'    => true,
				'public'          => true,
				'show_in_rest'    => true,
				'has_archive'     => true,
				'rewrite'         => [
					'slug'       => 'begivenhed',
					'with_front' => false,
				],
				'menu_icon'       => 'dashicons-location-alt',
				'menu_position'   => 5,
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
								'id'             => [ 'type' => 'string' ],
								'distance_km'    => [ 'type' => 'string' ],
								'start_time'     => [ 'type' => 'string' ],
								'cutoff_time'    => [ 'type' => 'string' ],
								'price'          => [ 'type' => 'string' ],
								'gpx_id'         => [ 'type' => 'string' ],
								'gpx_source_url' => [ 'type' => 'string' ],
								'gpx_name'       => [ 'type' => 'string' ],
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

		// Groups recurring occurrences of the same walk (e.g. DVL's weekly
		// walks) so a past one can redirect to the next. Set only by the
		// scraper pipeline; manually created and claimed events have none.
		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_SERIES_KEY,
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
				'default'      => '',
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
	 * @param string $municipality Municipality name, as listed in data/municipalities.json.
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
				$terms[] = 'kort';
			} elseif ( $km > 10 && $km <= 25 ) {
				$terms[] = 'mellem';
			} elseif ( $km > 25 ) {
				$terms[] = 'lang';
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
		return \Vandrekalender_Municipalities::region_map();
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
	 * Strip gpx_source_url from public REST responses.
	 *
	 * Scrapers set it to avoid re-downloading a GPX file already sideloaded
	 * for the same route (see docs/route-gpx-plan.md); it points at a
	 * third-party URL, so it has no reason to reach a public REST client.
	 *
	 * Only the default `view` context is stripped. The block editor requests
	 * `context=edit` (which core already restricts to users who can edit the
	 * post) to load post data, including meta, before a save — routes are
	 * stored as a single meta array, so if `edit` context did not carry
	 * gpx_source_url through, the next editor save would send the routes
	 * back without it and silently erase it. `context=edit` is never public,
	 * so this still meets the "not public" requirement.
	 *
	 * @param \WP_REST_Response $response The REST response.
	 * @param \WP_Post          $_post    The post object (unused — required by filter signature).
	 * @param \WP_REST_Request  $request  The REST request.
	 * @return \WP_REST_Response
	 */
	public function hide_gpx_source_url_in_rest( \WP_REST_Response $response, \WP_Post $_post, \WP_REST_Request $request ): \WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- required by filter signature.
		if ( 'edit' === $request->get_param( 'context' ) ) {
			return $response;
		}

		$data = $response->get_data();

		if ( empty( $data['meta'][ self::META_ROUTES ] ) || ! is_array( $data['meta'][ self::META_ROUTES ] ) ) {
			return $response;
		}

		$data['meta'][ self::META_ROUTES ] = self::strip_gpx_source_url( $data['meta'][ self::META_ROUTES ] );

		$response->set_data( $data );

		return $response;
	}

	/**
	 * Remove gpx_source_url from every route in a routes array.
	 *
	 * Shared by the core `rest_prepare_event` filter above and by
	 * Vandrekalender_Event_Rest_Api::format_event(), which also puts the raw
	 * `event_routes` meta into its own public response. Both need the same
	 * "never public" rule from docs/route-gpx-plan.md.
	 *
	 * @param array $routes A route array, as stored in event_routes meta.
	 * @return array The same routes with gpx_source_url removed from each.
	 */
	public static function strip_gpx_source_url( array $routes ): array {
		foreach ( $routes as &$route ) {
			if ( is_array( $route ) ) {
				unset( $route['gpx_source_url'] );
			}
		}
		unset( $route );

		return $routes;
	}

	/**
	 * Add a derived gpx_url to every route in a routes array.
	 *
	 * Resolves each route's gpx_id (an attachment ID) to a public URL, so
	 * consumers never need their own wp_get_attachment_url() call. Empty
	 * string when the route has no gpx_id or the attachment no longer
	 * exists. Shared by Vandrekalender_Event_Rest_Api::format_event() and the
	 * event-info-card and event-route-map block renders (see
	 * docs/route-gpx-plan.md, PR 3).
	 *
	 * @param array $routes A route array, as stored in event_routes meta.
	 * @return array The same routes with gpx_url added to each.
	 */
	public static function add_gpx_urls( array $routes ): array {
		foreach ( $routes as &$route ) {
			if ( ! is_array( $route ) ) {
				continue;
			}

			$gpx_id           = ! empty( $route['gpx_id'] ) ? (int) $route['gpx_id'] : 0;
			$gpx_url          = $gpx_id ? wp_get_attachment_url( $gpx_id ) : false;
			$route['gpx_url'] = $gpx_url ? $gpx_url : '';
		}
		unset( $route );

		return $routes;
	}

	/**
	 * An event's stored coordinates, or null when it has none.
	 *
	 * The editor stores 0 when an address is cleared, so 0 counts as missing.
	 *
	 * @param int $post_id Event post ID.
	 * @return array{lat: float, lng: float}|null
	 */
	public static function coordinates( int $post_id ): ?array {
		$lat = (float) get_post_meta( $post_id, self::META_LAT, true );
		$lng = (float) get_post_meta( $post_id, self::META_LNG, true );

		if ( 0.0 === $lat || 0.0 === $lng ) {
			return null;
		}

		return [
			'lat' => $lat,
			'lng' => $lng,
		];
	}

	/**
	 * Place text for events that have coordinates but no place name or
	 * municipality, e.g. "GPS 55.67286° N, 12.56103° E".
	 *
	 * Events placed only by pasted coordinates get no address or
	 * municipality until the reverse lookup exists (PR 3 of
	 * docs/dawa-migration-plan.md), so without this they would show no place
	 * at all. Five decimals is about one metre.
	 *
	 * @param int $post_id Event post ID.
	 * @return string Empty string when the event has no coordinates.
	 */
	public static function coordinates_label( int $post_id ): string {
		$coords = self::coordinates( $post_id );

		if ( null === $coords ) {
			return '';
		}

		$lat = sprintf(
			/* translators: %s: latitude in degrees, e.g. 55.67286 */
			$coords['lat'] >= 0 ? __( '%s° N', 'vandrekalender-events' ) : __( '%s° S', 'vandrekalender-events' ),
			number_format( abs( $coords['lat'] ), 5, '.', '' )
		);
		$lng = sprintf(
			/* translators: %s: longitude in degrees, e.g. 12.56103 */
			$coords['lng'] >= 0 ? __( '%s° E', 'vandrekalender-events' ) : __( '%s° W', 'vandrekalender-events' ),
			number_format( abs( $coords['lng'] ), 5, '.', '' )
		);

		/* translators: 1: latitude, e.g. 55.67286° N, 2: longitude, e.g. 12.56103° E */
		return sprintf( __( 'GPS %1$s, %2$s', 'vandrekalender-events' ), $lat, $lng );
	}

	/**
	 * Google Maps directions link for an event: to its address, or to its
	 * coordinates when it has no address.
	 *
	 * @param int $post_id Event post ID.
	 * @return string Empty string when the event has neither.
	 */
	public static function directions_url( int $post_id ): string {
		$address = trim( (string) get_post_meta( $post_id, self::META_ADDRESS, true ) );
		$coords  = self::coordinates( $post_id );

		if ( '' !== $address ) {
			$destination = $address;
		} elseif ( null !== $coords ) {
			$destination = $coords['lat'] . ',' . $coords['lng'];
		} else {
			return '';
		}

		return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $destination );
	}

	/**
	 * Sort a routes array by distance_km ascending.
	 *
	 * Routes with no distance (left incomplete in the editor) sort last —
	 * there is nothing meaningful to compare them against, so they should
	 * not jump to the front. Used everywhere routes are shown as an ordered
	 * list — the Event Info Card's tabs and the Event Route Map's default
	 * selection and download list — so both blocks agree on the same order
	 * instead of each falling back to event_routes' stored (creation) order.
	 *
	 * @param array $routes A route array, as stored in event_routes meta.
	 * @return array The same routes, sorted by distance_km ascending.
	 */
	public static function sort_routes_by_distance( array $routes ): array {
		usort(
			$routes,
			static function ( $a, $b ) {
				$a_has_distance = is_array( $a ) && isset( $a['distance_km'] ) && '' !== $a['distance_km'];
				$b_has_distance = is_array( $b ) && isset( $b['distance_km'] ) && '' !== $b['distance_km'];

				if ( $a_has_distance !== $b_has_distance ) {
					return $a_has_distance ? -1 : 1;
				}

				if ( ! $a_has_distance ) {
					return 0;
				}

				return (float) $a['distance_km'] <=> (float) $b['distance_km'];
			}
		);

		return $routes;
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
	 * Register the length taxonomy (kort / mellem / lang).
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

		// The Adressevælger token reaches the editor script only through this
		// constant (set per environment, see docs/deployment.md). Without it
		// the Location panel shows "Address search is not configured".
		$address_token = defined( 'VANDREKALENDER_ADRESSEVAELGER_TOKEN' )
			? (string) VANDREKALENDER_ADRESSEVAELGER_TOKEN
			: '';

		wp_add_inline_script(
			'vandrekalender-event-meta-fields',
			'window.vandrekalenderAddressSearch = ' . wp_json_encode( [ 'token' => $address_token ] ) . ';',
			'before'
		);

		wp_set_script_translations(
			'vandrekalender-event-meta-fields',
			'vandrekalender-events'
		);
	}
}
