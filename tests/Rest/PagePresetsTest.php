<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * Filters a page pins for its walk blocks: a region or length archive's
 * term, an organiser page's organiser (#32, step 5b).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Event;

/**
 * Vandrekalender_Event_Rest_Api::presets_for().
 */
class PagePresetsTest extends TestCase {

	/**
	 * Region and length archives pin their term under the filter's name.
	 */
	public function test_archives_pin_their_term(): void {
		$this->assertSame( [ 'region' => 'hovedstaden' ], Vandrekalender_Event_Rest_Api::presets_for( Event::TAX_REGION, 'hovedstaden', null ) );
		$this->assertSame( [ 'length' => 'lang' ], Vandrekalender_Event_Rest_Api::presets_for( Event::TAX_LENGTH, 'lang', null ) );
	}

	/**
	 * An organiser page pins its organiser.
	 */
	public function test_organiser_page_pins_its_organiser(): void {
		$this->assertSame( [ 'organiser' => 'mammutmarch' ], Vandrekalender_Event_Rest_Api::presets_for( '', '', 'mammutmarch' ) );
	}

	/**
	 * An organiser page without a term shows no walks rather than all of
	 * them: the pinned value matches no term.
	 */
	public function test_organiser_page_without_term_matches_nothing(): void {
		$presets = Vandrekalender_Event_Rest_Api::presets_for( '', '', '' );

		$this->assertSame( [ 'organiser' => Vandrekalender_Event_Rest_Api::NO_ORGANISER ], $presets );
		$this->assertSame( '', sanitize_title( $presets['organiser'] ) );
	}

	/**
	 * Other pages pin nothing, also an archive of another taxonomy.
	 */
	public function test_other_pages_pin_nothing(): void {
		$this->assertSame( [], Vandrekalender_Event_Rest_Api::presets_for( '', '', null ) );
		$this->assertSame( [], Vandrekalender_Event_Rest_Api::presets_for( 'category', 'nyheder', null ) );
	}
}
