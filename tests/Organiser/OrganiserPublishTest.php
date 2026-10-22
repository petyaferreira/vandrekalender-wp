<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * An organiser page needs its organiser before visitors can see it (#32,
 * step 5b).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Organiser;

/**
 * Organiser::needs_term().
 */
class OrganiserPublishTest extends TestCase {

	/**
	 * Published, scheduled and private pages need a term.
	 */
	public function test_visible_statuses_need_a_term(): void {
		$this->assertTrue( Organiser::needs_term( 'publish' ) );
		$this->assertTrue( Organiser::needs_term( 'future' ) );
		$this->assertTrue( Organiser::needs_term( 'private' ) );
	}

	/**
	 * A draft can be saved before the organiser is chosen.
	 */
	public function test_drafts_do_not(): void {
		$this->assertFalse( Organiser::needs_term( 'draft' ) );
		$this->assertFalse( Organiser::needs_term( 'pending' ) );
		$this->assertFalse( Organiser::needs_term( 'auto-draft' ) );
	}
}
