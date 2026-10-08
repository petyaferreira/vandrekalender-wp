<?php

defined( 'ABSPATH' ) || exit;

/**
 * The Danish municipalities, read from data/municipalities.json.
 *
 * That file is the single source for municipalities: code → { name, region }
 * for the 98 municipalities plus Christiansø. The block editor bundles the
 * same file to turn an Adressevælger municipality code into a name, the
 * server-side geocoder uses it for the same, and the region taxonomy is
 * assigned from it — so the names always match.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Municipalities {

	/**
	 * All municipalities, keyed by four-digit code ("0101").
	 *
	 * Read once per request. Without the file no event gets a municipality
	 * or region, and nothing else would show it, so a missing or invalid file
	 * is logged even without WP_DEBUG — a broken deploy is the likely cause.
	 *
	 * @return array<string, array{name: string, region: string}>
	 */
	public static function all(): array {
		static $all = null;

		if ( null !== $all ) {
			return $all;
		}

		$all  = [];
		$json = file_get_contents( VANDREKALENDER_EVENTS_DIR . 'data/municipalities.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		$data = false !== $json ? json_decode( $json, true ) : null;

		foreach ( is_array( $data ) ? $data : [] as $code => $municipality ) {
			if ( isset( $municipality['name'], $municipality['region'] ) ) {
				$all[ (string) $code ] = [
					'name'   => (string) $municipality['name'],
					'region' => (string) $municipality['region'],
				];
			}
		}

		if ( empty( $all ) ) {
			error_log( 'Vandrekalender: data/municipalities.json is missing or invalid, so municipalities and regions cannot be assigned.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- must reach the server log on production.
		}

		return $all;
	}

	/**
	 * Municipality name for a code, e.g. "0101" → "København".
	 *
	 * @param string $code Four-digit municipality code.
	 * @return string Empty string when the code is not in the list.
	 */
	public static function name( string $code ): string {
		$all = self::all();

		return isset( $all[ $code ] ) ? $all[ $code ]['name'] : '';
	}

	/**
	 * Region slugs keyed by lowercase municipality name.
	 *
	 * @return array<string, string>
	 */
	public static function region_map(): array {
		$map = [];

		foreach ( self::all() as $municipality ) {
			$map[ mb_strtolower( $municipality['name'] ) ] = $municipality['region'];
		}

		return $map;
	}
}
