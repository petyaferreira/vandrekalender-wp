<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * The event post type has no archive, so /begivenhed/ is a 404 (#176).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Event;

/**
 * Event::post_type_args().
 */
class EventPostTypeTest extends TestCase {

	/**
	 * No archive page at /begivenhed/.
	 */
	public function test_has_no_archive(): void {
		$this->assertFalse( Event::post_type_args()['has_archive'] );
	}

	/**
	 * Event URLs keep the /begivenhed/ base.
	 */
	public function test_event_urls_keep_the_begivenhed_base(): void {
		$this->assertSame( 'begivenhed', Event::post_type_args()['rewrite']['slug'] );
	}
}
