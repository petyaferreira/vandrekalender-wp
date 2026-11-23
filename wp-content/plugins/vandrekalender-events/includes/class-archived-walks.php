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
 * Walks are archived by the weekly job (Vandrekalender_Archive_Job), or by
 * hand with "Fjern nu" in the Events list (#268), for example when a private
 * guide asks for their walks to be removed early.
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
	 * The admin-post.php action of the "Fjern nu" row action.
	 */
	const REMOVE_ACTION = 'vk_remove_walk';

	/**
	 * Query param that names the action and walk for the notice after the
	 * redirect, as "<action>-<post ID>".
	 */
	const DONE_PARAM = 'vk_walk_done';

	/**
	 * What handle_status_change() reports.
	 */
	const CHANGED     = 'changed';
	const NOT_ALLOWED = 'not_allowed';
	const WRONG_STATE = 'wrong_state';
	const FAILED      = 'failed';

	/**
	 * Constructor — hooks the status, the archived date, "Gendan" and
	 * "Fjern nu".
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_status' ] );
		add_action( 'transition_post_status', [ $this, 'track_archived_at' ], 10, 3 );
		add_filter( 'post_row_actions', [ $this, 'add_row_actions' ], 10, 2 );
		add_action( 'admin_post_' . self::RESTORE_ACTION, [ $this, 'handle_row_action' ] );
		add_action( 'admin_post_' . self::REMOVE_ACTION, [ $this, 'handle_row_action' ] );
		add_action( 'admin_notices', [ $this, 'render_done_notice' ] );
	}

	/**
	 * The two row actions: the status a walk must have, the one it gets, and
	 * whether publishing rights are needed.
	 *
	 * @return array<string, array{from: string, to: string, publishes: bool}>
	 */
	private static function actions(): array {
		return [
			self::RESTORE_ACTION => [
				'from'      => self::STATUS,
				'to'        => 'publish',
				'publishes' => true,
			],
			self::REMOVE_ACTION  => [
				'from'      => 'publish',
				'to'        => self::STATUS,
				'publishes' => false,
			],
		];
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
	 * Adds "Gendan" to an archived walk's row in the Events list, and
	 * "Fjern nu" to a published walk's row (any date, also a kept one).
	 *
	 * @param array<string,string> $actions Row actions.
	 * @param \WP_Post             $post    The row's post.
	 * @return array<string,string>
	 */
	public function add_row_actions( array $actions, \WP_Post $post ): array {
		if ( \Vandrekalender\Event::CUSTOMPOSTTYPE !== $post->post_type ) {
			return $actions;
		}

		foreach ( self::actions() as $action => $rule ) {
			if ( $rule['from'] !== $post->post_status || ! self::can( $action, $post->ID ) ) {
				continue;
			}

			$url = wp_nonce_url(
				add_query_arg(
					[
						'action' => $action,
						'post'   => $post->ID,
					],
					admin_url( 'admin-post.php' )
				),
				$action . '_' . $post->ID
			);

			if ( self::RESTORE_ACTION === $action ) {
				$actions['vk_restore'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Restore', 'vandrekalender-events' ) . '</a>';
			} else {
				$confirm              = __( 'Remove this walk from the site now? You can undo it with Restore in the Archived list.', 'vandrekalender-events' );
				$actions['vk_remove'] = '<a href="' . esc_url( $url ) . '" onclick="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ');">' . esc_html__( 'Remove now', 'vandrekalender-events' ) . '</a>';
			}
		}

		return $actions;
	}

	/**
	 * "Gendan" and "Fjern nu": change the walk's status, then return to the
	 * Events list with a notice. track_archived_at() sets or clears
	 * event_archived_at.
	 *
	 * @return void
	 */
	public function handle_row_action(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the nonce is checked in handle_status_change().
		$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		// phpcs:enable

		$result = self::handle_status_change( $action, $post_id, false !== wp_verify_nonce( $nonce, $action . '_' . $post_id ) );

		switch ( $result ) {
			case self::NOT_ALLOWED:
				wp_die( esc_html__( 'You are not allowed to change this walk.', 'vandrekalender-events' ), 403 );
				break;
			case self::WRONG_STATE:
				wp_die( esc_html__( 'This walk cannot be changed this way: its status has changed. Reload the list.', 'vandrekalender-events' ), 400 );
				break;
			case self::FAILED:
				wp_die( esc_html__( 'The walk could not be saved. Try again.', 'vandrekalender-events' ), 500 );
				break;
		}

		$args = [
			'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
			self::DONE_PARAM => $action . '-' . $post_id,
		];
		// After "Gendan", stay in the Archived view the walk was restored from.
		if ( self::RESTORE_ACTION === $action ) {
			$args['post_status'] = self::STATUS;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php' ) ) );
		exit;
	}

	/**
	 * The checks and the change behind a row action, without the request
	 * around it: nothing changes unless the nonce is valid, the user may do
	 * it, and the walk has the status the action starts from.
	 *
	 * @param string $action   RESTORE_ACTION or REMOVE_ACTION.
	 * @param int    $post_id  Walk ID.
	 * @param bool   $nonce_ok Whether the request's nonce is valid.
	 * @return string CHANGED, NOT_ALLOWED, WRONG_STATE or FAILED.
	 */
	public static function handle_status_change( string $action, int $post_id, bool $nonce_ok ): string {
		$rules = self::actions();

		if ( ! $nonce_ok || ! isset( $rules[ $action ] ) || ! $post_id || ! self::can( $action, $post_id ) ) {
			return self::NOT_ALLOWED;
		}

		$post = get_post( $post_id );
		if ( ! $post || \Vandrekalender\Event::CUSTOMPOSTTYPE !== $post->post_type || $rules[ $action ]['from'] !== $post->post_status ) {
			return self::WRONG_STATE;
		}

		$result = wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => $rules[ $action ]['to'],
			],
			true
		);

		return is_wp_error( $result ) || ! $result ? self::FAILED : self::CHANGED;
	}

	/**
	 * Whether the current user may run $action on a walk. Both need the
	 * right to edit it; "Gendan" publishes, so it also needs the right to
	 * publish.
	 *
	 * @param string $action  RESTORE_ACTION or REMOVE_ACTION.
	 * @param int    $post_id Walk ID.
	 * @return bool
	 */
	private static function can( string $action, int $post_id ): bool {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		return ! self::actions()[ $action ]['publishes'] || current_user_can( 'publish_post', $post_id );
	}

	/**
	 * After a row action, says what happened to which walk.
	 *
	 * @return void
	 */
	public function render_done_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice after a nonce-checked redirect.
		$done = isset( $_GET[ self::DONE_PARAM ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::DONE_PARAM ] ) ) : '';
		if ( ! preg_match( '/^(' . self::RESTORE_ACTION . '|' . self::REMOVE_ACTION . ')-(\d+)$/', $done, $match ) ) {
			return;
		}

		$post_id = (int) $match[2];
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$message = self::RESTORE_ACTION === $match[1]
			/* translators: %s: walk title. */
			? __( '"%s" is published again.', 'vandrekalender-events' )
			/* translators: %s: walk title. */
			: __( '"%s" is archived and no longer shown on the site.', 'vandrekalender-events' );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( sprintf( $message, get_the_title( $post_id ) ) )
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
