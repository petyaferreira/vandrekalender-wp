<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * How many walks the Event Cards block shows (#204): CARDS_PER_PAGE at
 * first, CARDS_PER_PAGE more with each "Vis flere" (?side=N).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Event_Rest_Api::cards_shown_count().
 */
class CardsShownCountTest extends TestCase {

	/**
	 * 12 fills whole rows in the card grid's 1 to 4 columns.
	 */
	public function test_per_page_fills_whole_rows(): void {
		$this->assertSame( 12, Vandrekalender_Event_Rest_Api::CARDS_PER_PAGE );
		foreach ( [ 1, 2, 3, 4 ] as $columns ) {
			$this->assertSame( 0, Vandrekalender_Event_Rest_Api::CARDS_PER_PAGE % $columns );
		}
	}

	/**
	 * After ?side=N, min(total, N × 12), never more than there are.
	 */
	public function test_shown_count(): void {
		$this->assertSame( 0, Vandrekalender_Event_Rest_Api::cards_shown_count( 0, 1 ) );
		$this->assertSame( 7, Vandrekalender_Event_Rest_Api::cards_shown_count( 7, 1 ) );
		$this->assertSame( 12, Vandrekalender_Event_Rest_Api::cards_shown_count( 686, 1 ) );
		$this->assertSame( 36, Vandrekalender_Event_Rest_Api::cards_shown_count( 686, 3 ) );
		$this->assertSame( 686, Vandrekalender_Event_Rest_Api::cards_shown_count( 686, 99 ) );
	}

	/**
	 * A side of 0 or less shows the first step.
	 */
	public function test_shown_count_with_side_below_one(): void {
		$this->assertSame( 12, Vandrekalender_Event_Rest_Api::cards_shown_count( 686, 0 ) );
		$this->assertSame( 12, Vandrekalender_Event_Rest_Api::cards_shown_count( 686, -2 ) );
	}
}
