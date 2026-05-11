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
 * Enqueue editor styles.
 */
function vandrekalender_enqueue_editor_assets() {
	$uri = get_template_directory_uri() . '/public';
	$dir = get_template_directory() . '/public';

	wp_enqueue_style(
		'vandrekalender-editor',
		$uri . '/editor.css',
		[],
		file_exists( $dir . '/editor.css' ) ? filemtime( $dir . '/editor.css' ) : null
	);
}
add_action( 'enqueue_block_editor_assets', 'vandrekalender_enqueue_editor_assets' );
