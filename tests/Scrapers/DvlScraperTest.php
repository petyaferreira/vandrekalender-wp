<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * DVL scraper on saved copies of dvl.dk (tests/fixtures/dvl/, fetched
 * 2026-10-19): the maps feed, the Sæby tour (21 October, summer time) and the
 * Svanninge tour (29 October, after the switch to winter time).
 *
 * When dvl.dk changes its markup, save fresh copies and update the expected
 * values here on purpose (docs/contributing/tests.md).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Event;

/**
 * DVL scraper on the saved copies.
 */
class DvlScraperTest extends TestCase {

	/**
	 * Scraper reading the fixtures.
	 *
	 * @var Test_Scraper_DVL
	 */
	private Test_Scraper_DVL $scraper;

	/**
	 * Events parsed from the saved feed, keyed by source URL.
	 *
	 * @var array<string, array>
	 */
	private array $events;

	/**
	 * Parse the saved feed with a fresh scraper.
	 */
	protected function setUp(): void {
		Vandrekalender_Geocoder::$lookups = [];

		$this->scraper = new Test_Scraper_DVL();
		$this->events  = array_column(
			$this->scraper->parse_feed( Test_Scraper_DVL::fixture( 'maps-data.json' ) ),
			null,
			Event::META_SOURCE_URL
		);
	}

	/**
	 * A tour listed twice in the feed becomes one event.
	 */
	public function test_feed_gives_one_event_per_tour_page(): void {
		$this->assertSame(
			[
				'https://dvl.dk/vandreture/saeby/',
				'https://dvl.dk/vandreture/ud-i-det-blaa-i-svanninge-bakker-og-bjerge/',
			],
			array_keys( $this->events )
		);
	}

	/**
	 * Every field of a tour in summer time (UTC+2).
	 */
	public function test_summer_time_tour(): void {
		$event = $this->events['https://dvl.dk/vandreture/saeby/'];

		$this->assertSame( 'Sæby', $event['post_title'], 'title without " - DVL"' );

		// The calendar link says 08:30 UTC; Denmark is UTC+2 in summer time.
		$this->assertSame( '2026-10-21', $event[ Event::META_DATE ] );
		$this->assertSame( '10:30', $event[ Event::META_ROUTES ][0]['start_time'] );

		$this->assertSame( '9', $event[ Event::META_ROUTES ][0]['distance_km'] );
		$this->assertSame( '0', $event[ Event::META_ROUTES ][0]['price'], 'free for members' );

		$this->assertSame( 'DVL Aalborg', $event[ Event::META_ORGANISER_NAME ] );
		$this->assertSame( 'https://dvl.dk/aalborg-afd/', $event[ Event::META_ORGANISER_URL ] );

		$this->assertSame( 'p-plads ved Sæbygårds Slot, Sæbygårdvej 49,  9300 Sæby', $event[ Event::META_ADDRESS ] );

		$this->assertSame( 57.3342, $event[ Event::META_LAT ] );
		$this->assertSame( 10.4788, $event[ Event::META_LNG ] );
		$this->assertSame( Vandrekalender_Geocoder::TEST_MUNICIPALITY, $event[ Event::META_MUNICIPALITY ] );

		$this->assertSame( 'Dansk Vandrelaug', $event[ Event::META_SOURCE_NAME ] );

		$this->assertStringStartsWith( 'Vi vandrer gennem Sæbygård skov', $event['post_excerpt'] );
		$this->assertStringNotContainsString( 'Grøn/3/9', $event['post_excerpt'], 'tour code removed' );
	}

	/**
	 * Date and time of a tour after the switch to winter time (UTC+1).
	 */
	public function test_winter_time_tour(): void {
		$event = $this->events['https://dvl.dk/vandreture/ud-i-det-blaa-i-svanninge-bakker-og-bjerge/'];

		$this->assertSame( '“Ud i det blå” i Svanninge Bakker og Bjerge', $event['post_title'] );

		// The calendar link says 09:00 UTC; after 25 October Denmark is UTC+1.
		$this->assertSame( '2026-10-29', $event[ Event::META_DATE ] );
		$this->assertSame( '10:00', $event[ Event::META_ROUTES ][0]['start_time'] );

		$this->assertSame( '12', $event[ Event::META_ROUTES ][0]['distance_km'] );
		$this->assertSame( 'DVL Afdeling Fyn', $event[ Event::META_ORGANISER_NAME ] );
		$this->assertSame( 'Gåsebjergsand, Odensevej 150, 5600 Faaborg', $event[ Event::META_PLACE_NAME ] );
	}

	/**
	 * A tour page that fails to load is skipped, not treated as removed.
	 */
	public function test_unreachable_tour_page_is_skipped_but_still_seen(): void {
		// Otherwise unpublish_stale_events() would draft it.
		unset( $this->scraper->pages['https://dvl.dk/vandreture/saeby/'] );

		$events = $this->scraper->parse_feed( Test_Scraper_DVL::fixture( 'maps-data.json' ) );

		$this->assertCount( 1, $events );
		$this->assertContains( 'https://dvl.dk/vandreture/saeby/', $this->scraper->seen_urls() );
	}

	/**
	 * A feed that is not JSON gives no events and no seen URLs.
	 */
	public function test_broken_feed_gives_no_events(): void {
		$scraper = new Test_Scraper_DVL();

		$this->assertSame( [], $scraper->parse_feed( '<html>Not JSON</html>' ) );
		$this->assertSame( [], $scraper->seen_urls() );
	}
}
