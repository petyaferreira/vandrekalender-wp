<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * Breadcrumbs: every trail starts with "Kalender" and never contains the
 * event archive (#170).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Breadcrumbs::apply_rules().
 */
class BreadcrumbsTest extends TestCase {

	const HOME    = 'https://vandrekalender.dk/';
	const ARCHIVE = 'https://vandrekalender.dk/begivenhed/';

	/**
	 * Core names the home item "Home"; the site's home is the calendar.
	 */
	public function test_home_item_is_renamed(): void {
		$items = [
			[
				'label' => 'Home',
				'url'   => self::HOME,
			],
			[ 'label' => 'Midtjylland' ],
		];

		$result = Vandrekalender_Breadcrumbs::apply_rules( $items, self::HOME, 'Kalender', '', [ self::ARCHIVE ] );

		$this->assertSame( 'Kalender', $result[0]['label'] );
		$this->assertSame( self::HOME, $result[0]['url'] );
		$this->assertSame( 'Midtjylland', $result[1]['label'] );
	}

	/**
	 * On an English page the home item links to the English front page.
	 */
	public function test_home_link_is_replaced_when_given(): void {
		$items = [
			[
				'label' => 'Home',
				'url'   => self::HOME,
			],
		];

		$result = Vandrekalender_Breadcrumbs::apply_rules( $items, self::HOME, 'Calendar', 'https://vandrekalender.dk/en/', [] );

		$this->assertSame( 'https://vandrekalender.dk/en/', $result[0]['url'] );
	}

	/**
	 * An event's trail is "Kalender / Title": the event archive is dropped.
	 */
	public function test_event_archive_is_dropped(): void {
		$items = [
			[
				'label' => 'Home',
				'url'   => self::HOME,
			],
			[
				'label' => 'Begivenheder',
				'url'   => self::ARCHIVE,
			],
			[ 'label' => 'Kyststien' ],
		];

		$result = Vandrekalender_Breadcrumbs::apply_rules( $items, self::HOME, 'Kalender', '', [ self::ARCHIVE ] );

		$this->assertSame( [ 'Kalender', 'Kyststien' ], array_column( $result, 'label' ) );
	}

	/**
	 * The archive matches with or without a trailing slash.
	 */
	public function test_archive_matches_without_trailing_slash(): void {
		$items = [
			[
				'label' => 'Home',
				'url'   => self::HOME,
			],
			[
				'label' => 'Begivenheder',
				'url'   => 'https://vandrekalender.dk/begivenhed',
			],
			[ 'label' => 'Kyststien' ],
		];

		$result = Vandrekalender_Breadcrumbs::apply_rules( $items, self::HOME, 'Kalender', '', [ self::ARCHIVE ] );

		$this->assertCount( 2, $result );
	}

	/**
	 * Trails without the archive, such as the organiser pages, keep every item.
	 */
	public function test_other_items_are_kept(): void {
		$items = [
			[
				'label' => 'Home',
				'url'   => self::HOME,
			],
			[
				'label' => 'Arrangører',
				'url'   => 'https://vandrekalender.dk/arrangoerer/',
			],
			[ 'label' => 'Dansk Vandrelaug' ],
		];

		$result = Vandrekalender_Breadcrumbs::apply_rules( $items, self::HOME, 'Kalender', '', [ self::ARCHIVE ] );

		$this->assertSame( [ 'Kalender', 'Arrangører', 'Dansk Vandrelaug' ], array_column( $result, 'label' ) );
	}
}
