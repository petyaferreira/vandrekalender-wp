<?php
/**
 * Sportstiming scraper that reads fixtures instead of sportstiming.dk.
 *
 * @package Vandrekalender
 */

/**
 * Serves fixture files instead of fetching event pages from sportstiming.dk.
 */
class Test_Scraper_Sportstiming extends Vandrekalender_Scraper_Sportstiming {

	const FIXTURES = __DIR__ . '/../fixtures/sportstiming/';

	/**
	 * Event page URL → fixture file. A URL missing here behaves like a page
	 * that could not be loaded.
	 *
	 * @var array<string, string>
	 */
	public array $pages = [
		self::BASE_URL . '/event/17385' => 'event-17385.html',
		self::BASE_URL . '/event/18672' => 'event-18672.html',
		self::BASE_URL . '/event/19811' => 'event-19811.html',
	];

	/**
	 * URLs asked for, in order.
	 *
	 * @var string[]
	 */
	public array $requests = [];

	/**
	 * Fixture body for a known URL, '' otherwise (as remote_get() on failure).
	 *
	 * @param string $url     URL.
	 * @param array  $headers Ignored.
	 * @return string
	 */
	protected function remote_get( string $url, array $headers = [] ): string {
		$this->requests[] = $url;
		return isset( $this->pages[ $url ] ) ? self::fixture( $this->pages[ $url ] ) : '';
	}

	/**
	 * Contents of a fixture file.
	 *
	 * @param string $name File name in tests/fixtures/sportstiming/.
	 * @return string
	 */
	public static function fixture( string $name ): string {
		return (string) file_get_contents( self::FIXTURES . $name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local fixture, not a remote URL.
	}

	/**
	 * Parse a search JSON body (fetch() calls wp_remote_get() directly, so
	 * the tests start from parse()).
	 *
	 * @param string $json Search JSON body.
	 * @return array
	 */
	public function parse_search( string $json ): array {
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
