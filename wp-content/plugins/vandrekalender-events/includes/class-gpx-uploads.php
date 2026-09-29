<?php

defined( 'ABSPATH' ) || exit;

/**
 * Allow GPX files in the Media Library.
 *
 * GPX (`.gpx`) is an XML format for GPS tracks, used to attach a route to an
 * event (see docs/route-gpx-plan.md). WordPress does not allow it by default,
 * and PHP's finfo sniffs a GPX file's content as generic XML rather than
 * `application/gpx+xml`, which core's upload security check then rejects — so
 * both the allowed-mimes list and the type-sniffing result need a filter, on
 * top of validating that the uploaded file is actually a GPX track.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Gpx_Uploads {

	/**
	 * Maximum accepted GPX file size, in bytes.
	 */
	const MAX_SIZE = 5 * MB_IN_BYTES;

	/**
	 * MIME type used for GPX files throughout WordPress.
	 */
	const MIME_TYPE = 'application/gpx+xml';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'upload_mimes', [ $this, 'allow_gpx_mime' ] );
		add_filter( 'wp_check_filetype_and_ext', [ $this, 'fix_gpx_filetype' ], 10, 5 );
		add_filter( 'wp_handle_upload_prefilter', [ $this, 'validate_gpx_upload' ] );
	}

	/**
	 * Add `.gpx` to the list of allowed upload mime types.
	 *
	 * @param array $mimes Extension => mime type map.
	 * @return array Filtered map.
	 */
	public function allow_gpx_mime( array $mimes ): array {
		$mimes['gpx'] = self::MIME_TYPE;

		return $mimes;
	}

	/**
	 * Correct the sniffed type/extension for GPX files.
	 *
	 * PHP's finfo reports GPX content as `text/xml`, `application/xml`, or
	 * even `text/plain`, none of which match the `application/gpx+xml` mime
	 * type registered above. Core treats that mismatch between the sniffed
	 * type and the extension as suspicious and rejects the upload with "not
	 * permitted for security reasons". Filenames ending in `.gpx` whose
	 * sniffed type is one of those three get corrected here instead.
	 *
	 * @param array         $wp_check_filetype_and_ext Values for ext, type, proper_filename.
	 * @param string        $file                      Full path to the file.
	 * @param string        $filename                  The name of the file.
	 * @param string[]|null $mimes                     Allowed mime types.
	 * @param string|null   $real_mime                 The actual mime type sniffed from the file content.
	 * @return array Possibly corrected ext/type/proper_filename.
	 */
	public function fix_gpx_filetype( array $wp_check_filetype_and_ext, string $file, string $filename, $mimes, $real_mime = null ): array {
		if ( ! preg_match( '/\.gpx$/i', $filename ) ) {
			return $wp_check_filetype_and_ext;
		}

		$sniffed_as_xml = in_array( $real_mime, [ 'text/xml', 'application/xml', 'text/plain' ], true );

		if ( ! $sniffed_as_xml ) {
			return $wp_check_filetype_and_ext;
		}

		$wp_check_filetype_and_ext['ext']  = 'gpx';
		$wp_check_filetype_and_ext['type'] = self::MIME_TYPE;

		return $wp_check_filetype_and_ext;
	}

	/**
	 * Reject `.gpx` uploads that are not a valid GPX track.
	 *
	 * Runs before the file is moved into place, so it can only inspect the
	 * temporary upload. Keeps random XML (or a renamed `.txt`) out of the
	 * media library by requiring a `gpx` root element and at least one
	 * track point, route point, or waypoint.
	 *
	 * @param array $file Upload data as passed to wp_handle_upload().
	 * @return array Unmodified, or with an 'error' key set to reject the upload.
	 */
	public function validate_gpx_upload( array $file ): array {
		if ( ! preg_match( '/\.gpx$/i', (string) $file['name'] ) ) {
			return $file;
		}

		if ( (int) $file['size'] > self::MAX_SIZE ) {
			$file['error'] = __( 'GPX files must be smaller than 5 MB.', 'vandrekalender-events' );

			return $file;
		}

		if ( ! $this->is_valid_gpx( $file['tmp_name'] ) ) {
			$file['error'] = __( 'This file is not a valid GPX track.', 'vandrekalender-events' );

			return $file;
		}

		return $file;
	}

	/**
	 * Check that a file is a well-formed GPX track.
	 *
	 * @param string $path Path to the file on disk.
	 * @return bool True if the file has a `gpx` root element and at least one point.
	 */
	private function is_valid_gpx( string $path ): bool {
		if ( PHP_VERSION_ID < 80000 && function_exists( 'libxml_disable_entity_loader' ) ) {
			// External entity loading is off by default from PHP 8 on; only
			// PHP 7 needs it disabled explicitly to avoid XXE.
			libxml_disable_entity_loader( true ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated -- only reached on PHP < 8, where the function still exists and is not deprecated.
		}

		$previous_errors = libxml_use_internal_errors( true );
		$xml             = simplexml_load_file( $path, 'SimpleXMLElement', LIBXML_NONET );
		libxml_use_internal_errors( $previous_errors );

		if ( false === $xml ) {
			return false;
		}

		if ( 'gpx' !== strtolower( $xml->getName() ) ) {
			return false;
		}

		return isset( $xml->trk->trkseg->trkpt ) || isset( $xml->rte->rtept ) || isset( $xml->wpt );
	}
}
