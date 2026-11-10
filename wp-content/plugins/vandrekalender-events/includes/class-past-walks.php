<?php

defined( 'ABSPATH' ) || exit;

/**
 * The past walks page "Tidligere vandreture" at /tidligere-ture/ (#176):
 * walks of the last RETENTION_MONTHS months whose date has passed, newest
 * first, grouped by month, PER_PAGE more with each "Vis flere ture".
 * Design and spec: docs/design/passed-events-page/.
 *
 * The page is a normal WordPress page (page-without-title) built like the
 * front page: the Event Filters block (set to Past) and below it the Past
 * Walks block, which renders the results (decision 0015). The filters are
 * the calendar's, in the URL: region, organiser, length, date_from, date_to,
 * is_free. This class lists the walks, finds the page for the links to it,
 * and sets noindex on filtered and "Vis flere" URLs.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Past_Walks {

	const SLUG       = 'tidligere-ture';
	const BLOCK      = 'vandrekalender/past-walks';
	const PAGE_PARAM = 'side';
	const PER_PAGE   = 10;

	/**
	 * The calendar's filter params (Vandrekalender_Event_Rest_Api::filters_from_query()).
	 */
	const FILTER_KEYS = [ 'region', 'organiser', 'length', 'date_from', 'date_to', 'is_free' ];

	/**
	 * How long walks are listed (and, with #37, kept).
	 */
	const RETENTION_MONTHS = 12;

	/**
	 * Matching walk IDs per filter set, for this request.
	 *
	 * @var array<string,int[]>
	 */
	private static $ids = [];

	/**
	 * Constructor — hooks the robots tags.
	 */
	public function __construct() {
		add_filter( 'wp_robots', [ $this, 'filter_robots' ] );
		add_filter( 'rank_math/frontend/robots', [ $this, 'filter_rank_math_robots' ] );
	}

	/**
	 * The published page at /tidligere-ture/, with $filters as query params
	 * and, from 2, the number of "Vis flere" steps shown. '' when there is no
	 * published page, so callers leave the link out instead of linking to a
	 * 404.
	 *
	 * @param int                  $page    How many steps of PER_PAGE to show.
	 * @param array<string,string> $filters Filters (FILTER_KEYS); empty ones are left out.
	 * @return string
	 */
	public static function url( int $page = 1, array $filters = [] ): string {
		// On the page itself, its own URL, whatever its slug.
		$post = self::is_page() ? get_queried_object() : get_page_by_path( self::SLUG );
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
			return '';
		}

		$args = array_filter( array_intersect_key( $filters, array_flip( self::FILTER_KEYS ) ), 'strlen' );
		if ( $page > 1 ) {
			$args[ self::PAGE_PARAM ] = (string) $page;
		}

		$url = (string) get_permalink( $post );
		return $args ? add_query_arg( array_map( 'rawurlencode', $args ), $url ) : $url;
	}

	/**
	 * Whether the current request is a page with the Past Walks block.
	 *
	 * @return bool
	 */
	public static function is_page(): bool {
		return is_page() && has_block( self::BLOCK, get_queried_object() );
	}

	/**
	 * How many steps of PER_PAGE the current request shows (?side=N).
	 *
	 * @return int
	 */
	public static function current_page(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page number.
		return isset( $_GET[ self::PAGE_PARAM ] ) ? max( 1, (int) $_GET[ self::PAGE_PARAM ] ) : 1;
	}

	/**
	 * The dates the page lists: from RETENTION_MONTHS months before $today
	 * to yesterday.
	 *
	 * @param string $today Today as Y-m-d.
	 * @return array{from: string, to: string} Y-m-d, both inclusive.
	 */
	public static function window( string $today ): array {
		$day = new DateTimeImmutable( $today );

		return [
			'from' => $day->modify( '-' . self::RETENTION_MONTHS . ' months' )->format( 'Y-m-d' ),
			'to'   => $day->modify( '-1 day' )->format( 'Y-m-d' ),
		];
	}

	/**
	 * Whether the page lists a walk on $date (Y-m-d): inside window().
	 *
	 * @param string $date  Event date as Y-m-d.
	 * @param string $today Today as Y-m-d.
	 * @return bool
	 */
	public static function lists_date( string $date, string $today ): bool {
		$window = self::window( $today );
		return '' !== $date && $date >= $window['from'] && $date <= $window['to'];
	}

	/**
	 * The visitor's filters from the query string: the calendar's params.
	 *
	 * @return array<string,string> Only the set ones, sanitised.
	 */
	public static function filters_from_request(): array {
		return Vandrekalender_Event_Rest_Api::filters_from_query();
	}

	/**
	 * Whether any filter is set: such a URL is noindex.
	 *
	 * @param array<string,string> $filters Filters (filters_from_request()).
	 * @return bool
	 */
	public static function is_filtered( array $filters ): bool {
		return (bool) array_filter( array_intersect_key( $filters, array_flip( self::FILTER_KEYS ) ), 'strlen' );
	}

	/**
	 * The dates to list: the visitor's date_from / date_to, kept inside
	 * window(). from after to means nothing matches.
	 *
	 * @param array<string,string> $filters Filters (filters_from_request()).
	 * @param string               $today   Today as Y-m-d.
	 * @return array{from: string, to: string} Y-m-d, both inclusive.
	 */
	public static function date_range( array $filters, string $today ): array {
		$window = self::window( $today );
		$from   = self::valid_date( $filters['date_from'] ?? '' );
		$to     = self::valid_date( $filters['date_to'] ?? '' );

		return [
			'from' => '' !== $from && $from > $window['from'] ? $from : $window['from'],
			'to'   => '' !== $to && $to < $window['to'] ? $to : $window['to'],
		];
	}

	/**
	 * $date when it is a real Y-m-d date, otherwise ''.
	 *
	 * @param string $date Date from the URL.
	 * @return string
	 */
	private static function valid_date( string $date ): string {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		return $parsed && $parsed->format( 'Y-m-d' ) === $date ? $date : '';
	}

	/**
	 * How many of $total walks are shown after $page steps of PER_PAGE.
	 *
	 * @param int $total Number of matching walks.
	 * @param int $page  Steps shown (?side=N, at least 1).
	 * @return int
	 */
	public static function shown_count( int $total, int $page ): int {
		return min( $total, max( 1, $page ) * self::PER_PAGE );
	}

	/**
	 * Walks per month, in the order given.
	 *
	 * @param array<int,string> $dates Event date (Y-m-d) by post ID.
	 * @return array<string,int[]> Post IDs by Y-m.
	 */
	public static function group_by_month( array $dates ): array {
		$groups = [];
		foreach ( $dates as $id => $date ) {
			$groups[ substr( $date, 0, 7 ) ][] = (int) $id;
		}
		return $groups;
	}

	/**
	 * Published walks that match $filters, newest first, only dates inside
	 * the window (date_range()).
	 *
	 * @param array<string,string> $filters Filters (filters_from_request()).
	 * @return int[]
	 */
	public static function ids( array $filters ): array {
		// Empty values count as unset, so no filters and empty filters share
		// one query.
		$filters = array_filter( array_intersect_key( $filters, array_flip( self::FILTER_KEYS ) ), 'strlen' );
		ksort( $filters );
		$key = md5( (string) wp_json_encode( $filters ) );
		if ( isset( self::$ids[ $key ] ) ) {
			return self::$ids[ $key ];
		}

		$range = self::date_range( $filters, current_time( 'Y-m-d' ) );
		if ( $range['from'] > $range['to'] ) {
			self::$ids[ $key ] = [];
			return [];
		}

		$args = array_merge(
			Vandrekalender_Event_Rest_Api::build_query_args(
				array_merge(
					$filters,
					[
						'date_from' => $range['from'],
						'date_to'   => $range['to'],
					]
				)
			),
			[
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => [
					'meta_value' => 'DESC',
					'title'      => 'ASC',
				],
				'meta_key'       => \Vandrekalender\Event::META_DATE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one ID query per request.
			]
		);

		$ids = array_map( 'intval', get_posts( $args ) );

		// Price lives inside the routes JSON, so the free filter runs in PHP,
		// as in the calendar.
		if ( isset( $filters['is_free'] ) && $ids ) {
			update_meta_cache( 'post', $ids );
			$want_free = rest_sanitize_boolean( $filters['is_free'] );
			$ids       = array_values( array_filter( $ids, static fn( $id ) => Vandrekalender_Event_Rest_Api::is_event_free( $id ) === $want_free ) );
		}

		self::$ids[ $key ] = $ids;
		return $ids;
	}

	/**
	 * The terms of $taxonomy (region, organiser) that have a walk in the
	 * window, by name, so a filter dropdown never offers one that shows
	 * nothing.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return \WP_Term[]
	 */
	public static function window_terms( string $taxonomy ): array {
		$ids = self::ids( [] );
		if ( ! $ids ) {
			return [];
		}

		$terms = wp_get_object_terms(
			$ids,
			$taxonomy,
			[
				'orderby' => 'name',
				'order'   => 'ASC',
			]
		);
		return is_wp_error( $terms ) ? [] : $terms;
	}

	/**
	 * The event date of each walk, in the order of $ids.
	 *
	 * @param int[] $ids Post IDs.
	 * @return array<int,string> Y-m-d by post ID.
	 */
	public static function dates( array $ids ): array {
		if ( ! $ids ) {
			return [];
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one query for every walk's date instead of loading all their meta.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is only %d placeholders.
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN ($placeholders)",
				array_merge( [ \Vandrekalender\Event::META_DATE ], $ids )
			)
		);

		$by_id = [];
		foreach ( $rows as $row ) {
			$by_id[ (int) $row->post_id ] = (string) $row->meta_value;
		}

		$dates = [];
		foreach ( $ids as $id ) {
			$dates[ $id ] = $by_id[ $id ] ?? '';
		}
		return $dates;
	}

	/**
	 * Whether the current request is the page with filters or ?side=N: those
	 * repeat what the plain page lists, so only the plain page is indexed
	 * (its canonical is the page itself, without query params).
	 *
	 * @return bool
	 */
	private static function is_noindex_request(): bool {
		return self::is_page() && ( self::is_filtered( self::filters_from_request() ) || self::current_page() > 1 );
	}

	/**
	 * Filtered and "Vis flere" URLs are noindex, follow (core robots tag).
	 *
	 * @param array<string,bool|string> $robots Robots directives.
	 * @return array<string,bool|string>
	 */
	public function filter_robots( array $robots ): array {
		if ( self::is_noindex_request() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/**
	 * Filtered and "Vis flere" URLs are noindex, follow (Rank Math's robots
	 * tag).
	 *
	 * @param array<string,string> $robots Robots directives.
	 * @return array<string,string>
	 */
	public function filter_rank_math_robots( $robots ) {
		if ( self::is_noindex_request() ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	}
}
