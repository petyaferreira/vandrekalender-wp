<?php
/**
 * DVL scraper that reads fixtures instead of dvl.dk.
 *
 * @package Vandrekalender
 */

/**
 * Serves fixture files instead of fetching dvl.dk.
 */
class Test_Scraper_DVL extends Vandrekalender_Scraper_DVL {

	const FIXTURES = __DIR__ . '/../fixtures/dvl/';

	/**
	 * Tour page URL → fixture file. A URL missing here behaves like a page
	 * that could not be loaded.
	 *
	 * @var array<string, string>
	 */
	public array $pages = [
		'https://dvl.dk/vandreture/saeby/' => 'saeby.html',
		'https://dvl.dk/vandreture/ud-i-det-blaa-i-svanninge-bakker-og-bjerge/' => 'ud-i-det-blaa-i-svanninge-bakker-og-bjerge.html',
	];

	/**
	 * Fixture body for a known URL, '' otherwise (as remote_get() on failure).
	 *
	 * @param string $url     URL.
	 * @param array  $headers Ignored.
	 * @return string
	 */
	protected function remote_get( string $url, array $headers = [] ): string {
		return isset( $this->pages[ $url ] ) ? self::fixture( $this->pages[ $url ] ) : '';
	}

	/**
	 * Contents of a fixture file.
	 *
	 * @param string $name File name in tests/fixtures/dvl/.
	 * @return string
	 */
	public static function fixture( string $name ): string {
		return (string) file_get_contents( self::FIXTURES . $name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local fixture, not a remote URL.
	}

	/**
	 * Parse a feed body.
	 *
	 * @param string $json Feed body.
	 * @return array
	 */
	public function parse_feed( string $json ): array {
		return $this->parse( $json );
	}

	/**
	 * Source URLs marked as still listed at the source.
	 *
	 * @return string[]
	 */
	public function seen_urls(): array {
		return array_keys( $this->seen_source_urls );
	}
}
