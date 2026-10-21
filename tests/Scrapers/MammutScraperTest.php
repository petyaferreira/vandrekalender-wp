<?php
/**
 * Mammut scraper on saved copies of the Mammut API and mammutmarch.dk
 * (tests/fixtures/mammut/, fetched 2026-10-21): one page of the Danish event
 * list trimmed to Berlin, Aarhus and København 75/100, the variants of the
 * last two, and their event pages.
 *
 * Aarhus has a full address with a meeting point; København 75/100 has none
 * announced yet. Berlin is in the Danish list but outside v1's scope.
 *
 * When Mammut changes its API or markup, save fresh copies and update the
 * expected values here on purpose (docs/contributing/tests.md).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Event;

/**
 * Mammut scraper on the saved copies.
 */
class MammutScraperTest extends TestCase {

	const AARHUS   = 'https://mammutmarch.dk/events/mammutmarch-aarhus';
	const KBH_LONG = 'https://mammutmarch.dk/events/mammutmarch-koebenhavn-75-100-km';
	const VARIANTS = Vandrekalender_Scraper_Mammut::API_URL . '/events/180/variants';

	/**
	 * Scraper reading the fixtures.
	 *
	 * @var Test_Scraper_Mammut
	 */
	private Test_Scraper_Mammut $scraper;

	/**
	 * Events parsed from the saved list, keyed by source URL.
	 *
	 * @var array<string, array>
	 */
	private array $events;

	/**
	 * Parse the saved list with a fresh scraper.
	 */
	protected function setUp(): void {
		Vandrekalender_Geocoder::$addresses = [];

		$this->scraper = new Test_Scraper_Mammut();
		$this->events  = array_column(
			$this->scraper->parse_list( Test_Scraper_Mammut::fixture( 'events.json' ) ),
			null,
			Event::META_SOURCE_URL
		);
	}

	/**
	 * Only the Danish marches become events.
	 */
	public function test_list_gives_danish_events_only(): void {
		$this->assertSame( [ self::AARHUS, self::KBH_LONG ], array_keys( $this->events ) );
		$this->assertNotContains( 'https://mammutmarch.dk/events/berlin', $this->scraper->seen_urls() );
	}

	/**
	 * Every field of a march with a full address.
	 */
	public function test_march_with_address(): void {
		$event = $this->events[ self::AARHUS ];

		$this->assertSame( 'Mammutmarch Aarhus - 30/50 KM', $event['post_title'] );
		$this->assertSame( '2027-03-20', $event[ Event::META_DATE ], 'the page says 20. marts 2027' );

		// The variants say basePrice 54000 and 59500 øre, the same with or
		// without the event shirt. Start groups are not announced yet.
		$this->assertSame(
			[
				[
					'id'          => 'route-30',
					'distance_km' => '30',
					'start_time'  => '',
					'cutoff_time' => '',
					'price'       => '540',
				],
				[
					'id'          => 'route-50',
					'distance_km' => '50',
					'start_time'  => '',
					'cutoff_time' => '',
					'price'       => '595',
				],
			],
			$event[ Event::META_ROUTES ]
		);

		// "Tankrogen, Marselisborg Havnevej 1, 8000 Aarhus, Danmark".
		$this->assertSame( 'Tankrogen', $event[ Event::META_PLACE_NAME ] );
		$this->assertSame( 'Marselisborg Havnevej 1, 8000 Aarhus', $event[ Event::META_ADDRESS ] );
		$this->assertContains( 'Marselisborg Havnevej 1, 8000 Aarhus', Vandrekalender_Geocoder::$addresses );
		$this->assertSame( Vandrekalender_Geocoder::TEST_LAT, $event[ Event::META_LAT ] );
		$this->assertSame( Vandrekalender_Geocoder::TEST_LNG, $event[ Event::META_LNG ] );
		$this->assertSame( Vandrekalender_Geocoder::TEST_MUNICIPALITY, $event[ Event::META_MUNICIPALITY ] );

		$this->assertStringStartsWith( '<p>Allerede den 20. marts 2027 vender Mammutmarch tilbage til Aarhus!</p><p>Efter en fantastisk debut', $event['post_content'] );
		$this->assertStringStartsWith( 'Allerede den 20. marts 2027 vender Mammutmarch tilbage til Aarhus! Efter en fantastisk debut', $event['post_excerpt'] );

		$this->assertSame( 'Mammutmarch', $event[ Event::META_SOURCE_NAME ] );
		$this->assertSame( 'Mammutmarch', $event[ Event::META_ORGANISER_NAME ] );
		$this->assertSame( 'https://mammutmarch.dk/', $event[ Event::META_ORGANISER_URL ] );
		$this->assertStringEndsWith( '-Aarhus-dk-2026-2.png', $event['featured_image_url'] );
	}

	/**
	 * A march whose address is "Vil blive annonceret senere." is published
	 * with a placeholder place and is not geocoded.
	 */
	public function test_march_without_address_yet(): void {
		$event = $this->events[ self::KBH_LONG ];

		$this->assertSame( '2027-08-14', $event[ Event::META_DATE ], 'the page says 14. august 2027' );
		$this->assertSame( 'Annonceres senere', $event[ Event::META_PLACE_NAME ] );
		$this->assertSame( '', $event[ Event::META_ADDRESS ] );
		$this->assertArrayNotHasKey( Event::META_LAT, $event );
		$this->assertNotContains( '', Vandrekalender_Geocoder::$addresses );

		// The variants list 75 and 100 km out of order: 64500 and 69500 øre.
		$this->assertSame( [ '75', '100' ], array_column( $event[ Event::META_ROUTES ], 'distance_km' ) );
		$this->assertSame( [ '645', '695' ], array_column( $event[ Event::META_ROUTES ], 'price' ) );

		$this->assertStringStartsWith( '<p>75 eller 100 kilometer. Én dag, én nat og en udfordring, du sent vil glemme!</p>', $event['post_content'] );
	}

	/**
	 * The API calls carry the locale header, without which the API gives no
	 * Danish texts or DKK prices.
	 */
	public function test_api_requests_send_locale(): void {
		$this->assertSame( [ 'x-locale' => 'dk' ], $this->scraper->requests[ self::VARIANTS ] );
	}

	/**
	 * A march whose variants or page fail to load is skipped, not treated as
	 * removed.
	 */
	public function test_failed_request_skips_march_but_still_sees_it(): void {
		// Otherwise unpublish_stale_events() would draft them.
		unset( $this->scraper->pages[ self::VARIANTS ], $this->scraper->pages[ self::KBH_LONG ] );

		$events = $this->scraper->parse_list( Test_Scraper_Mammut::fixture( 'events.json' ) );

		$this->assertSame( [], $events );
		$this->assertContains( self::AARHUS, $this->scraper->seen_urls() );
		$this->assertContains( self::KBH_LONG, $this->scraper->seen_urls() );
	}

	/**
	 * A list that is not JSON gives no events and no seen URLs.
	 */
	public function test_broken_list_gives_no_events(): void {
		$scraper = new Test_Scraper_Mammut();

		$this->assertSame( [], $scraper->parse_list( '<html>Not JSON</html>' ) );
		$this->assertSame( [], $scraper->seen_urls() );
	}
}
