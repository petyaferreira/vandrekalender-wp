<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * The event archive (/begivenhed/) redirects to the front page (#176).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Event;

/**
 * Event::archive_redirect_target().
 */
class EventArchiveRedirectTest extends TestCase {

	/**
	 * The archive goes to the front page.
	 */
	public function test_archive_redirects_to_the_front_page(): void {
		$this->assertSame( 'https://example.test/', Event::archive_redirect_target( true, 'https://example.test/' ) );
	}

	/**
	 * Any other request is left alone.
	 */
	public function test_other_requests_are_not_redirected(): void {
		$this->assertNull( Event::archive_redirect_target( false, 'https://example.test/' ) );
	}
}
