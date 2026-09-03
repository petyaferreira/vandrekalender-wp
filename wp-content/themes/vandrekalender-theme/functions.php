<?php
/**
 * Vandrekalender Theme functions.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue compiled theme assets.
 */
function vandrekalender_enqueue_assets() {
	$uri = get_template_directory_uri() . '/public';
	$dir = get_template_directory() . '/public';

	wp_enqueue_style(
		'vandrekalender-screen',
		$uri . '/screen.css',
		[],
		file_exists( $dir . '/screen.css' ) ? filemtime( $dir . '/screen.css' ) : null
	);

	wp_enqueue_script(
		'vandrekalender-screen',
		$uri . '/screen.js',
		[],
		file_exists( $dir . '/screen.js' ) ? filemtime( $dir . '/screen.js' ) : null,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'vandrekalender_enqueue_assets' );

/**
 * Register editor styles.
 *
 * The add_editor_style() call loads stylesheets inside the editor canvas, so
 * blocks preview exactly as they render on the front end. Loading the full
 * frontend stylesheet (screen.css) keeps the canvas in sync automatically —
 * any style added for the front end shows up in the editor too. Enqueueing
 * via enqueue_block_editor_assets would load the CSS outside the canvas
 * iframe, where it cannot style the content.
 */
function vandrekalender_editor_styles() {
	add_editor_style(
		[
			'public/screen.css',
			'public/editor.css',
		]
	);
}
add_action( 'after_setup_theme', 'vandrekalender_editor_styles' );

/**
 * Hand the editor canvas the theme.json styles up front.
 *
 * In a block theme the server does not style the editor canvas. The editor
 * fetches the global styles record over the REST API and generates that CSS in
 * the browser. Both the 800px content width and the Publico headings come from
 * there.
 *
 * Administrators never see the difference, because WordPress embeds a copy of
 * that record in the page before the editor starts, so the canvas is styled on
 * its very first paint. Users who cannot edit theme options do not get the
 * benefit of that embedded copy and their editor has to make the request.
 * Measured on production as an Event Organizer: the canvas paints the post at
 * 918 ms, the request for /wp/v2/global-styles runs from 1299 to 1450 ms, and
 * the theme styles land at 1470 ms. Those 552 ms are the visible jump from raw
 * left-aligned HTML to the real layout, and they are why organizers see it and
 * admins do not.
 *
 * Adding the same CSS to the editor settings here closes the gap: the canvas
 * has it before it paints. The editor still generates its own copy a moment
 * later, but the rules are identical and land in the same place in the
 * cascade, so nothing moves when they arrive.
 *
 * @param array $settings Block editor settings.
 * @return array Settings with the global stylesheet appended.
 */
function vandrekalender_editor_global_styles( array $settings ): array {
	// Administrators already get this embedded in the page by core.
	if ( current_user_can( 'edit_theme_options' ) ) {
		return $settings;
	}

	$css = wp_get_global_stylesheet();

	if ( ! $css ) {
		return $settings;
	}

	$settings['styles'][] = [
		'css'            => $css,
		'__unstableType' => 'theme',
		'isGlobalStyles' => false,
	];

	return $settings;
}
add_filter( 'block_editor_settings_all', 'vandrekalender_editor_global_styles' );
