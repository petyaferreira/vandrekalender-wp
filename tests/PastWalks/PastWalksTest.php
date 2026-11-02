<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * The past walks page /tidligere-ture/ (#176): which dates it lists, how
 * many walks "Vis flere ture" shows, how it groups them, and which URLs are
 * filtered.
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
	 * 10 walks, then 10 more with each "Vis flere ture" (?side=N), never more
	 * than there are.
	 */
	public function test_shown_count(): void {
		$this->assertSame( 0, Vandrekalender_Past_Walks::shown_count( 0, 1 ) );
		$this->assertSame( 7, Vandrekalender_Past_Walks::shown_count( 7, 1 ) );
		$this->assertSame( 10, Vandrekalender_Past_Walks::shown_count( 212, 1 ) );
		$this->assertSame( 30, Vandrekalender_Past_Walks::shown_count( 212, 3 ) );
		$this->assertSame( 212, Vandrekalender_Past_Walks::shown_count( 212, 99 ) );
		$this->assertSame( 10, Vandrekalender_Past_Walks::shown_count( 212, 0 ) );
	}

	/**
	 * The visitor's dates narrow the window but never widen it.
	 */
	public function test_date_range_stays_inside_the_window(): void {
		$today = '2026-11-02';

		$this->assertSame(
			[
				'from' => '2025-11-02',
				'to'   => '2026-11-01',
			],
			Vandrekalender_Past_Walks::date_range( [], $today )
		);
		$this->assertSame(
			[
				'from' => '2026-10-01',
				'to'   => '2026-10-31',
			],
			Vandrekalender_Past_Walks::date_range(
				[
					'date_from' => '2026-10-01',
					'date_to'   => '2026-10-31',
				],
				$today
			)
		);
		$this->assertSame(
			[
				'from' => '2025-11-02',
				'to'   => '2026-11-01',
			],
			Vandrekalender_Past_Walks::date_range(
				[
					'date_from' => '2020-01-01',
					'date_to'   => '2027-06-01',
				],
				$today
			)
		);
	}

	/**
	 * A date that is not a real Y-m-d date is ignored.
	 */
	public function test_date_range_ignores_invalid_dates(): void {
		$range = Vandrekalender_Past_Walks::date_range(
			[
				'date_from' => '2026-02-31',
				'date_to'   => 'yesterday',
			],
			'2026-11-02'
		);

		$this->assertSame( '2025-11-02', $range['from'] );
		$this->assertSame( '2026-11-01', $range['to'] );
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
					'region'    => '',
					'organiser' => '',
				]
			)
		);
		$this->assertFalse( Vandrekalender_Past_Walks::is_filtered( [ 'utm_source' => 'x' ] ) );
		$this->assertFalse( Vandrekalender_Past_Walks::is_filtered( [ 'side' => '2' ] ) );
		$this->assertTrue( Vandrekalender_Past_Walks::is_filtered( [ 'organiser' => 'mammutmarch' ] ) );
		$this->assertTrue( Vandrekalender_Past_Walks::is_filtered( [ 'length' => 'kort,lang' ] ) );
		$this->assertTrue( Vandrekalender_Past_Walks::is_filtered( [ 'date_from' => '2026-10-01' ] ) );
		$this->assertTrue( Vandrekalender_Past_Walks::is_filtered( [ 'is_free' => 'true' ] ) );
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

	/**
	 * The front page link is added once per request: after the first tabs
	 * block, not after every one.
	 */
	public function test_front_page_link_is_added_once(): void {
		$this->reset_front_page_link();
		$url = 'https://example.dk/tidligere-ture/';

		$first  = Vandrekalender_Past_Walks::append_link_once( '<div>tabs 1</div>', $url );
		$second = Vandrekalender_Past_Walks::append_link_once( '<div>tabs 2</div>', $url );

		$this->assertStringStartsWith( '<div>tabs 1</div><p class="vk-past-walks-link">', $first );
		$this->assertStringContainsString( 'href="' . $url . '"', $first );
		$this->assertSame( '<div>tabs 2</div>', $second );
	}

	/**
	 * Without the page (no URL) there is no link, and a later tabs block can
	 * still get it.
	 */
	public function test_front_page_link_left_out_without_page(): void {
		$this->reset_front_page_link();

		$this->assertSame( '<div>tabs</div>', Vandrekalender_Past_Walks::append_link_once( '<div>tabs</div>', '' ) );
		$this->assertStringContainsString(
			'vk-past-walks-link',
			Vandrekalender_Past_Walks::append_link_once( '<div>tabs</div>', 'https://example.dk/tidligere-ture/' )
		);
	}

	/**
	 * The filter dropdowns offer only terms with a walk in the window, so
	 * with no walks in it (the test database is empty) there are none, not
	 * every region that has any event.
	 */
	public function test_window_terms_empty_without_walks_in_window(): void {
		$this->assertSame( [], Vandrekalender_Past_Walks::window_terms( \Vandrekalender\Event::TAX_REGION ) );
		$this->assertSame( [], Vandrekalender_Past_Walks::window_terms( \Vandrekalender\Event::TAX_ORGANISER ) );
	}

	/**
	 * Starts a new "request" for the front page link.
	 */
	private function reset_front_page_link(): void {
		( new ReflectionProperty( Vandrekalender_Past_Walks::class, 'front_page_link_added' ) )->setValue( null, false );
	}
}
