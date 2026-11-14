<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * Organiser FAQ class migration of #261.
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Organiser_Faq_Classes::migrated_content().
 */
class OrganiserFaqClassesTest extends TestCase {

	/**
	 * Old classes and the block name are renamed.
	 */
	public function test_renames_classes_and_block_name(): void {
		$old = '<!-- wp:group {"metadata":{"name":"Organiser about"}} --><div class="vk-organiser-about__intro"></div><div class="vk-organiser-about__image"></div>';
		$new = '<!-- wp:group {"metadata":{"name":"FAQ"}} --><div class="vk-faq__intro"></div><div class="vk-faq__image"></div>';
		$this->assertSame( $new, Vandrekalender_Organiser_Faq_Classes::migrated_content( $old ) );
	}

	/**
	 * Already migrated content stays, so a second run changes nothing.
	 */
	public function test_is_idempotent(): void {
		$new = '<div class="vk-faq__intro"></div>';
		$this->assertSame( $new, Vandrekalender_Organiser_Faq_Classes::migrated_content( $new ) );
	}
}
