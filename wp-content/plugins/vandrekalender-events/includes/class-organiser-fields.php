<?php

defined( 'ABSPATH' ) || exit;

/**
 * Fields of an organiser (an event_organiser term) for its page (#32).
 *
 * Only the short values the page template builds from live here as term
 * meta: short description, website, official badge and where the facts
 * were checked. Everything written as prose (the about text, the facts
 * table, the FAQ) lives in a synced pattern per organiser, edited in the
 * block editor, and the term only stores which pattern it is. Why:
 * docs/decisions/0008-organiser-content-in-patterns.md.
 *
 * The fields are on the term's edit screen (Events → Organisers → edit),
 * not on the "Add New Organiser" form: add the organiser, then edit it.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Organiser_Fields {

	const META_SHORT_DESCRIPTION = 'organiser_short_description';
	const META_WEBSITE_URL       = 'organiser_website_url';
	const META_IS_OFFICIAL       = 'organiser_is_official';
	const META_SOURCE_URL        = 'organiser_source_url';
	const META_SOURCE_CHECKED    = 'organiser_source_checked';
	const META_PATTERN_ID        = 'organiser_pattern_id';

	const NONCE_ACTION = 'vk_organiser_fields';
	const NONCE_FIELD  = 'vk_organiser_fields_nonce';

	/**
	 * Constructor — registers the meta and the edit-screen fields.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_meta' ] );
		add_action( \Vandrekalender\Event::TAX_ORGANISER . '_edit_form_fields', [ $this, 'render_fields' ] );
		add_action( 'edited_' . \Vandrekalender\Event::TAX_ORGANISER, [ $this, 'save_fields' ] );
	}

	/**
	 * Register the term meta, readable in REST for the page blocks. Writing
	 * needs the right to edit the term (admins, see the taxonomy's caps).
	 *
	 * @return void
	 */
	public function register_meta(): void {
		$types = [
			self::META_SHORT_DESCRIPTION => 'string',
			self::META_WEBSITE_URL       => 'string',
			self::META_IS_OFFICIAL       => 'boolean',
			self::META_SOURCE_URL        => 'string',
			self::META_SOURCE_CHECKED    => 'string',
			self::META_PATTERN_ID        => 'integer',
		];

		foreach ( $types as $key => $type ) {
			register_term_meta(
				\Vandrekalender\Event::TAX_ORGANISER,
				$key,
				[
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => fn( $value ) => self::sanitize_one( $key, $value ),
					'auth_callback'     => fn( $allowed, $meta_key, $term_id ) => current_user_can( 'edit_term', $term_id ),
				]
			);
		}
	}

	/**
	 * Clean raw form or REST input. Every key is optional; a missing key
	 * becomes its empty value, so the result always has all six.
	 *
	 * Pure (no database), so it is unit tested. Whether the pattern exists
	 * is checked separately in valid_pattern_id().
	 *
	 * @param array $raw Raw values keyed by meta key.
	 * @return array Clean values keyed by meta key.
	 */
	public static function sanitize( array $raw ): array {
		$date = trim( (string) ( $raw[ self::META_SOURCE_CHECKED ] ?? '' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! checkdate( (int) substr( $date, 5, 2 ), (int) substr( $date, 8, 2 ), (int) substr( $date, 0, 4 ) ) ) {
			$date = '';
		}

		return [
			self::META_SHORT_DESCRIPTION => sanitize_textarea_field( (string) ( $raw[ self::META_SHORT_DESCRIPTION ] ?? '' ) ),
			self::META_WEBSITE_URL       => esc_url_raw( trim( (string) ( $raw[ self::META_WEBSITE_URL ] ?? '' ) ), [ 'http', 'https' ] ),
			self::META_IS_OFFICIAL       => ! empty( $raw[ self::META_IS_OFFICIAL ] ),
			self::META_SOURCE_URL        => esc_url_raw( trim( (string) ( $raw[ self::META_SOURCE_URL ] ?? '' ) ), [ 'http', 'https' ] ),
			self::META_SOURCE_CHECKED    => $date,
			self::META_PATTERN_ID        => max( 0, (int) ( $raw[ self::META_PATTERN_ID ] ?? 0 ) ),
		];
	}

	/**
	 * Clean one value, including the pattern check, for REST and
	 * update_term_meta() writes.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Raw value.
	 * @return mixed Clean value.
	 */
	private static function sanitize_one( string $key, $value ) {
		$clean = self::sanitize( [ $key => $value ] )[ $key ];

		return self::META_PATTERN_ID === $key ? self::valid_pattern_id( $clean ) : $clean;
	}

	/**
	 * All fields of one organiser, with empty values for unset ones. The
	 * pattern ID is 0 when the pattern was deleted or unpublished since.
	 *
	 * @param int $term_id Organiser term ID.
	 * @return array Values keyed by meta key.
	 */
	public static function get( int $term_id ): array {
		$raw = [];
		foreach ( array_keys( self::sanitize( [] ) ) as $key ) {
			$raw[ $key ] = get_term_meta( $term_id, $key, true );
		}

		$fields                          = self::sanitize( $raw );
		$fields[ self::META_PATTERN_ID ] = self::valid_pattern_id( $fields[ self::META_PATTERN_ID ] );

		return $fields;
	}

	/**
	 * The ID if it is a published pattern (wp_block), else 0.
	 *
	 * @param int $pattern_id Post ID.
	 * @return int
	 */
	private static function valid_pattern_id( int $pattern_id ): int {
		if ( $pattern_id <= 0 ) {
			return 0;
		}

		$post = get_post( $pattern_id );

		return $post && 'wp_block' === $post->post_type && 'publish' === $post->post_status ? $pattern_id : 0;
	}

	/**
	 * The fields as rows of the term edit screen's form table.
	 *
	 * @param WP_Term $term The organiser being edited.
	 * @return void
	 */
	public function render_fields( WP_Term $term ): void {
		$fields   = self::get( $term->term_id );
		$patterns = get_posts(
			[
				'post_type'      => 'wp_block',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			]
		);

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<tr class="form-field">
			<th scope="row"><label for="vk-organiser-short-description"><?php esc_html_e( 'Short description', 'vandrekalender-events' ); ?></label></th>
			<td>
				<textarea id="vk-organiser-short-description" name="<?php echo esc_attr( self::META_SHORT_DESCRIPTION ); ?>" rows="3"><?php echo esc_textarea( $fields[ self::META_SHORT_DESCRIPTION ] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One or two sentences, shown at the top of the organiser page.', 'vandrekalender-events' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="vk-organiser-website"><?php esc_html_e( 'Website', 'vandrekalender-events' ); ?></label></th>
			<td><input type="url" id="vk-organiser-website" name="<?php echo esc_attr( self::META_WEBSITE_URL ); ?>" value="<?php echo esc_attr( $fields[ self::META_WEBSITE_URL ] ); ?>" placeholder="https://" /></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Official organiser', 'vandrekalender-events' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( self::META_IS_OFFICIAL ); ?>" value="1" <?php checked( $fields[ self::META_IS_OFFICIAL ] ); ?> />
					<?php esc_html_e( 'Show the "Official organiser" badge', 'vandrekalender-events' ); ?>
				</label>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="vk-organiser-source-url"><?php esc_html_e( 'Source', 'vandrekalender-events' ); ?></label></th>
			<td>
				<input type="url" id="vk-organiser-source-url" name="<?php echo esc_attr( self::META_SOURCE_URL ); ?>" value="<?php echo esc_attr( $fields[ self::META_SOURCE_URL ] ); ?>" placeholder="https://" />
				<p>
					<label for="vk-organiser-source-checked"><?php esc_html_e( 'Checked on', 'vandrekalender-events' ); ?></label>
					<input type="date" id="vk-organiser-source-checked" name="<?php echo esc_attr( self::META_SOURCE_CHECKED ); ?>" value="<?php echo esc_attr( $fields[ self::META_SOURCE_CHECKED ] ); ?>" />
				</p>
				<p class="description"><?php esc_html_e( 'Where the facts on the page come from, and when they were checked.', 'vandrekalender-events' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="vk-organiser-pattern"><?php esc_html_e( 'Page content', 'vandrekalender-events' ); ?></label></th>
			<td>
				<select id="vk-organiser-pattern" name="<?php echo esc_attr( self::META_PATTERN_ID ); ?>">
					<option value="0"><?php esc_html_e( '— None —', 'vandrekalender-events' ); ?></option>
					<?php foreach ( $patterns as $pattern ) : ?>
						<option value="<?php echo esc_attr( (string) $pattern->ID ); ?>" <?php selected( $fields[ self::META_PATTERN_ID ], $pattern->ID ); ?>><?php echo esc_html( get_the_title( $pattern ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					<?php esc_html_e( 'The pattern with the about text, facts and FAQ (Details blocks) for this organiser.', 'vandrekalender-events' ); ?>
					<?php if ( $fields[ self::META_PATTERN_ID ] ) : ?>
						<a href="<?php echo esc_url( (string) get_edit_post_link( $fields[ self::META_PATTERN_ID ] ) ); ?>"><?php esc_html_e( 'Edit this pattern', 'vandrekalender-events' ); ?></a> ·
					<?php endif; ?>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wp_block' ) ); ?>"><?php esc_html_e( 'Create a pattern', 'vandrekalender-events' ); ?></a>
				</p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save the fields from the term edit form.
	 *
	 * @param int $term_id Organiser term ID.
	 * @return void
	 */
	public function save_fields( int $term_id ): void {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$raw = [];
		foreach ( array_keys( self::sanitize( [] ) ) as $key ) {
			$raw[ $key ] = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below in sanitize().
		}

		$fields                          = self::sanitize( $raw );
		$fields[ self::META_PATTERN_ID ] = self::valid_pattern_id( $fields[ self::META_PATTERN_ID ] );

		foreach ( $fields as $key => $value ) {
			if ( '' === $value || false === $value || 0 === $value ) {
				delete_term_meta( $term_id, $key );
			} else {
				update_term_meta( $term_id, $key, $value );
			}
		}
	}
}
