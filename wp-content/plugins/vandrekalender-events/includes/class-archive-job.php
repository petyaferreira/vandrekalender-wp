<?php

defined( 'ABSPATH' ) || exit;

/**
 * The weekly job that archives old walks (#37, step 3: #267; decision 0018).
 *
 * Every published walk dated before Vandrekalender_Past_Walks::retention_cutoff()
 * and not kept ("Behold") gets the `archived` status. These are the walks the
 * "Due for removal" filter in the Events list shows. Images and GPX files are
 * left alone; the purge (step 5) handles them.
 *
 * Scheduled weekly where VK_ENABLE_SCRAPING is set (production), like the
 * scrapers, and cleared elsewhere. `wp vandrekalender archive-old [--dry-run]`
 * runs the same code by hand. Every real run is recorded in the Scraper Log.
 * The same weekly run then deletes walks archived 3 years ago
 * (Vandrekalender_Purge_Job).
 *
 * @package Vandrekalender
 */
class Vandrekalender_Archive_Job {

	const CRON_HOOK  = 'vandrekalender_archive_old_walks';
	const RECURRENCE = 'weekly';
	const RUN_HOUR   = 3;
	const RUN_MINUTE = 12;

	/**
	 * Name of the job in the Scraper Log.
	 */
	const LOG_NAME = 'Archive old walks';

	/**
	 * Constructor — hooks the cron job and keeps its schedule in step with
	 * the environment.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, [ __CLASS__, 'run_from_cron' ] );
		add_action( 'init', [ __CLASS__, 'reconcile_schedule' ] );
	}

	/**
	 * Schedules the weekly job where scraping is enabled, clears it
	 * everywhere else. Same gate as Vandrekalender_Scraper_Scheduler.
	 *
	 * @return void
	 */
	public static function reconcile_schedule(): void {
		if ( Vandrekalender_Scraper_Scheduler::scraping_enabled() ) {
			if ( self::RECURRENCE !== wp_get_schedule( self::CRON_HOOK ) ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
				wp_schedule_event( self::next_run_timestamp(), self::RECURRENCE, self::CRON_HOOK );
			}
		} elseif ( wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * Unschedules the job on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * The next 03:12 in the site's timezone, an hour after the scrapers, as a
	 * UTC timestamp for WP-Cron.
	 *
	 * @return int
	 */
	private static function next_run_timestamp(): int {
		$now  = new DateTimeImmutable( 'now', wp_timezone() );
		$next = $now->setTime( self::RUN_HOUR, self::RUN_MINUTE, 0 );

		if ( $next <= $now ) {
			$next = $next->modify( '+1 day' );
		}

		return $next->getTimestamp();
	}

	/**
	 * Cron entry point: archive old walks, then delete the walks archived
	 * ARCHIVE_PURGE_YEARS ago (Vandrekalender_Purge_Job). Each records its
	 * own row in the Scraper Log.
	 *
	 * @return void
	 */
	public static function run_from_cron(): void {
		self::execute( 'cron', false );
		Vandrekalender_Purge_Job::execute( 'cron', false );
	}

	/**
	 * Archives every walk that is due, or with $dry_run only lists them.
	 * A real run is recorded in the Scraper Log.
	 *
	 * @param string $trigger 'cron' or 'manual'.
	 * @param bool   $dry_run List only, change nothing, log nothing.
	 * @return array{walks: array<int, array{id: int, date: string, title: string}>, failed: array<int, string>}
	 *         The walks archived (or that would be), and the ones that failed, by ID.
	 */
	public static function execute( string $trigger, bool $dry_run ): array {
		$started = microtime( true );
		$today   = current_time( 'Y-m-d' );
		$walks   = [];
		$failed  = [];

		foreach ( get_posts( self::selection_args( $today ) ) as $post_id ) {
			$post_id = (int) $post_id;
			$date    = (string) get_post_meta( $post_id, \Vandrekalender\Event::META_DATE, true );

			$walk = [
				'id'    => $post_id,
				'date'  => $date,
				'title' => get_the_title( $post_id ),
			];

			if ( ! $dry_run ) {
				$result = wp_update_post(
					[
						'ID'          => $post_id,
						'post_status' => Vandrekalender_Archived_Walks::STATUS,
					],
					true
				);

				if ( is_wp_error( $result ) ) {
					$failed[ $post_id ] = $result->get_error_message();
					continue;
				}
			}

			$walks[] = $walk;
		}

		if ( ! $dry_run ) {
			Vandrekalender_Scraper_Log::record( self::log_entry( $trigger, count( $walks ), $failed, microtime( true ) - $started ) );
		}

		return [
			'walks'  => $walks,
			'failed' => $failed,
		];
	}

	/**
	 * The query for walks due for archiving: published events dated before
	 * the retention cutoff and not kept. The same meta query as the "Due for
	 * removal" filter in the Events list, so the two always agree. Each
	 * Polylang translation is its own post with its own date.
	 *
	 * @param string $today Today as Y-m-d.
	 * @return array<string,mixed> get_posts() arguments.
	 */
	public static function selection_args( string $today ): array {
		return [
			'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => \Vandrekalender\Event::due_for_removal_meta_query( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- weekly job, not a request-time query.
				Vandrekalender_Past_Walks::retention_cutoff( $today )
			),
		];
	}

	/**
	 * The Scraper Log entry for a run: one row, the job as its only "scraper".
	 * Vandrekalender_Purge_Job uses it too, with its own name.
	 *
	 * @param string             $trigger  'cron' or 'manual'.
	 * @param int                $archived Walks archived (or deleted).
	 * @param array<int, string> $failed   Error message by walk or file ID.
	 * @param float              $seconds  How long the run took.
	 * @param string             $name     The job's name in the log.
	 * @return array
	 */
	public static function log_entry( string $trigger, int $archived, array $failed, float $seconds, string $name = self::LOG_NAME ): array {
		$errors = [];
		foreach ( $failed as $post_id => $message ) {
			$errors[] = sprintf( '#%d: %s', $post_id, $message );
		}

		return [
			'time'     => current_time( 'mysql' ),
			'trigger'  => 'cron' === $trigger ? 'cron' : 'manual',
			'duration' => round( $seconds, 1 ),
			'total'    => $archived,
			'scrapers' => [
				[
					'name'     => $name,
					'count'    => $archived,
					'status'   => $errors ? 'error' : 'ok',
					'error'    => implode( '; ', $errors ),
					'problem'  => '',
					'warnings' => [],
				],
			],
		];
	}
}
