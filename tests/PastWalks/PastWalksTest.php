<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * The past walks page /tidligere-ture/ (#176): which dates it lists, how
 * it pages and groups the walks, and which URLs are filtered.
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Past_Walks.
 */
class PastWalksTest extends TestCase {

	/**
	 * The page lists the last RETENTION_MONTHS months up to yesterday.
	 */
	public function test_window_is_the_last_months_up_to_yesterday(): void {
		$this->assertSame(
			[
				'from' => '2025-10-28',
				'to'   => '2026-10-27',
			],
			Vandrekalender_Past_Walks::window( '2026-10-28' )
		);
	}

	/**
	 * Yesterday crosses month and year boundaries.
	 */
	public function test_window_ends_yesterday_across_boundaries(): void {
		$this->assertSame( '2026-02-28', Vandrekalender_Past_Walks::window( '2026-03-01' )['to'] );
		$this->assertSame( '2025-12-31', Vandrekalender_Past_Walks::window( '2026-01-01' )['to'] );
	}

	/**
	 * 30 walks per page, and one (empty) page when there are none.
	 */
	public function test_page_count(): void {
		$this->assertSame( 1, Vandrekalender_Past_Walks::page_count( 0 ) );
		$this->assertSame( 1, Vandrekalender_Past_Walks::page_count( 30 ) );
		$this->assertSame( 2, Vandrekalender_Past_Walks::page_count( 31 ) );
		$this->assertSame( 8, Vandrekalender_Past_Walks::page_count( 212 ) );
	}

	/**
	 * Walks are grouped by month in the order given (newest first), and a
	 * month with no walks has no group.
	 */
	public function test_group_by_month_keeps_order_and_skips_empty_months(): void {
		$groups = Vandrekalender_Past_Walks::group_by_month(
			[
				12 => '2026-10-27',
				11 => '2026-10-03',
				10 => '2026-08-30',
			]
		);

		$this->assertSame(
			[
				'2026-10' => [ 12, 11 ],
				'2026-08' => [ 10 ],
			],
			$groups
		);
	}

	/**
	 * A URL is filtered when any filter has a value; other params do not
	 * count.
	 */
	public function test_is_filtered(): void {
		$this->assertFalse(
			Vandrekalender_Past_Walks::is_filtered(
				[
					'q'         => '',
					'region'    => '',
					'organiser' => '',
				]
			)
		);
		$this->assertFalse( Vandrekalender_Past_Walks::is_filtered( [ 'utm_source' => 'x' ] ) );
		$this->assertTrue( Vandrekalender_Past_Walks::is_filtered( [ 'q' => 'Viborg' ] ) );
		$this->assertTrue( Vandrekalender_Past_Walks::is_filtered( [ 'organiser' => 'mammutmarch' ] ) );
	}

	/**
	 * The page lists a date inside the window only: not today, not before
	 * the window, not empty.
	 */
	public function test_lists_date_only_inside_the_window(): void {
		$this->assertTrue( Vandrekalender_Past_Walks::lists_date( '2026-10-27', '2026-10-28' ) );
		$this->assertTrue( Vandrekalender_Past_Walks::lists_date( '2025-10-28', '2026-10-28' ) );
		$this->assertFalse( Vandrekalender_Past_Walks::lists_date( '2025-10-27', '2026-10-28' ) );
		$this->assertFalse( Vandrekalender_Past_Walks::lists_date( '2026-10-28', '2026-10-28' ) );
		$this->assertFalse( Vandrekalender_Past_Walks::lists_date( '', '2026-10-28' ) );
	}
}
