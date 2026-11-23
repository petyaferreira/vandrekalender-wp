<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * Archived walks (#266): what an archived walk's URL answers, and when a
 * scraper leaves an archived walk archived.
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Event_Past_Events::archived_walk_response() and
 * Vandrekalender_Archived_Walks::scraper_keeps_archived().
 */
class ArchivedWalksTest extends TestCase {

	/**
	 * A series with an upcoming occurrence keeps the 301 to it.
	 */
	public function test_series_with_next_occurrence_redirects(): void {
		$response = Vandrekalender_Event_Past_Events::archived_walk_response( 'dvl-motionstur', false, fn() => 42 );

		$this->assertSame(
			[
				'status'  => 301,
				'post_id' => 42,
			],
			$response
		);
	}

	/**
	 * A series with no upcoming occurrence is gone.
	 */
	public function test_series_without_next_occurrence_is_gone(): void {
		$response = Vandrekalender_Event_Past_Events::archived_walk_response( 'dvl-motionstur', false, fn() => 0 );

		$this->assertSame( 410, $response['status'] );
	}

	/**
	 * A one-off walk is gone, and no occurrence is looked up.
	 */
	public function test_one_off_walk_is_gone_without_lookup(): void {
		$looked_up = false;
		$response  = Vandrekalender_Event_Past_Events::archived_walk_response(
			'',
			false,
			function () use ( &$looked_up ) {
				$looked_up = true;
				return 42;
			}
		);

		$this->assertSame( 410, $response['status'] );
		$this->assertFalse( $looked_up );
	}

	/**
	 * A claimed walk is never redirected, even with an upcoming occurrence.
	 */
	public function test_claimed_walk_is_gone(): void {
		$response = Vandrekalender_Event_Past_Events::archived_walk_response( 'dvl-motionstur', true, fn() => 42 );

		$this->assertSame( 410, $response['status'] );
	}

	/**
	 * A source that still lists an old archived walk leaves it archived.
	 */
	public function test_scraper_keeps_old_archived_walk_archived(): void {
		$this->assertTrue( Vandrekalender_Archived_Walks::scraper_keeps_archived( 'archived', '2025-06-01', '2026-11-23' ) );
	}

	/**
	 * A source that reuses the URL for a date inside the window publishes it again.
	 */
	public function test_scraper_publishes_archived_walk_with_current_date(): void {
		$this->assertFalse( Vandrekalender_Archived_Walks::scraper_keeps_archived( 'archived', '2027-06-01', '2026-11-23' ) );
		$this->assertFalse( Vandrekalender_Archived_Walks::scraper_keeps_archived( 'archived', '2025-11-23', '2026-11-23' ), 'The cutoff day itself is inside the window.' );
	}

	/**
	 * Without a scraped date nothing says the walk is current again.
	 */
	public function test_scraper_keeps_archived_walk_without_date(): void {
		$this->assertTrue( Vandrekalender_Archived_Walks::scraper_keeps_archived( 'archived', '', '2026-11-23' ) );
	}

	/**
	 * Walks with any other status are updated as before.
	 */
	public function test_scraper_updates_walks_that_are_not_archived(): void {
		$this->assertFalse( Vandrekalender_Archived_Walks::scraper_keeps_archived( 'publish', '2025-06-01', '2026-11-23' ) );
		$this->assertFalse( Vandrekalender_Archived_Walks::scraper_keeps_archived( 'draft', '2025-06-01', '2026-11-23' ) );
	}
}
