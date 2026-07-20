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
