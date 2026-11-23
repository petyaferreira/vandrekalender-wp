<?php
/**
 * DVL scraper on saved copies of the new dvl.dk (tests/fixtures/dvl/,
 * fetched 2026-11-22, #287): the tour search page with its embedded list of
 * 413 tours, and four tour pages:
 *
 * - Lunteholdets mandagstur (23 Nov): the "Turen er gratis for medlemmer"
 *   note in "Om turen", a meeting point with a transit note.
 * - Gå grønt gennem byen … gratis for alle (25 Nov): free by its title.
 * - Dragefestival på Fanø (16 Jun 2027, summer time): no lead paragraph.
 * - Efter-Pinsetur 2027 (22 May 2027): a paid trip with no free note.
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

	const LUNTE  = 'https://www.dvl.dk/ture/7820e612-4486-5a42-a62f-33251b94171e/lunteholdets-mandagstur';
	const GROENT = 'https://www.dvl.dk/ture/da249383-7e71-5392-ab20-fc04ad6b69eb/gaa-groent-gennem-byen-fra-carlsberg-st-til-broenshoej-torv-gratis-for-alle';
	const DRAGE  = 'https://www.dvl.dk/ture/db0b7694-0274-5eb1-96c6-f039fb3de826/dragefestival-paa-fanoe';
	const PINSE  = 'https://www.dvl.dk/ture/6a8de1cf-6798-56aa-9d6c-dced38284060/efter-pinsetur-2027-til-esbjerg-og-fanoe-turen-er-fuldtegnet-venteliste-oprettes';

	/**
	 * Scraper reading the fixtures.
	 *
	 * @var Test_Scraper_DVL
	 */
	private Test_Scraper_DVL $scraper;

	/**
	 * Events parsed from the saved search page, keyed by source URL.
	 *
	 * @var array<string, array>
	 */
	private array $events;

	/**
	 * Parse the saved search page with a fresh scraper.
	 */
	protected function setUp(): void {
		Vandrekalender_Geocoder::$lookups = [];

		$this->scraper = new Test_Scraper_DVL();
		$this->events  = array_column(
			$this->scraper->parse_list( Test_Scraper_DVL::fixture( 'soeg.html' ) ),
			null,
			Event::META_SOURCE_URL
		);
	}

	/**
	 * Every listed walk is seen; the four saved pages become events, the
	 * other tour pages "fail to load" and are skipped.
	 */
	public function test_every_walk_is_seen_and_saved_pages_become_events(): void {
		$this->assertSame( [ self::LUNTE, self::GROENT, self::PINSE, self::DRAGE ], array_keys( $this->events ) );

		// 413 listed, minus 3 member meetings, 1 course and the general
		// assembly typed as a walk.
		$this->assertCount( 408, $this->scraper->seen_urls() );
	}

	/**
	 * Member meetings, courses and general assemblies are not walks (#287).
	 */
	public function test_non_walks_are_skipped(): void {
		$titles = array_column( Vandrekalender_Scraper_DVL::listed_tours( Test_Scraper_DVL::fixture( 'soeg.html' ) ), 'title' );

		$this->assertNotContains( 'Laugsaften for medlemmer og gæster', $titles, 'medlemsmøde' );
		$this->assertNotContains( 'Outsitekurser', $titles, 'kursus' );
		$this->assertNotContains( 'Generalforsamling 2027 i DVL Silkeborg', $titles, 'typed as a walk' );
		$this->assertContains( 'Introduktionsmøde for nye medlemmer i Københavns afdlingen - TILMELDING', $titles, 'a meeting with a 6 km walk stays' );
	}

	/**
	 * Every field of a walk with the members' free note.
	 */
	public function test_tour_fields(): void {
		$event = $this->events[ self::LUNTE ];
		$route = $event[ Event::META_ROUTES ][0];

		$this->assertSame( 'Lunteholdets mandagstur', $event['post_title'] );
		$this->assertSame( '2026-11-23', $event[ Event::META_DATE ] );
		$this->assertSame( '10:00', $route['start_time'] );
		$this->assertSame( '6', $route['distance_km'] );
		$this->assertSame( '0', $route['price'], '"Turen er gratis for medlemmer"' );

		$this->assertSame( 'DVL Aarhus', $event[ Event::META_ORGANISER_NAME ] );
		$this->assertSame( 'https://www.dvl.dk/afdelinger/aarhus', $event[ Event::META_ORGANISER_URL ] );
		$this->assertSame( 'Dansk Vandrelaug', $event[ Event::META_SOURCE_NAME ] );

		$this->assertSame( 'Frederiks Kirke ved Oddervej. Bus 6A, 100', $event[ Event::META_ADDRESS ] );
		$this->assertSame( 'Frederiks Kirke ved Oddervej', $event[ Event::META_PLACE_NAME ], 'transit note cut off' );

		$this->assertSame( 56.111648, $event[ Event::META_LAT ] );
		$this->assertSame( 10.211695, $event[ Event::META_LNG ] );
		$this->assertSame( Vandrekalender_Geocoder::TEST_MUNICIPALITY, $event[ Event::META_MUNICIPALITY ] );

		$this->assertStringStartsWith( 'https://bpezyatntupjqzbuctws.supabase.co/', $event['featured_image_url'] );

		$this->assertSame( 'Rundtur i Marselisskoven.', $event['post_excerpt'] );
		$this->assertStringContainsString( 'Turene for Lunterne er rundture', $event['post_content'] );
		$this->assertStringNotContainsString( 'Normalpris', $event['post_content'], 'membership boilerplate removed' );
	}

	/**
	 * "Gratis for alle" makes an open walk free.
	 */
	public function test_open_walk_is_free(): void {
		$this->assertSame( '0', $this->events[ self::GROENT ][ Event::META_ROUTES ][0]['price'] );
		$this->assertSame( 'Carlsberg St.', $this->events[ self::GROENT ][ Event::META_PLACE_NAME ] );
	}

	/**
	 * A summer-time walk keeps the list's local time; with no lead paragraph
	 * the excerpt is the first line.
	 */
	public function test_summer_time_tour(): void {
		$event = $this->events[ self::DRAGE ];

		// The page says "kl. 09:45"; its JSON-LD 2027-06-16T09:45:00+02:00.
		$this->assertSame( '2027-06-16', $event[ Event::META_DATE ] );
		$this->assertSame( '09:45', $event[ Event::META_ROUTES ][0]['start_time'] );
		$this->assertSame( 'Færgelejet på Fanø', $event[ Event::META_PLACE_NAME ] );
		$this->assertStringStartsWith( 'Så er det tid til vi igen skal se de mange flotte drager', $event['post_excerpt'] );
		$this->assertSame( '0', $event[ Event::META_ROUTES ][0]['price'] );
	}

	/**
	 * A paid trip with no free note gets no price: "Pris ikke oplyst", not free.
	 */
	public function test_paid_trip_has_no_price(): void {
		$event = $this->events[ self::PINSE ];

		$this->assertSame( '', $event[ Event::META_ROUTES ][0]['price'] );
		$this->assertSame( '50', $event[ Event::META_ROUTES ][0]['distance_km'] );
		$this->assertSame( 'DVL Vestsjælland', $event[ Event::META_ORGANISER_NAME ] );
		$this->assertStringNotContainsString( 'Blå/4-5/50', $event['post_excerpt'], 'tour code removed' );
	}

	/**
	 * Without the free note a tour is free unless its own text names an
	 * amount (#287); "gratis" in the title wins over an amount (#285). The
	 * membership boilerplate's "147,50 kr." is not the tour's own price.
	 */
	public function test_free_unless_own_text_names_a_price(): void {
		$tour  = Vandrekalender_Scraper_DVL::listed_tours( Test_Scraper_DVL::fixture( 'soeg.html' ) )[0];
		$price = function ( string $title, string $about ) use ( $tour ): string {
			$tour['title'] = $title;
			$page          = '<section class="tur-detail-section tur-om"><p>' . $about . '</p></section>';
			return $this->scraper->build_event( $tour, $page )[ Event::META_ROUTES ][0]['price'];
		};

		$this->assertSame( '0', $price( 'Bynær tur i Haderslev', 'Vi går en tur hver tirsdag.' ), 'no note, no amount' );
		$this->assertSame( '0', $price( 'Bynær tur', 'Vi går en tur.<br>Du får første halvår til halv pris = 147,50 kr. (Normalpris 295 kr.).' ), 'boilerplate amount' );
		$this->assertSame( '', $price( 'Juleafslutning', 'Pris for julefrokosten er 225kr.' ), 'own amount' );
		$this->assertSame( '', $price( 'Gløggtur', 'Gløgg 20,- pr. person.' ), 'amount as "20,-"' );
		$this->assertSame( '0', $price( 'Tur til Stensballe', 'Pris for kørsel 10kr.<br>Turen er gratis for medlemmer.' ), 'the note wins' );
		$this->assertSame( '0', $price( 'Gå grønt gennem byen – gratis for alle', 'Kaffe 20 kr.' ), '"gratis" in the title wins' );
	}

	/**
	 * An old event moves to the new URL by meeting point only when exactly
	 * one old event on that day has the tour's pin.
	 */
	public function test_pick_same_pin(): void {
		$here  = [ 56.111648, 10.211695 ];
		$near  = [ 56.1117, 10.2117 ];
		$there = [ 55.0, 12.0 ];

		$this->assertSame(
			7,
			Vandrekalender_Scraper_DVL::pick_same_pin(
				[
					5 => $there,
					7 => $near,
				],
				...$here
			)
		);
		$this->assertSame( 0, Vandrekalender_Scraper_DVL::pick_same_pin( [ 5 => $there ], ...$here ), 'no pin there' );
		$this->assertSame(
			0,
			Vandrekalender_Scraper_DVL::pick_same_pin(
				[
					5 => $here,
					7 => $near,
				],
				...$here
			),
			'two at one spot'
		);
	}

	/**
	 * A page without the embedded list gives no events and no seen URLs, so
	 * the run is reported as broken and nothing is drafted.
	 */
	public function test_page_without_list_gives_no_events(): void {
		$scraper = new Test_Scraper_DVL();

		$this->assertSame( [], $scraper->parse_list( '<html>Søg ture</html>' ) );
		$this->assertSame( [], $scraper->seen_urls() );
	}
}
