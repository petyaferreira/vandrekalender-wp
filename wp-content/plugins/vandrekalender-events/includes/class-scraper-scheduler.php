<?php

defined( 'ABSPATH' ) || exit;

/**
 * Schedules and runs the event scraping pipeline.
 *
 * Automatic runs happen once a day at 02:12 (site timezone) and only where the
 * VK_ENABLE_SCRAPING constant is truthy — i.e. production. Local and staging
 * never auto-run; locally the pipeline is triggered by hand (`./scrape.sh` /
 * `wp vandrekalender scrape`). Every run, automatic or manual, is recorded via
 * Vandrekalender_Scraper_Log so the Scraper Log admin page shows its history.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Scraper_Scheduler {

	const CRON_HOOK  = 'vandrekalender_run_scrapers';
	const RUN_HOUR   = 2;
	const RUN_MINUTE = 12;
	const RECURRENCE = 'daily';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, [ __CLASS__, 'run_from_cron' ] );
		// Keep the scheduled state in step with the VK_ENABLE_SCRAPING flag, so
		// toggling it (or deploying to a new environment) needs no reactivation.
		add_action( 'init', [ __CLASS__, 'reconcile_schedule' ] );
	}

	/**
	 * Whether automatic scraping is enabled for this environment.
	 *
	 * @return bool
	 */
	public static function scraping_enabled(): bool {
		return defined( 'VK_ENABLE_SCRAPING' ) && VK_ENABLE_SCRAPING;
	}

	/**
	 * Schedule the daily cron event on plugin activation (production only).
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::reconcile_schedule();
	}

	/**
	 * Unschedule the cron event on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Align the cron schedule with the current environment flag.
	 *
	 * Schedules the daily job where scraping is enabled (repinning any legacy
	 * schedule to the current recurrence), and clears it everywhere else.
	 *
	 * @return void
	 */
	public static function reconcile_schedule(): void {
		if ( self::scraping_enabled() ) {
			if ( self::RECURRENCE !== wp_get_schedule( self::CRON_HOOK ) ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
				wp_schedule_event( self::next_run_timestamp(), self::RECURRENCE, self::CRON_HOOK );
			}
		} elseif ( wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}
	}

	/**
	 * The next 02:12 in the site's timezone, as a UTC timestamp for WP-Cron.
	 *
	 * @return int
	 */
	private static function next_run_timestamp(): int {
		$timezone = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $timezone );
		$next     = $now->setTime( self::RUN_HOUR, self::RUN_MINUTE, 0 );

		if ( $next <= $now ) {
			$next = $next->modify( '+1 day' );
		}

		return $next->getTimestamp();
	}

	/**
	 * Cron entry point: run the pipeline and log it as an automatic run.
	 *
	 * @return void
	 */
	public static function run_from_cron(): void {
		self::execute( 'cron' );
	}

	/**
	 * Run every scraper, capturing per-scraper counts and failures, and log it.
	 *
	 * A failing scraper is isolated so the others still run and the error is
	 * recorded rather than fatal.
	 *
	 * @param string $trigger How the run was started: 'cron' or 'manual'.
	 * @return array The recorded run summary.
	 */
	public static function execute( string $trigger ): array {
		$scrapers = [
			'Mammutmarch'      => new Vandrekalender_Scraper_Mammut(),
			'Sportstiming'     => new Vandrekalender_Scraper_Sportstiming(),
			'Dansk Vandrelaug' => new Vandrekalender_Scraper_DVL(),
		];

		$started = microtime( true );
		$results = [];
		$total   = 0;

		foreach ( $scrapers as $name => $scraper ) {
			try {
				$count     = (int) $scraper->run();
				$total    += $count;
				$results[] = [
					'name'   => $name,
					'count'  => $count,
					'status' => 'ok',
					'error'  => '',
				];
			} catch ( \Throwable $e ) {
				$results[] = [
					'name'   => $name,
					'count'  => 0,
					'status' => 'error',
					'error'  => $e->getMessage(),
				];
			}
		}

		$results[] = self::cleanup_past_events();

		$entry = [
			'time'     => current_time( 'mysql' ),
			'trigger'  => 'cron' === $trigger ? 'cron' : 'manual',
			'duration' => round( microtime( true ) - $started, 1 ),
			'total'    => $total,
			'scrapers' => $results,
		];

		Vandrekalender_Scraper_Log::record( $entry );

		return $entry;
	}

	/**
	 * Draft scraped, unclaimed events whose date is more than a week past.
	 *
	 * Recurring sources (e.g. DVL's weekly walks) publish a fresh page per
	 * occurrence, so past occurrences would otherwise accumulate forever as
	 * near-identical public pages — which Google flags as duplicate content.
	 * A week's grace keeps just-finished events visible; manually created and
	 * organiser-claimed events are never touched.
	 *
	 * @return array Log row (name, count, status, error) matching scraper rows.
	 */
	private static function cleanup_past_events(): array {
		$cutoff = ( new DateTimeImmutable( 'now', wp_timezone() ) )
			->modify( '-7 days' )
			->format( 'Y-m-d' );

		$candidates = get_posts(
			[
				'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => \Vandrekalender\Event::META_SOURCE,
						'value' => 'scraped',
					],
					[
						'key'     => \Vandrekalender\Event::META_DATE,
						'value'   => $cutoff,
						'compare' => '<',
						'type'    => 'DATE',
					],
				],
			]
		);

		$count = 0;
		foreach ( $candidates as $post_id ) {
			if ( get_post_meta( $post_id, \Vandrekalender\Event::META_CLAIMED, true ) ) {
				continue;
			}

			wp_update_post(
				[
					'ID'          => $post_id,
					'post_status' => 'draft',
				]
			);
			++$count;
		}

		return [
			'name'   => 'Cleanup (past events)',
			'count'  => $count,
			'status' => 'ok',
			'error'  => '',
		];
	}
}
