<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * Breadcrumbs: every trail starts with "Kalender" (#170), and an event's
 * trail is "Kalender / Title" (#176).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Breadcrumbs::apply_rules().
 */
class BreadcrumbsTest extends TestCase {

	const HOME = 'https://vandrekalender.dk/';

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

		$result = Vandrekalender_Breadcrumbs::apply_rules( $items, self::HOME, 'Kalender', '' );

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

		$result = Vandrekalender_Breadcrumbs::apply_rules( $items, self::HOME, 'Calendar', 'https://vandrekalender.dk/en/' );

		$this->assertSame( 'https://vandrekalender.dk/en/', $result[0]['url'] );
	}

	/**
	 * Every item after home is kept, such as the organiser overview.
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

		$result = Vandrekalender_Breadcrumbs::apply_rules( $items, self::HOME, 'Kalender', '' );

		$this->assertSame( [ 'Kalender', 'Arrangører', 'Dansk Vandrelaug' ], array_column( $result, 'label' ) );
	}

	/**
	 * An event's trail is "Kalender / Title": the region core adds goes.
	 */
	public function test_event_trail_drops_core_middle_items(): void {
		$items = [
			[
				'label' => 'Home',
				'url'   => self::HOME,
			],
			[
				'label' => 'Midtjylland',
				'url'   => 'https://vandrekalender.dk/region/midtjylland/',
			],
			[ 'label' => 'Bjerge Skov' ],
		];

		$result = Vandrekalender_Breadcrumbs::apply_rules( $items, self::HOME, 'Kalender', '', [] );

		$this->assertSame( [ 'Kalender', 'Bjerge Skov' ], array_column( $result, 'label' ) );
	}

	/**
	 * Given middle items replace core's.
	 */
	public function test_middle_items_replace_cores(): void {
		$items  = [
			[
				'label' => 'Home',
				'url'   => self::HOME,
			],
			[
				'label' => 'Sjælland',
				'url'   => 'https://vandrekalender.dk/region/sjaelland/',
			],
			[ 'label' => 'Glasruten' ],
		];
		$middle = [
			[
				'label' => 'Tidligere ture',
				'url'   => 'https://vandrekalender.dk/tidligere-ture/',
			],
		];

		$result = Vandrekalender_Breadcrumbs::apply_rules( $items, self::HOME, 'Kalender', '', $middle );

		$this->assertSame( [ 'Kalender', 'Tidligere ture', 'Glasruten' ], array_column( $result, 'label' ) );
	}

	/**
	 * A trail with only the home item is left alone.
	 */
	public function test_home_only_trail_is_left_alone(): void {
		$items = [
			[
				'label' => 'Home',
				'url'   => self::HOME,
			],
		];

		$this->assertCount( 1, Vandrekalender_Breadcrumbs::apply_rules( $items, self::HOME, 'Kalender', '', [] ) );
	}
}
