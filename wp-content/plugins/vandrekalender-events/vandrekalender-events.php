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
