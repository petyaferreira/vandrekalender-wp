<?php
/**
 * Page template migration for #255.
 *
 * Before #255 the default template (page.html) rendered no title, so the
 * editor showed no title field and every page put its own H1 in the content.
 * Since #255 page.html renders the title (it took over page-with-title.html),
 * and the old no-title default lives on as page-without-title.html.
 *
 * Which template a page uses is stored in the database (_wp_page_template), not
 * in the theme, so a deploy alone would give every existing page a second
 * title. This class moves the pages once, on the first request after the
 * deploy, with the same versioned-option pattern as Vandrekalender_Roles:
 * local, staging and production need no manual step.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

/**
 * Moves existing pages to the template that looks like the one they had.
 */
class Vandrekalender_Page_Templates {

	/**
	 * Bump this only together with a new mapping in migrated_template().
	 */
	private const VERSION = 1;

	/**
	 * Option storing the last applied version.
	 */
	private const VERSION_OPTION = 'vandrekalender_theme_page_templates_version';

	/**
	 * The stored value for "Default template".
	 */
	public const DEFAULT = 'default';

	/**
	 * The template that is the pre-#255 default: no title, hero flush.
	 */
	public const WITHOUT_TITLE = 'page-without-title';

	/**
	 * The pre-#255 title template; page.html now does the same.
	 */
	public const LEGACY_WITH_TITLE = 'page-with-title';

	/**
	 * Hook the migration.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', [ self::class, 'maybe_migrate' ] );
	}

	/**
	 * The template a page moves to, or null when it stays as it is.
	 *
	 * @param string $current The page's template slug ('' or 'default' for the default).
	 * @return string|null
	 */
	public static function migrated_template( string $current ): ?string {
		if ( '' === $current || self::DEFAULT === $current ) {
			return self::WITHOUT_TITLE;
		}
		if ( self::LEGACY_WITH_TITLE === $current ) {
			return self::DEFAULT;
		}
		return null;
	}

	/**
	 * Move every page once per VERSION.
	 *
	 * The version is stored before the pages are moved: a second request that
	 * starts while the first is still running must not move a page twice (a
	 * page-with-title page, once moved to the default, would otherwise move on
	 * to page-without-title).
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {
		if ( (int) get_option( self::VERSION_OPTION, 0 ) >= self::VERSION ) {
			return;
		}
		update_option( self::VERSION_OPTION, self::VERSION );

		$pages = get_posts(
			[
				'post_type'   => 'page',
				'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private', 'trash' ],
				'numberposts' => -1,
				'fields'      => 'ids',
				'lang'        => '', // Every language, not only Polylang's current one.
			]
		);

		// Decide for every page first, then write, so no page is read after another was moved.
		$moves = [];
		foreach ( $pages as $page_id ) {
			$target = self::migrated_template( (string) get_page_template_slug( $page_id ) );
			if ( null !== $target ) {
				$moves[ $page_id ] = $target;
			}
		}
		foreach ( $moves as $page_id => $target ) {
			update_post_meta( $page_id, '_wp_page_template', $target );
		}
	}
}
