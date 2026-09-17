<?php

defined( 'ABSPATH' ) || exit;

/**
 * Drops redirecting past events from Rank Math's XML sitemap.
 *
 * A past event with a series key and a later published occurrence
 * 301-redirects to it (see Vandrekalender_Event_Past_Events) — listing that
 * URL in the sitemap would just send crawlers straight into a redirect. A
 * past one-off event, or a recurring one with no next occurrence yet, still
 * renders normally and stays in the sitemap.
 *
 * The set of redirecting event IDs is computed with one query and cached,
 * rather than re-derived per sitemap entry.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Event_Sitemap {

	use Vandrekalender_Polylang_Language;

	const CACHE_KEY = 'vandrekalender_sitemap_redirecting_events';
	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Constructor — hooks the sitemap filter and cache invalidation.
	 */
	public function __construct() {
		add_filter( 'rank_math/sitemap/entry', [ $this, 'exclude_redirecting_past_events' ], 10, 3 );

		add_action( 'save_post_' . \Vandrekalender\Event::CUSTOMPOSTTYPE, [ $this, 'clear_cache' ] );
		// before_delete_post, not deleted_post: the post (and its type) is
		// gone by the time deleted_post fires.
		add_action( 'before_delete_post', [ $this, 'maybe_clear_cache_on_delete' ] );
	}

	/**
	 * Drop a sitemap entry for an event that 301-redirects to its next
	 * occurrence.
	 *
	 * @param array|false $url  URL parts for the sitemap entry, or false.
	 * @param string      $type Rank Math's object type (always 'post' here).
	 * @param object      $post The post object.
	 * @return array|false
	 */
	public function exclude_redirecting_past_events( $url, $type, $post ) {
		if ( ! $url || ! isset( $post->post_type, $post->ID ) || \Vandrekalender\Event::CUSTOMPOSTTYPE !== $post->post_type ) {
			return $url;
		}

		if ( isset( $this->redirecting_event_ids()[ (int) $post->ID ] ) ) {
			return false;
		}

		return $url;
	}

	/**
	 * The set of event post IDs that redirect, as an ID => true map for O(1)
	 * lookups, cached for CACHE_TTL.
	 *
	 * @return array<int, bool>
	 */
	private function redirecting_event_ids(): array {
		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$ids = array_fill_keys( $this->compute_redirecting_event_ids(), true );

		set_transient( self::CACHE_KEY, $ids, self::CACHE_TTL );

		return $ids;
	}

	/**
	 * One query: pairs of (past event, candidate next event) sharing a series
	 * key, where the past one's event_date is behind today and the
	 * candidate's is today or later — the same base criteria
	 * Vandrekalender_Event_Past_Events::find_next_occurrence() redirects on.
	 * Language is then checked in PHP against the actual Polylang API (see
	 * post_language()) rather than reimplemented against Polylang's internal
	 * tables here, so the two places can't drift out of agreement.
	 *
	 * @return int[] Post IDs of past events with a same-language next occurrence.
	 */
	private function compute_redirecting_event_ids(): array {
		global $wpdb;

		$sql = $wpdb->prepare(
			"
			SELECT DISTINCT past_key.post_id AS past_id, next_key.post_id AS next_id
			FROM {$wpdb->postmeta} AS past_key
			INNER JOIN {$wpdb->posts} AS past
				ON past.ID = past_key.post_id
				AND past.post_type = %s
				AND past.post_status = 'publish'
			INNER JOIN {$wpdb->postmeta} AS past_date
				ON past_date.post_id = past_key.post_id
				AND past_date.meta_key = %s
				AND past_date.meta_value < %s
			INNER JOIN {$wpdb->postmeta} AS next_key
				ON next_key.meta_key = %s
				AND next_key.meta_value = past_key.meta_value
				AND next_key.post_id != past_key.post_id
			INNER JOIN {$wpdb->posts} AS next
				ON next.ID = next_key.post_id
				AND next.post_type = %s
				AND next.post_status = 'publish'
			INNER JOIN {$wpdb->postmeta} AS next_date
				ON next_date.post_id = next_key.post_id
				AND next_date.meta_key = %s
				AND next_date.meta_value >= %s
			WHERE past_key.meta_key = %s
				AND past_key.meta_value != ''
			",
			\Vandrekalender\Event::CUSTOMPOSTTYPE,
			\Vandrekalender\Event::META_DATE,
			current_time( 'Y-m-d' ),
			\Vandrekalender\Event::META_SERIES_KEY,
			\Vandrekalender\Event::CUSTOMPOSTTYPE,
			\Vandrekalender\Event::META_DATE,
			current_time( 'Y-m-d' ),
			\Vandrekalender\Event::META_SERIES_KEY
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- built with $wpdb->prepare() above (only hardcoded identifiers sit outside the placeholders); result is cached in a transient by the caller, not per-call.
		$pairs = $wpdb->get_results( $sql );

		$redirecting = [];

		foreach ( $pairs as $pair ) {
			$past_id = (int) $pair->past_id;

			if ( isset( $redirecting[ $past_id ] ) ) {
				continue;
			}

			// Claimed events are never redirected — see the matching guard
			// in Vandrekalender_Event_Past_Events::maybe_redirect_to_next_occurrence().
			if ( get_post_meta( $past_id, \Vandrekalender\Event::META_CLAIMED, true ) ) {
				continue;
			}

			$next_id = (int) $pair->next_id;

			// Mirrors future_occurrences_in_series(): a claimed post is no
			// longer touched by the scraper, so it isn't safe to treat as
			// this series' authoritative next occurrence either. Don't mark
			// $past_id resolved from this pair — a different, unclaimed
			// pair for the same $past_id may still turn up.
			if ( get_post_meta( $next_id, \Vandrekalender\Event::META_CLAIMED, true ) ) {
				continue;
			}

			// Mirrors find_next_occurrence(): no language on the past post
			// means no restriction (matches how it skips adding 'lang' to
			// the query in that case); otherwise the candidate must match.
			$past_lang = $this->post_language( $past_id );

			if ( '' === $past_lang || $past_lang === $this->post_language( $next_id ) ) {
				$redirecting[ $past_id ] = true;
			}
		}

		return array_keys( $redirecting );
	}

	/**
	 * Clear the cached ID set when an event is saved.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Clear the cached ID set when an event is deleted.
	 *
	 * @param int $post_id The deleted post's ID.
	 * @return void
	 */
	public function maybe_clear_cache_on_delete( int $post_id ): void {
		if ( \Vandrekalender\Event::CUSTOMPOSTTYPE === get_post_type( $post_id ) ) {
			$this->clear_cache();
		}
	}
}
