<?php
/**
 * Mammut scraper that reads fixtures instead of the Mammut API and site.
 *
 * @package Vandrekalender
 */

/**
 * Serves fixture files instead of fetching the Mammut API and event pages.
 */
class Test_Scraper_Mammut extends Vandrekalender_Scraper_Mammut {

	const FIXTURES = __DIR__ . '/../fixtures/mammut/';

	/**
	 * URL → fixture file. A URL missing here behaves like a request that
	 * failed.
	 *
	 * @var array<string, string>
	 */
	public array $pages = [
		self::API_URL . '/events/180/variants' => 'variants-180.json',
		self::API_URL . '/events/162/variants' => 'variants-162.json',
		self::EVENT_URL . 'mammutmarch-aarhus' => 'mammutmarch-aarhus.html',
		self::EVENT_URL . 'mammutmarch-koebenhavn-75-100-km' => 'mammutmarch-koebenhavn-75-100-km.html',
	];

	/**
	 * Headers sent with each request, keyed by URL.
	 *
	 * @var array<string, array>
	 */
	public array $requests = [];

	/**
	 * Fixture body for a known URL, '' otherwise (as remote_get() on failure).
	 *
	 * @param string $url     URL.
	 * @param array  $headers Request headers, recorded.
	 * @return string
	 */
	protected function remote_get( string $url, array $headers = [] ): string {
		$this->requests[ $url ] = $headers;
		return isset( $this->pages[ $url ] ) ? self::fixture( $this->pages[ $url ] ) : '';
	}

	/**
	 * Contents of a fixture file.
	 *
	 * @param string $name File name in tests/fixtures/mammut/.
	 * @return string
	 */
	public static function fixture( string $name ): string {
		return (string) file_get_contents( self::FIXTURES . $name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local fixture, not a remote URL.
	}

	/**
	 * Parse an event list as fetch() returns it: the list's events as a JSON
	 * array.
	 *
	 * @param string $json Body of one page of `/events?country=dk`.
	 * @return array
	 */
	public function parse_list( string $json ): array {
		$data = json_decode( $json, true );
		return $this->parse( (string) wp_json_encode( $data['data']['events'] ?? null ) );
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
