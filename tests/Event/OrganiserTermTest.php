<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * Which event_organiser term a scraped event gets from its source (#32).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Event;

/**
 * Event::organiser_term_for_source().
 */
class OrganiserTermTest extends TestCase {

	/**
	 * Every DVL chapter shares one term, named after the source.
	 */
	public function test_dvl_events_get_the_dansk_vandrelaug_term(): void {
		$this->assertSame( 'Dansk Vandrelaug', Event::organiser_term_for_source( Vandrekalender_Scraper_DVL::SOURCE_NAME ) );
	}

	/**
	 * Sportstiming is a sign-up platform, not an organiser (#88).
	 */
	public function test_sportstiming_events_get_no_term(): void {
		$this->assertSame( '', Event::organiser_term_for_source( 'Sportstiming' ) );
	}

	/**
	 * Manual events have no source name.
	 */
	public function test_no_source_gets_no_term(): void {
		$this->assertSame( '', Event::organiser_term_for_source( '' ) );
	}
}
