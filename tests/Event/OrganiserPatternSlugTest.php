<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * Which theme pattern holds a part of an organiser's page.
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Event;

/**
 * Event::organiser_pattern_slug().
 */
class OrganiserPatternSlugTest extends TestCase {

	/**
	 * Term slug and part make the slug of the theme's pattern file.
	 */
	public function test_slug_from_term_and_part(): void {
		$this->assertSame( 'vandrekalender-theme/organiser-dansk-vandrelaug-about', Event::organiser_pattern_slug( 'dansk-vandrelaug', 'about' ) );
		$this->assertSame( 'vandrekalender-theme/organiser-mammutmarch-faq', Event::organiser_pattern_slug( 'mammutmarch', 'faq' ) );
	}

	/**
	 * Anything that is not a plain slug gives no pattern, so a block
	 * attribute cannot point at another theme's or plugin's pattern.
	 */
	public function test_no_slug_for_other_input(): void {
		$this->assertSame( '', Event::organiser_pattern_slug( '', 'about' ) );
		$this->assertSame( '', Event::organiser_pattern_slug( 'mammutmarch', '' ) );
		$this->assertSame( '', Event::organiser_pattern_slug( 'mammutmarch', '../hero' ) );
		$this->assertSame( '', Event::organiser_pattern_slug( 'core/x', 'about' ) );
		$this->assertSame( '', Event::organiser_pattern_slug( 'Mammutmarch', 'about' ) );
		$this->assertSame( '', Event::organiser_pattern_slug( 'mammutmarch', "about\n" ) );
	}
}
