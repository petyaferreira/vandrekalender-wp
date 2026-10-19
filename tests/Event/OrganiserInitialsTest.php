<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * Initials on an organiser's badge, until it has a logo.
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Event;

/**
 * Event::organiser_initials().
 */
class OrganiserInitialsTest extends TestCase {

	/**
	 * One letter per word, joining words skipped, at most three.
	 */
	public function test_initials_of_the_design_organisers(): void {
		$this->assertSame( 'DV', Event::organiser_initials( 'Dansk Vandrelaug' ) );
		$this->assertSame( 'OSS', Event::organiser_initials( 'Over Stok og Sten' ) );
		$this->assertSame( 'LLW', Event::organiser_initials( 'The Long Lonely Wolf Walk' ) );
		$this->assertSame( 'GLK', Event::organiser_initials( 'En GÅtur langs kysten' ) );
		$this->assertSame( 'M', Event::organiser_initials( 'Mammutmarch' ) );
	}

	/**
	 * Danish letters stay whole.
	 */
	public function test_danish_letters(): void {
		$this->assertSame( 'ÅØ', Event::organiser_initials( 'åben Ørsted' ) );
	}
}
