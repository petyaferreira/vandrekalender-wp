<?php

defined( 'ABSPATH' ) || exit;

/**
 * Schedules and runs the event scraping pipeline via WP-Cron.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Scraper_Scheduler {

	const CRON_HOOK = 'vandrekalender_run_scrapers';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'run_all_scrapers' ] );
		add_filter( 'cron_schedules', [ $this, 'add_weekly_schedule' ] );
	}

	/**
	 * Schedule the cron event on plugin activation.
	 */
	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'weekly', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule the cron event on plugin deactivation.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Add a weekly interval to WP-Cron schedules.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array
	 */
	public function add_weekly_schedule( array $schedules ): array {
		$schedules['weekly'] = [
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'vandrekalender-events' ),
		];
		return $schedules;
	}

	/**
	 * Run all registered scrapers.
	 */
	public function run_all_scrapers() {
		$scrapers = [
			new Vandrekalender_Scraper_Mammut(),
			new Vandrekalender_Scraper_Sportstiming(),
		];

		foreach ( $scrapers as $scraper ) {
			$scraper->run();
		}
	}
}
