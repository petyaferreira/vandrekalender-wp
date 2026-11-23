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
		'https://www.dvl.dk/ture/7820e612-4486-5a42-a62f-33251b94171e/lunteholdets-mandagstur' => 'lunteholdets-mandagstur.html',
		'https://www.dvl.dk/ture/da249383-7e71-5392-ab20-fc04ad6b69eb/gaa-groent-gennem-byen-fra-carlsberg-st-til-broenshoej-torv-gratis-for-alle' => 'gaa-groent-gennem-byen-fra-carlsberg-st-til-broenshoej-torv-gratis-for-alle.html',
		'https://www.dvl.dk/ture/db0b7694-0274-5eb1-96c6-f039fb3de826/dragefestival-paa-fanoe' => 'dragefestival-paa-fanoe.html',
		'https://www.dvl.dk/ture/6a8de1cf-6798-56aa-9d6c-dced38284060/efter-pinsetur-2027-til-esbjerg-og-fanoe-turen-er-fuldtegnet-venteliste-oprettes' => 'efter-pinsetur.html',
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
	 * Parse a tour search page.
	 *
	 * @param string $html Search page HTML.
	 * @return array
	 */
	public function parse_list( string $html ): array {
		return $this->parse( $html );
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
