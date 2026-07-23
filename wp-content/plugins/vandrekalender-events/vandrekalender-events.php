<?php
/**
 * Plugin Name: Vandrekalender Events
 * Plugin URI:  https://vandrekalender.dk
 * Description: Custom post type, blocks, REST API, and scraping pipeline for Vandrekalender.
 * Version:     1.0.0
 * Author:      Petya Ferreira
 * Text Domain: vandrekalender-events
 * Domain Path: /languages
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

define( 'VANDREKALENDER_EVENTS_VERSION', '1.0.0' );
define( 'VANDREKALENDER_EVENTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'VANDREKALENDER_EVENTS_URL', plugin_dir_url( __FILE__ ) );

require_once VANDREKALENDER_EVENTS_DIR . 'includes/class-roles.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/class-organizer-dashboard.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/class-event-rest-api.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/class-event-attendees.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/class-event-join-mailer.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/class-event-join.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/class-geocoder.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/class-scraper-base.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/class-scraper-log.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/class-scraper-scheduler.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/class-scraper-admin.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/class-facebook-importer.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/scrapers/class-scraper-mammut.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/scrapers/class-scraper-sportstiming.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/scrapers/class-scraper-dvl.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/event/class-event.php';

new Vandrekalender_Roles();
new Vandrekalender_Organizer_Dashboard();
new Vandrekalender_Event_Rest_Api();
new Vandrekalender_Event_Attendees();
new Vandrekalender_Event_Join();
new Vandrekalender_Scraper_Scheduler();
new Vandrekalender_Scraper_Admin();
new Vandrekalender_Facebook_Importer();

/**
 * Initialize classes.
 */
\Vandrekalender\Event::instance();

/**
 * Inline upcoming-events counter for use inside paragraph text, where a block
 * cannot be nested. Renders the same number as the Upcoming Events Count block
 * but inherits the surrounding text colour and size.
 *
 * Usage: Over [vk_upcoming_count] ture fra hele landet.
 */
add_shortcode(
	'vk_upcoming_count',
	function () {
		$count = Vandrekalender_Event_Rest_Api::count_events(
			[ 'date_from' => current_time( 'Y-m-d' ) ]
		);

		return '<span class="vk-upcoming-count">' . esc_html( number_format_i18n( $count ) ) . '</span>';
	}
);

/**
 * Register (but don't enqueue) the filtered-count frontend script, so the
 * shortcode below can enqueue it only on pages that actually use it.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_register_script(
			'vandrekalender-filtered-count',
			VANDREKALENDER_EVENTS_URL . 'assets/js/filtered-count-view.js',
			[],
			VANDREKALENDER_EVENTS_VERSION,
			true
		);
	}
);

/**
 * Register Swiper styles and scripts. This is currently used by slider block.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		$css_rel = 'assets/vendor/swiper/swiper-bundle.min.css';
		$js_rel  = 'assets/vendor/swiper/swiper-bundle.min.js';

		$css_path = VANDREKALENDER_EVENTS_DIR . $css_rel;
		$js_path  = VANDREKALENDER_EVENTS_DIR . $js_rel;

		$css_url = VANDREKALENDER_EVENTS_URL . $css_rel;
		$js_url  = VANDREKALENDER_EVENTS_URL . $js_rel;

		$css_ver = file_exists( $css_path ) ? filemtime( $css_path ) : null;
		$js_ver  = file_exists( $js_path ) ? filemtime( $js_path ) : null;

		wp_register_style(
			'swiper',
			$css_url,
			[],
			$css_ver
		);

		wp_register_script(
			'swiper',
			$js_url,
			[],
			$js_ver,
			true
		);
	}
);

/**
 * Inline filter-reactive counter for use inside paragraph text. The script
 * hydrates every .vk-filtered-count element on the page — the number updates
 * live as filters change, while inheriting the surrounding text colour and
 * size.
 *
 * Usage: Der er [vk_filtered_count] ture.
 */
add_shortcode(
	'vk_filtered_count',
	function () {
		$count = Vandrekalender_Event_Rest_Api::count_events(
			Vandrekalender_Event_Rest_Api::filters_from_query()
		);

		wp_enqueue_script( 'vandrekalender-filtered-count' );

		return sprintf(
			'<span class="vk-filtered-count" role="status" data-rest-url="%s">%s</span>',
			esc_attr( esc_url_raw( rest_url( 'vandrekalender/v1/events/count' ) ) ),
			esc_html( number_format_i18n( $count ) )
		);
	}
);

/**
 * Register the `wp vandrekalender scrape` command for manual runs.
 *
 * Runs the full scraping pipeline once and logs it as a manual run, so local
 * runs (via ./scrape.sh) appear in the Scraper Log alongside production's cron
 * runs.
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'vandrekalender scrape',
		function () {
			$entry = Vandrekalender_Scraper_Scheduler::execute( 'manual' );

			foreach ( $entry['scrapers'] as $scraper ) {
				if ( 'error' === $scraper['status'] ) {
					WP_CLI::warning( sprintf( '%s: %s', $scraper['name'], $scraper['error'] ) );
				} else {
					WP_CLI::log( sprintf( '%s: %d events', $scraper['name'], $scraper['count'] ) );
				}
			}

			WP_CLI::success( sprintf( '%d events updated in %ss.', $entry['total'], $entry['duration'] ) );
		}
	);
}

/**
 * Fall back to the admin address when WordPress's default From is invalid.
 *
 * The default From is `wordpress@` + the site host (minus a leading `www.`). On
 * a real domain that is already a valid address and this filter is a no-op —
 * production keeps sending as `wordpress@vandrekalender.dk`, unchanged. Locally
 * the host is `localhost`, so the default becomes `wordpress@localhost`: no TLD,
 * which PHPMailer rejects outright, and every core email (password reset,
 * new-user notification) fails before it can reach Mailpit. Swapping in the
 * admin address — which always carries a real domain — makes those mails
 * deliverable in local dev without altering production behaviour.
 *
 * Runs at priority 1 so a mail-transport plugin (FluentSMTP) setting its own
 * From at the default priority still wins. The join emails set their own From
 * (see class-event-join-mailer.php) and never depend on this.
 *
 * @param string $from The From address WordPress computed.
 * @return string A valid From address.
 */
add_filter(
	'wp_mail_from',
	function ( $from ) {
		return is_email( $from ) ? $from : get_option( 'admin_email' );
	},
	1
);

register_activation_hook( __FILE__, [ 'Vandrekalender_Scraper_Scheduler', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Vandrekalender_Scraper_Scheduler', 'deactivate' ] );

/**
 * Flush rewrite rules automatically when any custom rewrite slug changes.
 *
 * WordPress only regenerates permalink rewrite rules when flush_rewrite_rules()
 * runs — normally a manual "Save" on Settings → Permalinks. A file-sync deploy
 * flushes nothing, so a changed CPT or taxonomy slug 404s until someone flushes
 * by hand. This builds a signature from every non-core post type and taxonomy's
 * rewrite config (plus the plugin version) and flushes once whenever it changes.
 * It costs nothing on normal requests, self-heals after any deploy that alters a
 * slug, and needs no edits when new post types or taxonomies are added.
 *
 * Runs on init at priority 99, after all post types and taxonomies register.
 *
 * @return void
 */
function vandrekalender_events_maybe_flush_rewrite_rules() {
	$signature = [ 'version' => VANDREKALENDER_EVENTS_VERSION ];

	foreach ( get_post_types( [ '_builtin' => false ], 'objects' ) as $post_type ) {
		$signature['cpt'][ $post_type->name ] = [ $post_type->rewrite, $post_type->has_archive ];
	}

	foreach ( get_taxonomies( [ '_builtin' => false ], 'objects' ) as $taxonomy ) {
		$signature['tax'][ $taxonomy->name ] = $taxonomy->rewrite;
	}

	if ( isset( $signature['cpt'] ) ) {
		ksort( $signature['cpt'] );
	}
	if ( isset( $signature['tax'] ) ) {
		ksort( $signature['tax'] );
	}

	$encoded = wp_json_encode( $signature );
	$option  = 'vandrekalender_events_rewrite_signature';

	if ( get_option( $option ) !== $encoded ) {
		flush_rewrite_rules();
		update_option( $option, $encoded );
	}
}
add_action( 'init', 'vandrekalender_events_maybe_flush_rewrite_rules', 99 );
