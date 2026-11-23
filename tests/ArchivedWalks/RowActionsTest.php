<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * "Fjern nu" and "Gendan" in the Events list (#268, #266): nothing changes
 * without a valid nonce, the right capability and the right status.
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Archived_Walks::handle_status_change().
 */
class RowActionsTest extends TestCase {

	/**
	 * A published walk (12) and an archived one (13); the user may edit and publish.
	 */
	protected function setUp(): void {
		$GLOBALS['vandrekalender_test_posts']   = [
			12 => (object) [
				'ID'          => 12,
				'post_type'   => 'event',
				'post_status' => 'publish',
			],
			13 => (object) [
				'ID'          => 13,
				'post_type'   => 'event',
				'post_status' => 'archived',
			],
		];
		$GLOBALS['vandrekalender_test_caps']    = [
			'edit_post'    => true,
			'publish_post' => true,
		];
		$GLOBALS['vandrekalender_test_updates'] = [];
	}

	/**
	 * "Fjern nu" archives a published walk.
	 */
	public function test_remove_now_archives_a_published_walk(): void {
		$this->assertSame( 'changed', Vandrekalender_Archived_Walks::handle_status_change( 'vk_remove_walk', 12, true ) );
		$this->assertSame( 'archived', $GLOBALS['vandrekalender_test_posts'][12]->post_status );
	}

	/**
	 * Without a valid nonce nothing changes.
	 */
	public function test_no_nonce_changes_nothing(): void {
		$this->assertSame( 'not_allowed', Vandrekalender_Archived_Walks::handle_status_change( 'vk_remove_walk', 12, false ) );
		$this->assertSame( [], $GLOBALS['vandrekalender_test_updates'] );
	}

	/**
	 * Without the right to edit the walk nothing changes.
	 */
	public function test_no_capability_changes_nothing(): void {
		$GLOBALS['vandrekalender_test_caps'] = [];

		$this->assertSame( 'not_allowed', Vandrekalender_Archived_Walks::handle_status_change( 'vk_remove_walk', 12, true ) );
		$this->assertSame( 'not_allowed', Vandrekalender_Archived_Walks::handle_status_change( 'vk_restore_walk', 13, true ) );
		$this->assertSame( [], $GLOBALS['vandrekalender_test_updates'] );
	}

	/**
	 * "Gendan" publishes, so editing alone is not enough; "Fjern nu" needs only editing.
	 */
	public function test_restore_needs_the_right_to_publish(): void {
		$GLOBALS['vandrekalender_test_caps'] = [ 'edit_post' => true ];

		$this->assertSame( 'not_allowed', Vandrekalender_Archived_Walks::handle_status_change( 'vk_restore_walk', 13, true ) );
		$this->assertSame( 'changed', Vandrekalender_Archived_Walks::handle_status_change( 'vk_remove_walk', 12, true ) );
	}

	/**
	 * An action on a walk with the wrong status changes nothing.
	 */
	public function test_wrong_status_changes_nothing(): void {
		$this->assertSame( 'wrong_state', Vandrekalender_Archived_Walks::handle_status_change( 'vk_remove_walk', 13, true ), 'Already archived.' );
		$this->assertSame( 'wrong_state', Vandrekalender_Archived_Walks::handle_status_change( 'vk_restore_walk', 12, true ), 'Not archived.' );
		$this->assertSame( 'wrong_state', Vandrekalender_Archived_Walks::handle_status_change( 'vk_remove_walk', 99, true ), 'No such walk.' );
		$this->assertSame( [], $GLOBALS['vandrekalender_test_updates'] );
	}

	/**
	 * Only walks, not other post types.
	 */
	public function test_other_post_types_are_left_alone(): void {
		$GLOBALS['vandrekalender_test_posts'][12]->post_type = 'page';

		$this->assertSame( 'wrong_state', Vandrekalender_Archived_Walks::handle_status_change( 'vk_remove_walk', 12, true ) );
		$this->assertSame( [], $GLOBALS['vandrekalender_test_updates'] );
	}

	/**
	 * An unknown action changes nothing.
	 */
	public function test_unknown_action_changes_nothing(): void {
		$this->assertSame( 'not_allowed', Vandrekalender_Archived_Walks::handle_status_change( 'vk_delete_walk', 12, true ) );
		$this->assertSame( [], $GLOBALS['vandrekalender_test_updates'] );
	}

	/**
	 * "Gendan" publishes an archived walk.
	 */
	public function test_restore_publishes_an_archived_walk(): void {
		$this->assertSame( 'changed', Vandrekalender_Archived_Walks::handle_status_change( 'vk_restore_walk', 13, true ) );
		$this->assertSame( 'publish', $GLOBALS['vandrekalender_test_posts'][13]->post_status );
	}
}
