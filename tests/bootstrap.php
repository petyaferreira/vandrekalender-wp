<?php
/**
 * Loads plugin classes without WordPress.
 *
 * The tests run in about a second with no database, Docker or network, so
 * ./validate.sh can run them on every change. WordPress functions the tested
 * code calls are replaced by the small stand-ins in wp-stubs.php; anything the
 * stand-ins do not cover fails with "Call to undefined function", which is the
 * signal to add one (or to test that code another way).
 *
 * @package Vandrekalender
 */

require __DIR__ . '/../vendor/autoload.php';

define( 'ABSPATH', __DIR__ . '/' );

require __DIR__ . '/wp-stubs.php';
require __DIR__ . '/fakes/class-vandrekalender-geocoder.php';

define( 'VANDREKALENDER_TEST_INCLUDES', __DIR__ . '/../wp-content/plugins/vandrekalender-events/includes' );

require VANDREKALENDER_TEST_INCLUDES . '/event/class-event.php';
require VANDREKALENDER_TEST_INCLUDES . '/organiser/class-organiser.php';
require VANDREKALENDER_TEST_INCLUDES . '/class-scraper-base.php';
require VANDREKALENDER_TEST_INCLUDES . '/class-event-rest-api.php';
require VANDREKALENDER_TEST_INCLUDES . '/scrapers/class-scraper-dvl.php';
require VANDREKALENDER_TEST_INCLUDES . '/scrapers/class-scraper-mammut.php';
require VANDREKALENDER_TEST_INCLUDES . '/scrapers/class-scraper-opdagverden.php';
require VANDREKALENDER_TEST_INCLUDES . '/scrapers/class-scraper-sportstiming.php';

require __DIR__ . '/fakes/class-test-scraper-dvl.php';
require __DIR__ . '/fakes/class-test-scraper-mammut.php';
