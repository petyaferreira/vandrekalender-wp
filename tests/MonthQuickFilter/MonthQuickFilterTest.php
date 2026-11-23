<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * The months behind the Month Quick Filter block (#198).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Month_Quick_Filter.
 */
class MonthQuickFilterTest extends TestCase {

	/**
	 * The first days of the months month_options() gives.
	 *
	 * @param string $direction 'past' or 'future'.
	 * @param string $today     Today as Y-m-d.
	 * @return string[]
	 */
	private function starts( string $direction, string $today ): array {
		return array_column( Vandrekalender_Month_Quick_Filter::month_options( $direction, $today ), 'from' );
	}

	/**
	 * Future: this month and the next 5, soonest first.
	 */
	public function test_future_is_this_month_and_the_next_five(): void {
		$this->assertSame(
			[ '2026-11-01', '2026-12-01', '2027-01-01', '2027-02-01', '2027-03-01', '2027-04-01' ],
			$this->starts( 'future', '2026-11-02' )
		);
	}

	/**
	 * Past: this month and the 5 before it, newest first.
	 */
	public function test_past_is_this_month_and_the_five_before_newest_first(): void {
		$this->assertSame(
			[ '2026-11-01', '2026-10-01', '2026-09-01', '2026-08-01', '2026-07-01', '2026-06-01' ],
			$this->starts( 'past', '2026-11-02' )
		);
	}

	/**
	 * Future from December crosses into the next year.
	 */
	public function test_future_crosses_the_year_boundary(): void {
		$this->assertSame(
			[ '2026-12-01', '2027-01-01', '2027-02-01', '2027-03-01', '2027-04-01', '2027-05-01' ],
			$this->starts( 'future', '2026-12-31' )
		);
	}

	/**
	 * Past from January crosses into the year before.
	 */
	public function test_past_crosses_the_year_boundary(): void {
		$this->assertSame(
			[ '2027-01-01', '2026-12-01', '2026-11-01', '2026-10-01', '2026-09-01', '2026-08-01' ],
			$this->starts( 'past', '2027-01-01' )
		);
	}

	/**
	 * Months with 31 days at the end of the day count: no skipping from the 31st.
	 */
	public function test_start_on_the_31st_does_not_skip_months(): void {
		$this->assertSame(
			[ '2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01', '2026-05-01', '2026-06-01' ],
			$this->starts( 'future', '2026-01-31' )
		);
	}

	/**
	 * The last day is right for February in a normal year and a leap year.
	 */
	public function test_last_day_of_february_in_normal_and_leap_years(): void {
		$normal = Vandrekalender_Month_Quick_Filter::month_options( 'future', '2027-02-10' );
		$this->assertSame( '2027-02-28', $normal[0]['to'] );
		$this->assertSame( '2027-03-31', $normal[1]['to'] );

		$leap = Vandrekalender_Month_Quick_Filter::month_options( 'future', '2028-02-10' );
		$this->assertSame( '2028-02-29', $leap[0]['to'] );
	}

	/**
	 * Every chip spans its whole month.
	 */
	public function test_each_month_runs_from_first_to_last_day(): void {
		$months = Vandrekalender_Month_Quick_Filter::month_options( 'future', '2026-11-02' );
		$this->assertSame( '2026-11-30', $months[0]['to'] );
		$this->assertSame( '2026-12-31', $months[1]['to'] );
		$this->assertSame( '2027-04-30', $months[5]['to'] );
	}

	/**
	 * The label timestamp falls inside its month.
	 */
	public function test_label_time_is_inside_the_month(): void {
		foreach ( Vandrekalender_Month_Quick_Filter::month_options( 'future', '2026-11-02' ) as $month ) {
			$this->assertSame( substr( $month['from'], 0, 7 ), gmdate( 'Y-m', $month['label_time'] ) );
		}
	}
}
