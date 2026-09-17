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

	const NEARBY_COUNT = 5;
	const NEARBY_MIN   = 3;

	/**
	 * Constructor — hooks the redirect, the join-button gate, and the notice.
	 */
	public function __construct() {
		add_action( 'template_redirect', [ $this, 'maybe_redirect_to_next_occurrence' ] );
		add_filter( 'vandrekalender_event_is_joinable', [ $this, 'disable_join_for_past_events' ], 10, 2 );
		add_filter( 'the_content', [ $this, 'append_past_event_notice' ] );
	}

	/**
	 * On a past recurring event with a later occurrence, 301-redirect there.
	 * One-off events, and recurring events with no next occurrence, fall
	 * through and render normally (see append_past_event_notice()).
	 *
	 * @return void
	 */
	public function maybe_redirect_to_next_occurrence(): void {
		if ( ! is_singular( \Vandrekalender\Event::CUSTOMPOSTTYPE ) ) {
			return;
		}

		$post_id = get_queried_object_id();

		if ( ! $post_id || ! $this->is_past( $post_id ) ) {
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
	 * Find the published event with the same series key and the earliest
	 * event_date on or after today, in the same Polylang language.
	 *
	 * @param string $series_key Series key to match.
	 * @param int    $exclude_id Post ID to exclude (the current past event).
	 * @return int Post ID of the next occurrence, or 0 if none.
	 */
	private function find_next_occurrence( string $series_key, int $exclude_id ): int {
		$args = [
			'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
			'post_status'    => 'publish',
			// A generous candidate pool, not just 1: the earliest-dated
			// occurrence isn't necessarily the first one in this event's
			// language, and the 'lang' query arg can't be trusted to filter
			// this for us (see post_language()'s docblock).
			'posts_per_page' => 20,
			'fields'         => 'ids',
			'post__not_in'   => [ $exclude_id ],
			'orderby'        => [ 'date_clause' => 'ASC' ],
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one row per singular page load, not a listing query.
				[
					'key'   => \Vandrekalender\Event::META_SERIES_KEY,
					'value' => $series_key,
				],
				'date_clause' => [
					'key'     => \Vandrekalender\Event::META_DATE,
					'value'   => current_time( 'Y-m-d' ),
					'compare' => '>=',
					'type'    => 'DATE',
				],
			],
		];

		$lang = $this->post_language( $exclude_id );

		foreach ( get_posts( $args ) as $candidate_id ) {
			if ( '' === $lang || $lang === $this->post_language( (int) $candidate_id ) ) {
				return (int) $candidate_id;
			}
		}

		return 0;
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
		// A generous candidate pool, not just NEARBY_COUNT: the 'lang' query
		// arg can't be trusted to filter this for us (see post_language()'s
		// docblock), so language filtering happens in PHP below and needs
		// more rows to pick NEARBY_COUNT matches from.
		$pool_size = self::NEARBY_COUNT * 6;

		$base_args = [
			'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $pool_size,
			'fields'         => 'ids',
			'post__not_in'   => [ $post_id ],
			'meta_key'       => \Vandrekalender\Event::META_DATE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one row per singular page load, not a listing query.
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one row per singular page load, not a listing query.
				[
					'key'     => \Vandrekalender\Event::META_DATE,
					'value'   => current_time( 'Y-m-d' ),
					'compare' => '>=',
					'type'    => 'DATE',
				],
			],
		];

		$lang = $this->post_language( $post_id );

		$region_terms = wp_get_post_terms( $post_id, \Vandrekalender\Event::TAX_REGION, [ 'fields' => 'ids' ] );
		$region_terms = is_wp_error( $region_terms ) ? [] : $region_terms;

		if ( $region_terms ) {
			$regional_args              = $base_args;
			$regional_args['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- one row per singular page load, not a listing query.
				[
					'taxonomy' => \Vandrekalender\Event::TAX_REGION,
					'field'    => 'term_id',
					'terms'    => $region_terms,
				],
			];
			$regional                   = $this->filter_by_language( get_posts( $regional_args ), $lang );

			if ( count( $regional ) >= self::NEARBY_MIN ) {
				return array_slice( $regional, 0, self::NEARBY_COUNT );
			}
		}

		return array_slice( $this->filter_by_language( get_posts( $base_args ), $lang ), 0, self::NEARBY_COUNT );
	}

	/**
	 * Keep only the IDs matching $lang (all of them when $lang is empty —
	 * no language on the reference post means no restriction).
	 *
	 * @param array<int|string> $post_ids Candidate post IDs.
	 * @param string            $lang     Language slug to match, or ''.
	 * @return int[]
	 */
	private function filter_by_language( array $post_ids, string $lang ): array {
		if ( '' === $lang ) {
			return array_map( 'intval', $post_ids );
		}

		$matched = [];

		foreach ( $post_ids as $post_id ) {
			if ( $lang === $this->post_language( (int) $post_id ) ) {
				$matched[] = (int) $post_id;
			}
		}

		return $matched;
	}

	/**
	 * The Polylang language slug for a post, or '' when Polylang is inactive
	 * or the post has none.
	 *
	 * Used to filter candidates in PHP after the fact, rather than passing
	 * 'lang' as a get_posts()/WP_Query arg: the `language` taxonomy's
	 * query_var is registered but 'public' => false, and in this environment
	 * that WP_Query arg silently applies no restriction at all (confirmed by
	 * inspecting the generated SQL) — so a redirect built on it could send a
	 * Danish past page to an English next occurrence with no error anywhere.
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
