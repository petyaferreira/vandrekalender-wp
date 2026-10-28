<?php

defined( 'ABSPATH' ) || exit;

/**
 * The past walks page "Tidligere vandreture" at /tidligere-ture/ (#176):
 * walks of the last RETENTION_MONTHS months whose date has passed, newest
 * first, grouped by month, 30 per page (/tidligere-ture/side/2/).
 * Design and spec: docs/design/passed-events-page/.
 *
 * Defined in code, not as a page in the database, so staging and production
 * need no setup: rewrite rules set the query vars, the main query is skipped
 * (the Past Walks block runs its own), and the theme's past-walks.html
 * template renders the page.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Past_Walks {

	const SLUG        = 'tidligere-ture';
	const QUERY_VAR   = 'vk_past_walks';
	const PAGE_VAR    = 'vk_side';
	const PAGE_SLUG   = 'side';
	const TEMPLATE    = 'past-walks';
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
	 * Constructor — hooks the route, the template, the head tags and the
	 * front page link.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_filter( 'posts_pre_query', [ $this, 'skip_main_query' ], 10, 2 );
		add_action( 'template_redirect', [ $this, 'maybe_404' ] );
		add_filter( 'home_template_hierarchy', [ $this, 'use_template' ] );
		add_filter( 'document_title_parts', [ $this, 'filter_title_parts' ] );
		add_filter( 'document_title_separator', [ $this, 'filter_title_separator' ] );
		add_filter( 'wp_robots', [ $this, 'filter_robots' ] );
		add_filter( 'rank_math/frontend/title', [ $this, 'filter_rank_math_title' ] );
		add_filter( 'rank_math/frontend/description', [ $this, 'filter_rank_math_description' ] );
		add_filter( 'rank_math/frontend/canonical', [ $this, 'filter_rank_math_canonical' ] );
		add_filter( 'rank_math/frontend/robots', [ $this, 'filter_rank_math_robots' ] );
		add_filter( 'render_block_vandrekalender/tabs', [ $this, 'append_front_page_link' ] );
	}

	/**
	 * A page of the list, with the visitor's filters as query params.
	 *
	 * @param int                  $page    Page number.
	 * @param array<string,string> $filters Filters (q, region, organiser); empty ones are left out.
	 * @return string
	 */
	public static function url( int $page = 1, array $filters = [] ): string {
		$path = $page > 1 ? self::SLUG . '/' . self::PAGE_SLUG . '/' . $page : self::SLUG;
		$url  = home_url( user_trailingslashit( $path ) );

		$filters = array_filter( array_intersect_key( $filters, array_flip( self::FILTER_KEYS ) ), 'strlen' );

		return $filters ? add_query_arg( array_map( 'rawurlencode', $filters ), $url ) : $url;
	}

	/**
	 * Whether the current request is the page.
	 *
	 * @return bool
	 */
	public static function is_page(): bool {
		return (bool) get_query_var( self::QUERY_VAR );
	}

	/**
	 * The page number of the current request.
	 *
	 * @return int
	 */
	public static function current_page(): int {
		return max( 1, (int) get_query_var( self::PAGE_VAR ) );
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
	 * Route /tidligere-ture/ and /tidligere-ture/side/N/ to the page.
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		add_rewrite_rule( '^' . self::SLUG . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
		add_rewrite_rule( '^' . self::SLUG . '/' . self::PAGE_SLUG . '/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=1&' . self::PAGE_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Make the query vars public, so the rewrite rules can set them.
	 *
	 * @param string[] $vars Public query vars.
	 * @return string[]
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::PAGE_VAR;
		return $vars;
	}

	/**
	 * The page's main query would list blog posts; the Past Walks block
	 * queries the walks itself, so return no posts without touching the
	 * database.
	 *
	 * @param array<int,mixed>|null $posts Posts to return instead of querying, or null.
	 * @param \WP_Query             $query The query.
	 * @return array<int,mixed>|null
	 */
	public function skip_main_query( ?array $posts, \WP_Query $query ): ?array {
		if ( $query->is_main_query() && $query->get( self::QUERY_VAR ) ) {
			return [];
		}
		return $posts;
	}

	/**
	 * /side/1/ redirects to the clean URL; a page number past the last page
	 * is a 404.
	 *
	 * @return void
	 */
	public function maybe_404(): void {
		if ( ! self::is_page() || '' === (string) get_query_var( self::PAGE_VAR ) ) {
			return;
		}

		$page    = (int) get_query_var( self::PAGE_VAR );
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
	 * WordPress treats the page as the blog home (no other query flag is
	 * set), so put the page's own template first.
	 *
	 * @param string[] $templates Template candidates, best first.
	 * @return string[]
	 */
	public function use_template( array $templates ): array {
		if ( self::is_page() ) {
			array_unshift( $templates, self::TEMPLATE . '.php' );
		}
		return $templates;
	}

	/**
	 * The page's title, with the page number from page 2.
	 *
	 * @return string
	 */
	private static function title(): string {
		$title = __( 'Past walks in Denmark', 'vandrekalender-events' );
		$page  = self::current_page();

		/* translators: 1: page title, 2: page number. */
		return $page > 1 ? sprintf( __( '%1$s, page %2$d', 'vandrekalender-events' ), $title, $page ) : $title;
	}

	/**
	 * The browser title without Rank Math: "Tidligere vandreture i Danmark
	 * | <site>".
	 *
	 * @param array<string,string> $parts Title parts.
	 * @return array<string,string>
	 */
	public function filter_title_parts( array $parts ): array {
		if ( self::is_page() ) {
			$parts['title'] = self::title();
		}
		return $parts;
	}

	/**
	 * The separator the spec asks for ("|").
	 *
	 * @param string $separator Title separator.
	 * @return string
	 */
	public function filter_title_separator( string $separator ): string {
		return self::is_page() ? '|' : $separator;
	}

	/**
	 * The browser title with Rank Math, which has no title of its own for
	 * this page.
	 *
	 * @param string $title Rank Math's title.
	 * @return string
	 */
	public function filter_rank_math_title( $title ) {
		return self::is_page() ? self::title() . ' | ' . get_bloginfo( 'name' ) : $title;
	}

	/**
	 * The meta description, through Rank Math.
	 *
	 * @param string $description Rank Math's description.
	 * @return string
	 */
	public function filter_rank_math_description( $description ) {
		if ( ! self::is_page() ) {
			return $description;
		}
		return sprintf(
			/* translators: %d: number of months the page lists. */
			_n(
				'See walks that have already taken place in the last %d month: date, place, length and organiser.',
				'See walks that have already taken place in the last %d months: date, place, length and organiser.',
				self::RETENTION_MONTHS,
				'vandrekalender-events'
			),
			self::RETENTION_MONTHS
		);
	}

	/**
	 * The canonical URL, through Rank Math, which has none for this page:
	 * the page without filters.
	 *
	 * @param string $canonical Rank Math's canonical URL.
	 * @return string
	 */
	public function filter_rank_math_canonical( $canonical ) {
		return self::is_page() ? self::url( self::current_page() ) : $canonical;
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
	 * "Se tidligere vandreture" under the calendar, list and map tabs on the
	 * front page. Added here rather than in the page's content, so no site
	 * needs the page edited by hand.
	 *
	 * @param string $block_content The rendered tabs block.
	 * @return string
	 */
	public function append_front_page_link( string $block_content ): string {
		if ( ! is_front_page() ) {
			return $block_content;
		}
		return $block_content . sprintf(
			'<p class="vk-past-walks-link"><a href="%s">%s <span aria-hidden="true">›</span></a></p>',
			esc_url( self::url() ),
			esc_html__( 'See past walks', 'vandrekalender-events' )
		);
	}
}
