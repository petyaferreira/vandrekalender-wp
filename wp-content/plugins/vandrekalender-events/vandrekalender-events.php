<?php
/**
 * Plugin Name: Vandrekalender Events
 * Plugin URI:  https://vandrekalender.dk
 * Description: Custom post type, blocks, REST API, and scraping pipeline for Vandrekalender.
 * Version:     1.0.0
 * Author:      Petya Ferreira
 * Text Domain: vandrekalender-events
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

define( 'VANDREKALENDER_EVENTS_VERSION', '1.0.0' );
define( 'VANDREKALENDER_EVENTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'VANDREKALENDER_EVENTS_URL', plugin_dir_url( __FILE__ ) );

require_once VANDREKALENDER_EVENTS_DIR . 'includes/class-event-rest-api.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/class-scraper-base.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/class-scraper-scheduler.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/scrapers/class-scraper-mammut.php';
require_once VANDREKALENDER_EVENTS_DIR . 'includes/event/class-event.php';

new Vandrekalender_Event_Rest_Api();
new Vandrekalender_Scraper_Scheduler();

/**
 * Initialize classes.
 */
\Vandrekalender\Event::instance();

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
