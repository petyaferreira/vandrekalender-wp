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
	$dir = get_template_directory_uri() . '/public';
	$v   = wp_get_theme()->get( 'Version' );

	wp_enqueue_style(
		'vandrekalender-screen',
		$dir . '/screen.css',
		[],
		$v
	);

	wp_enqueue_script(
		'vandrekalender-screen',
		$dir . '/screen.js',
		[],
		$v,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'vandrekalender_enqueue_assets' );

/**
 * Enqueue editor styles.
 */
function vandrekalender_enqueue_editor_assets() {
	$dir = get_template_directory_uri() . '/public';
	$v   = wp_get_theme()->get( 'Version' );

	wp_enqueue_style(
		'vandrekalender-editor',
		$dir . '/editor.css',
		[],
		$v
	);
}
add_action( 'enqueue_block_editor_assets', 'vandrekalender_enqueue_editor_assets' );
