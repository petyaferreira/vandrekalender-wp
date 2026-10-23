<?php
/**
 * Whether an event date has passed, which hides the booking link and sign-up.
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Event;

/**
 * Event::is_past_date().
 */
class PastDateTest extends TestCase {

	/**
	 * Yesterday is past; today and tomorrow are not.
	 */
	public function test_past_today_and_future(): void {
		$this->assertTrue( Event::is_past_date( '2026-10-22', '2026-10-23' ) );
		$this->assertFalse( Event::is_past_date( '2026-10-23', '2026-10-23' ) );
		$this->assertFalse( Event::is_past_date( '2026-10-24', '2026-10-23' ) );
	}

	/**
	 * An event with no date is never treated as past.
	 */
	public function test_missing_date_is_not_past(): void {
		$this->assertFalse( Event::is_past_date( '', '2026-10-23' ) );
	}

	/**
	 * Without a given day it compares against today in the site timezone.
	 */
	public function test_defaults_to_site_today(): void {
		$this->assertFalse( Event::is_past_date( current_time( 'Y-m-d' ) ) );
		$this->assertTrue( Event::is_past_date( '2000-01-01' ) );
	}
}
