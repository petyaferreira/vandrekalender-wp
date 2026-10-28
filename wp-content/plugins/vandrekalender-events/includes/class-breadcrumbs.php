<?php

defined( 'ABSPATH' ) || exit;

/**
 * Site-wide breadcrumbs for the core Breadcrumbs block: every trail starts
 * with "Kalender" (the site's home is the calendar), and the event archive
 * is never part of a trail, because /begivenhed/ is not a page of its own.
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
		$archive = get_post_type_archive_link( \Vandrekalender\Event::CUSTOMPOSTTYPE );

		// Polylang translates home_url() only for callers on its whitelist,
		// which this block is not on; pll_home_url() gives /en/ on English pages.
		$home_link = function_exists( 'pll_home_url' ) ? (string) pll_home_url() : '';

		return self::apply_rules(
			$items,
			home_url( '/' ),
			__( 'Calendar', 'vandrekalender-events' ),
			$home_link,
			is_string( $archive ) ? [ $archive ] : []
		);
	}

	/**
	 * The trail rules, without WordPress.
	 *
	 * @param array<int,array<string,mixed>> $items       Breadcrumb items, home first.
	 * @param string                         $home_url    home_url( '/' ), which core gives the first item.
	 * @param string                         $home_label  Label for the home item.
	 * @param string                         $home_link   URL for the home item, '' to keep core's.
	 * @param string[]                       $hidden_urls URLs whose items are dropped from the trail.
	 * @return array<int,array<string,mixed>>
	 */
	public static function apply_rules( array $items, string $home_url, string $home_label, string $home_link, array $hidden_urls ): array {
		if ( isset( $items[0]['url'] ) && untrailingslashit( $items[0]['url'] ) === untrailingslashit( $home_url ) ) {
			$items[0]['label'] = $home_label;
			if ( '' !== $home_link ) {
				$items[0]['url'] = $home_link;
			}
		}

		$hidden = array_map( 'untrailingslashit', $hidden_urls );
		$kept   = [];
		foreach ( $items as $index => $item ) {
			// Never drop the home item or the page itself (the last item has no url).
			if ( $index > 0 && isset( $item['url'] ) && in_array( untrailingslashit( $item['url'] ), $hidden, true ) ) {
				continue;
			}
			$kept[] = $item;
		}

		return $kept;
	}
}
