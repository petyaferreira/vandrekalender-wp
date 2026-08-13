<?php

defined( 'ABSPATH' ) || exit;

/**
 * Prints a schema.org/Event JSON-LD <script> tag in the <head> of singular
 * event pages, for search-engine crawlers (Google's Event rich result).
 *
 * This is the class's *only* effect. It is read-only and side-effect free: it
 * reads existing event data and echoes one <script type="application/ld+json">
 * tag into wp_head. It never writes to the database, never changes posts, meta,
 * or taxonomies, registers no fields, and alters nothing about how events look
 * or behave for visitors. Remove this class and the events are unchanged —
 * only the invisible crawler tag disappears.
 *
 * One canonical Event block is emitted per upcoming event. Past events output
 * nothing — expired Event markup is index noise and trips Search Console
 * errors, so silence is deliberately preferred over an incomplete block.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Event_Schema {

	/**
	 * Constructor — hooks the output late in wp_head.
	 */
	public function __construct() {
		add_action( 'wp_head', [ $this, 'render' ], 99 );
	}

	/**
	 * Print the Event JSON-LD block, when the event qualifies.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! is_singular( \Vandrekalender\Event::CUSTOMPOSTTYPE ) ) {
			return;
		}

		$post_id = get_queried_object_id();

		if ( ! $post_id ) {
			return;
		}

		// Single read of the whole meta row — cheaper and clearer than one
		// get_post_meta() call per field.
		$meta = get_post_meta( $post_id );

		$schema = $this->build_schema( $post_id, $meta );

		if ( empty( $schema ) ) {
			return;
		}

		// wp_json_encode escapes correctly for a <script> context. Running the
		// result through esc_html()/wp_kses() would corrupt the JSON, so the
		// output is intentionally left unescaped here.
		echo '<script type="application/ld+json">'
			. wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode escapes for a script context; further escaping breaks the JSON.
			. '</script>' . "\n";
	}

	/**
	 * Build the pruned Event schema array, or an empty array when the event
	 * should produce no output.
	 *
	 * @param int   $post_id The event post ID.
	 * @param array $meta    Raw meta row from get_post_meta( $post_id ).
	 * @return array The schema array, or [] to output nothing.
	 */
	private function build_schema( int $post_id, array $meta ): array {
		$tz   = wp_timezone();
		$date = trim( (string) $this->meta_single( $meta, \Vandrekalender\Event::META_DATE ) );

		// No parseable start date → no output. An incomplete Event is worse than
		// none. createFromFormat rolls invalid values over (2026-13-40 becomes a
		// real date), so round-trip the value to reject them.
		$start_day = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, $tz );

		if ( ! $start_day || $start_day->format( 'Y-m-d' ) !== $date ) {
			return [];
		}

		$routes = $this->meta_single( $meta, \Vandrekalender\Event::META_ROUTES );
		$routes = is_array( $routes ) ? $routes : [];

		// The event has no end field, so treat it as ended at the close of its
		// start day. A today event whose start time has already passed is still
		// "not ended" and keeps its schema until midnight.
		$ended  = $start_day->setTime( 23, 59, 59 ) < current_datetime();
		$should = ! $ended;

		/**
		 * Filters whether the Event schema should be output for this post.
		 *
		 * @param bool $should  Whether to output the schema.
		 * @param int  $post_id The event post ID.
		 */
		$should = apply_filters( 'vandrekalender_event_schema_should_output', $should, $post_id );

		if ( ! $should ) {
			return [];
		}

		$location = $this->build_location( $post_id, $meta );

		// location is required by Google. An event with no usable location gets
		// no block rather than an invalid one.
		if ( empty( $location ) ) {
			return [];
		}

		$schema = [
			'@context'            => 'https://schema.org',
			'@type'               => 'Event',
			'name'                => get_the_title( $post_id ),
			'startDate'           => $this->start_date( $start_day, $routes ),
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'eventStatus'         => 'https://schema.org/EventScheduled',
			'description'         => $this->description( $post_id ),
			'url'                 => get_permalink( $post_id ),
			'image'               => get_the_post_thumbnail_url( $post_id, 'full' ) ? get_the_post_thumbnail_url( $post_id, 'full' ) : '',
			'location'            => $location,
			'organizer'           => $this->build_organizer( $meta ),
			'offers'              => $this->build_offers( $post_id, $meta, $routes ),
		];

		$schema = $this->prune( $schema );

		/**
		 * Filters the assembled Event schema array before output.
		 *
		 * @param array $schema  The pruned schema array.
		 * @param int   $post_id The event post ID.
		 */
		return apply_filters( 'vandrekalender_event_schema', $schema, $post_id );
	}

	/**
	 * Build the startDate value: ISO 8601 with offset when a time is known,
	 * otherwise the bare date. Uses the earliest route start time.
	 *
	 * @param DateTimeImmutable $start_day Midnight of the event's start day.
	 * @param array             $routes    Event routes.
	 * @return string
	 */
	private function start_date( DateTimeImmutable $start_day, array $routes ): string {
		$best = null;

		foreach ( $routes as $route ) {
			$time = isset( $route['start_time'] ) ? trim( (string) $route['start_time'] ) : '';

			if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)$/', $time ) ) {
				continue;
			}

			list( $hour, $minute ) = array_map( 'intval', explode( ':', $time ) );
			$minutes               = ( $hour * 60 ) + $minute;

			if ( null === $best || $minutes < $best['minutes'] ) {
				$best = [
					'minutes' => $minutes,
					'hour'    => $hour,
					'minute'  => $minute,
				];
			}
		}

		if ( null === $best ) {
			// Date only, no invented time.
			return $start_day->format( 'Y-m-d' );
		}

		return $start_day->setTime( $best['hour'], $best['minute'], 0 )->format( 'c' );
	}

	/**
	 * Build the location Place node, or [] when no usable location exists.
	 *
	 * Name uses the venue when present, then the municipality, then a cleaned
	 * address string as a last resort. The municipality also drives a structured
	 * PostalAddress; no street is parsed out of the raw address string.
	 *
	 * @param int   $post_id The event post ID.
	 * @param array $meta    Raw meta row.
	 * @return array
	 */
	private function build_location( int $post_id, array $meta ): array {
		$place        = trim( (string) $this->meta_single( $meta, \Vandrekalender\Event::META_PLACE_NAME ) );
		$municipality = trim( (string) $this->meta_single( $meta, \Vandrekalender\Event::META_MUNICIPALITY ) );
		$address      = $this->clean_address( (string) $this->meta_single( $meta, \Vandrekalender\Event::META_ADDRESS ) );

		$name = '' !== $place ? $place : ( '' !== $municipality ? $municipality : $address );

		if ( '' === $name ) {
			return [];
		}

		$location = [
			'@type' => 'Place',
			'name'  => $name,
		];

		if ( '' !== $municipality ) {
			/**
			 * Filters the schema addressCountry (ISO 3166-1 alpha-2).
			 *
			 * @param string $country The country code.
			 * @param int    $post_id The event post ID.
			 */
			$country = apply_filters( 'vandrekalender_event_schema_address_country', 'DK', $post_id );

			$location['address'] = [
				'@type'           => 'PostalAddress',
				'addressLocality' => $municipality,
				'addressCountry'  => $country,
			];
		}

		$lat = $this->meta_single( $meta, \Vandrekalender\Event::META_LAT );
		$lng = $this->meta_single( $meta, \Vandrekalender\Event::META_LNG );

		if ( is_numeric( $lat ) && is_numeric( $lng ) && ! ( 0.0 === (float) $lat && 0.0 === (float) $lng ) ) {
			$location['geo'] = [
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) $lng,
			];
		}

		return $location;
	}

	/**
	 * Build the organizer node, or [] when no organiser name is set.
	 *
	 * @param array $meta Raw meta row.
	 * @return array
	 */
	private function build_organizer( array $meta ): array {
		$name = trim( (string) $this->meta_single( $meta, \Vandrekalender\Event::META_ORGANISER_NAME ) );

		if ( '' === $name ) {
			return [];
		}

		$organizer = [
			'@type' => 'Organization',
			'name'  => $name,
		];

		$url = trim( (string) $this->meta_single( $meta, \Vandrekalender\Event::META_ORGANISER_URL ) );

		if ( '' !== $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
			$organizer['url'] = $url;
		}

		return $organizer;
	}

	/**
	 * Build the offers node, or [] when no real price data exists.
	 *
	 * A recorded price of 0 is reliable "free" and is emitted. An absence of any
	 * recorded price is unknown, and offers is omitted rather than guessed.
	 *
	 * @param int   $post_id The event post ID.
	 * @param array $meta    Raw meta row.
	 * @param array $routes  Event routes.
	 * @return array
	 */
	private function build_offers( int $post_id, array $meta, array $routes ): array {
		$prices = [];

		foreach ( $routes as $route ) {
			if ( isset( $route['price'] ) && '' !== (string) $route['price'] && is_numeric( $route['price'] ) ) {
				$prices[] = (float) $route['price'];
			}
		}

		if ( empty( $prices ) ) {
			return [];
		}

		$source_url = trim( (string) $this->meta_single( $meta, \Vandrekalender\Event::META_SOURCE_URL ) );
		$url        = ( '' !== $source_url && filter_var( $source_url, FILTER_VALIDATE_URL ) ) ? $source_url : get_permalink( $post_id );

		return [
			'@type'         => 'Offer',
			'price'         => rtrim( rtrim( sprintf( '%.2f', min( $prices ) ), '0' ), '.' ),
			'priceCurrency' => 'DKK',
			'availability'  => 'https://schema.org/InStock',
			'url'           => $url,
		];
	}

	/**
	 * Plain-text event description, entity-decoded and trimmed to ~300 chars at
	 * a word boundary. Uses the manual excerpt when set, else an excerpt derived
	 * from the content.
	 *
	 * @param int $post_id The event post ID.
	 * @return string
	 */
	private function description( int $post_id ): string {
		$text = html_entity_decode( wp_strip_all_tags( get_the_excerpt( $post_id ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

		if ( mb_strlen( $text ) <= 300 ) {
			return $text;
		}

		$cut   = mb_substr( $text, 0, 300 );
		$space = mb_strrpos( $cut, ' ' );

		if ( false !== $space && $space > 0 ) {
			$cut = mb_substr( $cut, 0, $space );
		}

		return rtrim( $cut, ' ,.;:–—-' ) . '…';
	}

	/**
	 * Collapse empty segments in a raw DAWA address string, e.g.
	 * "Hvidovrevej 280, , 2650 Hvidovre" → "Hvidovrevej 280, 2650 Hvidovre".
	 *
	 * @param string $address Raw address string.
	 * @return string
	 */
	private function clean_address( string $address ): string {
		$address = (string) preg_replace( '/\s*(,\s*)+/', ', ', $address );

		return trim( $address, ", \t\n\r\0\x0B" );
	}

	/**
	 * Read a single meta value out of a full get_post_meta( $id ) row.
	 *
	 * The whole-row form of get_post_meta() returns values still serialized —
	 * unlike the single-key form — so array meta (event_routes) arrives as a
	 * string. maybe_unserialize() restores it and leaves scalars untouched.
	 *
	 * @param array  $meta Raw meta row.
	 * @param string $key  Meta key.
	 * @return mixed The first value, or '' when absent.
	 */
	private function meta_single( array $meta, string $key ): mixed {
		return isset( $meta[ $key ][0] ) ? maybe_unserialize( $meta[ $key ][0] ) : '';
	}

	/**
	 * Recursively drop null, empty-string, and empty-array values so no blank
	 * keys or all-blank sub-objects reach the output.
	 *
	 * @param array $data Schema fragment.
	 * @return array
	 */
	private function prune( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$value        = $this->prune( $value );
				$data[ $key ] = $value;
			}

			if ( null === $value || '' === $value || ( is_array( $value ) && [] === $value ) ) {
				unset( $data[ $key ] );
			}
		}

		return $data;
	}
}
