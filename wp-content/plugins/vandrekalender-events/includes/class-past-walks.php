<?php

defined( 'ABSPATH' ) || exit;

/**
 * The past walks page "Tidligere vandreture" at /tidligere-ture/ (#176):
 * walks of the last RETENTION_MONTHS months whose date has passed, newest
 * first, grouped by month, 30 per page (?side=2).
 * Design and spec: docs/design/passed-events-page/.
 *
 * The page is a normal WordPress page (default template) built from the
 * theme's "Past walks page" pattern, whose Past Walks block renders the list
 * (decision 0015). This class finds that page for the links to it, and sets
 * what the page's own settings can't: noindex on filtered URLs, the
 * canonical of page 2 and on, and a 404 past the last page.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Past_Walks {

	const SLUG        = 'tidligere-ture';
	const BLOCK       = 'vandrekalender/past-walks';
	const PAGE_PARAM  = 'side';
	const PER_PAGE    = 30;
	const FILTER_KEYS = [ 'q', 'region', 'organiser' ];

	/**
	 * How long walks are listed (and, with #37, kept). The intro text reads
	 * this too.
	 */
	const RETENTION_MONTHS = 12;

	/**
	 * Matching walk IDs per filter set, for this request.
	 *
	 * @var array<string,int[]>
	 */
	private static $ids = [];

	/**
	 * Constructor — hooks the page checks, the head tags and the front page
	 * link.
	 */
	public function __construct() {
		add_action( 'template_redirect', [ $this, 'check_page_number' ] );
		add_filter( 'wp_robots', [ $this, 'filter_robots' ] );
		add_filter( 'rank_math/frontend/robots', [ $this, 'filter_rank_math_robots' ] );
		add_filter( 'rank_math/frontend/canonical', [ $this, 'filter_rank_math_canonical' ] );
		add_filter( 'render_block_vandrekalender/tabs', [ $this, 'append_front_page_link' ] );
	}

	/**
	 * A page of the list, with the visitor's filters as query params: the
	 * published page at /tidligere-ture/. '' when there is none, so callers
	 * leave the link out instead of linking to a 404.
	 *
	 * @param int                  $page    Page number.
	 * @param array<string,string> $filters Filters (q, region, organiser); empty ones are left out.
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
	 * The page number of the current request (?side=N).
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
	 * The visitor's filters from the query string.
	 *
	 * @return array<string,string> q, region, organiser; '' when not set.
	 */
	public static function filters_from_request(): array {
		$filters = [];
		foreach ( self::FILTER_KEYS as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filters.
			$filters[ $key ] = isset( $_GET[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) ) : '';
		}
		return $filters;
	}

	/**
	 * Whether any filter is set: such a URL is noindex and canonical to the
	 * clean one.
	 *
	 * @param array<string,string> $filters Filters (filters_from_request()).
	 * @return bool
	 */
	public static function is_filtered( array $filters ): bool {
		return (bool) array_filter( array_intersect_key( $filters, array_flip( self::FILTER_KEYS ) ), 'strlen' );
	}

	/**
	 * Number of pages for $total walks; at least 1.
	 *
	 * @param int $total Number of walks.
	 * @return int
	 */
	public static function page_count( int $total ): int {
		return max( 1, (int) ceil( $total / self::PER_PAGE ) );
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
	 * Published walks in the window that match $filters, newest first.
	 * Search matches the walk's name, place or municipality.
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

		$window = self::window( current_time( 'Y-m-d' ) );
		$args   = array_merge(
			Vandrekalender_Event_Rest_Api::build_query_args(
				[
					'date_from' => $window['from'],
					'date_to'   => $window['to'],
					'region'    => $filters['region'] ?? '',
					'organiser' => $filters['organiser'] ?? '',
				]
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

		$search = $filters['q'] ?? '';
		if ( '' !== $search && $ids ) {
			$by_name = get_posts(
				array_merge(
					$args,
					[
						's'              => $search,
						'search_columns' => [ 'post_title' ],
					]
				)
			);

			$by_place                 = $args;
			$by_place['meta_query'][] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- only when searching.
				'relation' => 'OR',
				[
					'key'     => \Vandrekalender\Event::META_PLACE_NAME,
					'value'   => $search,
					'compare' => 'LIKE',
				],
				[
					'key'     => \Vandrekalender\Event::META_MUNICIPALITY,
					'value'   => $search,
					'compare' => 'LIKE',
				],
			];

			$matches = array_flip( array_map( 'intval', array_merge( $by_name, get_posts( $by_place ) ) ) );
			$ids     = array_values( array_filter( $ids, static fn( $id ) => isset( $matches[ $id ] ) ) );
		}

		self::$ids[ $key ] = $ids;
		return $ids;
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
	 * ?side=1 redirects to the page without it; a page number past the last
	 * page is a 404.
	 *
	 * @return void
	 */
	public function check_page_number(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page number.
		if ( ! isset( $_GET[ self::PAGE_PARAM ] ) || ! self::is_page() ) {
			return;
		}

		$page    = self::current_page();
		$filters = self::filters_from_request();

		if ( $page <= 1 ) {
			wp_safe_redirect( self::url( 1, $filters ), 301 );
			exit;
		}

		if ( $page <= self::page_count( count( self::ids( $filters ) ) ) ) {
			return;
		}

		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Filtered and search URLs are noindex, follow (core robots tag).
	 *
	 * @param array<string,bool|string> $robots Robots directives.
	 * @return array<string,bool|string>
	 */
	public function filter_robots( array $robots ): array {
		if ( self::is_page() && self::is_filtered( self::filters_from_request() ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/**
	 * Filtered and search URLs are noindex, follow (Rank Math's robots tag).
	 *
	 * @param array<string,string> $robots Robots directives.
	 * @return array<string,string>
	 */
	public function filter_rank_math_robots( $robots ) {
		if ( self::is_page() && self::is_filtered( self::filters_from_request() ) ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	}

	/**
	 * Page 2 and on are their own canonical (Rank Math would point them at
	 * page 1, which drops the query string); filters are never part of it.
	 *
	 * @param string $canonical Rank Math's canonical URL.
	 * @return string
	 */
	public function filter_rank_math_canonical( $canonical ) {
		if ( ! self::is_page() || self::current_page() < 2 ) {
			return $canonical;
		}
		$url = self::url( self::current_page() );
		return '' !== $url ? $url : $canonical;
	}

	/**
	 * "Se tidligere vandreture" under the calendar, list and map tabs on the
	 * front page. Added here rather than in the page's content, so no site
	 * needs the front page edited by hand.
	 *
	 * @param string $block_content The rendered tabs block.
	 * @return string
	 */
	public function append_front_page_link( string $block_content ): string {
		$url = is_front_page() ? self::url() : '';
		if ( '' === $url ) {
			return $block_content;
		}
		return $block_content . sprintf(
			'<p class="vk-past-walks-link"><a href="%s">%s <span aria-hidden="true">›</span></a></p>',
			esc_url( $url ),
			esc_html__( 'See past walks', 'vandrekalender-events' )
		);
	}
}
