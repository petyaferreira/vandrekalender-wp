<?php

defined( 'ABSPATH' ) || exit;

/**
 * Scraper for guided walks from dvl.dk (Dansk Vandrelaug).
 *
 * The tour listing at /vandreture/ is JavaScript-rendered, but the site
 * exposes a public JSON feed at /wp-json/dvl/v1/maps/data with every upcoming
 * tour: title, exact meeting-point coordinates, and a popup snippet linking to
 * the tour page. That feed is the index; each tour page (server-rendered, one
 * consistent "hike" template) is then fetched for the details — date and time
 * from the add-to-calendar link, distance, organising chapter, meeting point,
 * and description.
 *
 * Meeting points are often station or landmark names ("Birkerød St.") that
 * DAWA cannot geocode, so coordinates come straight from the feed and only the
 * municipality is reverse-geocoded from them.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Scraper_DVL extends Vandrekalender_Scraper_Base {

	const MAPS_DATA_URL = 'https://dvl.dk/wp-json/dvl/v1/maps/data';
	const SOURCE_NAME   = 'Dansk Vandrelaug';
	const ORGANISER_URL = 'https://dvl.dk/';

	/**
	 * Fetch the maps data feed listing all upcoming tours.
	 *
	 * @return string JSON body, or empty string on failure.
	 */
	protected function fetch(): string {
		return $this->remote_get( self::MAPS_DATA_URL );
	}

	/**
	 * Parse the feed, fetch each tour page, and build event arrays.
	 *
	 * Each feed item is a tuple: [ title, lat, lng, popup HTML ]. The popup
	 * HTML carries the tour page URL.
	 *
	 * @param string $json JSON body from fetch().
	 * @return array
	 */
	protected function parse( string $json ): array {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return [];
		}

		$events = [];
		$seen   = [];

		foreach ( $data as $item ) {
			if ( ! is_array( $item ) || count( $item ) < 4 ) {
				continue;
			}

			if ( ! preg_match( '#href="(https://dvl\.dk/vandreture/[^"]+)"#', (string) $item[3], $link ) ) {
				continue;
			}

			$url = html_entity_decode( $link[1], ENT_QUOTES, 'UTF-8' );
			if ( isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ] = true;
			$this->mark_source_url_seen( $url );

			$page = $this->remote_get( $url );
			if ( '' === $page ) {
				continue;
			}

			$event = $this->parse_tour( $page, $url, (float) $item[1], (float) $item[2] );
			if ( null !== $event ) {
				$events[] = $event;
			}
		}

		return $events;
	}

	/**
	 * Parse a single tour page into a canonical event array.
	 *
	 * @param string $html Raw HTML of the tour page.
	 * @param string $url  The tour page URL (dedup key).
	 * @param float  $lat  Meeting-point latitude from the maps feed.
	 * @param float  $lng  Meeting-point longitude from the maps feed.
	 * @return array|null Event array, or null if it lacks a title.
	 */
	private function parse_tour( string $html, string $url, float $lat, float $lng ): ?array {
		// Title from og:title, minus the trailing " - DVL".
		$title = '';
		if ( preg_match( '#<meta property="og:title" content="([^"]+)"#', $html, $title_match ) ) {
			$title = html_entity_decode( $title_match[1], ENT_QUOTES, 'UTF-8' );
			$title = trim( (string) preg_replace( '/\s*-\s*DVL\s*$/u', '', $title ) );
		}
		if ( '' === $title ) {
			return null;
		}

		// Date and start time from the add-to-calendar link, which carries the
		// full UTC start/end ("dates=20260718T080000Z/20260718T110000Z").
		// Converted to the site timezone this matches the local date and the
		// visible "10:00 - 13:00" time. Multi-day tours use the start date.
		$date       = '';
		$start_time = '';
		if ( preg_match( '#dates=(\d{8})T(\d{6})Z(?:%2F|/)\d{8}T\d{6}Z#i', $html, $cal ) ) {
			$start = DateTimeImmutable::createFromFormat(
				'Ymd His',
				$cal[1] . ' ' . $cal[2],
				new DateTimeZone( 'UTC' )
			);
			if ( false !== $start ) {
				$start      = $start->setTimezone( wp_timezone() );
				$date       = $start->format( 'Y-m-d' );
				$start_time = $start->format( 'H:i' );
			}
		}

		// Distance from the sidebar's "hike-distance" span ("7 km").
		$distance = '';
		if ( preg_match( '#class="hike-distance">\s*([\d.,]+)\s*km#u', $html, $dist_match ) ) {
			$km       = (float) str_replace( ',', '.', $dist_match[1] );
			$distance = $km > 0 ? (string) $km : '';
		}

		// Day walks carry a "Turen er gratis for medlemmer" note — price 0 keeps
		// the derived event_is_free flag accurate. Pages without it (typically
		// paid multi-day vandreferier) get no price rather than a wrong one.
		$is_free = (bool) preg_match( '/Turen er gratis|gratis for medlemmer/iu', $html );

		$routes = [
			[
				'id'          => 'route-' . ( '' !== $distance ? $distance : '0' ),
				'distance_km' => $distance,
				'start_time'  => $start_time,
				'cutoff_time' => '',
				'price'       => $is_free ? '0' : '',
			],
		];

		// Organising chapter from the "Arrangør" sidebar row ("Aarhus Afd.").
		$organiser_name = self::SOURCE_NAME;
		if ( preg_match( '#Arrangør</h5>\s*</div>\s*<div class="hike-row">\s*([^<]+)#u', $html, $arr_match ) ) {
			$chapter = trim( html_entity_decode( $arr_match[1], ENT_QUOTES, 'UTF-8' ) );
			$chapter = trim( (string) preg_replace( '/\s+afd\.?\s*$/iu', '', $chapter ) );
			if ( '' !== $chapter ) {
				$organiser_name = 'DVL ' . $chapter;
			}
		}

		// Chapter page URL from the article's hike_organizer-{slug} class.
		$organiser_url = self::ORGANISER_URL;
		if ( preg_match( '#hike_organizer-([a-z0-9\-]+)#', $html, $slug_match ) ) {
			$organiser_url = 'https://dvl.dk/' . $slug_match[1] . '/';
		}

		// Meeting point from the "Mødested" sidebar row. The full text often
		// appends transit notes after a period ("Tiset kirke. Bus 17. …"), so
		// the place name is the part before the first sentence break.
		$address = '';
		$place   = '';
		if ( preg_match( '#Mødested</h5>\s*</div>\s*<div class="hike-row">\s*([^<]+)#u', $html, $meet_match ) ) {
			$address = trim( html_entity_decode( $meet_match[1], ENT_QUOTES, 'UTF-8' ) );
			$parts   = preg_split( '/\.\s+/u', $address );
			$place   = trim( (string) $parts[0], " .\t" );
		}

		$description = $this->extract_description( $html );

		// og:image is the tour's own featured image.
		$image_url = '';
		if ( preg_match( '#<meta property="og:image" content="([^"]+)"#', $html, $image_match ) ) {
			$image_url = html_entity_decode( $image_match[1], ENT_QUOTES, 'UTF-8' );
		}

		$event = [
			'post_title'                               => $title,
			'post_content'                             => $description['content'],
			'post_excerpt'                             => $description['excerpt'],
			'featured_image_url'                       => $image_url,
			\Vandrekalender\Event::META_DATE           => $date,
			\Vandrekalender\Event::META_ROUTES         => $routes,
			\Vandrekalender\Event::META_PLACE_NAME     => $place,
			\Vandrekalender\Event::META_ADDRESS        => $address,
			\Vandrekalender\Event::META_ORGANISER_NAME => $organiser_name,
			\Vandrekalender\Event::META_ORGANISER_URL  => $organiser_url,
			\Vandrekalender\Event::META_SOURCE_URL     => $url,
			\Vandrekalender\Event::META_SOURCE_NAME    => self::SOURCE_NAME,
		];

		// The feed's coordinates are the exact meeting-point pin; only the
		// municipality (for the region taxonomy) needs a DAWA lookup.
		if ( 0.0 !== $lat && 0.0 !== $lng ) {
			$event[ \Vandrekalender\Event::META_LAT ] = $lat;
			$event[ \Vandrekalender\Event::META_LNG ] = $lng;

			$municipality = ( new Vandrekalender_Geocoder() )->municipality_from_coords( $lat, $lng );
			if ( '' !== $municipality ) {
				$event[ \Vandrekalender\Event::META_MUNICIPALITY ] = $municipality;
			}
		}

		return $event;
	}

	/**
	 * Extract the tour description from the article body.
	 *
	 * Content is every substantial paragraph between the article header and
	 * the membership boilerplate ("Turen er gratis for medlemmer …"), which is
	 * skipped along with the sign-up section. The excerpt is the header's
	 * subtitle with DVL's tour code (e.g. "Grøn/3-4/7") removed.
	 *
	 * @param string $html Raw HTML of the tour page.
	 * @return array Array of { content: string, excerpt: string }.
	 */
	private function extract_description( string $html ): array {
		$article = $html;
		if ( preg_match( '#<article[^>]*>(.*?)</article>#s', $html, $article_match ) ) {
			$article = $article_match[1];
		}

		// Header subtitle → excerpt basis; drop it from the body parse.
		$subtitle = '';
		if ( preg_match( '#</h1>\s*<h3>(.*?)</h3>#s', $article, $subtitle_match ) ) {
			$subtitle = $this->clean_text( $subtitle_match[1] );
			// Strip the tour code: colour/pace/km, e.g. "Grøn/3-4/7".
			$subtitle = trim( (string) preg_replace( '#\b[\p{L}]+/[\d\-]+/[\d\-,]+\b#u', '', $subtitle ) );
		}
		$article = (string) preg_replace( '#^.*?</header>#s', '', $article );

		$paragraphs  = [];
		$boilerplate = '/gratis for medlemmer|melder dig ind i Dansk Vandrelaug|Normalpris/iu';

		if ( preg_match_all( '#<p[^>]*>(.*?)</p>#s', $article, $para_matches ) ) {
			foreach ( $para_matches[1] as $raw ) {
				$paragraph = $this->clean_text( $raw );
				if ( '' === $paragraph || preg_match( $boilerplate, $paragraph ) ) {
					continue;
				}
				$paragraphs[] = $paragraph;
				if ( count( $paragraphs ) >= 10 ) {
					break;
				}
			}
		}

		$content = '';
		foreach ( $paragraphs as $paragraph ) {
			$content .= '<p>' . esc_html( $paragraph ) . '</p>';
		}

		$excerpt = '' !== $subtitle ? $subtitle : ( ! empty( $paragraphs ) ? $paragraphs[0] : '' );
		$excerpt = wp_trim_words( $excerpt, 40, '…' );

		return [
			'content' => $content,
			'excerpt' => $excerpt,
		];
	}

	/**
	 * Strip tags and entities from a markup fragment into single-line text.
	 *
	 * @param string $fragment Markup fragment.
	 * @return string Plain text.
	 */
	private function clean_text( string $fragment ): string {
		// Space out tag boundaries so "<p>a</p><p>b</p>" does not glue "ab".
		$fragment = str_replace( '<', ' <', $fragment );
		$text     = html_entity_decode( wp_strip_all_tags( $fragment, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}
}
