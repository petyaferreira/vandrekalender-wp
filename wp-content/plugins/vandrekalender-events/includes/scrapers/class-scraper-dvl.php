<?php

defined( 'ABSPATH' ) || exit;

/**
 * Scraper for guided walks from dvl.dk (Dansk Vandrelaug).
 *
 * Since November 2026 dvl.dk is an Astro site (#287). The tour search page
 * /ture/soeg embeds every listed tour as JSON (`dvl-tour-search-embed`):
 * id, title, slug, local date and start time, distance, chapter, exact
 * meeting-point coordinates and image. That list is the index; each tour
 * page (/ture/<id>/<slug>) is then fetched for the meeting point (its
 * schema.org Event JSON-LD), the description and the free note in "Om turen".
 *
 * Every date of a repeating tour is its own tour with its own id, so the id
 * is part of the source URL. Meeting points are often station or landmark
 * names an address search cannot geocode, so coordinates come straight from
 * the list and only the municipality is reverse-geocoded.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Scraper_DVL extends Vandrekalender_Scraper_Base {

	const LIST_URL      = 'https://www.dvl.dk/ture/soeg';
	const TOUR_URL      = 'https://www.dvl.dk/ture/';
	const SOURCE_NAME   = 'Dansk Vandrelaug';
	const ORGANISER_URL = 'https://www.dvl.dk/';

	/**
	 * Listing types that are not walks: member meetings and courses. The
	 * calendar is for walks (Petya's decision on #287).
	 */
	const SKIP_TYPES = [ 'medlemsmoede', 'kursus' ];

	/**
	 * How close (in degrees, about 50 m) an old event's pin must be to a
	 * tour's to count as the same meeting point.
	 */
	const SAME_PIN = 0.0005;

	/**
	 * Fetch the tour search page, which embeds the list of tours.
	 *
	 * @return string HTML, or empty string on failure.
	 */
	protected function fetch(): string {
		return $this->remote_get( self::LIST_URL );
	}

	/**
	 * Read the embedded tour list, fetch each tour page, and build event arrays.
	 *
	 * @param string $html HTML of the tour search page.
	 * @return array
	 */
	protected function parse( string $html ): array {
		$tours = self::listed_tours( $html );

		// Move events stored under an older URL to the tour's URL before
		// upsert_event() looks them up: a tour whose slug changed on the new
		// site (same id), or one from the old WordPress site (/vandreture/
		// <slug>/, sometimes with "-2"). Every slug match goes first, then
		// the meeting-point match for edited titles, so a pin match never takes
		// a post that a slug match needs.
		foreach ( $tours as $tour ) {
			$this->adopt_legacy_source_url( $tour['url'], $tour['date'], '#^https://www\.dvl\.dk/ture/' . preg_quote( $tour['id'], '#' ) . '(/|$)#' );
			$this->adopt_legacy_source_url( $tour['url'], $tour['date'], '#^https://dvl\.dk/vandreture/' . preg_quote( $tour['slug'], '#' ) . '(-\d+)?/$#' );
		}
		foreach ( $tours as $tour ) {
			$this->adopt_by_meeting_point( $tour );
		}

		$events = [];
		foreach ( $tours as $tour ) {
			// A page that fails to load is skipped, not treated as removed.
			$this->mark_source_url_seen( $tour['url'] );

			$page = $this->remote_get( $tour['url'] );
			if ( '' !== $page ) {
				$events[] = $this->build_event( $tour, $page );
			}
		}

		return $events;
	}

	/**
	 * The walks in the search page's embedded list, without member meetings,
	 * courses and general assemblies.
	 *
	 * @param string $html HTML of the tour search page.
	 * @return array[] Each { id, slug, url, title, date, start_time, distance, chapter, chapter_slug, lat, lng, image }.
	 */
	public static function listed_tours( string $html ): array {
		if ( ! preg_match( '#<script type="application/json" id="dvl-tour-search-embed">(.*?)</script>#s', $html, $match ) ) {
			return [];
		}

		$items = json_decode( $match[1], true );
		if ( ! is_array( $items ) ) {
			return [];
		}

		$tours = [];
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$id    = (string) ( $item['id'] ?? '' );
			$title = trim( (string) preg_replace( '/\s+/u', ' ', (string) ( $item['title'] ?? '' ) ) );
			if ( ! preg_match( '/^[0-9a-f-]{36}$/', $id ) || '' === $title || isset( $tours[ $id ] ) ) {
				continue;
			}

			// A general assembly is sometimes typed as a walk ("Generalforsamling
			// 2027 i DVL Silkeborg", 0 km).
			$types = array_map( 'strval', (array) ( $item['types'] ?? [] ) );
			if ( array_intersect( $types, self::SKIP_TYPES ) || preg_match( '/generalforsamling/iu', $title ) ) {
				continue;
			}

			$slug = sanitize_title( (string) ( $item['slug'] ?? '' ) );
			$km   = (float) ( $item['km'] ?? 0 );
			$date = (string) ( $item['dateYmd'] ?? '' );
			$time = (string) ( $item['tidspunkt'] ?? '' );

			$tours[ $id ] = [
				'id'           => $id,
				'slug'         => $slug,
				'url'          => self::TOUR_URL . $id . ( '' !== $slug ? '/' . $slug : '' ),
				'title'        => $title,
				'date'         => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '',
				'start_time'   => preg_match( '/^\d{2}:\d{2}$/', $time ) ? $time : '',
				'distance'     => $km > 0 ? (string) $km : '',
				'chapter'      => trim( (string) ( $item['afdeling'] ?? '' ) ),
				'chapter_slug' => sanitize_title( (string) ( $item['afdelingSlug'] ?? '' ) ),
				'lat'          => (float) ( $item['lat'] ?? 0 ),
				'lng'          => (float) ( $item['lng'] ?? 0 ),
				'image'        => (string) ( $item['image'] ?? '' ),
			];
		}

		return array_values( $tours );
	}

	/**
	 * Build the event array from a listed tour and its tour page.
	 *
	 * @param array  $tour One entry from listed_tours().
	 * @param string $page HTML of the tour page.
	 * @return array
	 */
	public function build_event( array $tour, string $page ): array {
		$about       = self::about_markup( $page );
		$description = self::description( $about );

		// DVL tours are free for members (every tour page says so). Most say
		// it in "Om turen" ("Turen er gratis for medlemmer"), open walks say
		// "gratis for alle", sometimes only in the title (#285). A tour without
		// that note is free too unless its own text names an amount ("Deltager-
		// gebyr 1.950 kr.", "Buffeten koster 249 kr"): then the price is
		// unknown rather than a wrong "free" (Petya's decision on #287). The
		// membership boilerplate ("halv pris = 147,50 kr.") is not the tour's
		// own text, nor is the template's "Gratis tur for medlemmer" box.
		$is_free = preg_match( '/Turen er gratis|gratis for (medlemmer|alle)|gratis tilbud/iu', $about )
			|| preg_match( '/\bgratis\b/iu', $tour['title'] )
			|| ! preg_match( '/\d\s*(kr\b|kroner|,-)/iu', $description['text'] );

		$routes = [
			[
				'id'          => 'route-' . ( '' !== $tour['distance'] ? $tour['distance'] : '0' ),
				'distance_km' => $tour['distance'],
				'start_time'  => $tour['start_time'],
				'cutoff_time' => '',
				'price'       => $is_free ? '0' : '',
			],
		];

		// The meeting point often appends transit notes after a period
		// ("Frederiks Kirke ved Oddervej. Bus 6A, 100"), so the place name is
		// the part before the first sentence break. A final dot stays: it is
		// usually an abbreviation ("Carlsberg St.").
		$address = self::meeting_point( $page );
		$parts   = preg_split( '/\.\s+/u', $address );
		$place   = trim( (string) $parts[0] );

		$event = [
			'post_title'                               => $tour['title'],
			'post_content'                             => $description['content'],
			'post_excerpt'                             => $description['excerpt'],
			'featured_image_url'                       => $tour['image'],
			\Vandrekalender\Event::META_DATE           => $tour['date'],
			\Vandrekalender\Event::META_ROUTES         => $routes,
			\Vandrekalender\Event::META_PLACE_NAME     => $place,
			\Vandrekalender\Event::META_ADDRESS        => $address,
			\Vandrekalender\Event::META_ORGANISER_NAME => '' !== $tour['chapter'] ? 'DVL ' . $tour['chapter'] : self::SOURCE_NAME,
			\Vandrekalender\Event::META_ORGANISER_URL  => '' !== $tour['chapter_slug'] ? self::ORGANISER_URL . 'afdelinger/' . $tour['chapter_slug'] : self::ORGANISER_URL,
			\Vandrekalender\Event::META_SOURCE_URL     => $tour['url'],
			\Vandrekalender\Event::META_SOURCE_NAME    => self::SOURCE_NAME,
		];

		// The list's coordinates are the exact meeting-point pin; only the
		// municipality (for the region taxonomy) needs a reverse lookup. It
		// returns '' when the lookup fails, and '' is not written, so the
		// municipality a post already has is kept.
		if ( 0.0 !== $tour['lat'] && 0.0 !== $tour['lng'] ) {
			$event[ \Vandrekalender\Event::META_LAT ] = $tour['lat'];
			$event[ \Vandrekalender\Event::META_LNG ] = $tour['lng'];

			$municipality = ( new Vandrekalender_Geocoder() )->municipality_from_coords( $tour['lat'], $tour['lng'] );
			if ( '' !== $municipality ) {
				$event[ \Vandrekalender\Event::META_MUNICIPALITY ] = $municipality;
			}
		}

		return $event;
	}

	/**
	 * The markup of the tour's "Om turen" section, or '' when missing.
	 *
	 * @param string $page HTML of the tour page.
	 * @return string
	 */
	private static function about_markup( string $page ): string {
		if ( preg_match( '#<section class="tur-detail-section tur-om"[^>]*>(.*?)</section>#s', $page, $match ) ) {
			return (string) preg_replace( '#<h2[^>]*>.*?</h2>#s', '', $match[1] );
		}

		return '';
	}

	/**
	 * The meeting point: the location name in the tour page's schema.org
	 * Event JSON-LD.
	 *
	 * @param string $page HTML of the tour page.
	 * @return string
	 */
	private static function meeting_point( string $page ): string {
		if ( ! preg_match_all( '#<script type="application/ld\+json">(.*?)</script>#s', $page, $matches ) ) {
			return '';
		}

		foreach ( $matches[1] as $json ) {
			$data = json_decode( $json, true );
			if ( is_array( $data ) && 'Event' === ( $data['@type'] ?? '' ) ) {
				$name = $data['location']['name'] ?? '';
				return is_string( $name ) ? trim( (string) preg_replace( '/\s+/u', ' ', $name ) ) : '';
			}
		}

		return '';
	}

	/**
	 * The description and excerpt from the "Om turen" markup.
	 *
	 * Content is every line of the lead and the rich text, without the
	 * membership boilerplate ("Turen er gratis for medlemmer …", "halv pris").
	 * The excerpt is the lead (else the first line) with DVL's tour code
	 * (e.g. "Blå/4-5/50") removed.
	 *
	 * @param string $about Markup from about_markup().
	 * @return array Array of { content: string, excerpt: string, text: string }.
	 */
	private static function description( string $about ): array {
		$lead = '';
		if ( preg_match( '#<p class="[^"]*tur-detail-description__lead[^"]*"[^>]*>(.*?)</p>#s', $about, $lead_match ) ) {
			$lead = self::clean_text( $lead_match[1] );
		}

		$boilerplate = '/gratis for medlemmer|melder dig ind i Dansk Vandrelaug|Normalpris|halv pris|½ pris/iu';
		$lines       = [];

		foreach ( preg_split( '#<br\s*/?>|</p>#i', $about ) as $raw ) {
			$line = self::clean_text( $raw );
			if ( '' === $line || preg_match( $boilerplate, $line ) ) {
				continue;
			}
			$lines[] = $line;
		}

		$content = '';
		foreach ( array_slice( $lines, 0, 10 ) as $line ) {
			$content .= '<p>' . esc_html( $line ) . '</p>';
		}

		$excerpt = '' !== $lead ? $lead : ( $lines[0] ?? '' );
		$excerpt = trim( (string) preg_replace( '#\b[\p{L}]+/[\d\-]+/[\d\-,]+\b#u', '', $excerpt ) );
		$excerpt = wp_trim_words( $excerpt, 40, '…' );

		return [
			'content' => $content,
			'excerpt' => $excerpt,
			'text'    => implode( ' ', $lines ),
		];
	}

	/**
	 * Move an event stored under an old WordPress URL whose slug no longer
	 * matches (the title was edited) to the tour's URL: same date, old URL
	 * format, and the same meeting-point pin.
	 *
	 * @param array $tour One entry from listed_tours().
	 * @return void
	 */
	private function adopt_by_meeting_point( array $tour ): void {
		if ( 0.0 === $tour['lat'] && 0.0 === $tour['lng'] ) {
			return;
		}

		$candidates = [];
		foreach ( $this->same_day_posts( $tour['date'] ) as $post_id ) {
			$url = (string) get_post_meta( $post_id, \Vandrekalender\Event::META_SOURCE_URL, true );
			if ( $url === $tour['url'] ) {
				return;
			}
			if ( str_starts_with( $url, 'https://dvl.dk/vandreture/' ) ) {
				$candidates[ $post_id ] = [
					(float) get_post_meta( $post_id, \Vandrekalender\Event::META_LAT, true ),
					(float) get_post_meta( $post_id, \Vandrekalender\Event::META_LNG, true ),
				];
			}
		}

		$post_id = self::pick_same_pin( $candidates, $tour['lat'], $tour['lng'] );
		if ( $post_id > 0 ) {
			update_post_meta( $post_id, \Vandrekalender\Event::META_SOURCE_URL, $tour['url'] );
		}
	}

	/**
	 * The one candidate whose pin is the tour's meeting point, or 0 when none
	 * or several are: two old events at one spot on one day cannot be told
	 * apart, so neither is moved.
	 *
	 * @param array<int, float[]> $candidates Post ID => [ lat, lng ].
	 * @param float               $lat        The tour's latitude.
	 * @param float               $lng        The tour's longitude.
	 * @return int
	 */
	public static function pick_same_pin( array $candidates, float $lat, float $lng ): int {
		$hits = [];
		foreach ( $candidates as $post_id => $pin ) {
			if ( abs( $pin[0] - $lat ) < self::SAME_PIN && abs( $pin[1] - $lng ) < self::SAME_PIN ) {
				$hits[] = (int) $post_id;
			}
		}

		return 1 === count( $hits ) ? $hits[0] : 0;
	}

	/**
	 * Strip tags and entities from a markup fragment into single-line text.
	 *
	 * @param string $fragment Markup fragment.
	 * @return string Plain text.
	 */
	private static function clean_text( string $fragment ): string {
		// Space out tag boundaries so "<p>a</p><p>b</p>" does not glue "ab".
		$fragment = str_replace( '<', ' <', $fragment );
		$text     = html_entity_decode( wp_strip_all_tags( $fragment, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}
