<?php

defined( 'ABSPATH' ) || exit;

class Vandrekalender_Scraper_Scheduler {

	const CRON_HOOK = 'vandrekalender_run_scrapers';

	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'run_all_scrapers' ] );
		add_filter( 'cron_schedules', [ $this, 'add_weekly_schedule' ] );
	}

	public static function activate() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'weekly', self::CRON_HOOK );
		}
	}

	public static function deactivate() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	public function add_weekly_schedule( array $schedules ): array {
		$schedules['weekly'] = [
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'vandrekalender-events' ),
		];
		return $schedules;
	}

	public function run_all_scrapers() {
		$scrapers = [
			new Vandrekalender_Scraper_Loberdk(),
			new Vandrekalender_Scraper_Mammut(),
		];

		foreach ( $scrapers as $scraper ) {
			$scraper->run();
		}
	}
}
