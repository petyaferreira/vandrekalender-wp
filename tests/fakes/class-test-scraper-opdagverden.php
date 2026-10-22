<?php
/**
 * Opdag Verden scraper that reads fixtures instead of opdagverden.dk.
 *
 * @package Vandrekalender
 */

/**
 * Serves fixture files instead of fetching opdagverden.dk and Google Maps.
 */
class Test_Scraper_Opdagverden extends Vandrekalender_Scraper_Opdagverden {

	const FIXTURES = __DIR__ . '/../fixtures/opdagverden/';

	/**
	 * URL → fixture file. A URL missing here behaves like a page that could
	 * not be loaded.
	 *
	 * @var array<string, string>
	 */
	public array $pages = [
		self::SOURCE_URL . '?page=1'                   => 'dagsvandringer-page-1.html',
		self::SOURCE_URL . '?page=2'                   => 'dagsvandringer-page-2.html',
		self::BASE_URL . '/faellesture/hald-so-rundt-dollerup-bakker-21-km' => 'hald-so-rundt-dollerup-bakker-21-km.html',
		self::BASE_URL . '/faellesture/endelave-kaninoen-21-km' => 'endelave-kaninoen-21-km.html',
		self::BASE_URL . '/faellesture/farum-so-rundt' => 'farum-so-rundt.html',
		self::BASE_URL . '/ruter/kaninoen-pa-endelave' => 'ruter-kaninoen-pa-endelave.html',
	];

	/**
	 * Google Maps short link → saved headers of its redirect, for
	 * wp_remote_head().
	 *
	 * @var array<string, string>
	 */
	const MAPS_LINKS = [
		'https://maps.app.goo.gl/DwKdW6ZQQSPsefTF9' => 'maps-DwKdW6ZQQSPsefTF9.headers',
	];

	/**
	 * Serve the saved Maps redirects to wp_remote_head().
	 */
	public function __construct() {
		$GLOBALS['vandrekalender_test_head_responses'] = [];
		foreach ( self::MAPS_LINKS as $url => $file ) {
			$headers = [];
			foreach ( explode( "\n", self::fixture( $file ) ) as $line ) {
				if ( preg_match( '/^([\w-]+):\s*(.*)$/', trim( $line ), $header ) ) {
					$headers[ strtolower( $header[1] ) ] = $header[2];
				}
			}
			$GLOBALS['vandrekalender_test_head_responses'][ $url ] = $headers;
		}
	}

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
	 * @param string $name File name in tests/fixtures/opdagverden/.
	 * @return string
	 */
	public static function fixture( string $name ): string {
		return (string) file_get_contents( self::FIXTURES . $name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local fixture, not a remote URL.
	}

	/**
	 * Fetch the listing (both saved pages) and parse it.
	 *
	 * @return array
	 */
	public function scrape(): array {
		return $this->parse( $this->fetch() );
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
