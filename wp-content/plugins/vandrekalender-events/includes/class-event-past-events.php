<?php

defined( 'ABSPATH' ) || exit;

/**
 * Keeps past event pages public instead of drafting them.
 *
 * A recurring occurrence (has event_series_key) 301-redirects to the next
 * upcoming occurrence of the same walk, once one exists — solving the
 * duplicate-content problem that cleanup_past_events() used to solve by
 * drafting the page instead. A one-off past event, or a recurring one with
 * no next occurrence yet, stays public with a notice and a short list of
 * upcoming walks nearby. See docs/past-events-brief.md.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Event_Past_Events {

	use Vandrekalender_Polylang_Language;

	const NEARBY_COUNT            = 5;
	const NEARBY_MIN              = 3;
	const CACHE_TTL               = HOUR_IN_SECONDS;
	const CACHE_GENERATION_OPTION = 'vandrekalender_past_events_cache_gen';

	/**
	 * Constructor — hooks the redirect, the join-button gate, the notice,
	 * and cache invalidation.
	 */
	public function __construct() {
		add_action( 'template_redirect', [ $this, 'maybe_redirect_to_next_occurrence' ] );
		add_filter( 'vandrekalender_event_is_joinable', [ $this, 'disable_join_for_past_events' ], 10, 2 );
		add_filter( 'the_content', [ $this, 'append_past_event_notice' ] );

		add_action( 'save_post_' . \Vandrekalender\Event::CUSTOMPOSTTYPE, [ $this, 'clear_cache' ] );
		// before_delete_post, not deleted_post: the post (and its type) is
		// gone by the time deleted_post fires.
		add_action( 'before_delete_post', [ $this, 'maybe_clear_cache_on_delete' ] );
	}

	/**
	 * On a past recurring event with a later occurrence, 301-redirect there.
	 * One-off events, and recurring events with no next occurrence, fall
	 * through and render normally (see append_past_event_notice()).
	 *
	 * @return void
	 */
	public function maybe_redirect_to_next_occurrence(): void {
		// An editor previewing unsaved changes must see the preview, not get
		// bounced to the next occurrence.
		if ( is_preview() ) {
			return;
		}

		if ( ! is_singular( \Vandrekalender\Event::CUSTOMPOSTTYPE ) ) {
			return;
		}

		$post_id = get_queried_object_id();

		if ( ! $post_id || ! $this->is_past( $post_id ) ) {
			return;
		}

		// Belt and braces: upsert_event() never writes event_series_key on a
		// claimed post, so this should already be unreachable for one — but
		// don't rely on that holding forever (e.g. a post claimed after it
		// already had a series key from an earlier scrape). Claimed events
		// are never redirected, full stop.
		if ( get_post_meta( $post_id, \Vandrekalender\Event::META_CLAIMED, true ) ) {
			return;
		}

		$series_key = (string) get_post_meta( $post_id, \Vandrekalender\Event::META_SERIES_KEY, true );

		if ( '' === $series_key ) {
			return;
		}

		$next = $this->find_next_occurrence( $series_key, $post_id );

		if ( $next ) {
			wp_safe_redirect( get_permalink( $next ), 301 );
			exit;
		}
	}

	/**
	 * Gate "Jeg kommer" joining off once an event's date has passed.
	 *
	 * @param bool $joinable Whether joining is currently allowed.
	 * @param int  $event_id Event post ID.
	 * @return bool
	 */
	public function disable_join_for_past_events( bool $joinable, int $event_id ): bool {
		if ( ! $joinable ) {
			return false;
		}

		return ! $this->is_past( $event_id );
	}

	/**
	 * Append the "this walk has taken place" notice and a nearby-upcoming
	 * list to a past event's content. Runs through the_content(), which the
	 * core/post-content block calls to render single-event.html — so this is
	 * server-rendered and crawlable without JS.
	 *
	 * @param string $content The post content.
	 * @return string
	 */
	public function append_past_event_notice( string $content ): string {
		if ( ! is_singular( \Vandrekalender\Event::CUSTOMPOSTTYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_queried_object_id();

		if ( ! $post_id || ! $this->is_past( $post_id ) ) {
			return $content;
		}

		return $content . $this->render_notice( $post_id );
	}

	/**
	 * Clear every cached lookup here (both future_occurrences_in_series()
	 * and upcoming_events_pool() entries) by bumping the cache generation,
	 * rather than tracking down every individual transient key.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		update_option( self::CACHE_GENERATION_OPTION, $this->cache_generation() + 1, false );
	}

	/**
	 * Clear the cache when an event is deleted.
	 *
	 * @param int $post_id The deleted post's ID.
	 * @return void
	 */
	public function maybe_clear_cache_on_delete( int $post_id ): void {
		if ( \Vandrekalender\Event::CUSTOMPOSTTYPE === get_post_type( $post_id ) ) {
			$this->clear_cache();
		}
	}

	/**
	 * The current cache generation, incremented by clear_cache().
	 *
	 * @return int
	 */
	private function cache_generation(): int {
		$gen = get_option( self::CACHE_GENERATION_OPTION );

		return is_numeric( $gen ) ? (int) $gen : 0;
	}

	/**
	 * Whether an event's date is before today, in the site timezone.
	 *
	 * @param int $post_id Event post ID.
	 * @return bool
	 */
	private function is_past( int $post_id ): bool {
		$date = (string) get_post_meta( $post_id, \Vandrekalender\Event::META_DATE, true );

		if ( '' === $date ) {
			return false;
		}

		return $date < current_time( 'Y-m-d' );
	}

	/**
	 * The published event with the same series key and the earliest
	 * event_date on or after today, in the same Polylang language.
	 *
	 * @param string $series_key Series key to match.
	 * @param int    $exclude_id Post ID to exclude (the current past event).
	 * @return int Post ID of the next occurrence, or 0 if none.
	 */
	private function find_next_occurrence( string $series_key, int $exclude_id ): int {
		$lang = $this->post_language( $exclude_id );

		foreach ( $this->future_occurrences_in_series( $series_key ) as $candidate ) {
			if ( $candidate['id'] === $exclude_id ) {
				continue;
			}

			if ( '' === $lang || $lang === $candidate['lang'] ) {
				return $candidate['id'];
			}
		}

		return 0;
	}

	/**
	 * Published events in $series_key dated today or later, each as
	 * ['id' => int, 'lang' => string], ordered by date ascending.
	 *
	 * Not capped: this is scoped to one series_key, so the result set is
	 * bounded by that walk's own publishing cadence, not a sitewide scan. It
	 * must be exhaustive — the earliest-dated occurrence isn't necessarily
	 * the first one in this event's language, and a capped pool could miss a
	 * same-language match that Vandrekalender_Event_Sitemap's uncapped SQL
	 * does find, leaving the two disagreeing on whether this event redirects.
	 *
	 * Cached per series (see clear_cache()): this runs on every request to a
	 * past-event page, and those are exactly the URLs this feature expects
	 * heavy repeat crawler traffic on.
	 *
	 * @param string $series_key Series key to match.
	 * @return array<int, array{id: int, lang: string}>
	 */
	private function future_occurrences_in_series( string $series_key ): array {
		$cache_key = 'vk_pe_series_' . $this->cache_generation() . '_' . md5( $series_key );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$ids = get_posts(
			[
				'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'meta_value',
				'meta_key'       => \Vandrekalender\Event::META_DATE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- cached, see future_occurrences_in_series().
				'order'          => 'ASC',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- cached, see future_occurrences_in_series().
					[
						'key'   => \Vandrekalender\Event::META_SERIES_KEY,
						'value' => $series_key,
					],
					[
						'key'     => \Vandrekalender\Event::META_DATE,
						'value'   => current_time( 'Y-m-d' ),
						'compare' => '>=',
						'type'    => 'DATE',
					],
				],
			]
		);

		$result = [];
		foreach ( $ids as $id ) {
			$id = (int) $id;

			// A claimed post is no longer touched by the scraper, so its
			// event_date and event_series_key can go stale or get
			// repurposed by the organiser — not safe to treat as this
			// series' authoritative next occurrence.
			if ( get_post_meta( $id, \Vandrekalender\Event::META_CLAIMED, true ) ) {
				continue;
			}

			$result[] = [
				'id'   => $id,
				'lang' => $this->post_language( $id ),
			];
		}

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Build the notice + nearby-upcoming-walks markup for a past event page.
	 *
	 * @param int $post_id Event post ID.
	 * @return string HTML.
	 */
	private function render_notice( int $post_id ): string {
		$upcoming = $this->nearby_upcoming_events( $post_id );

		ob_start();
		?>
		<div class="vk-past-event-notice">
			<p class="vk-past-event-notice__text">
				<?php esc_html_e( 'This walk has taken place.', 'vandrekalender-events' ); ?>
			</p>
			<?php if ( $upcoming ) : ?>
				<p class="vk-past-event-notice__heading">
					<?php esc_html_e( 'Upcoming walks nearby', 'vandrekalender-events' ); ?>
				</p>
				<ul class="vk-past-event-notice__list">
					<?php foreach ( $upcoming as $event_id ) : ?>
						<li>
							<a href="<?php echo esc_url( get_permalink( $event_id ) ); ?>">
								<?php echo esc_html( get_the_title( $event_id ) ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * 3 to 5 upcoming events in the same region as $post_id, falling back to
	 * nationwide when the region has too few.
	 *
	 * @param int $post_id Event post ID.
	 * @return int[] Post IDs.
	 */
	private function nearby_upcoming_events( int $post_id ): array {
		$lang         = $this->post_language( $post_id );
		$region_terms = wp_get_post_terms( $post_id, \Vandrekalender\Event::TAX_REGION, [ 'fields' => 'ids' ] );
		$region_terms = is_wp_error( $region_terms ) ? [] : array_map( 'intval', $region_terms );

		if ( $region_terms ) {
			$regional = $this->pick_nearby( $this->upcoming_events_pool( $region_terms ), $post_id, $lang );

			if ( count( $regional ) >= self::NEARBY_MIN ) {
				return $regional;
			}
		}

		return $this->pick_nearby( $this->upcoming_events_pool( [] ), $post_id, $lang );
	}

	/**
	 * All published, upcoming events, each as ['id' => int, 'lang' => string],
	 * ordered by date ascending, optionally restricted to $region_terms.
	 *
	 * Not capped: capping before pick_nearby() applies the language filter
	 * would mean a minority-language page (e.g. English, the smaller of the
	 * two per docs/i18n.md) could see a short or empty list purely because
	 * its matches sort past the cap, even when plenty exist further down.
	 * Cached (see clear_cache()), so an uncapped fetch costs nothing per
	 * request — it's the same trade a bounded per-request query would make,
	 * just paid once per cache generation instead of on every page load.
	 *
	 * @param int[] $region_terms Region term IDs, or [] for nationwide.
	 * @return array<int, array{id: int, lang: string}>
	 */
	private function upcoming_events_pool( array $region_terms ): array {
		sort( $region_terms );
		$cache_key = 'vk_pe_nearby_' . $this->cache_generation() . '_' . ( $region_terms ? md5( implode( ',', $region_terms ) ) : 'all' );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$args = [
			'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => \Vandrekalender\Event::META_DATE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- cached, see upcoming_events_pool().
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- cached, see upcoming_events_pool().
				[
					'key'     => \Vandrekalender\Event::META_DATE,
					'value'   => current_time( 'Y-m-d' ),
					'compare' => '>=',
					'type'    => 'DATE',
				],
			],
		];

		if ( $region_terms ) {
			$args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- cached, see upcoming_events_pool().
				[
					'taxonomy' => \Vandrekalender\Event::TAX_REGION,
					'field'    => 'term_id',
					'terms'    => $region_terms,
				],
			];
		}

		$result = [];
		foreach ( get_posts( $args ) as $id ) {
			$result[] = [
				'id'   => (int) $id,
				'lang' => $this->post_language( (int) $id ),
			];
		}

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}

	/**
	 * Filter a cached candidate pool down to up to NEARBY_COUNT matches in
	 * $lang (or any, when $lang is empty), excluding $post_id itself.
	 *
	 * @param array<int, array{id: int, lang: string}> $pool    Candidate events.
	 * @param int                                      $post_id The event the list is being built for.
	 * @param string                                   $lang    $post_id's language, or ''.
	 * @return int[]
	 */
	private function pick_nearby( array $pool, int $post_id, string $lang ): array {
		$matched = [];

		foreach ( $pool as $candidate ) {
			if ( $candidate['id'] === $post_id ) {
				continue;
			}

			if ( '' === $lang || $lang === $candidate['lang'] ) {
				$matched[] = $candidate['id'];

				if ( count( $matched ) >= self::NEARBY_COUNT ) {
					break;
				}
			}
		}

		return $matched;
	}
}
