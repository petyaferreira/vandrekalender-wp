<?php

defined( 'ABSPATH' ) || exit;

/**
 * The `archived` status for walks (#37, step 2: #266; decision 0018).
 *
 * An archived walk is left out of every list and its URL answers 410 Gone,
 * or 301 to the next occurrence of its series (see
 * Vandrekalender_Event_Past_Events::handle_archived_walk()). Its row and files
 * stay, so "Gendan" in the Events list publishes it again. When a walk
 * becomes archived it gets event_archived_at, the date the purge (step 5)
 * counts from.
 *
 * Nothing archives walks automatically yet; that is step 3 (#267).
 *
 * @package Vandrekalender
 */
class Vandrekalender_Archived_Walks {

	/**
	 * Post status of an archived walk.
	 */
	const STATUS = 'archived';

	/**
	 * Meta: the day (Y-m-d, site timezone) the walk became archived.
	 */
	const META_ARCHIVED_AT = 'event_archived_at';

	/**
	 * The admin-post.php action of the "Gendan" row action.
	 */
	const RESTORE_ACTION = 'vk_restore_walk';

	/**
	 * Query param that shows the "restored" notice after "Gendan".
	 */
	const RESTORED_PARAM = 'vk_restored';

	/**
	 * Constructor — hooks the status, the archived date and "Gendan".
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_status' ] );
		add_action( 'transition_post_status', [ $this, 'track_archived_at' ], 10, 3 );
		add_filter( 'post_row_actions', [ $this, 'add_restore_row_action' ], 10, 2 );
		add_action( 'admin_post_' . self::RESTORE_ACTION, [ $this, 'handle_restore' ] );
		add_action( 'admin_notices', [ $this, 'render_restored_notice' ] );
	}

	/**
	 * Registers the status and its meta.
	 *
	 * Not public, so WordPress 404s the URL (handle_archived_walk() then
	 * answers 410 or 301). exclude_from_search is false on purpose: that keeps
	 * archived walks in `post_status => 'any'` queries, so the scrapers' and
	 * the Facebook importer's lookups by source URL still find an archived
	 * walk instead of creating a duplicate. Front-end lists all ask for
	 * 'publish', so they leave archived walks out.
	 *
	 * @return void
	 */
	public function register_status(): void {
		register_post_status(
			self::STATUS,
			[
				'label'                     => _x( 'Archived', 'post status', 'vandrekalender-events' ),
				/* translators: %s: number of archived walks. */
				'label_count'               => _n_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>', 'vandrekalender-events' ),
				'public'                    => false,
				'internal'                  => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => true,
			]
		);

		register_post_meta(
			\Vandrekalender\Event::CUSTOMPOSTTYPE,
			self::META_ARCHIVED_AT,
			[
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => false,
				'default'      => '',
			]
		);
	}

	/**
	 * Sets event_archived_at when a walk becomes archived, and clears it when
	 * it leaves the status.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       The post.
	 * @return void
	 */
	public function track_archived_at( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( \Vandrekalender\Event::CUSTOMPOSTTYPE !== $post->post_type || $new_status === $old_status ) {
			return;
		}

		if ( self::STATUS === $new_status ) {
			update_post_meta( $post->ID, self::META_ARCHIVED_AT, current_time( 'Y-m-d' ) );
		} elseif ( self::STATUS === $old_status ) {
			delete_post_meta( $post->ID, self::META_ARCHIVED_AT );
		}
	}

	/**
	 * Adds "Gendan" to an archived walk's row in the Events list.
	 *
	 * @param array<string,string> $actions Row actions.
	 * @param \WP_Post             $post    The row's post.
	 * @return array<string,string>
	 */
	public function add_restore_row_action( array $actions, \WP_Post $post ): array {
		if ( \Vandrekalender\Event::CUSTOMPOSTTYPE !== $post->post_type || self::STATUS !== $post->post_status ) {
			return $actions;
		}

		if ( ! self::can_restore( $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				[
					'action' => self::RESTORE_ACTION,
					'post'   => $post->ID,
				],
				admin_url( 'admin-post.php' )
			),
			self::RESTORE_ACTION . '_' . $post->ID
		);

		$actions['vk_restore'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Restore', 'vandrekalender-events' ) . '</a>';

		return $actions;
	}

	/**
	 * "Gendan": publishes an archived walk again, then returns to the list.
	 * track_archived_at() clears event_archived_at.
	 *
	 * @return void
	 */
	public function handle_restore(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		check_admin_referer( self::RESTORE_ACTION . '_' . $post_id );

		if ( ! $post_id || ! self::can_restore( $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to restore this walk.', 'vandrekalender-events' ), 403 );
		}

		$post = get_post( $post_id );
		if ( ! $post || \Vandrekalender\Event::CUSTOMPOSTTYPE !== $post->post_type || self::STATUS !== $post->post_status ) {
			wp_die( esc_html__( 'This walk is not archived.', 'vandrekalender-events' ), 400 );
		}

		$result = wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'publish',
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), 500 );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'post_type'          => \Vandrekalender\Event::CUSTOMPOSTTYPE,
					'post_status'        => self::STATUS,
					self::RESTORED_PARAM => $post_id,
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Whether the current user may restore a walk: "Gendan" publishes it, so
	 * it needs the right to publish as well as to edit.
	 *
	 * @param int $post_id Walk ID.
	 * @return bool
	 */
	private static function can_restore( int $post_id ): bool {
		return current_user_can( 'edit_post', $post_id ) && current_user_can( 'publish_post', $post_id );
	}

	/**
	 * After "Gendan", says which walk was published again.
	 *
	 * @return void
	 */
	public function render_restored_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice after a nonce-checked redirect.
		$post_id = isset( $_GET[ self::RESTORED_PARAM ] ) ? absint( $_GET[ self::RESTORED_PARAM ] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: walk title. */
					__( '"%s" is published again.', 'vandrekalender-events' ),
					get_the_title( $post_id )
				)
			)
		);
	}

	/**
	 * Whether a scraper should leave an archived walk archived: its scraped
	 * date is still before the retention cutoff. A date inside the window
	 * (e.g. a yearly event that reuses its URL) means the walk is current
	 * again, so the scraper updates and publishes it.
	 *
	 * @param string $status       The existing post's status.
	 * @param string $scraped_date The scraped event_date (Y-m-d), may be ''.
	 * @param string $today        Today as Y-m-d.
	 * @return bool
	 */
	public static function scraper_keeps_archived( string $status, string $scraped_date, string $today ): bool {
		if ( self::STATUS !== $status ) {
			return false;
		}

		// No date: nothing says the walk is current again.
		if ( '' === $scraped_date ) {
			return true;
		}

		return $scraped_date < Vandrekalender_Past_Walks::retention_cutoff( $today );
	}
}
