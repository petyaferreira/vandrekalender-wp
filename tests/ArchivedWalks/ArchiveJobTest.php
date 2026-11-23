<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * The weekly archive job (#267): which walks it selects, and what it records
 * in the Scraper Log.
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Archive_Job.
 */
class ArchiveJobTest extends TestCase {

	/**
	 * Today in these tests; the cutoff is then 2025-11-23.
	 */
	private const TODAY = '2026-11-23';

	/**
	 * Only published events.
	 */
	public function test_selects_published_events_only(): void {
		$args = Vandrekalender_Archive_Job::selection_args( self::TODAY );

		$this->assertSame( 'event', $args['post_type'] );
		$this->assertSame( 'publish', $args['post_status'] );
		$this->assertSame( -1, $args['posts_per_page'] );
	}

	/**
	 * The job and the "Due for removal" filter select the same walks.
	 */
	public function test_uses_the_due_for_removal_query(): void {
		$this->assertSame(
			\Vandrekalender\Event::due_for_removal_meta_query( '2025-11-23' ),
			Vandrekalender_Archive_Job::selection_args( self::TODAY )['meta_query']
		);
	}

	/**
	 * A walk one day before the cutoff is archived, unless it is kept.
	 */
	public function test_walk_before_the_cutoff_is_selected(): void {
		$this->assertTrue( $this->selects( [ 'event_date' => '2025-11-22' ] ), 'No keep flag.' );
		$this->assertTrue(
			$this->selects(
				[
					'event_date' => '2025-11-22',
					'event_keep' => '',
				]
			),
			'Empty keep flag.'
		);
		$this->assertTrue(
			$this->selects(
				[
					'event_date' => '2025-11-22',
					'event_keep' => '0',
				]
			),
			'Keep flag off.'
		);
	}

	/**
	 * A kept walk is skipped.
	 */
	public function test_kept_walk_is_skipped(): void {
		$this->assertFalse(
			$this->selects(
				[
					'event_date' => '2024-01-01',
					'event_keep' => '1',
				]
			)
		);
	}

	/**
	 * A walk on the cutoff day is one day inside the window and is skipped.
	 */
	public function test_walk_one_day_inside_the_window_is_skipped(): void {
		$this->assertFalse( $this->selects( [ 'event_date' => '2025-11-23' ] ) );
		$this->assertFalse( $this->selects( [ 'event_date' => '2026-12-01' ] ), 'Upcoming walk.' );
	}

	/**
	 * A walk without a date is never archived (MySQL reads '' as before any date).
	 */
	public function test_walk_without_date_is_skipped(): void {
		$this->assertFalse( $this->selects( [ 'event_date' => '' ] ) );
		$this->assertFalse( $this->selects( [] ), 'No event_date row at all.' );
	}

	/**
	 * A run shows in the Scraper Log as one row with the number archived.
	 */
	public function test_log_entry(): void {
		$entry = Vandrekalender_Archive_Job::log_entry( 'cron', 3, [], 0.42 );

		$this->assertSame( 'cron', $entry['trigger'] );
		$this->assertSame( 3, $entry['total'] );
		$this->assertSame( 0.4, $entry['duration'] );
		$this->assertSame( 'Archive old walks', $entry['scrapers'][0]['name'] );
		$this->assertSame( 'ok', $entry['scrapers'][0]['status'] );
		$this->assertSame( '', $entry['scrapers'][0]['problem'], 'Never an alert email.' );
	}

	/**
	 * Walks that could not be archived show as an error in the log.
	 */
	public function test_log_entry_with_failures(): void {
		$entry = Vandrekalender_Archive_Job::log_entry( 'manual', 1, [ 12 => 'Database error' ], 0.1 );

		$this->assertSame( 'manual', $entry['trigger'] );
		$this->assertSame( 'error', $entry['scrapers'][0]['status'] );
		$this->assertSame( '#12: Database error', $entry['scrapers'][0]['error'] );
	}

	/**
	 * Whether the job's meta query matches a walk with $meta, read the way
	 * MySQL reads it (DATE compare, NOT EXISTS, IN).
	 *
	 * @param array<string,string> $meta The walk's meta.
	 * @return bool
	 */
	private function selects( array $meta ): bool {
		return $this->clause_matches( Vandrekalender_Archive_Job::selection_args( self::TODAY )['meta_query'], $meta );
	}

	/**
	 * Evaluates one meta query clause or group.
	 *
	 * @param array<int|string,mixed> $clause Clause or nested group.
	 * @param array<string,string>    $meta   The walk's meta.
	 * @return bool
	 */
	private function clause_matches( array $clause, array $meta ): bool {
		if ( isset( $clause['key'] ) ) {
			$exists = array_key_exists( $clause['key'], $meta );
			$value  = $meta[ $clause['key'] ] ?? null;

			switch ( $clause['compare'] ) {
				case 'NOT EXISTS':
					return ! $exists;
				case 'IN':
					return $exists && in_array( $value, $clause['value'], true );
				case '<':
					return $exists && $value < $clause['value'];
				case '!=':
					return $exists && $value !== $clause['value'];
			}

			$this->fail( 'Unhandled compare ' . $clause['compare'] );
		}

		$relation = $clause['relation'] ?? 'AND';
		$results  = [];
		foreach ( $clause as $key => $child ) {
			if ( 'relation' !== $key ) {
				$results[] = $this->clause_matches( $child, $meta );
			}
		}

		return 'OR' === $relation ? in_array( true, $results, true ) : ! in_array( false, $results, true );
	}
}
