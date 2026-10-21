<?php

defined( 'ABSPATH' ) || exit;

/**
 * Version every script and style of this plugin by its file time.
 *
 * A deploy that changes a file then also changes its URL, so browsers load
 * the new copy instead of a cached old one (#118). This covers files
 * registered by hand with a fixed version, and any file registered later,
 * whatever version it passes. Other URLs (CDNs, other plugins, core) are left
 * alone. Script modules (viewScriptModule) do not pass these filters; the
 * build already gives them a content hash.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Asset_Versions {

	/**
	 * Hook into the URLs WordPress prints for scripts and styles.
	 */
	public function __construct() {
		add_filter( 'script_loader_src', [ $this, 'version_by_file_time' ] );
		add_filter( 'style_loader_src', [ $this, 'version_by_file_time' ] );
	}

	/**
	 * Replace the `ver` of a plugin file's URL with the file's modification time.
	 *
	 * @param string|false $src URL, already carrying the registered `?ver=`.
	 * @return string|false
	 */
	public function version_by_file_time( $src ) {
		if ( ! is_string( $src ) ) {
			return $src;
		}

		$path = self::path_of( $src, VANDREKALENDER_EVENTS_URL, VANDREKALENDER_EVENTS_DIR );

		return null !== $path ? add_query_arg( 'ver', (string) filemtime( $path ), $src ) : $src;
	}

	/**
	 * The file behind a URL of this plugin.
	 *
	 * @param string $src      Script or style URL.
	 * @param string $base_url The plugin's URL, with a trailing slash.
	 * @param string $base_dir The plugin's directory, with a trailing slash.
	 * @return string|null Path of an existing file, or null for any other URL.
	 */
	public static function path_of( string $src, string $base_url, string $base_dir ): ?string {
		if ( ! str_starts_with( $src, $base_url ) ) {
			return null;
		}

		$relative = rawurldecode( (string) strtok( substr( $src, strlen( $base_url ) ), '?#' ) );
		if ( '' === $relative || str_contains( $relative, '..' ) ) {
			return null;
		}

		$path = $base_dir . $relative;

		return is_file( $path ) ? $path : null;
	}
}
