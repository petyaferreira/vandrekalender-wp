<?php
/**
 * Sportstiming scraper on saved copies of the search JSON and three event
 * pages (tests/fixtures/sportstiming/, fetched 2026-10-23 with the scraper's
 * user agent): the search trimmed to four events, and the pages of the three
 * walks and hikes among them.
 *
 * - 17385 Lundhags EventyrHike: one distance with an expired price tier, a
 *   schema.org place.
 * - 18672 Long Lonely Wolf Walk: listed on two dates, three distances.
 * - 19811 Vandretur på Jyske Ås: no place on the page.
 * - 19426 Egeskovløbet is typed "Løb, Walk" and must be dropped.
 *
 * When Sportstiming changes its JSON or markup, save fresh copies and update
 * the expected values here on purpose (docs/contributing/tests.md).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Event;

/**
 * Sportstiming scraper on the saved copies.
 */
class SportstimingScraperTest extends TestCase {

	const HIKE     = 'https://www.sportstiming.dk/event/17385#2026-10-25';
	const WOLF_1   = 'https://www.sportstiming.dk/event/18672#2027-05-07';
	const WOLF_2   = 'https://www.sportstiming.dk/event/18672#2027-05-08';
	const JYSKE_AS = 'https://www.sportstiming.dk/event/19811#2027-06-12';

	/**
	 * Scraper reading the fixtures.
	 *
	 * @var Test_Scraper_Sportstiming
	 */
	private Test_Scraper_Sportstiming $scraper;

	/**
	 * Events parsed from the saved search, keyed by source URL.
	 *
	 * @var array<string, array>
	 */
	private array $events;

	/**
	 * Parse the saved search with a fresh scraper.
	 */
	protected function setUp(): void {
		Vandrekalender_Geocoder::$addresses   = [];
		Vandrekalender_Geocoder::$approximate = false;

		$this->scraper = new Test_Scraper_Sportstiming();
		$this->events  = array_column(
			$this->scraper->parse_search( Test_Scraper_Sportstiming::fixture( 'search.json' ) ),
			null,
			Event::META_SOURCE_URL
		);
	}

	/**
	 * Only events typed exactly Walk or Hike become events.
	 */
	public function test_walk_and_hike_only(): void {
		$this->assertSame( [ self::HIKE, self::WOLF_1, self::WOLF_2, self::JYSKE_AS ], array_keys( $this->events ) );
		$this->assertNotContains( 'https://www.sportstiming.dk/event/19426#2026-11-01', $this->scraper->seen_urls() );
		$this->assertNotContains( Test_Scraper_Sportstiming::BASE_URL . '/event/19426', $this->scraper->requests, 'a dropped event is not fetched' );
	}

	/**
	 * Every field of a hike with a place and a price that changed on 2 January.
	 */
	public function test_hike_with_place_and_price(): void {
		$event = $this->events[ self::HIKE ];

		$this->assertSame( 'Lundhags EventyrHike Svanninge Bakker 2026', $event['post_title'] );
		$this->assertSame( '2026-10-25', $event[ Event::META_DATE ], 'the listing says 25 Oct 2026' );

		// The page: "Til og med 1. jan 2026 299,00 DKK" (struck through) and
		// "Fra 2. jan 2026 399,00 DKK". Only the current tier counts.
		$this->assertSame(
			[
				[
					'id'          => 'dist-15',
					'distance_km' => '15',
					'start_time'  => '08:00',
					'cutoff_time' => '',
					'price'       => '399',
				],
			],
			$event[ Event::META_ROUTES ]
		);

		// JSON-LD: Place "Odensevej 150", Odensevej 150, Faaborg.
		$this->assertSame( 'Odensevej 150', $event[ Event::META_PLACE_NAME ] );
		$this->assertSame( 'Odensevej 150, Faaborg', $event[ Event::META_ADDRESS ] );
		$this->assertContains( 'Odensevej 150, Faaborg', Vandrekalender_Geocoder::$addresses );
		$this->assertSame( Vandrekalender_Geocoder::TEST_LAT, $event[ Event::META_LAT ] );
		$this->assertSame( Vandrekalender_Geocoder::TEST_LNG, $event[ Event::META_LNG ] );
		$this->assertSame( Vandrekalender_Geocoder::TEST_MUNICIPALITY, $event[ Event::META_MUNICIPALITY ] );
		$this->assertFalse( $event[ Event::META_LOCATION_APPROXIMATE ] );

		$description = 'Jeg har netop tilmeldt mig Lundhags EventyrHike Svanninge Bakker 2026. Du kan tilmelde dig eller læse mere på https://www.sportstiming.dk/event/17385';
		$this->assertSame( $description, $event['post_excerpt'] );
		$this->assertSame( '<p>' . $description . '</p>', $event['post_content'] );
		$this->assertSame( '', $event['featured_image_url'], 'the page only has the Sportstiming logo' );

		$this->assertSame( 'Sportstiming', $event[ Event::META_SOURCE_NAME ] );
		$this->assertSame( 'Sportstiming', $event[ Event::META_ORGANISER_NAME ] );
		$this->assertSame( 'https://www.sportstiming.dk/event/17385', $event[ Event::META_ORGANISER_URL ] );
	}

	/**
	 * Three distances, each with its own start time and price.
	 */
	public function test_event_with_three_distances(): void {
		$event = $this->events[ self::WOLF_1 ];

		$this->assertSame( 'Long Lonely Wolf Walk 2027', $event['post_title'] );
		$this->assertSame(
			[
				[
					'id'          => 'dist-100',
					'distance_km' => '100',
					'start_time'  => '19:00',
					'cutoff_time' => '',
					'price'       => '699',
				],
				[
					'id'          => 'dist-50',
					'distance_km' => '50',
					'start_time'  => '07:00',
					'cutoff_time' => '',
					'price'       => '599',
				],
				[
					'id'          => 'dist-25',
					'distance_km' => '25',
					'start_time'  => '13:00',
					'cutoff_time' => '',
					'price'       => '399',
				],
			],
			$event[ Event::META_ROUTES ]
		);

		// JSON-LD: Place "Ringvej 24", Ringvej 24, Roslev.
		$this->assertSame( 'Ringvej 24', $event[ Event::META_PLACE_NAME ] );
		$this->assertSame( 'Ringvej 24, Roslev', $event[ Event::META_ADDRESS ] );
	}

	/**
	 * An event listed on two dates under one link gives one event per date.
	 */
	public function test_two_dates_under_one_link(): void {
		$this->assertSame( '2027-05-07', $this->events[ self::WOLF_1 ][ Event::META_DATE ] );
		$this->assertSame( '2027-05-08', $this->events[ self::WOLF_2 ][ Event::META_DATE ] );
		$this->assertSame( $this->events[ self::WOLF_1 ][ Event::META_ROUTES ], $this->events[ self::WOLF_2 ][ Event::META_ROUTES ] );
		$this->assertContains( self::WOLF_1, $this->scraper->seen_urls() );
		$this->assertContains( self::WOLF_2, $this->scraper->seen_urls() );
	}

	/**
	 * A page with neither a schema.org place nor a "Sted:" line is published
	 * without a place and is not geocoded.
	 */
	public function test_event_without_place(): void {
		$event = $this->events[ self::JYSKE_AS ];

		$this->assertSame( 'Vandretur På Jyske Ås - Klokkerholm - Østervrå', $event['post_title'] );
		$this->assertSame( '2027-06-12', $event[ Event::META_DATE ] );
		$this->assertSame( '', $event[ Event::META_PLACE_NAME ] );
		$this->assertSame( '', $event[ Event::META_ADDRESS ] );
		$this->assertArrayNotHasKey( Event::META_LAT, $event );
		$this->assertNotContains( '', Vandrekalender_Geocoder::$addresses );

		$this->assertSame( '25', $event[ Event::META_ROUTES ][0]['distance_km'] );
		$this->assertSame( '08:00', $event[ Event::META_ROUTES ][0]['start_time'] );
		$this->assertSame( '150', $event[ Event::META_ROUTES ][0]['price'] );
	}

	/**
	 * When the geocoder only finds the postcode area (#112), the pin is
	 * marked approximate.
	 */
	public function test_postcode_area_pin_is_approximate(): void {
		Vandrekalender_Geocoder::$approximate = true;

		$events = array_column(
			( new Test_Scraper_Sportstiming() )->parse_search( Test_Scraper_Sportstiming::fixture( 'search.json' ) ),
			null,
			Event::META_SOURCE_URL
		);

		$this->assertTrue( $events[ self::HIKE ][ Event::META_LOCATION_APPROXIMATE ] );
	}

	/**
	 * An event page that fails to load still gives an event, with the title
	 * from the listing, and is still seen so it is not drafted.
	 */
	public function test_failed_page_falls_back_to_listing(): void {
		$scraper        = new Test_Scraper_Sportstiming();
		$scraper->pages = [];

		$events = $scraper->parse_search( Test_Scraper_Sportstiming::fixture( 'search.json' ) );

		$this->assertCount( 4, $events );
		$this->assertSame( 'Lundhags EventyrHike Svanninge Bakker 2026', $events[0]['post_title'] );
		$this->assertSame( [], $events[0][ Event::META_ROUTES ] );
		$this->assertSame( '', $events[0]['post_content'] );
		$this->assertContains( self::HIKE, $scraper->seen_urls() );
	}

	/**
	 * A body that is not JSON gives no events and no seen URLs.
	 */
	public function test_broken_search_gives_no_events(): void {
		$scraper = new Test_Scraper_Sportstiming();

		$this->assertSame( [], $scraper->parse_search( '<html>Not JSON</html>' ) );
		$this->assertSame( [], $scraper->seen_urls() );
	}
}
