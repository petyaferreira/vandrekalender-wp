<?php

defined( 'ABSPATH' ) || exit;

/**
 * Shared post_language() helper for classes that need to compare posts'
 * Polylang languages.
 *
 * Used instead of passing 'lang' as a get_posts()/WP_Query arg: the
 * `language` taxonomy's query_var is registered but 'public' => false, and
 * in this environment that WP_Query arg silently applies no restriction at
 * all (confirmed by inspecting the generated SQL). Every class relying on
 * "same language" matching (Vandrekalender_Event_Past_Events,
 * Vandrekalender_Event_Sitemap, Vandrekalender_Legacy_Event_Urls) needs to
 * agree on what that means, so this lives in one place rather than three
 * copies that could drift.
 *
 * @package Vandrekalender
 */
trait Vandrekalender_Polylang_Language {

	/**
	 * The Polylang language slug for a post, or '' when Polylang is inactive
	 * or the post has none.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function post_language( int $post_id ): string {
		if ( ! function_exists( 'pll_get_post_language' ) ) {
			return '';
		}

		$lang = pll_get_post_language( $post_id );

		return is_string( $lang ) ? $lang : '';
	}
}
