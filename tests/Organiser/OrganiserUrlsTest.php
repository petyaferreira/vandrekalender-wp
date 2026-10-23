<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * Organiser pages: one page per organiser, the calendar fallback link and
 * the old term archive URLs (#32, step 5a).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Organiser;

/**
 * Organiser::post_with_term(), calendar_url() and slug_from_request().
 */
class OrganiserUrlsTest extends TestCase {

	/**
	 * Another post already linked to the term is the one returned.
	 */
	public function test_a_term_linked_to_another_post_is_taken(): void {
		$links = [
			10 => 3,
			11 => 5,
		];

		$this->assertSame( 11, Organiser::post_with_term( 5, 0, $links ) );
		$this->assertSame( 11, Organiser::post_with_term( 5, 10, $links ) );
	}

	/**
	 * Saving the post that already holds the term, or picking a free term,
	 * is allowed.
	 */
	public function test_own_or_free_term_is_not_taken(): void {
		$links = [
			10 => 3,
			11 => 5,
		];

		$this->assertSame( 0, Organiser::post_with_term( 5, 11, $links ) );
		$this->assertSame( 0, Organiser::post_with_term( 7, 10, $links ) );
		$this->assertSame( 0, Organiser::post_with_term( 7, 0, [] ) );
	}

	/**
	 * Without a page, an organiser links to the front page filtered by it.
	 */
	public function test_calendar_url_filters_the_front_page(): void {
		$this->assertSame(
			'https://vandrekalender.dk/?organiser=dansk-vandrelaug',
			Organiser::calendar_url( 'dansk-vandrelaug', 'https://vandrekalender.dk/' )
		);
	}

	/**
	 * A home URL that already has a query string gets the filter appended.
	 */
	public function test_calendar_url_appends_to_an_existing_query(): void {
		$this->assertSame(
			'http://localhost:8080/?lang=da&organiser=mammutmarch',
			Organiser::calendar_url( 'mammutmarch', 'http://localhost:8080/?lang=da' )
		);
	}

	/**
	 * Only /arrangoerer/<slug>/ is an organiser URL; the overview and
	 * deeper paths are not.
	 */
	public function test_slug_from_request(): void {
		$this->assertSame( 'dansk-vandrelaug', Organiser::slug_from_request( 'arrangoerer/dansk-vandrelaug' ) );
		$this->assertSame( 'dansk-vandrelaug', Organiser::slug_from_request( 'arrangoerer/dansk-vandrelaug/' ) );
		$this->assertSame( '', Organiser::slug_from_request( 'arrangoerer' ) );
		$this->assertSame( '', Organiser::slug_from_request( 'arrangoerer/page/2' ) );
		$this->assertSame( '', Organiser::slug_from_request( 'begivenhed/dansk-vandrelaug' ) );
		$this->assertSame( '', Organiser::slug_from_request( 'en/arrangoerer/dansk-vandrelaug' ) );
	}
}
