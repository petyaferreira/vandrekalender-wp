<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * Cleaning an organiser's fields before they are stored (#32).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Organiser_Fields::sanitize().
 */
class OrganiserFieldsTest extends TestCase {

	/**
	 * Nothing given: every field is there, empty.
	 */
	public function test_missing_fields_become_empty(): void {
		$this->assertSame(
			[
				Vandrekalender_Organiser_Fields::META_SHORT_DESCRIPTION => '',
				Vandrekalender_Organiser_Fields::META_WEBSITE_URL       => '',
				Vandrekalender_Organiser_Fields::META_IS_OFFICIAL       => false,
				Vandrekalender_Organiser_Fields::META_SOURCE_URL        => '',
				Vandrekalender_Organiser_Fields::META_SOURCE_CHECKED    => '',
				Vandrekalender_Organiser_Fields::META_PATTERN_ID        => 0,
			],
			Vandrekalender_Organiser_Fields::sanitize( [] )
		);
	}

	/**
	 * The short description loses markup but keeps its line breaks.
	 */
	public function test_short_description_is_plain_text(): void {
		$clean = Vandrekalender_Organiser_Fields::sanitize( [ Vandrekalender_Organiser_Fields::META_SHORT_DESCRIPTION => " Lang <b>vandring</b>\nlinje 2 " ] );

		$this->assertSame( "Lang vandring\nlinje 2", $clean[ Vandrekalender_Organiser_Fields::META_SHORT_DESCRIPTION ] );
	}

	/**
	 * Only http(s) links are kept, so a javascript: link can never reach
	 * the website button.
	 */
	public function test_only_http_links_are_kept(): void {
		$clean = Vandrekalender_Organiser_Fields::sanitize(
			[
				Vandrekalender_Organiser_Fields::META_WEBSITE_URL => 'javascript:alert(1)',
				Vandrekalender_Organiser_Fields::META_SOURCE_URL  => ' https://dvl.dk ',
			]
		);

		$this->assertSame( '', $clean[ Vandrekalender_Organiser_Fields::META_WEBSITE_URL ] );
		$this->assertSame( 'https://dvl.dk', $clean[ Vandrekalender_Organiser_Fields::META_SOURCE_URL ] );
	}

	/**
	 * The checked-on date must be a real YYYY-MM-DD date.
	 */
	public function test_source_date_must_be_a_real_date(): void {
		$key = Vandrekalender_Organiser_Fields::META_SOURCE_CHECKED;

		$this->assertSame( '2026-10-19', Vandrekalender_Organiser_Fields::sanitize( [ $key => '2026-10-19' ] )[ $key ] );
		$this->assertSame( '', Vandrekalender_Organiser_Fields::sanitize( [ $key => '2026-02-30' ] )[ $key ] );
		$this->assertSame( '', Vandrekalender_Organiser_Fields::sanitize( [ $key => '19. oktober 2026' ] )[ $key ] );
	}

	/**
	 * A ticked checkbox is true, anything empty is false; the pattern ID is
	 * a whole number, never negative.
	 */
	public function test_badge_and_pattern_id(): void {
		$clean = Vandrekalender_Organiser_Fields::sanitize(
			[
				Vandrekalender_Organiser_Fields::META_IS_OFFICIAL => '1',
				Vandrekalender_Organiser_Fields::META_PATTERN_ID  => '-5',
			]
		);

		$this->assertTrue( $clean[ Vandrekalender_Organiser_Fields::META_IS_OFFICIAL ] );
		$this->assertSame( 0, $clean[ Vandrekalender_Organiser_Fields::META_PATTERN_ID ] );
		$this->assertFalse( Vandrekalender_Organiser_Fields::sanitize( [ Vandrekalender_Organiser_Fields::META_IS_OFFICIAL => '' ] )[ Vandrekalender_Organiser_Fields::META_IS_OFFICIAL ] );
	}
}
