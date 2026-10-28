<?php
/**
 * Organiser Class
 *
 * Registers the organiser post type: one page per organisation at
 * /arrangoerer/<slug>/, linked to its event_organiser term (decision 0008).
 * The taxonomy stays what events, filters and counts use; the post only
 * holds the page.
 *
 * @package Vandrekalender
 */

namespace Vandrekalender;

/**
 * Organiser class to manage the organiser pages.
 */
class Organiser {
	/**
	 * Singleton instance.
	 *
	 * @var Organiser|null
	 */
	private static $instance = null;

	public const CUSTOMPOSTTYPE = 'organiser';

	// URL base of the pages and of the overview archive.
	public const SLUG = 'arrangoerer';

	// Meta keys.
	public const META_TERM     = 'organiser_term';
	public const META_OFFICIAL = 'organiser_official';

	// The theme's section patterns a new organiser page starts from, top to
	// bottom (docs/history/organisers-plan.md, step 5).
	public const PATTERNS = [
		'vandrekalender-theme/organiser-hero',
		'vandrekalender-theme/organiser-walks',
		'vandrekalender-theme/organiser-about',
		'vandrekalender-theme/organiser-regions-past',
	];

	/**
	 * Published organiser post ID per event_organiser term ID, loaded once
	 * per request by published_post_for_term().
	 *
	 * @var array<int,int>|null
	 */
	private $published_posts = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Organiser
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — registers all hooks.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'init', [ $this, 'register_meta' ] );
		add_filter( 'rest_pre_insert_' . self::CUSTOMPOSTTYPE, [ $this, 'validate_term' ], 10, 2 );
		add_filter( 'term_link', [ $this, 'filter_term_link' ], 10, 3 );
		// The REST API writes meta after the post, so a changed term clears
		// the lookup too, not only the post save.
		add_action( 'save_post_' . self::CUSTOMPOSTTYPE, [ $this, 'forget_published_posts' ] );
		add_action( 'deleted_post', [ $this, 'forget_published_posts' ] );
		add_action( 'added_post_meta', [ $this, 'forget_published_posts_on_term_change' ], 10, 3 );
		add_action( 'updated_post_meta', [ $this, 'forget_published_posts_on_term_change' ], 10, 3 );
		add_action( 'deleted_post_meta', [ $this, 'forget_published_posts_on_term_change' ], 10, 3 );
		add_action( 'template_redirect', [ $this, 'maybe_redirect_to_calendar' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
		add_filter( 'post_type_archive_title', [ $this, 'filter_archive_title' ], 10, 2 );
	}

	/**
	 * Register the organiser post type. Only administrators hold its
	 * capabilities (Vandrekalender_Roles); #86 opens a page to its
	 * organisation's members.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		register_post_type(
			self::CUSTOMPOSTTYPE,
			[
				'labels'          => [
					'name'               => __( 'Organiser pages', 'vandrekalender-events' ),
					'singular_name'      => __( 'Organiser page', 'vandrekalender-events' ),
					'menu_name'          => __( 'Organiser pages', 'vandrekalender-events' ),
					'add_new_item'       => __( 'Add New Organiser page', 'vandrekalender-events' ),
					'edit_item'          => __( 'Edit Organiser page', 'vandrekalender-events' ),
					'view_item'          => __( 'View Organiser page', 'vandrekalender-events' ),
					'view_items'         => __( 'View Organiser pages', 'vandrekalender-events' ),
					'all_items'          => __( 'All Organiser pages', 'vandrekalender-events' ),
					'search_items'       => __( 'Search Organiser pages', 'vandrekalender-events' ),
					'not_found'          => __( 'No organiser pages found.', 'vandrekalender-events' ),
					'not_found_in_trash' => __( 'No organiser pages found in trash.', 'vandrekalender-events' ),
					// The overview's title and its breadcrumb item.
					'archives'           => __( 'Organisers', 'vandrekalender-events' ),
				],
				// 'custom-fields' lets the editor save the post meta below.
				'supports'        => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ],
				'capability_type' => [ 'organiser', 'organisers' ],
				'map_meta_cap'    => true,
				'public'          => true,
				'show_in_rest'    => true,
				'has_archive'     => self::SLUG,
				'rewrite'         => [
					'slug'       => self::SLUG,
					'with_front' => false,
				],
				// Without this WordPress reads ?organiser=<slug> as a request
				// for an organiser post, and the calendar fallback in
				// filter_term_link() would 404.
				'query_var'       => false,
				'menu_icon'       => 'dashicons-groups',
				'menu_position'   => 6,
				// A new page starts from the section patterns; each section
				// locks itself to its content (templateLock "contentOnly").
				'template'        => array_map(
					static function ( string $slug ): array {
						return [ 'core/pattern', [ 'slug' => $slug ] ];
					},
					self::PATTERNS
				),
			]
		);
	}

	/**
	 * Register the organiser post meta. Only administrators set either one,
	 * also once organisations edit their own page (#86).
	 *
	 * @return void
	 */
	public function register_meta(): void {
		$admin_only = static function (): bool {
			return current_user_can( 'manage_options' );
		};

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_TERM,
			[
				'type'          => 'integer',
				'single'        => true,
				'show_in_rest'  => true,
				'default'       => 0,
				'auth_callback' => $admin_only,
			]
		);

		register_post_meta(
			self::CUSTOMPOSTTYPE,
			self::META_OFFICIAL,
			[
				'type'          => 'boolean',
				'single'        => true,
				'show_in_rest'  => true,
				'default'       => false,
				'auth_callback' => $admin_only,
			]
		);
	}

	/**
	 * Refuse to save an organiser post linked to a term that is not an
	 * event_organiser term, or that another organiser post already has (one
	 * page per organiser), and to publish one without a term: the page would
	 * have no walks. The editor shows the error and keeps the post unsaved.
	 *
	 * @param \stdClass        $prepared_post Post about to be saved.
	 * @param \WP_REST_Request $request       The request.
	 * @return \stdClass|\WP_Error
	 */
	public function validate_term( $prepared_post, \WP_REST_Request $request ) {
		$post_id = (int) ( $prepared_post->ID ?? 0 );
		$meta    = $request->get_param( 'meta' );

		// The editor only sends meta that changed, so fall back to the saved value.
		$term_id = is_array( $meta ) && isset( $meta[ self::META_TERM ] )
			? (int) $meta[ self::META_TERM ]
			: ( $post_id ? (int) get_post_meta( $post_id, self::META_TERM, true ) : 0 );
		$status  = $prepared_post->post_status ?? ( $post_id ? get_post_status( $post_id ) : 'draft' );

		if ( 0 === $term_id ) {
			if ( self::needs_term( (string) $status ) ) {
				return new \WP_Error(
					'vandrekalender_organiser_term_missing',
					__( 'Choose the organisation in the Organiser panel before publishing.', 'vandrekalender-events' ),
					[ 'status' => 400 ]
				);
			}
			return $prepared_post;
		}

		if ( ! get_term( $term_id, Event::TAX_ORGANISER ) instanceof \WP_Term ) {
			return new \WP_Error(
				'vandrekalender_organiser_term_invalid',
				__( 'This organiser does not exist.', 'vandrekalender-events' ),
				[ 'status' => 400 ]
			);
		}

		$owner = self::post_with_term( $term_id, $post_id, $this->linked_terms() );
		if ( $owner ) {
			return new \WP_Error(
				'vandrekalender_organiser_term_taken',
				sprintf(
					/* translators: %s: title of the organiser page that already has the organiser */
					__( 'This organiser already has a page: "%s".', 'vandrekalender-events' ),
					get_the_title( $owner )
				),
				[ 'status' => 409 ]
			);
		}

		return $prepared_post;
	}

	/**
	 * Whether a post in this status must have a term: every status a visitor
	 * can see, or will see without another save.
	 *
	 * @param string $status Post status.
	 * @return bool
	 */
	public static function needs_term( string $status ): bool {
		return in_array( $status, [ 'publish', 'future', 'private' ], true );
	}

	/**
	 * The organiser post, other than $post_id, that is linked to $term_id;
	 * 0 when none is.
	 *
	 * @param int            $term_id Term the post is being linked to.
	 * @param int            $post_id Post being saved, 0 for a new one.
	 * @param array<int,int> $links   Term ID per organiser post ID.
	 * @return int
	 */
	public static function post_with_term( int $term_id, int $post_id, array $links ): int {
		foreach ( $links as $other_id => $other_term_id ) {
			if ( $other_id !== $post_id && $other_term_id === $term_id ) {
				return $other_id;
			}
		}
		return 0;
	}

	/**
	 * Term ID per organiser post, in every status but trash and auto-draft:
	 * a draft holds its term too, so two pages are never written for one
	 * organiser.
	 *
	 * @param string|string[] $status Post statuses to include.
	 * @return array<int,int>
	 */
	private function linked_terms( $status = [ 'publish', 'future', 'draft', 'pending', 'private' ] ): array {
		$post_ids = get_posts(
			[
				'post_type'      => self::CUSTOMPOSTTYPE,
				'post_status'    => $status,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => self::META_TERM, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- a few organiser posts.
			]
		);

		$links = [];
		foreach ( $post_ids as $post_id ) {
			$links[ (int) $post_id ] = (int) get_post_meta( (int) $post_id, self::META_TERM, true );
		}
		return $links;
	}

	/**
	 * The published organiser post per event_organiser term ID.
	 *
	 * @return array<int,int>
	 */
	public function published_posts(): array {
		if ( null === $this->published_posts ) {
			$this->published_posts = array_flip( array_filter( $this->linked_terms( 'publish' ) ) );
		}
		return $this->published_posts;
	}

	/**
	 * The published organiser post linked to a term, 0 when there is none.
	 *
	 * @param int $term_id event_organiser term ID.
	 * @return int
	 */
	public function published_post_for_term( int $term_id ): int {
		return $this->published_posts()[ $term_id ] ?? 0;
	}

	/**
	 * Drop the lookup of published_post_for_term(), so a term link built
	 * later in the same request sees a page published, unpublished or
	 * relinked in it.
	 *
	 * @return void
	 */
	public function forget_published_posts(): void {
		$this->published_posts = null;
	}

	/**
	 * Drop the lookup when an organiser post's term changes.
	 *
	 * @param int|int[] $_meta_id  Meta ID(s).
	 * @param int       $_post_id  Post ID.
	 * @param string    $meta_key  Meta key.
	 * @return void
	 */
	public function forget_published_posts_on_term_change( $_meta_id, $_post_id, $meta_key ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- required by action signature.
		if ( self::META_TERM === $meta_key ) {
			$this->forget_published_posts();
		}
	}

	/**
	 * An event_organiser term links to its organiser page when one is
	 * published, else to the calendar filtered by the organiser. The
	 * taxonomy has no archive of its own.
	 *
	 * @param string   $url      Term link.
	 * @param \WP_Term $term     Term.
	 * @param string   $taxonomy Taxonomy slug.
	 * @return string
	 */
	public function filter_term_link( string $url, \WP_Term $term, string $taxonomy ): string {
		if ( Event::TAX_ORGANISER !== $taxonomy ) {
			return $url;
		}

		$post_id = $this->published_post_for_term( $term->term_id );

		return $post_id ? (string) get_permalink( $post_id ) : self::calendar_url( $term->slug, home_url( '/' ) );
	}

	/**
	 * The event_organiser term an organiser post is linked to, null when it
	 * has none (a draft not linked yet) or the term was deleted.
	 *
	 * @param int $post_id Organiser post ID.
	 * @return \WP_Term|null
	 */
	public static function term_for_post( int $post_id ): ?\WP_Term {
		$term_id = (int) get_post_meta( $post_id, self::META_TERM, true );
		$term    = $term_id ? get_term( $term_id, Event::TAX_ORGANISER ) : null;

		return $term instanceof \WP_Term ? $term : null;
	}

	/**
	 * The overview's title is "Arrangører" (the archives label, as in the
	 * breadcrumb), not the admin's "Arrangørsider" that WordPress takes
	 * from the post type's name.
	 *
	 * @param string $title     Archive title.
	 * @param string $post_type Post type of the archive.
	 * @return string
	 */
	public function filter_archive_title( string $title, string $post_type ): string {
		if ( self::CUSTOMPOSTTYPE !== $post_type ) {
			return $title;
		}

		$object = get_post_type_object( self::CUSTOMPOSTTYPE );

		return $object ? (string) $object->labels->archives : $title;
	}

	/**
	 * The front page filtered by one organiser: the filter bar reads
	 * ?organiser=<slug>.
	 *
	 * @param string $slug event_organiser term slug.
	 * @param string $home Front page URL.
	 * @return string
	 */
	public static function calendar_url( string $slug, string $home ): string {
		return $home . ( str_contains( $home, '?' ) ? '&' : '?' ) . 'organiser=' . rawurlencode( $slug );
	}

	/**
	 * The organiser slug in a request path /arrangoerer/<slug>/, '' for any
	 * other path, the overview included.
	 *
	 * @param string $request Request path without the leading slash ($wp->request).
	 * @return string
	 */
	public static function slug_from_request( string $request ): string {
		return preg_match( '#^' . self::SLUG . '/([^/]+)/?$#', $request, $matches ) ? $matches[1] : '';
	}

	/**
	 * /arrangoerer/<slug>/ of an organiser without a published page goes to
	 * the calendar filtered by it, so the term archive URLs of before 5a
	 * keep working. A 302: the URL becomes a page once one is published.
	 *
	 * @return void
	 */
	public function maybe_redirect_to_calendar(): void {
		if ( ! is_404() ) {
			return;
		}

		global $wp;

		$slug = self::slug_from_request( (string) ( $wp->request ?? '' ) );
		if ( '' === $slug ) {
			return;
		}

		$term = get_term_by( 'slug', sanitize_title( $slug ), Event::TAX_ORGANISER );
		if ( $term instanceof \WP_Term ) {
			wp_safe_redirect( self::calendar_url( $term->slug, home_url( '/' ) ), 302 );
			exit;
		}
	}

	/**
	 * Enqueue the organiser fields panel when editing an organiser post.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		$screen     = get_current_screen();
		$asset_file = VANDREKALENDER_EVENTS_DIR . 'build/resources/organiser-fields/index.asset.php';

		if ( ! $screen || self::CUSTOMPOSTTYPE !== $screen->post_type || ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'vandrekalender-organiser-fields',
			VANDREKALENDER_EVENTS_URL . 'build/resources/organiser-fields/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations(
			'vandrekalender-organiser-fields',
			'vandrekalender-events'
		);
	}
}
