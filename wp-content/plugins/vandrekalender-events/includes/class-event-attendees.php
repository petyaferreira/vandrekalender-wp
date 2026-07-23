<?php
/**
 * Attendee storage for the "Jeg kommer" join button.
 *
 * One row per (event, user) in a dedicated table rather than a list in post
 * meta: two people pressing the button in the same second would each read the
 * same meta array and write it back, and the second write would silently drop
 * the first person. A row insert with a UNIQUE key cannot lose a join that way,
 * and it lets the database — not PHP — be the thing that rejects a double join.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

/**
 * Creates and reads the event attendees table.
 */
class Vandrekalender_Event_Attendees {

	/**
	 * Bump this whenever the schema below changes.
	 */
	private const SCHEMA_VERSION = 1;

	/**
	 * Option storing the last applied schema version.
	 */
	private const VERSION_OPTION = 'vandrekalender_attendees_schema_version';

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'maybe_install_table' ] );
	}

	/**
	 * Fully qualified table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'event_attendees';
	}

	/**
	 * Create or migrate the table once per SCHEMA_VERSION.
	 *
	 * Same versioned-option pattern as the roles setup and the rewrite flush:
	 * deploys are a file sync, so an activation hook never runs again after the
	 * first install and could not be relied on to add a column.
	 *
	 * @return void
	 */
	public function maybe_install_table(): void {
		if ( (int) get_option( self::VERSION_OPTION ) === self::SCHEMA_VERSION ) {
			return;
		}

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				joined_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY event_user (event_id,user_id),
				KEY user_id (user_id)
			) {$collate};"
		);

		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Record a join.
	 *
	 * INSERT IGNORE plus the UNIQUE key makes a repeated click a no-op instead
	 * of a duplicate row or a fatal — the return value tells the caller whether
	 * this was a genuinely new join, which is what gates the emails.
	 *
	 * @param int $event_id Event post ID.
	 * @param int $user_id  WordPress user ID.
	 * @return bool True when a new row was created, false when already joined.
	 */
	public static function add( int $event_id, int $user_id ): bool {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, name built from $wpdb->prefix.
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be placeholders.
				"INSERT IGNORE INTO {$table} (event_id, user_id, joined_at) VALUES (%d, %d, %s)",
				$event_id,
				$user_id,
				current_time( 'mysql', true )
			)
		);

		return 1 === $wpdb->rows_affected;
	}

	/**
	 * Remove a join.
	 *
	 * Deliberately does not consult is_joinable(): if an event stops accepting
	 * sign-ups after someone joined, they must still be able to cancel.
	 *
	 * @param int $event_id Event post ID.
	 * @param int $user_id  WordPress user ID.
	 * @return bool True when a row was deleted, false when there was none.
	 */
	public static function remove( int $event_id, int $user_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$deleted = $wpdb->delete(
			self::table(),
			[
				'event_id' => $event_id,
				'user_id'  => $user_id,
			],
			[ '%d', '%d' ]
		);

		return (bool) $deleted;
	}

	/**
	 * Whether a user has already joined an event.
	 *
	 * @param int $event_id Event post ID.
	 * @param int $user_id  WordPress user ID.
	 * @return bool
	 */
	public static function is_attending( int $event_id, int $user_id ): bool {
		if ( $event_id <= 0 || $user_id <= 0 ) {
			return false;
		}

		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, name built from $wpdb->prefix.
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be placeholders.
				"SELECT 1 FROM {$table} WHERE event_id = %d AND user_id = %d LIMIT 1",
				$event_id,
				$user_id
			)
		);
	}

	/**
	 * Number of people who have joined an event.
	 *
	 * @param int $event_id Event post ID.
	 * @return int
	 */
	public static function count( int $event_id ): int {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table, name built from $wpdb->prefix.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be placeholders.
				"SELECT COUNT(*) FROM {$table} WHERE event_id = %d",
				$event_id
			)
		);
	}

	/**
	 * Whether an event accepts joins at all.
	 *
	 * Only events created on vandrekalender.dk qualify. A scraped or Facebook
	 * event is run by an external organiser who never sees our attendee list,
	 * so a join there would promise the visitor a registration that does not
	 * exist — those events keep their "book with the organiser" link instead.
	 *
	 * @param int $event_id Event post ID.
	 * @return bool
	 */
	public static function is_joinable( int $event_id ): bool {
		$post = get_post( $event_id );

		$joinable = $post
			&& \Vandrekalender\Event::CUSTOMPOSTTYPE === $post->post_type
			&& 'publish' === $post->post_status
			// Legacy rows predate the meta default, so an empty value is manual.
			&& in_array( get_post_meta( $event_id, \Vandrekalender\Event::META_SOURCE, true ), [ '', 'manual' ], true );

		/**
		 * Filter whether an event accepts "Jeg kommer" joins.
		 *
		 * @param bool $joinable Whether joining is allowed.
		 * @param int  $event_id Event post ID.
		 */
		return (bool) apply_filters( 'vandrekalender_event_is_joinable', $joinable, $event_id );
	}
}
