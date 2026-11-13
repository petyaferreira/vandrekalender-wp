<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * The login URL a logged-out joiner is sent to (#247).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Event_Join::login_url().
 */
class JoinLoginUrlTest extends TestCase {

	/**
	 * The redirect_to is the event URL with joined=1, so Login with Google lands
	 * on the just-joined note.
	 */
	public function test_redirect_to_is_the_joined_url(): void {
		$url = Vandrekalender_Event_Join::login_url( 12 );

		$this->assertSame(
			'https://example.test/wp-login.php?redirect_to=' . rawurlencode( 'https://example.test/begivenhed/test-event/?joined=1#vk-join' ),
			$url
		);
	}
}
