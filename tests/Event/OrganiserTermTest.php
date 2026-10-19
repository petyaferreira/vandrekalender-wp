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
	 * Each organisation's scraper gets a term named after its source, so
	 * renaming a SOURCE_NAME without updating ORGANISER_SOURCES fails here.
	 * Every DVL chapter shares the Dansk Vandrelaug term.
	 */
	public function test_organisation_scrapers_get_a_term_named_after_the_source(): void {
		$this->assertSame( 'Dansk Vandrelaug', Event::organiser_term_for_source( Vandrekalender_Scraper_DVL::SOURCE_NAME ) );
		$this->assertSame( 'Mammutmarch', Event::organiser_term_for_source( Vandrekalender_Scraper_Mammut::SOURCE_NAME ) );
		$this->assertSame( 'Opdag Verden', Event::organiser_term_for_source( Vandrekalender_Scraper_Opdagverden::SOURCE_NAME ) );
	}

	/**
	 * Sportstiming is a sign-up platform, not an organiser (#88).
	 */
	public function test_sportstiming_events_get_no_term(): void {
		$this->assertSame( '', Event::organiser_term_for_source( Vandrekalender_Scraper_Sportstiming::SOURCE_NAME ) );
	}

	/**
	 * Manual events have no source name.
	 */
	public function test_no_source_gets_no_term(): void {
		$this->assertSame( '', Event::organiser_term_for_source( '' ) );
	}
}
