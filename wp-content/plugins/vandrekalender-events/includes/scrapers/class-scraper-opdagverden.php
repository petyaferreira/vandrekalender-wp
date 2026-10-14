<?php

defined( 'ABSPATH' ) || exit;

/**
 * Scraper for day walks from opdagverden.dk (Opdag Verden).
 *
 * The site is a Laravel app with Inertia (since autumn 2026): every page
 * carries its data as JSON in the `data-page` attribute of the app element.
 * Day walks are listed at /faellesture/dagsvandringer (paginated). Each trip
 * has a detail page /faellesture/<slug> with the description, the meeting
 * point (`location`) and its upcoming dates, each with its own price. A trip
 * walked on several dates becomes one event per date, keyed by
 * /faellesture/<slug>#<date>.
 *
 * The meeting point is the "Start: Kl.10 ved …" line of the description
 * (the trip's `location` is only a label for the area). Its pin comes from,
 * in order: the Google Maps link many start lines carry (exact), the point
 * of the trip's linked route page (the route's area, not the meeting
 * point), or the centre of the trip's region. The last two are marked
 * approximate (`event_location_approximate`).
 *
 * @package Vandrekalender
 */
class Vandrekalender_Scraper_Opdagverden extends Vandrekalender_Scraper_Base {

	const BASE_URL      = 'https://www.opdagverden.dk';
	const SOURCE_URL    = 'https://www.opdagverden.dk/faellesture/dagsvandringer';
	const SOURCE_NAME   = 'Opdag Verden';
	const ORGANISER_URL = 'https://www.opdagverden.dk/';

	/**
	 * Fetch every page of the day-walk listing and return its trips as JSON.
	 *
	 * @return string JSON array of trip objects, or empty string on failure.
	 */
	protected function fetch(): string {
		$trips = [];
		$page  = 1;

		do {
			$props = $this->page_props( $this->remote_get( self::SOURCE_URL . '?page=' . $page ) );
			if ( ! isset( $props['trips']['data'] ) || ! is_array( $props['trips']['data'] ) ) {
				// A failed page must not look like a short list, or the
				// stale sweep would draft the trips on the missing pages.
				return '';
			}

			$trips     = array_merge( $trips, $props['trips']['data'] );
			$last_page = (int) ( $props['trips']['last_page'] ?? 1 );
			++$page;
		} while ( $page <= $last_page && $page <= 10 );

		return (string) wp_json_encode( $trips );
	}

	/**
	 * Build one event per upcoming date of every listed trip.
	 *
	 * @param string $html JSON from fetch().
	 * @return array
	 */
	protected function parse( string $html ): array {
		$trips = json_decode( $html, true );
		if ( ! is_array( $trips ) ) {
			return [];
		}

		$events = [];

		foreach ( $trips as $listed ) {
			if ( ! is_array( $listed ) || empty( $listed['slug'] ) || empty( $listed['upcoming_dates'] ) ) {
				continue;
			}

			$trip_url = self::BASE_URL . '/faellesture/' . $listed['slug'];

			$props = $this->page_props( $this->remote_get( $trip_url ) );
			if ( ! isset( $props['trip'] ) || ! is_array( $props['trip'] ) ) {
				// Detail page failed: mark the listed dates, so they are not
				// mistaken for removed events. Otherwise run() marks the
				// events returned below, which leaves out cancelled dates,
				// so the stale sweep drafts those.
				foreach ( $listed['upcoming_dates'] as $date ) {
					$this->mark_source_url_seen( $trip_url . '#' . ( $date['start_date'] ?? '' ) );
				}
				continue;
			}

			foreach ( $this->parse_trip( $props['trip'], $trip_url ) as $event ) {
				$events[] = $event;
			}
		}

		return $events;
	}

	/**
	 * Read the Inertia page data (the `data-page` JSON) of a page.
	 *
	 * @param string $html Raw HTML of the page.
	 * @return array The page's props, or empty array if missing.
	 */
	private function page_props( string $html ): array {
		if ( ! preg_match( '/\sdata-page="([^"]*)"/', $html, $match ) ) {
			return [];
		}

		$page = json_decode( html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), true );

		return is_array( $page['props'] ?? null ) ? $page['props'] : [];
	}

	/**
	 * Build the events for a trip, one per upcoming date.
	 *
	 * @param array  $trip     The `trip` prop of the detail page.
	 * @param string $trip_url The trip's page URL.
	 * @return array Event arrays.
	 */
	private function parse_trip( array $trip, string $trip_url ): array {
		// Source titles carry a stray comma before the distance, e.g.
		// "Svanninge Bakker, (18 km)" — tidy it to "Svanninge Bakker (18 km)".
		$title = html_entity_decode( (string) ( $trip['title'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$title = (string) preg_replace( '/\s*,\s*\(/u', ' (', $title );
		$title = trim( (string) preg_replace( '/\s+/u', ' ', $title ), " \t\n\r\0\x0B," );
		if ( '' === $title ) {
			return [];
		}

		$description = (string) ( $trip['description'] ?? '' );
		$text        = html_entity_decode( wp_strip_all_tags( $description ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Distance from the title, e.g. "(21 km)" / "(25km)" -> 21 / 25.
		$distance = '';
		if ( preg_match( '/(\d+)\s*km/iu', $title, $dist_match ) ) {
			$distance = (string) (int) $dist_match[1];
		}

		// Start time from the description: "Start: Kl.10 ved ..." / "Start: kl. 9.30".
		$start_time = '';
		if ( preg_match( '/Start\s*:?\s*kl\.?\s*(\d{1,2})(?:[.:](\d{2}))?/iu', $text, $time_match ) ) {
			$start_time = sprintf( '%02d:%s', (int) $time_match[1], $time_match[2] ?? '00' );
		}

		// Meeting point from the start line; the trip's location label is
		// only a fallback ("Blixen ruten (folehave skov) rundsted").
		$meeting = $this->meeting_point( $description );
		$place   = '' !== $meeting['name']
			? $meeting['name']
			: trim( html_entity_decode( (string) ( $trip['location']['name'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		$excerpt = trim( (string) ( $trip['hero']['description'] ?? '' ) );
		if ( '' === $excerpt ) {
			$excerpt = wp_trim_words( $text, 40, '…' );
		}

		// Resolved once per trip: every date meets at the same place.
		$region   = $this->region_from_categories( is_array( $trip['categories'] ?? null ) ? $trip['categories'] : [] );
		$location = $this->locate( $title, $meeting['maps_url'], (string) ( $trip['hike_route']['slug'] ?? '' ), $region );
		$slug     = (string) ( $trip['slug'] ?? '' );
		$events   = [];

		foreach ( is_array( $trip['upcoming_dates'] ?? null ) ? $trip['upcoming_dates'] : [] as $date_row ) {
			$date = (string) ( $date_row['start_date'] ?? '' );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || 'cancelled' === ( $date_row['status'] ?? '' ) ) {
				continue;
			}

			// Non-member price of this date; a free walk becomes "0", and no
			// published price stays empty rather than looking free.
			$raw_price = $date_row['price'] ?? $trip['price'] ?? null;
			$price     = is_numeric( $raw_price ) ? (string) (int) round( (float) $raw_price ) : '';

			$url = $trip_url . '#' . $date;

			$event = [
				'post_title'                               => $title,
				'post_content'                             => $description,
				'post_excerpt'                             => $excerpt,
				'featured_image_url'                       => (string) ( $trip['image'] ?? '' ),
				\Vandrekalender\Event::META_DATE           => $date,
				// Single route per event: distance and time live here, not as flat meta.
				\Vandrekalender\Event::META_ROUTES         => [
					[
						'id'          => '' !== $distance ? 'route-' . $distance : 'route-1',
						'distance_km' => $distance,
						'start_time'  => $start_time,
						'cutoff_time' => '',
						'price'       => $price,
					],
				],
				\Vandrekalender\Event::META_PLACE_NAME     => $place,
				// No street address: the meeting point is a car park, a
				// station or a harbour, shown as the place name.
				\Vandrekalender\Event::META_ADDRESS        => '',
				\Vandrekalender\Event::META_ORGANISER_NAME => self::SOURCE_NAME,
				\Vandrekalender\Event::META_ORGANISER_URL  => self::ORGANISER_URL,
				\Vandrekalender\Event::META_SOURCE_URL     => $url,
				\Vandrekalender\Event::META_SOURCE_NAME    => self::SOURCE_NAME,
			];

			if ( null !== $location ) {
				$event[ \Vandrekalender\Event::META_LAT ]                  = $location['lat'];
				$event[ \Vandrekalender\Event::META_LNG ]                  = $location['lng'];
				$event[ \Vandrekalender\Event::META_MUNICIPALITY ]         = $location['municipality'];
				$event[ \Vandrekalender\Event::META_LOCATION_APPROXIMATE ] = $location['approximate'];
			}

			if ( ( null === $location || '' === $location['municipality'] ) && '' !== $region ) {
				// No municipality. Region normally derives from it; use the
				// trip's region category so the event stays in the filters.
				$event['tax_terms'] = [ \Vandrekalender\Event::TAX_REGION => [ $region ] ];
			}

			// Each date used to be its own page at
			// /ture/dagsvandring/<region>/<slug>-<id>.
			$this->adopt_legacy_source_url(
				$url,
				$date,
				'#^https://www\.opdagverden\.dk/ture/dagsvandring/[a-z]+/' . preg_quote( $slug, '#' ) . '-\d+$#'
			);

			$events[] = $event;
		}

		return $events;
	}

	/**
	 * Read the meeting point from the description's start line.
	 *
	 * The line reads "Start: Kl.10 ved Rungsted station" or "Start: Kl. 10 på
	 * <a href="https://maps.app.goo.gl/…">P-pladsen Hasmark strand</a>", and
	 * sometimes runs on into "Slut: …" in the same paragraph.
	 *
	 * @param string $description Description HTML.
	 * @return array{name: string, maps_url: string} The meeting point, and
	 *               its Google Maps link if it has one ('' when missing).
	 */
	private function meeting_point( string $description ): array {
		$point = [
			'name'     => '',
			'maps_url' => '',
		];

		if ( ! preg_match( '#Start(?:\s|<[^>]+>|&nbsp;|:)*kl\.?.*?(?=</p>|<strong>\s*Slut|$)#isu', $description, $line ) ) {
			return $point;
		}

		// The first link with visible text; some lines have an empty one first.
		if ( preg_match_all( '#<a\s[^>]*href="(https://maps\.app\.goo\.gl/[^"]+)"[^>]*>(.*?)</a>#isu', $line[0], $links, PREG_SET_ORDER ) ) {
			foreach ( $links as $link ) {
				if ( '' !== $this->plain_text( $link[2] ) ) {
					$point['maps_url'] = $link[1];
					break;
				}
			}
		}

		$name = $this->plain_text( $line[0] );
		$name = (string) preg_replace( '/^Start\s*:?\s*kl\.?\s*\d{1,2}(?:[.:]\d{2})?\s*/iu', '', $name );
		$name = (string) preg_replace( '/^(?:[-–]\s*)?(?:(?:ved|på|fra)\s+)?/iu', '', $name );
		$name = trim( $name, ' .,;:-–' );

		$point['name'] = mb_strtoupper( mb_substr( $name, 0, 1 ) ) . mb_substr( $name, 1 );

		return $point;
	}

	/**
	 * Strip tags and entities from an HTML fragment and collapse whitespace.
	 *
	 * @param string $html HTML fragment.
	 * @return string Plain text.
	 */
	private function plain_text( string $html ): string {
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', $text ) );
	}

	/**
	 * Find the pin for a trip, exact when possible, otherwise approximate.
	 *
	 * 1. The Google Maps link of the start line: the exact meeting point.
	 * 2. The point of the trip's route page: somewhere on the route, so only
	 *    used when it lies in the trip's region (one route point is in the
	 *    wrong part of Jutland).
	 * 3. The centre of the trip's region, so the walk still shows on the map.
	 *
	 * @param string $title      Trip title, for the Scraper Log.
	 * @param string $maps_url   Google Maps short link of the start line, or ''.
	 * @param string $route_slug Slug of the linked route page, or ''.
	 * @param string $region     Region slug from the trip's categories, or ''.
	 * @return array|null Array of { lat, lng, municipality, approximate }, or
	 *                    null if not even the region is known.
	 */
	private function locate( string $title, string $maps_url, string $route_slug, string $region ): ?array {
		$geocoder = new Vandrekalender_Geocoder();

		$point = '' !== $maps_url ? $this->maps_link_coordinates( $maps_url ) : null;
		if ( null !== $point ) {
			return [
				'lat'          => $point[0],
				'lng'          => $point[1],
				'municipality' => $geocoder->municipality_from_coords( $point[0], $point[1] ),
				'approximate'  => false,
			];
		}

		$point = '' !== $route_slug ? $this->route_coordinates( $route_slug ) : null;
		if ( null !== $point ) {
			$municipality = $geocoder->municipality_from_coords( $point[0], $point[1] );
			$in_region    = Vandrekalender_Municipalities::region_map()[ mb_strtolower( $municipality ) ] ?? '';

			if ( '' !== $municipality && ( '' === $region || $in_region === $region ) ) {
				return [
					'lat'          => $point[0],
					'lng'          => $point[1],
					'municipality' => $municipality,
					'approximate'  => true,
				];
			}

			$this->record_warning(
				/* translators: %s: trip title. */
				sprintf( __( 'The route point of "%s" is outside its region, so the pin is placed in the middle of the region.', 'vandrekalender-events' ), $title )
			);
		}

		if ( isset( Vandrekalender_Municipalities::REGION_CENTRES[ $region ] ) ) {
			return [
				'lat'          => Vandrekalender_Municipalities::REGION_CENTRES[ $region ][0],
				'lng'          => Vandrekalender_Municipalities::REGION_CENTRES[ $region ][1],
				'municipality' => '',
				'approximate'  => true,
			];
		}

		$this->record_warning(
			/* translators: %s: trip title. */
			sprintf( __( '"%s" has no meeting point link, route point or region, so it gets no pin.', 'vandrekalender-events' ), $title )
		);

		return null;
	}

	/**
	 * Coordinates of a Google Maps short link (maps.app.goo.gl).
	 *
	 * The link redirects to a google.com/maps URL that carries the place's
	 * coordinates as "!3d<lat>!4d<lng>" (or the map view's "@<lat>,<lng>").
	 * Only the redirect is read, not the page. Cached for a month, since a
	 * short link always points to the same place.
	 *
	 * @param string $url Short link.
	 * @return array|null [ lat, lng ] in Denmark, or null.
	 */
	private function maps_link_coordinates( string $url ): ?array {
		$cache_key = 'vk_ov_maps_' . md5( $url );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_head(
			$url,
			[
				'timeout'     => 15,
				'redirection' => 0,
				'user-agent'  => 'Vandrekalender/1.0 (+https://allevandreture.dk)',
			]
		);
		$location = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_header( $response, 'location' );

		if ( preg_match( '/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $location, $m ) || preg_match( '/@(-?\d+\.\d+),(-?\d+\.\d+)/', $location, $m ) ) {
			$point = [ (float) $m[1], (float) $m[2] ];
			if ( $this->in_denmark( $point ) ) {
				set_transient( $cache_key, $point, MONTH_IN_SECONDS );
				return $point;
			}
		}

		$this->record_warning(
			/* translators: %s: Google Maps link. */
			sprintf( __( 'Could not read the coordinates of the meeting point link %s.', 'vandrekalender-events' ), $url )
		);

		return null;
	}

	/**
	 * The point of a route page (/ruter/<slug>), somewhere on the route.
	 *
	 * @param string $slug Route slug, from the trip's `hike_route`.
	 * @return array|null [ lat, lng ] in Denmark, or null.
	 */
	private function route_coordinates( string $slug ): ?array {
		$props = $this->page_props( $this->remote_get( self::BASE_URL . '/ruter/' . rawurlencode( $slug ) ) );
		$lat   = $props['hikeRoute']['latitude'] ?? null;
		$lng   = $props['hikeRoute']['longitude'] ?? null;

		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return null;
		}

		$point = [ (float) $lat, (float) $lng ];

		return $this->in_denmark( $point ) ? $point : null;
	}

	/**
	 * Whether a point lies within Denmark's bounding box (incl. Bornholm).
	 *
	 * @param array $point [ lat, lng ].
	 * @return bool
	 */
	private function in_denmark( array $point ): bool {
		return $point[0] >= 54.5 && $point[0] <= 57.8 && $point[1] >= 8.0 && $point[1] <= 15.2;
	}

	/**
	 * Derive a region taxonomy slug from the trip's region category.
	 *
	 * Trips carry Opdag Verden's own geographic grouping as a category
	 * ("storkobenhavn", "midtjylland", ...). This maps those to the site's
	 * five official regions. Used to check a route's point, for the
	 * approximate pin, and as the region when the pin has no municipality.
	 *
	 * @param array $categories The trip's categories, each with a `slug`.
	 * @return string Region slug (e.g. "syddanmark"), or empty string if unknown.
	 */
	private function region_from_categories( array $categories ): string {
		$map = [
			'storkobenhavn' => 'hovedstaden',
			'kobenhavn'     => 'hovedstaden',
			'nordsjaelland' => 'hovedstaden',
			'bornholm'      => 'hovedstaden',
			'sjaelland'     => 'sjaelland',
			'fyn'           => 'syddanmark',
			'sydjylland'    => 'syddanmark',
			'jylland'       => 'midtjylland',
			'midtjylland'   => 'midtjylland',
			'vestjylland'   => 'midtjylland',
			'nordjylland'   => 'nordjylland',
		];

		foreach ( $categories as $category ) {
			$slug = (string) ( $category['slug'] ?? '' );
			if ( isset( $map[ $slug ] ) ) {
				return $map[ $slug ];
			}
		}

		return '';
	}
}
