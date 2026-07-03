<?php

defined( 'ABSPATH' ) || exit;

/**
 * Stores a rolling history of scraper runs for display in wp-admin.
 *
 * Every run — whether triggered by cron on production or manually via WP-CLI
 * locally — records a summary here, so the Scraper Log admin page shows the same
 * history in both environments. Kept in a single autoload-off option (capped to
 * the most recent runs) rather than a custom table, since the volume is tiny.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Scraper_Log {

	const OPTION      = 'vandrekalender_scraper_runs';
	const MAX_ENTRIES = 30;

	/**
	 * Prepend a run summary to the log, trimming to the most recent entries.
	 *
	 * @param array $entry Run summary (time, trigger, duration, total, scrapers).
	 * @return void
	 */
	public static function record( array $entry ): void {
		$runs = self::all();
		array_unshift( $runs, $entry );
		$runs = array_slice( $runs, 0, self::MAX_ENTRIES );

		update_option( self::OPTION, $runs, false );
	}

	/**
	 * Return all logged runs, most recent first.
	 *
	 * @return array
	 */
	public static function all(): array {
		$runs = get_option( self::OPTION, [] );

		return is_array( $runs ) ? $runs : [];
	}

	/**
	 * Remove all logged runs.
	 *
	 * @return void
	 */
	public static function clear(): void {
		delete_option( self::OPTION );
	}
}
