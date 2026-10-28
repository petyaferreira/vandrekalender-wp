<?php

defined( 'ABSPATH' ) || exit;

/**
 * Site-wide breadcrumbs for the core Breadcrumbs block: every trail starts
 * with "Kalender" (the site's home is the calendar), and an event's trail is
 * "Kalender / Title". Events have no archive (#176), so core would otherwise
 * put one of the event's terms (its region) in between.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Breadcrumbs {

	/**
	 * Constructor — hooks the core block's items filter.
	 */
	public function __construct() {
		add_filter( 'block_core_breadcrumbs_items', [ $this, 'filter_items' ] );
	}

	/**
	 * Apply the site's trail rules to the items core built.
	 *
	 * @param array<int,array<string,mixed>> $items Breadcrumb items, home first.
	 * @return array<int,array<string,mixed>>
	 */
	public function filter_items( array $items ): array {
		// Polylang translates home_url() only for callers on its whitelist,
		// which this block is not on; pll_home_url() gives /en/ on English pages.
		$home_link = function_exists( 'pll_home_url' ) ? (string) pll_home_url() : '';

		return self::apply_rules(
			$items,
			home_url( '/' ),
			__( 'Calendar', 'vandrekalender-events' ),
			$home_link,
			is_singular( \Vandrekalender\Event::CUSTOMPOSTTYPE ) ? [] : null
		);
	}

	/**
	 * The trail rules, without WordPress.
	 *
	 * @param array<int,array<string,mixed>>      $items      Breadcrumb items, home first.
	 * @param string                              $home_url   home_url( '/' ), which core gives the first item.
	 * @param string                              $home_label Label for the home item.
	 * @param string                              $home_link  URL for the home item, '' to keep core's.
	 * @param array<int,array<string,mixed>>|null $middle     Items between home and the page itself,
	 *                                                        replacing core's; null keeps core's.
	 * @return array<int,array<string,mixed>>
	 */
	public static function apply_rules( array $items, string $home_url, string $home_label, string $home_link, ?array $middle = null ): array {
		if ( isset( $items[0]['url'] ) && untrailingslashit( $items[0]['url'] ) === untrailingslashit( $home_url ) ) {
			$items[0]['label'] = $home_label;
			if ( '' !== $home_link ) {
				$items[0]['url'] = $home_link;
			}
		}

		if ( null !== $middle && count( $items ) > 1 ) {
			$items = array_merge( [ $items[0] ], $middle, [ $items[ count( $items ) - 1 ] ] );
		}

		return $items;
	}
}
