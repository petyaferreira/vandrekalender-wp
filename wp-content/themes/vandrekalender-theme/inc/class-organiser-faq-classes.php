<?php
/**
 * Organiser FAQ class migration for #261.
 *
 * Since #259 the FAQ pattern uses the classes vk-faq__intro and vk-faq__image
 * and the block name "FAQ". Organiser pages made before that have the old
 * vk-organiser-about__* classes and the name "Organiser about" saved in their
 * content. This class renames them once, on the first request after the
 * deploy, with the same versioned-option pattern as Vandrekalender_Page_Templates.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renames the pre-#259 FAQ classes and block name in organiser content.
 */
class Vandrekalender_Organiser_Faq_Classes {

	/**
	 * Bump this only together with a new mapping in migrated_content().
	 */
	private const VERSION = 1;

	/**
	 * Option storing the last applied version.
	 */
	private const VERSION_OPTION = 'vandrekalender_theme_organiser_faq_classes_version';

	/**
	 * Hook the migration.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', [ self::class, 'maybe_migrate' ] );
	}

	/**
	 * The content with the old names replaced; unchanged content comes back as is.
	 *
	 * @param string $content Saved post content.
	 * @return string
	 */
	public static function migrated_content( string $content ): string {
		return str_replace(
			[ 'vk-organiser-about__', '"name":"Organiser about"' ],
			[ 'vk-faq__', '"name":"FAQ"' ],
			$content
		);
	}

	/**
	 * Migrate every organiser once per VERSION.
	 *
	 * The version is stored first, so a second request does not run it in
	 * parallel. If the request dies midway, delete the option to run it again:
	 * ./wp.sh option delete vandrekalender_theme_organiser_faq_classes_version
	 *
	 * @return void
	 */
	public static function maybe_migrate(): void {
		if ( (int) get_option( self::VERSION_OPTION, 0 ) >= self::VERSION ) {
			return;
		}
		update_option( self::VERSION_OPTION, self::VERSION );

		$posts = get_posts(
			[
				'post_type'   => 'organiser',
				'post_status' => [ 'publish', 'future', 'draft', 'pending', 'private', 'trash' ],
				'numberposts' => -1,
				'lang'        => '', // Every language, not only Polylang's current one.
			]
		);

		foreach ( $posts as $post ) {
			$content = self::migrated_content( $post->post_content );
			if ( $content !== $post->post_content ) {
				// Direct write: the content is only renamed, so no revision, kses or modified date.
				global $wpdb;
				$wpdb->update( $wpdb->posts, [ 'post_content' => $content ], [ 'ID' => $post->ID ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration.
				clean_post_cache( $post->ID );
			}
		}
	}
}
