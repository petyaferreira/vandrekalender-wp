<?php

defined( 'ABSPATH' ) || exit;

/**
 * Scraper for day walks from opdagverden.dk (Opdag Verden).
 *
 * Opdag Verden is a Joomla site using the "Events Booking" (EB) extension. Day
 * walks are listed at /ture/dagsvandring as a table of cards, each linking to a
 * detail page under /ture/dagsvandring/<region>/<slug>-<id>. Every detail page
 * carries a consistent "Begivenhedsoversigt" (event summary) table with labelled
 * rows — Startdato (date + time), Pris, Det foregår (location) — so this scraper
 * parses those rows plus the og:title (title + distance) and og:image.
 *
 * The full tour description is members-only (behind a paywall), so only the
 * structured summary is scraped. Meeting points are landmark names ("Stevns
 * klint", "Æbelø"); DAWA's address autocomplete resolves them to the nearest
 * real address, giving approximate coordinates and the correct municipality.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Scraper_Opdagverden extends Vandrekalender_Scraper_Base {

	const BASE_URL      = 'https://www.opdagverden.dk';
	const SOURCE_URL    = 'https://www.opdagverden.dk/ture/dagsvandring';
	const SOURCE_NAME   = 'Opdag Verden';
	const ORGANISER_URL = 'https://www.opdagverden.dk/';

	/**
	 * Fetch the day-walk listing page.
	 *
	 * @return string HTML body.
	 */
	protected function fetch(): string {
		return $this->remote_get( self::SOURCE_URL );
	}

	/**
	 * Parse the listing: find event URLs, fetch each detail page, build events.
	 *
	 * @param string $html Raw HTML from fetch().
	 * @return array
	 */
	protected function parse( string $html ): array {
		if ( ! preg_match_all( '#/ture/dagsvandring/[a-z]+/[a-z0-9\-]+-\d+#', $html, $matches ) ) {
			return [];
		}

		$paths  = array_unique( $matches[0] );
		$events = [];

		foreach ( $paths as $path ) {
			$url = self::BASE_URL . $path;

			$this->mark_source_url_seen( $url );

			$page = $this->remote_get( $url );
			if ( '' === $page ) {
				continue;
			}

			$event = $this->parse_event( $page, $url );
			if ( null !== $event ) {
				$events[] = $event;
			}
		}

		return $events;
	}

	/**
	 * Parse a single event detail page into a canonical event array.
	 *
	 * @param string $html Raw HTML of the event page.
	 * @param string $url  The event page URL (dedup key).
	 * @return array|null Event array, or null if it lacks a title.
	 */
	private function parse_event( string $html, string $url ): ?array {
		// Title from og:title, minus the trailing " (1234)" event id EB appends.
		$title = '';
		if ( preg_match( '#<meta property="og:title" content="([^"]+)"#', $html, $title_match ) ) {
			$title = html_entity_decode( $title_match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$title = (string) preg_replace( '/\s*\(\d+\)\s*$/u', '', $title );
			// Source titles carry a stray comma before the distance, e.g.
			// "Svanninge Bakker, (18 km)" — tidy it to "Svanninge Bakker (18 km)".
			$title = (string) preg_replace( '/\s*,\s*\(/u', ' (', $title );
			$title = trim( (string) preg_replace( '/\s+/u', ' ', $title ), " \t\n\r\0\x0B," );
		}
		if ( '' === $title ) {
			return null;
		}

		// Labelled rows from the "Begivenhedsoversigt" (event summary) table.
		$props = $this->parse_summary_table( $html );

		// Date and start time: "22-08-2026 09:00 - 16:00" -> YYYY-MM-DD + HH:MM.
		$date       = '';
		$start_time = '';
		$startdato  = $props['Startdato'] ?? '';
		if ( preg_match( '/(\d{2})-(\d{2})-(\d{4})/', $startdato, $date_match ) ) {
			$date = sprintf( '%s-%s-%s', $date_match[3], $date_match[2], $date_match[1] );
		}
		if ( preg_match( '/(\d{1,2}):(\d{2})/', $startdato, $time_match ) ) {
			$start_time = sprintf( '%02d:%s', (int) $time_match[1], $time_match[2] );
		}

		// Distance from the title, e.g. "(21 km)" / "(25km)" -> 21 / 25.
		$distance = '';
		if ( preg_match( '/(\d+)\s*km/iu', $title, $dist_match ) ) {
			$distance = (string) (int) $dist_match[1];
		}

		// Price: a "Kr. 100" style amount becomes "100"; a free walk becomes "0".
		// Match a run that starts with a digit so the dot in "Kr." is not caught.
		$price = '';
		$pris  = $props['Pris'] ?? '';
		if ( preg_match( '/gratis|free/iu', $pris ) ) {
			$price = '0';
		} elseif ( preg_match( '/(\d[\d.]*)/', $pris, $price_match ) ) {
			$price = (string) (int) str_replace( '.', '', $price_match[1] );
		}

		// Meeting-point / location name ("Det foregår").
		$place = trim( $props['Det foregår'] ?? '' );

		// Single route per event: distance and time live here, not as flat meta.
		$routes = [
			[
				'id'          => '' !== $distance ? 'route-' . $distance : 'route-1',
				'distance_km' => $distance,
				'start_time'  => $start_time,
				'cutoff_time' => '',
				'price'       => $price,
			],
		];

		// Featured image from og:image.
		$image_url = '';
		if ( preg_match( '#<meta property="og:image" content="([^"]+)"#', $html, $image_match ) ) {
			$image_url = html_entity_decode( $image_match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		$event = [
			'post_title'                               => $title,
			'post_content'                             => '',
			'post_excerpt'                             => '',
			'featured_image_url'                       => $image_url,
			\Vandrekalender\Event::META_DATE           => $date,
			\Vandrekalender\Event::META_ROUTES         => $routes,
			\Vandrekalender\Event::META_PLACE_NAME     => $place,
			\Vandrekalender\Event::META_ADDRESS        => $place,
			\Vandrekalender\Event::META_ORGANISER_NAME => self::SOURCE_NAME,
			\Vandrekalender\Event::META_ORGANISER_URL  => self::ORGANISER_URL,
			\Vandrekalender\Event::META_SOURCE_URL     => $url,
			\Vandrekalender\Event::META_SOURCE_NAME    => self::SOURCE_NAME,
		];

		// Resolve coordinates from the landmark name. Meeting points here are
		// natural features ("Stevns Klint"), not street addresses, so DAWA's
		// place-name register is tried first and the coordinates are the
		// feature's representative point — approximate, but in the right place.
		$geo = $this->resolve_location( $title, $place );
		if ( null !== $geo ) {
			$event[ \Vandrekalender\Event::META_LAT ]          = $geo['lat'];
			$event[ \Vandrekalender\Event::META_LNG ]          = $geo['lng'];
			$event[ \Vandrekalender\Event::META_MUNICIPALITY ] = $geo['municipality'];
		} else {
			// No coordinates: the event still publishes (the map endpoint skips
			// events without a position) but would miss its region filter, since
			// region normally derives from the municipality. Fall back to the
			// region encoded in the source URL so it stays discoverable.
			$region = $this->region_from_url( $url );
			if ( '' !== $region ) {
				$event['tax_terms'] = [ \Vandrekalender\Event::TAX_REGION => [ $region ] ];
			}
		}

		return $event;
	}

	/**
	 * Parse the EB "Begivenhedsoversigt" table into a label => value map.
	 *
	 * Each row pairs a label cell (class "eb-event-property-label") with a value
	 * cell (class "eb-event-property-value"). Tags inside the value cell (e.g.
	 * the duration span) are stripped and whitespace collapsed.
	 *
	 * @param string $html Raw HTML of the event page.
	 * @return array<string,string> Map of row label to cleaned value.
	 */
	private function parse_summary_table( string $html ): array {
		$props = [];

		if ( ! preg_match_all(
			'#eb-event-property-label"[^>]*>(.*?)</td>.*?eb-event-property-value[^>]*>(.*?)</td>#us',
			$html,
			$rows,
			PREG_SET_ORDER
		) ) {
			return $props;
		}

		foreach ( $rows as $row ) {
			$label = trim( html_entity_decode( wp_strip_all_tags( $row[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			$value = html_entity_decode( wp_strip_all_tags( $row[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$value = trim( (string) preg_replace( '/\s+/u', ' ', $value ) );

			if ( '' !== $label ) {
				$props[ $label ] = $value;
			}
		}

		return $props;
	}

	/**
	 * Resolve coordinates and municipality for an event's landmark meeting point.
	 *
	 * Builds an ordered list of query candidates from the event title and the
	 * "Det foregår" value, then tries DAWA's place-name register for each, and
	 * finally the address autocomplete. The first hit wins.
	 *
	 * @param string $title Event title, e.g. "Møns Klint: Klintekongens Rige (15 km)".
	 * @param string $place The "Det foregår" location value.
	 * @return array|null Array of { lat, lng, municipality }, or null if unresolved.
	 */
	private function resolve_location( string $title, string $place ): ?array {
		$geocoder   = new Vandrekalender_Geocoder();
		$candidates = $this->geocode_candidates( $title, $place );

		foreach ( $candidates as $candidate ) {
			$geo = $geocoder->geocode_place( $candidate );
			if ( null !== $geo ) {
				return $geo;
			}
		}

		foreach ( $candidates as $candidate ) {
			$geo = $geocoder->geocode( $candidate );
			if ( null !== $geo ) {
				return $geo;
			}
		}

		return null;
	}

	/**
	 * Build an ordered, de-duplicated list of landmark query strings to geocode.
	 *
	 * The title's leading segment (before a colon, with any trailing "(21 km)" /
	 * "(1234)" removed) is the most reliable landmark; the "Det foregår" value is
	 * a fallback. Each is also offered in a cleaned form with tour words ("rundt",
	 * "dagstur") and secondary segments stripped.
	 *
	 * @param string $title Event title.
	 * @param string $place The "Det foregår" location value.
	 * @return string[] Ordered unique candidate strings.
	 */
	private function geocode_candidates( string $title, string $place ): array {
		$title_base = (string) preg_replace( '/\s*\([^)]*\)\s*/u', ' ', $title );
		$title_base = trim( (string) preg_split( '/:/u', $title_base )[0] );

		$candidates = [
			$title_base,
			$this->clean_place( $title_base ),
			$this->clean_place( $place ),
			$place,
		];

		$candidates = array_filter( array_map( 'trim', $candidates ) );

		return array_values( array_unique( $candidates ) );
	}

	/**
	 * Reduce a location string to its leading landmark name for geocoding.
	 *
	 * Drops parentheticals, keeps only the first segment before a comma, ampersand,
	 * slash or " og ", and strips trailing tour words like "rundt" or "dagstur".
	 *
	 * @param string $value Raw location or title string.
	 * @return string Cleaned landmark name.
	 */
	private function clean_place( string $value ): string {
		$value = (string) preg_replace( '/\([^)]*\)/u', ' ', $value );
		$value = (string) preg_split( '/[,&\/:]| og /u', $value )[0];
		$value = (string) preg_replace( '/\b(rundtur|rundt|rundsted|weekendtur|dagstur|tur)\b/iu', ' ', $value );

		return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
	}

	/**
	 * Derive a region taxonomy slug from the region segment in the source URL.
	 *
	 * Detail URLs are /ture/dagsvandring/<region>/<slug>-<id>, where <region> is
	 * Opdag Verden's own geographic grouping. This maps those to the site's five
	 * official regions. Used only as a fallback when geocoding fails, so the
	 * looser groupings ("jylland") only matter for the rare unresolved event.
	 *
	 * @param string $url Event detail URL.
	 * @return string Region slug (e.g. "syddanmark"), or empty string if unknown.
	 */
	private function region_from_url( string $url ): string {
		if ( ! preg_match( '#/ture/dagsvandring/([a-z]+)/#', $url, $match ) ) {
			return '';
		}

		$map = [
			'kobenhavn'   => 'hovedstaden',
			'sjaelland'   => 'sjaelland',
			'fyn'         => 'syddanmark',
			'sydjylland'  => 'syddanmark',
			'jylland'     => 'midtjylland',
			'midtjylland' => 'midtjylland',
			'vestjylland' => 'midtjylland',
			'nordjylland' => 'nordjylland',
		];

		return $map[ $match[1] ] ?? '';
	}
}
