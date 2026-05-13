<?php
/**
 * Event Meta Class
 *
 * Registers meta fields for the vandrekalender_event post type and
 * renders a meta box so fields are editable in the WP admin editor.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles meta field registration and the admin meta box.
 */
class Vandrekalender_Event_Meta {

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_meta' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'save_post_' . Vandrekalender_Event_Post_Type::POST_TYPE, [ $this, 'save_meta_box' ] );
	}

	/**
	 * Register all meta fields for the vandrekalender_event post type.
	 *
	 * Show_in_rest => true (or with a schema) exposes each field via the REST API,
	 * which is required for Gutenberg to read and write them.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		$post_type = Vandrekalender_Event_Post_Type::POST_TYPE;

		// Event date (ISO 8601 date string, e.g. "2025-08-15").
		register_post_meta(
			$post_type,
			'_event_date',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);

		// Human-readable location name (e.g. "Dyrehaven, Klampenborg").
		register_post_meta(
			$post_type,
			'_event_location_name',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);

		// Full address (street, zip, city).
		register_post_meta(
			$post_type,
			'_event_address',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);

		// Start point latitude.
		register_post_meta(
			$post_type,
			'_event_start_lat',
			[
				'type'              => 'number',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => 0.0,
				'sanitize_callback' => 'floatval',
			]
		);

		// Start point longitude.
		register_post_meta(
			$post_type,
			'_event_start_lng',
			[
				'type'              => 'number',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => 0.0,
				'sanitize_callback' => 'floatval',
			]
		);

		// Organiser name.
		register_post_meta(
			$post_type,
			'_event_organiser',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);

		// Original source URL — used for deduplication in scrapers.
		register_post_meta(
			$post_type,
			'_event_source_url',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			]
		);

		// Claim status: 'unclaimed' (scraped) or 'claimed' (organiser has taken ownership).
		register_post_meta(
			$post_type,
			'_event_claim_status',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => 'unclaimed',
				'sanitize_callback' => 'sanitize_text_field',
			]
		);

		// Routes — JSON array of route/distance options.
		// Each item: { km, price, start_time, max_time, finish_lat, finish_lng,
		// route_map_url, registration_url, description }
		// The vandrekalender_distance_range taxonomy is auto-synced from this on save.
		register_post_meta(
			$post_type,
			'_event_routes',
			[
				'type'         => 'array',
				'single'       => true,
				'default'      => [],
				'show_in_rest' => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => [
								'km'               => [ 'type' => 'number' ],
								'price'            => [ 'type' => 'number' ],
								'start_time'       => [ 'type' => 'string' ],
								'max_time'         => [ 'type' => 'string' ],
								'finish_lat'       => [ 'type' => 'number' ],
								'finish_lng'       => [ 'type' => 'number' ],
								'route_map_url'    => [ 'type' => 'string' ],
								'registration_url' => [ 'type' => 'string' ],
								'description'      => [ 'type' => 'string' ],
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Register the meta box on the event editor screen.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'vandrekalender_event_details',
			__( 'Event Details', 'vandrekalender-events' ),
			[ $this, 'render_meta_box' ],
			Vandrekalender_Event_Post_Type::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the Event Details meta box.
	 *
	 * @param \WP_Post $post The current post object.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'vandrekalender_save_event_meta', 'vandrekalender_event_nonce' );

		$date             = get_post_meta( $post->ID, '_event_date', true );
		$location_name    = get_post_meta( $post->ID, '_event_location_name', true );
		$address          = get_post_meta( $post->ID, '_event_address', true );
		$start_lat        = get_post_meta( $post->ID, '_event_start_lat', true );
		$start_lng        = get_post_meta( $post->ID, '_event_start_lng', true );
		$organiser        = get_post_meta( $post->ID, '_event_organiser', true );
		$source_url       = get_post_meta( $post->ID, '_event_source_url', true );
		$claim_status_raw = get_post_meta( $post->ID, '_event_claim_status', true );
		$claim_status     = $claim_status_raw ? $claim_status_raw : 'unclaimed';
		$routes_raw       = get_post_meta( $post->ID, '_event_routes', true );
		$routes_json      = is_array( $routes_raw ) ? wp_json_encode( $routes_raw, JSON_PRETTY_PRINT ) : '[]';

		?>
		<style>
			.vk-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; margin-bottom: 16px; }
			.vk-meta-grid label, .vk-meta-full label { display: block; font-weight: 600; margin-bottom: 4px; }
			.vk-meta-grid input, .vk-meta-full input, .vk-meta-full select, .vk-meta-full textarea { width: 100%; }
			.vk-meta-full { margin-bottom: 12px; }
			.vk-meta-section { border-top: 1px solid #ddd; margin-top: 16px; padding-top: 12px; }
			.vk-meta-section h4 { margin: 0 0 10px; font-size: 13px; text-transform: uppercase; color: #666; letter-spacing: .04em; }
			.vk-routes-hint { font-size: 12px; color: #888; margin-top: 4px; }
		</style>

		<div class="vk-meta-grid">
			<div>
				<label for="vk_event_date"><?php esc_html_e( 'Event Date', 'vandrekalender-events' ); ?></label>
				<input type="date" id="vk_event_date" name="vk_event_date"
					value="<?php echo esc_attr( $date ); ?>">
			</div>
			<div>
				<label for="vk_event_claim_status"><?php esc_html_e( 'Claim Status', 'vandrekalender-events' ); ?></label>
				<select id="vk_event_claim_status" name="vk_event_claim_status">
					<option value="unclaimed" <?php selected( $claim_status, 'unclaimed' ); ?>>
						<?php esc_html_e( 'Unclaimed (scraped)', 'vandrekalender-events' ); ?>
					</option>
					<option value="claimed" <?php selected( $claim_status, 'claimed' ); ?>>
						<?php esc_html_e( 'Claimed', 'vandrekalender-events' ); ?>
					</option>
				</select>
			</div>
		</div>

		<div class="vk-meta-full">
			<label for="vk_event_organiser"><?php esc_html_e( 'Organiser', 'vandrekalender-events' ); ?></label>
			<input type="text" id="vk_event_organiser" name="vk_event_organiser"
				value="<?php echo esc_attr( $organiser ); ?>">
		</div>

		<div class="vk-meta-section">
			<h4><?php esc_html_e( 'Location', 'vandrekalender-events' ); ?></h4>
		</div>

		<div class="vk-meta-full">
			<label for="vk_event_location_name"><?php esc_html_e( 'Location Name', 'vandrekalender-events' ); ?></label>
			<input type="text" id="vk_event_location_name" name="vk_event_location_name"
				placeholder="<?php esc_attr_e( 'e.g. Dyrehaven, Klampenborg', 'vandrekalender-events' ); ?>"
				value="<?php echo esc_attr( $location_name ); ?>">
		</div>

		<div class="vk-meta-full">
			<label for="vk_event_address"><?php esc_html_e( 'Address', 'vandrekalender-events' ); ?></label>
			<input type="text" id="vk_event_address" name="vk_event_address"
				placeholder="<?php esc_attr_e( 'Street, ZIP City', 'vandrekalender-events' ); ?>"
				value="<?php echo esc_attr( $address ); ?>">
		</div>

		<div class="vk-meta-grid">
			<div>
				<label for="vk_event_start_lat"><?php esc_html_e( 'Start Latitude', 'vandrekalender-events' ); ?></label>
				<input type="number" id="vk_event_start_lat" name="vk_event_start_lat"
					step="any" value="<?php echo esc_attr( $start_lat ); ?>">
			</div>
			<div>
				<label for="vk_event_start_lng"><?php esc_html_e( 'Start Longitude', 'vandrekalender-events' ); ?></label>
				<input type="number" id="vk_event_start_lng" name="vk_event_start_lng"
					step="any" value="<?php echo esc_attr( $start_lng ); ?>">
			</div>
		</div>

		<div class="vk-meta-section">
			<h4><?php esc_html_e( 'Routes', 'vandrekalender-events' ); ?></h4>
		</div>

		<div class="vk-meta-full">
			<label for="vk_event_routes"><?php esc_html_e( 'Routes (JSON)', 'vandrekalender-events' ); ?></label>
			<textarea id="vk_event_routes" name="vk_event_routes"
				rows="10"><?php echo esc_textarea( $routes_json ); ?></textarea>
			<p class="vk-routes-hint">
				<?php
				esc_html_e(
					'JSON array of route objects. Each route: { "km": 25, "price": 0, "start_time": "09:00", "max_time": "8:00", "registration_url": "https://..." }. Distance Range taxonomy is auto-assigned from km values on save.',
					'vandrekalender-events'
				);
				?>
			</p>
		</div>

		<div class="vk-meta-section">
			<h4><?php esc_html_e( 'Source', 'vandrekalender-events' ); ?></h4>
		</div>

		<div class="vk-meta-full">
			<label for="vk_event_source_url"><?php esc_html_e( 'Source URL', 'vandrekalender-events' ); ?></label>
			<input type="url" id="vk_event_source_url" name="vk_event_source_url"
				value="<?php echo esc_attr( $source_url ); ?>">
		</div>
		<?php
	}

	/**
	 * Save meta box values when the event post is saved.
	 *
	 * @param int $post_id The post ID being saved.
	 * @return void
	 */
	public function save_meta_box( int $post_id ): void {
		// Verify nonce.
		if (
			! isset( $_POST['vandrekalender_event_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['vandrekalender_event_nonce'] ) ),
				'vandrekalender_save_event_meta'
			)
		) {
			return;
		}

		// Don't save during autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Only save for users who can edit this post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Simple string fields.
		$string_fields = [
			'vk_event_date'          => '_event_date',
			'vk_event_location_name' => '_event_location_name',
			'vk_event_address'       => '_event_address',
			'vk_event_organiser'     => '_event_organiser',
			'vk_event_claim_status'  => '_event_claim_status',
		];

		foreach ( $string_fields as $input_name => $meta_key ) {
			if ( isset( $_POST[ $input_name ] ) ) {
				update_post_meta(
					$post_id,
					$meta_key,
					sanitize_text_field( wp_unslash( $_POST[ $input_name ] ) )
				);
			}
		}

		// Source URL.
		if ( isset( $_POST['vk_event_source_url'] ) ) {
			update_post_meta(
				$post_id,
				'_event_source_url',
				esc_url_raw( wp_unslash( $_POST['vk_event_source_url'] ) )
			);
		}

		// Coordinates.
		if ( isset( $_POST['vk_event_start_lat'] ) ) {
			update_post_meta( $post_id, '_event_start_lat', (float) $_POST['vk_event_start_lat'] );
		}
		if ( isset( $_POST['vk_event_start_lng'] ) ) {
			update_post_meta( $post_id, '_event_start_lng', (float) $_POST['vk_event_start_lng'] );
		}

		// Routes JSON — decode, validate, re-encode before saving.
		if ( isset( $_POST['vk_event_routes'] ) ) {
			$raw    = wp_unslash( $_POST['vk_event_routes'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below after JSON decode.
			$routes = json_decode( $raw, true );

			if ( is_array( $routes ) ) {
				$sanitized = $this->sanitize_routes( $routes );
				update_post_meta( $post_id, '_event_routes', $sanitized );
			}
		}
	}

	/**
	 * Sanitize an array of route objects.
	 *
	 * @param array $routes Raw routes array from JSON decode.
	 * @return array Sanitized routes.
	 */
	private function sanitize_routes( array $routes ): array {
		$clean = [];

		foreach ( $routes as $route ) {
			if ( ! is_array( $route ) ) {
				continue;
			}

			$clean[] = [
				'km'               => isset( $route['km'] ) ? (float) $route['km'] : 0.0,
				'price'            => isset( $route['price'] ) ? (float) $route['price'] : 0.0,
				'start_time'       => isset( $route['start_time'] ) ? sanitize_text_field( $route['start_time'] ) : '',
				'max_time'         => isset( $route['max_time'] ) ? sanitize_text_field( $route['max_time'] ) : '',
				'finish_lat'       => isset( $route['finish_lat'] ) ? (float) $route['finish_lat'] : 0.0,
				'finish_lng'       => isset( $route['finish_lng'] ) ? (float) $route['finish_lng'] : 0.0,
				'route_map_url'    => isset( $route['route_map_url'] ) ? esc_url_raw( $route['route_map_url'] ) : '',
				'registration_url' => isset( $route['registration_url'] ) ? esc_url_raw( $route['registration_url'] ) : '',
				'description'      => isset( $route['description'] ) ? sanitize_textarea_field( $route['description'] ) : '',
			];
		}

		return $clean;
	}
}
