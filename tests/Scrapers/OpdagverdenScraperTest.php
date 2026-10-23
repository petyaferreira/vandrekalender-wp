<?php
/**
 * Opdag Verden scraper on saved copies of opdagverden.dk
 * (tests/fixtures/opdagverden/, fetched 2026-10-21): both pages of the
 * day-walk listing, three trip pages, the route page of one, and the redirect
 * of one Google Maps link.
 *
 * The three trips cover the three ways to the pin: Hald Sø has a Maps link in
 * its start line (exact), Endelave has none but a route page (approximate),
 * Farum Sø has neither (the region centre, approximate). The other listed
 * trips have no saved page, so they behave like pages that failed to load.
 *
 * When opdagverden.dk changes its markup, save fresh copies and update the
 * expected values here on purpose (docs/contributing/tests.md).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Event;

/**
 * Opdag Verden scraper on the saved copies.
 */
class OpdagverdenScraperTest extends TestCase {

	const HALD     = 'https://www.opdagverden.dk/faellesture/hald-so-rundt-dollerup-bakker-21-km';
	const ENDELAVE = 'https://www.opdagverden.dk/faellesture/endelave-kaninoen-21-km';
	const FARUM    = 'https://www.opdagverden.dk/faellesture/farum-so-rundt';

	/**
	 * Point of the Endelave route page (`hikeRoute` latitude, longitude).
	 */
	const ENDELAVE_ROUTE_POINT = '55.7540832,10.2704696';

	/**
	 * Scraper reading the fixtures.
	 *
	 * @var Test_Scraper_Opdagverden
	 */
	private Test_Scraper_Opdagverden $scraper;

	/**
	 * Events scraped from the saved pages, keyed by source URL.
	 *
	 * @var array<string, array>
	 */
	private array $events;

	/**
	 * Scrape the saved pages with a fresh scraper. The Endelave route point
	 * lies on the island, in Horsens Kommune (Midtjylland).
	 */
	protected function setUp(): void {
		Vandrekalender_Geocoder::$lookups        = [];
		Vandrekalender_Geocoder::$municipalities = [ self::ENDELAVE_ROUTE_POINT => 'Horsens' ];

		$this->scraper = new Test_Scraper_Opdagverden();
		$this->events  = array_column( $this->scraper->scrape(), null, Event::META_SOURCE_URL );
	}

	/**
	 * Clear the municipalities set for this test class.
	 */
	protected function tearDown(): void {
		Vandrekalender_Geocoder::$municipalities = [];
	}

	/**
	 * A trip with several dates becomes one event per date of its page, keyed
	 * by the trip URL and the date.
	 */
	public function test_one_event_per_date(): void {
		// In listing order. The trip pages list more dates than the listing does.
		$this->assertSame(
			[
				self::FARUM . '#2027-03-27',
				self::FARUM . '#2027-10-03',
				self::HALD . '#2026-11-14',
				self::HALD . '#2027-03-20',
				self::HALD . '#2027-10-16',
				self::ENDELAVE . '#2026-10-24',
				self::ENDELAVE . '#2027-05-22',
				self::ENDELAVE . '#2027-08-21',
			],
			array_keys( $this->events )
		);

		foreach ( [ '2026-11-14', '2027-03-20', '2027-10-16' ] as $date ) {
			$event = $this->events[ self::HALD . '#' . $date ];
			$this->assertSame( $date, $event[ Event::META_DATE ] );
			$this->assertSame( '100', $event[ Event::META_ROUTES ][0]['price'], "price of $date" );
		}
	}

	/**
	 * Every field of a trip whose start line links to Google Maps: the pin is
	 * the point in the link's redirect, exact.
	 */
	public function test_trip_with_maps_link(): void {
		$event = $this->events[ self::HALD . '#2026-11-14' ];

		$this->assertSame( 'Hald Sø rundt og Dollerup Bakker (21 km)', $event['post_title'] );
		$this->assertSame( '21', $event[ Event::META_ROUTES ][0]['distance_km'] );

		// "Start: Kl.10 ved <a href="https://maps.app.goo.gl/…">p-pladsen ved Hald Ege Hundeskov&nbsp;</a>".
		$this->assertSame( '10:00', $event[ Event::META_ROUTES ][0]['start_time'] );
		$this->assertSame( 'P-pladsen ved Hald Ege Hundeskov', $event[ Event::META_PLACE_NAME ] );
		$this->assertSame( '', $event[ Event::META_ADDRESS ] );

		// The redirect's place is "!3d56.378306!4d9.3331913", not the map view "@56.3780485,9.3295379".
		$this->assertSame( 56.378306, $event[ Event::META_LAT ] );
		$this->assertSame( 9.3331913, $event[ Event::META_LNG ] );
		$this->assertFalse( $event[ Event::META_LOCATION_APPROXIMATE ] );
		$this->assertSame( Vandrekalender_Geocoder::TEST_MUNICIPALITY, $event[ Event::META_MUNICIPALITY ] );

		$this->assertSame( 'Smuk, varieret og kuperet rundtur ved Hald Ege, Hald Sø og i Dollerup Bakker', $event['post_excerpt'], 'the hero text' );
		$this->assertStringContainsString( 'Hald Ruin', $event['post_content'] );
		$this->assertSame( 'https://www.opdagverden.dk/storage/trips/images/hald-so-rundt-dollerup-bakker-21-km_1787650310.jpg', $event['featured_image_url'] );

		$this->assertSame( 'Opdag Verden', $event[ Event::META_SOURCE_NAME ] );
		$this->assertSame( 'Opdag Verden', $event[ Event::META_ORGANISER_NAME ] );
		$this->assertSame( 'https://www.opdagverden.dk/', $event[ Event::META_ORGANISER_URL ] );
	}

	/**
	 * A trip without a Maps link but with a route page in its region: the pin
	 * is the route's point, marked approximate.
	 */
	public function test_trip_with_route_page(): void {
		$event = $this->events[ self::ENDELAVE . '#2027-05-22' ];

		$this->assertSame( 'Endelave: Kaninoen (21 km)', $event['post_title'] );

		// "Start: Kl. 9.15 ved Snaptun Havn".
		$this->assertSame( '09:15', $event[ Event::META_ROUTES ][0]['start_time'] );
		$this->assertSame( 'Snaptun Havn', $event[ Event::META_PLACE_NAME ] );

		$this->assertSame( 55.7540832, $event[ Event::META_LAT ] );
		$this->assertSame( 10.2704696, $event[ Event::META_LNG ] );
		$this->assertTrue( $event[ Event::META_LOCATION_APPROXIMATE ] );
		$this->assertSame( 'Horsens', $event[ Event::META_MUNICIPALITY ] );
	}

	/**
	 * A trip with neither: the pin is its region's centre, approximate, and
	 * the region is set directly because there is no municipality.
	 */
	public function test_trip_without_link_or_route(): void {
		$event = $this->events[ self::FARUM . '#2027-03-27' ];

		$this->assertSame( 'Farum Sø rundt (11 km)', $event['post_title'] );

		// "Start: Kl.10 fra P-pladsen ved Farum station".
		$this->assertSame( '10:00', $event[ Event::META_ROUTES ][0]['start_time'] );
		$this->assertSame( 'P-pladsen ved Farum station', $event[ Event::META_PLACE_NAME ] );

		// Category "storkobenhavn" → Hovedstaden.
		$this->assertSame( Vandrekalender_Municipalities::REGION_CENTRES['hovedstaden'], [ $event[ Event::META_LAT ], $event[ Event::META_LNG ] ] );
		$this->assertTrue( $event[ Event::META_LOCATION_APPROXIMATE ] );
		$this->assertSame( '', $event[ Event::META_MUNICIPALITY ] );
		$this->assertSame( [ Event::TAX_REGION => [ 'hovedstaden' ] ], $event['tax_terms'] );
	}

	/**
	 * A route point outside the trip's region is not used: the pin falls
	 * back to the region centre, with a warning.
	 */
	public function test_route_point_outside_region_falls_back(): void {
		Vandrekalender_Geocoder::$municipalities = [ self::ENDELAVE_ROUTE_POINT => 'Furesø' ];

		$scraper = new Test_Scraper_Opdagverden();
		$events  = array_column( $scraper->scrape(), null, Event::META_SOURCE_URL );
		$event   = $events[ self::ENDELAVE . '#2027-05-22' ];

		$this->assertSame( Vandrekalender_Municipalities::REGION_CENTRES['midtjylland'], [ $event[ Event::META_LAT ], $event[ Event::META_LNG ] ] );
		$this->assertTrue( $event[ Event::META_LOCATION_APPROXIMATE ] );
		$this->assertContains( 'The route point of "Endelave: Kaninoen (21 km)" is outside its region, so the pin is placed in the middle of the region.', $scraper->take_warnings() );
	}

	/**
	 * A trip page that fails to load is skipped, but its listed dates are
	 * still seen, so the stale sweep does not draft them.
	 */
	public function test_unreachable_trip_page_is_skipped_but_still_seen(): void {
		// Stevns Klint is on the second listing page and has no saved page.
		$this->assertArrayNotHasKey( 'https://www.opdagverden.dk/faellesture/stevns-klint-21-km#2026-11-07', $this->events );
		$this->assertNotEmpty(
			array_filter(
				$this->scraper->seen_urls(),
				fn( string $url ): bool => str_starts_with( $url, 'https://www.opdagverden.dk/faellesture/stevns-klint-21-km#' )
			)
		);
	}

	/**
	 * A listing page that fails to load gives no trips at all, not a short
	 * list.
	 */
	public function test_failed_listing_page_gives_nothing(): void {
		$scraper = new Test_Scraper_Opdagverden();
		unset( $scraper->pages[ Test_Scraper_Opdagverden::SOURCE_URL . '?page=2' ] );

		$this->assertSame( [], $scraper->scrape() );
		$this->assertSame( [], $scraper->seen_urls() );
	}
}
