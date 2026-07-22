<?php
/**
 * Organizer dashboard.
 *
 * Replaces the default WordPress Dashboard screen for the Event Organizer role
 * with a single onboarding widget whose only job is to get a new user to their
 * first event as fast as possible. Administrators keep the normal dashboard.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

/**
 * Custom dashboard widget for the Event Organizer role.
 */
class Vandrekalender_Organizer_Dashboard {

	/**
	 * The onboarding video, shipped with the plugin.
	 *
	 * Deliberately a bundled file rather than a Media Library attachment. An
	 * attachment ID lives in the database, and deploys only rsync wp-content —
	 * so an ID can never survive one, and every environment would need setting
	 * up by hand, forever. As a plugin asset the video deploys like any other
	 * file: push once, correct on local, staging and production alike.
	 *
	 * Self-hosted on purpose either way: a YouTube or Vimeo embed would load
	 * third-party trackers before the visitor has accepted the cookie policy.
	 */
	private const BUNDLED_VIDEO = 'assets/video/onboarding.mp4';

	/**
	 * Optional per-site override: a Media Library attachment ID.
	 *
	 * Unset by default, and nothing needs it — the bundled file above is the
	 * normal path. Set it (wp option update) only to point one environment at
	 * a different video without redeploying.
	 */
	public const VIDEO_OPTION = 'vandrekalender_onboarding_video_id';

	/**
	 * Dashboard widget ID.
	 */
	private const WIDGET_ID = 'vandrekalender_organizer_welcome';

	/**
	 * Video formats browsers actually play.
	 *
	 * Deliberately narrow. WordPress happily accepts a video/quicktime upload
	 * — a .mov straight off a Mac screen recording — but Chrome and Firefox
	 * refuse the container outright, so the organizer gets a dead player with
	 * no error. Anything outside this list renders nothing at all, plus an
	 * admin-only warning from render_format_warning().
	 */
	private const PLAYABLE_MIMES = [ 'video/mp4', 'video/webm', 'video/ogg' ];

	/**
	 * Constructor — registers hooks.
	 */
	public function __construct() {
		// Late priority so widgets other plugins add on wp_dashboard_setup are
		// removed too, not just the core ones registered before the action.
		add_action( 'wp_dashboard_setup', [ $this, 'setup_dashboard' ], 999 );
		// Priority 20: after Event::enqueue_editor_assets() has registered the
		// handle this attaches the video data to.
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_video_data' ], 20 );
		add_action( 'admin_head', [ $this, 'print_styles' ] );
		add_action( 'admin_notices', [ $this, 'render_format_warning' ] );
		add_filter( 'login_redirect', [ $this, 'redirect_organizers_to_dashboard' ], 10, 3 );
		add_filter( 'get_user_option_screen_layout_dashboard', [ $this, 'force_single_column' ] );
		add_filter( 'screen_layout_columns', [ $this, 'force_single_column_option' ] );
	}

	/**
	 * Whether the current user gets the organizer dashboard.
	 *
	 * Administrators are excluded even if they also hold the organizer role —
	 * they need the real dashboard.
	 *
	 * @return bool
	 */
	private static function is_organizer(): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}

		$user = wp_get_current_user();

		return $user && in_array( Vandrekalender_Roles::EVENT_ORGANIZER, (array) $user->roles, true );
	}

	/**
	 * Strip the default dashboard down to the single onboarding widget.
	 *
	 * @return void
	 */
	public function setup_dashboard(): void {
		if ( ! self::is_organizer() ) {
			return;
		}

		global $wp_meta_boxes;

		foreach ( (array) ( $wp_meta_boxes['dashboard'] ?? [] ) as $context => $priorities ) {
			foreach ( (array) $priorities as $widgets ) {
				foreach ( array_keys( (array) $widgets ) as $widget_id ) {
					remove_meta_box( $widget_id, 'dashboard', $context );
				}
			}
		}

		// Not a meta box, so remove_meta_box() above does not cover it.
		remove_action( 'welcome_panel', 'wp_welcome_panel' );

		wp_add_dashboard_widget(
			self::WIDGET_ID,
			__( 'Vandrekalender', 'vandrekalender-events' ),
			[ $this, 'render_widget' ]
		);
	}

	/**
	 * Render the onboarding widget.
	 *
	 * @return void
	 */
	public function render_widget(): void {
		$user  = wp_get_current_user();
		$count = (int) count_user_posts( $user->ID, \Vandrekalender\Event::CUSTOMPOSTTYPE );
		$first = 0 === $count;

		echo '<div class="vk-onboarding">';

		// Section A — welcome header.
		printf(
			'<h2 class="vk-onboarding__greeting">%s</h2>',
			esc_html(
				sprintf(
					$first
						/* translators: %s: user display name. */
						? __( 'Hej %s, velkommen til Vandrekalender!', 'vandrekalender-events' )
						/* translators: %s: user display name. */
						: __( 'Hej %s, klar til at oprette din næste vandretur?', 'vandrekalender-events' ),
					$user->display_name
				)
			)
		);

		// Section B — primary call to action.
		printf(
			'<p><a class="button button-primary button-hero vk-onboarding__cta" href="%s">%s</a></p>',
			esc_url( admin_url( 'post-new.php?post_type=' . \Vandrekalender\Event::CUSTOMPOSTTYPE ) ),
			esc_html(
				$first
					? __( 'Opret din første begivenhed', 'vandrekalender-events' )
					: __( 'Opret en ny begivenhed', 'vandrekalender-events' )
			)
		);

		// Section C — how-to video: prominent while they have no events,
		// tucked away once they clearly know the drill.
		if ( $first ) {
			$video = self::video_html();

			// Heading only alongside the full player — the collapsed variant
			// below already labels itself, and neither should appear when no
			// video is configured.
			if ( '' !== $video ) {
				printf(
					'<h3 class="vk-onboarding__video-heading">%s</h3>',
					esc_html__( 'Sådan opretter du en begivenhed', 'vandrekalender-events' )
				);
			}

			echo $video; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in video_html().
		} else {
			echo self::video_html( __( 'Se hvordan-videoen igen', 'vandrekalender-events' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in video_html().
		}

		// Section D — my events.
		if ( ! $first ) {
			printf(
				'<p class="vk-onboarding__events">%s <a href="%s">%s</a></p>',
				esc_html(
					sprintf(
						/* translators: %s: number of events the user has created. */
						_n( 'Du har %s begivenhed.', 'Du har %s begivenheder.', $count, 'vandrekalender-events' ),
						number_format_i18n( $count )
					)
				),
				esc_url( admin_url( 'edit.php?post_type=' . \Vandrekalender\Event::CUSTOMPOSTTYPE ) ),
				esc_html__( 'Se dine begivenheder', 'vandrekalender-events' )
			);
		}

		echo '</div>';
	}

	/**
	 * Markup for the self-hosted onboarding video.
	 *
	 * The single place the video is wired up: used both by the dashboard widget
	 * and by the help link on the event editor screen.
	 *
	 * @param string $summary Optional. When given, the player is collapsed
	 *                        behind a text link with this label.
	 * @return string HTML, or an empty string when no video is configured.
	 */
	public static function video_html( string $summary = '' ): string {
		$video = self::video_data();

		if ( ! $video ) {
			return '';
		}

		$player = sprintf(
			'<video class="vk-onboarding__video" controls preload="metadata" playsinline%s><source src="%s" type="%s" /></video>',
			$video['poster'] ? ' poster="' . esc_url( $video['poster'] ) . '"' : '',
			esc_url( $video['url'] ),
			esc_attr( $video['mime'] )
		);

		if ( '' === $summary ) {
			return '<div class="vk-onboarding__player">' . $player . '</div>';
		}

		return sprintf(
			'<details class="vk-onboarding__player is-collapsed"><summary>%s</summary>%s</details>',
			esc_html( $summary ),
			$player
		);
	}

	/**
	 * The configured onboarding video, or null when there is none.
	 *
	 * The single place the video option is resolved: the dashboard widget
	 * renders it through video_html(), the block editor gets the same data as
	 * JSON through enqueue_editor_video_data().
	 *
	 * @return array{url:string,mime:string,poster:string}|null
	 */
	public static function video_data(): ?array {
		$attachment_id = (int) get_option( self::VIDEO_OPTION );

		// No override configured: use the copy bundled with the plugin. The
		// mtime query arg busts browser caches when a redeploy replaces it.
		if ( ! $attachment_id ) {
			$path = VANDREKALENDER_EVENTS_DIR . self::BUNDLED_VIDEO;

			if ( ! file_exists( $path ) ) {
				return null;
			}

			return [
				'url'    => add_query_arg( 'ver', filemtime( $path ), VANDREKALENDER_EVENTS_URL . self::BUNDLED_VIDEO ),
				'mime'   => 'video/mp4',
				'poster' => '',
			];
		}

		$url  = wp_get_attachment_url( $attachment_id );
		$mime = (string) get_post_mime_type( $attachment_id );

		if ( ! $url || ! in_array( $mime, self::PLAYABLE_MIMES, true ) ) {
			return null;
		}

		return [
			'url'    => $url,
			'mime'   => $mime,
			'poster' => (string) get_the_post_thumbnail_url( $attachment_id, 'large' ),
		];
	}

	/**
	 * Hand the video to the block editor on the event creation screen.
	 *
	 * A PHP-rendered link cannot be used here: core hides classic admin
	 * notices on block editor screens (`#wpbody-content > div:not(.block-editor)`
	 * is display:none), so the help link is rendered by the editor script,
	 * which reads the data set here.
	 *
	 * @return void
	 */
	public function enqueue_editor_video_data(): void {
		$screen = get_current_screen();

		// Only when creating an event, not when editing an existing one.
		if ( ! $screen || 'add' !== $screen->action || \Vandrekalender\Event::CUSTOMPOSTTYPE !== $screen->post_type ) {
			return;
		}

		$video = self::video_data();

		if ( ! $video || ! wp_script_is( 'vandrekalender-event-meta-fields', 'enqueued' ) ) {
			return;
		}

		wp_add_inline_script(
			'vandrekalender-event-meta-fields',
			'window.vkOnboardingVideo = ' . wp_json_encode( $video ) . ';',
			'before'
		);
	}

	/**
	 * Land organizers on the dashboard after login, not on their profile.
	 *
	 * Core sends any user without edit_posts to profile.php, and the
	 * event_organizer role has edit_events but deliberately never edit_posts —
	 * so without this an organizer never sees the onboarding widget they just
	 * logged in for.
	 *
	 * Returns index.php rather than admin_url() on purpose: that fallback is
	 * keyed on $redirect_to being exactly admin_url(), and this filter runs
	 * before it, so the bare admin URL would be quietly overwritten.
	 *
	 * @param string             $redirect_to Destination core settled on.
	 * @param string             $requested   Destination asked for in the request.
	 * @param \WP_User|\WP_Error $user     Logged-in user, or an error.
	 * @return string
	 */
	public function redirect_organizers_to_dashboard( $redirect_to, $requested, $user ) {
		if ( ! $user instanceof \WP_User || user_can( $user, 'manage_options' ) ) {
			return $redirect_to;
		}

		if ( ! in_array( Vandrekalender_Roles::EVENT_ORGANIZER, (array) $user->roles, true ) ) {
			return $redirect_to;
		}

		// An explicit destination (a link into a specific screen) still wins.
		if ( ! empty( $requested ) ) {
			return $redirect_to;
		}

		return admin_url( 'index.php' );
	}

	/**
	 * Lay the organizer dashboard out in a single full-width column.
	 *
	 * There is only one widget, and the how-to video is a full-resolution
	 * screen recording — unreadable squeezed into one of the default columns.
	 *
	 * @param mixed $columns Stored column preference.
	 * @return mixed 1 for organizers, the original value otherwise.
	 */
	public function force_single_column( $columns ) {
		return self::is_organizer() ? 1 : $columns;
	}

	/**
	 * Cap the Screen Options column choices to 1 to match the forced layout.
	 *
	 * @param array $columns Screen ID => max columns.
	 * @return array
	 */
	public function force_single_column_option( $columns ): array {
		if ( self::is_organizer() ) {
			$columns['dashboard'] = 1;
		}

		return $columns;
	}

	/**
	 * Warn administrators when the configured video is not web-playable.
	 *
	 * Without this the failure is invisible to the person who can fix it:
	 * organizers see no player, administrators never see the widget at all.
	 *
	 * @return void
	 */
	public function render_format_warning(): void {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->id, [ 'dashboard', 'upload' ], true ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( self::video_data() ) {
			return;
		}

		$attachment_id = (int) get_option( self::VIDEO_OPTION );

		if ( $attachment_id ) {
			$mime = (string) get_post_mime_type( $attachment_id );

			$message = sprintf(
				/* translators: 1: attachment ID, 2: MIME type of the configured file, 3: option name. */
				esc_html__( 'The onboarding video override (attachment %1$s, %2$s) is not a format browsers can play, so no video is shown to organizers. Re-export it as MP4 (H.264/AAC), or delete the %3$s option to fall back to the video bundled with the plugin.', 'vandrekalender-events' ),
				esc_html( (string) $attachment_id ),
				esc_html( $mime ? $mime : __( 'file missing', 'vandrekalender-events' ) ),
				'<code>' . esc_html( self::VIDEO_OPTION ) . '</code>'
			);
		} else {
			$message = sprintf(
				/* translators: %s: expected path of the bundled video file. */
				esc_html__( 'The onboarding video is missing at %s, so no video is shown to organizers. It ships with the plugin, so this usually means the file was not committed or the deploy did not carry it.', 'vandrekalender-events' ),
				'<code>' . esc_html( self::BUNDLED_VIDEO ) . '</code>'
			);
		}

		wp_admin_notice(
			$message,
			[
				'type'               => 'warning',
				'additional_classes' => [ 'vk-onboarding-warning' ],
			]
		);
	}

	/**
	 * Styles for the dashboard widget.
	 *
	 * @return void
	 */
	public function print_styles(): void {
		$screen = get_current_screen();

		if ( ! $screen || 'dashboard' !== $screen->id || ! self::is_organizer() ) {
			return;
		}

		?>
		<style>
			/* Core's dashboard.css misses a columns-1 override at the
				1500–1800px breakpoint, where it pins #postbox-container-1 to
				33% with three IDs of specificity. Adding .columns-1 outranks
				it without !important, and only bites when we forced one
				column. */
			#wpbody-content #dashboard-widgets.columns-1 #postbox-container-1 { width: 100%; }
			/* The greeting deliberately has no rule: core's #dashboard-widgets h2
				already renders it at 23px, and any single-class override here
				would silently lose to that ID selector anyway. */
			/* Theme palette, hard-coded: wp-admin does not load theme.json, so
				the presets (--wp--preset--color--forest etc.) do not exist here.
				forest #2D5F3F, cream #FAF5EC — keep in sync with theme.json.
				.wp-core-ui matches core's own .button-primary specificity and
				this stylesheet prints after it, so no !important is needed. */
			.wp-core-ui .vk-onboarding__cta {
				background: #2D5F3F;
				border-color: #2D5F3F;
				color: #FAF5EC;
				border-radius: 1rem;
				box-shadow: none;
				text-shadow: none;
				margin-bottom: 1em;
			}
			.wp-core-ui .vk-onboarding__cta:hover,
			.wp-core-ui .vk-onboarding__cta:focus {
				background: #244C32;
				border-color: #244C32;
				color: #FAF5EC;
			}
			.wp-core-ui .vk-onboarding__cta:focus {
				box-shadow: 0 0 0 2px #FFFFFF, 0 0 0 4px #2D5F3F;
			}
			/* ID-prefixed to outrank core's #dashboard-widgets h3, which would
				otherwise flatten this to 14px/400. */
			#dashboard-widgets .vk-onboarding__video-heading { margin: 1.5em 0 0.6em; font-size: 1.15em; font-weight: 600; }
			.vk-onboarding__player { margin: 0 0 1em; }
			.vk-onboarding__video { width: 100%; max-width: 900px; height: auto; display: block; border-radius: 4px; }
			.vk-onboarding__player.is-collapsed summary { cursor: pointer; color: #2271b1; width: fit-content; }
			.vk-onboarding__player.is-collapsed summary:hover { color: #135e96; }
			.vk-onboarding__player.is-collapsed .vk-onboarding__video { margin-top: 1em; }
			.vk-onboarding__events { margin-bottom: 0; }
		</style>
		<?php
	}
}
