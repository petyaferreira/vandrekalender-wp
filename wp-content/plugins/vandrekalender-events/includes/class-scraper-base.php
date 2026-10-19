<?php

defined( 'ABSPATH' ) || exit;

/**
 * Base class for all event scrapers.
 *
 * @package Vandrekalender
 */
abstract class Vandrekalender_Scraper_Base {

	/**
	 * Source URLs seen at the source during the current run, keyed by URL.
	 *
	 * @var array
	 */
	protected array $seen_source_urls = [];

	/**
	 * Problems that did not stop the run, for the Scraper Log, keyed by message.
	 *
	 * @var array<string, int>
	 */
	protected array $warnings = [];

	/**
	 * Why the source gave no events in the current run, or '' if it gave some.
	 *
	 * @var string
	 */
	protected string $source_problem = '';

	/**
	 * Fetch raw HTML from the event source.
	 *
	 * @return string Raw HTML body.
	 */
	abstract protected function fetch(): string;

	/**
	 * Parse HTML into an array of event data arrays.
	 *
	 * @param string $html Raw HTML from fetch().
	 * @return array Array of event data arrays.
	 */
	abstract protected function parse( string $html ): array;

	/**
	 * Fetch, parse, and upsert all events from the source.
	 *
	 * @return int Number of events created or updated.
	 */
	public function run(): int {
		$this->seen_source_urls = [];
		$this->source_problem   = '';

		$html   = $this->fetch();
		$events = $this->parse( $html );
		$count  = 0;

		foreach ( $events as $event ) {
			$this->mark_source_url_seen( (string) ( $event[ \Vandrekalender\Event::META_SOURCE_URL ] ?? '' ) );
			if ( $this->upsert_event( $event ) ) {
				++$count;
			}
		}

		// Every source has always listed upcoming events, so finding none
		// almost always means its site moved or changed and the scraper needs
		// fixing. Shown in the Scraper Log and emailed by the nightly run.
		if ( '' === $html ) {
			$this->source_problem = __( 'Could not load the source. Its site may be down, or its address has changed.', 'vandrekalender-events' );
		} elseif ( empty( $this->seen_source_urls ) ) {
			$this->source_problem = __( 'The source loaded, but no events were found in it. Its site may have changed.', 'vandrekalender-events' );
		}
		if ( '' !== $this->source_problem ) {
			$this->record_warning( $this->source_problem );
		}

		$this->unpublish_stale_events();

		return $count;
	}

	/**
	 * Why the last run found no events at the source, or '' if it found some.
	 *
	 * Read by Vandrekalender_Scraper_Scheduler::execute(), which emails an
	 * alert when a source stops working (Vandrekalender_Scraper_Alerts).
	 *
	 * @return string
	 */
	public function source_problem(): string {
		return $this->source_problem;
	}

	/**
	 * Return and clear the warnings recorded since the last call.
	 *
	 * Read by Vandrekalender_Scraper_Scheduler::execute() after each scraper,
	 * next to the geocoder's issues.
	 *
	 * @return string[] One line per distinct message, with a count when repeated.
	 */
	public function take_warnings(): array {
		$lines = [];

		foreach ( $this->warnings as $message => $count ) {
			$lines[] = $count > 1 ? sprintf( '%s (%d×)', $message, $count ) : $message;
		}

		$this->warnings = [];
		return $lines;
	}

	/**
	 * Record a problem that does not stop the run.
	 *
	 * @param string $message Message for the Scraper Log.
	 * @return void
	 */
	protected function record_warning( string $message ): void {
		$this->warnings[ $message ] = ( $this->warnings[ $message ] ?? 0 ) + 1;
	}

	/**
	 * Record a source URL as still present at the source during this run.
	 *
	 * Scrapers should call this for every URL they find in the listing,
	 * before fetching the detail page, so that a temporarily unreachable
	 * detail page is not mistaken for a removed event.
	 *
	 * @param string $url Source URL.
	 * @return void
	 */
	protected function mark_source_url_seen( string $url ): void {
		if ( '' !== $url ) {
			$this->seen_source_urls[ $url ] = true;
		}
	}

	/**
	 * Draft published events that have disappeared from the source.
	 *
	 * Every scraper's listing covers all upcoming events at its source, so an
	 * upcoming event whose source URL was not seen in the current run has
	 * been cancelled or removed. Such events are drafted rather than deleted:
	 * the source-URL dedup in upsert_event() matches drafts, so a re-listed
	 * event is republished by the next run instead of duplicated.
	 *
	 * Past events are left alone — they drop out of "upcoming" listings
	 * naturally — and claimed events stay under organiser control. If the run
	 * saw no URLs at all (failed fetch or empty listing), nothing is drafted.
	 *
	 * @return void
	 */
	protected function unpublish_stale_events(): void {
		if ( empty( $this->seen_source_urls ) || ! defined( static::class . '::SOURCE_NAME' ) ) {
			return;
		}

		$candidates = get_posts(
			[
				'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => \Vandrekalender\Event::META_SOURCE_NAME,
						'value' => (string) constant( static::class . '::SOURCE_NAME' ),
					],
					[
						'key'   => \Vandrekalender\Event::META_SOURCE,
						'value' => 'scraped',
					],
					[
						'key'     => \Vandrekalender\Event::META_DATE,
						'value'   => current_time( 'Y-m-d' ),
						'compare' => '>=',
					],
				],
			]
		);

		foreach ( $candidates as $post_id ) {
			$url = (string) get_post_meta( $post_id, \Vandrekalender\Event::META_SOURCE_URL, true );
			if ( isset( $this->seen_source_urls[ $url ] ) ) {
				continue;
			}
			if ( get_post_meta( $post_id, \Vandrekalender\Event::META_CLAIMED, true ) ) {
				continue;
			}

			wp_update_post(
				[
					'ID'          => $post_id,
					'post_status' => 'draft',
				]
			);
		}
	}

	/**
	 * Insert or update an event post, deduplicating by source URL.
	 *
	 * @param array $event Event data array including post fields and meta keys.
	 * @return bool True if the event was created or updated.
	 */
	protected function upsert_event( array $event ): bool {
		$source_url_key = \Vandrekalender\Event::META_SOURCE_URL;

		if ( empty( $event[ $source_url_key ] ) ) {
			return false;
		}

		// Deduplicate by source URL.
		$existing = get_posts(
			[
				'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_query'     => [
					[
						'key'   => $source_url_key,
						'value' => $event[ $source_url_key ],
					],
				],
			]
		);

		// Skip if already claimed — the organiser manages their own data.
		if ( $existing ) {
			$claimed = get_post_meta( $existing[0]->ID, \Vandrekalender\Event::META_CLAIMED, true );
			if ( $claimed ) {
				return false;
			}
		}

		$author_id = $this->get_scraper_author_id();

		$base_title = sanitize_text_field( $event['post_title'] ?? '' );

		$title = $this->disambiguate_title(
			$base_title,
			(string) ( $event[ \Vandrekalender\Event::META_DATE ] ?? '' ),
			$existing ? (int) $existing[0]->ID : 0
		);

		// Groups recurring occurrences of the same walk (same source, same
		// title before the date suffix) so a past one can redirect to the
		// next. See docs/history/past-events-brief.md.
		$event[ \Vandrekalender\Event::META_SERIES_KEY ] = sanitize_title(
			( $event[ \Vandrekalender\Event::META_SOURCE_NAME ] ?? '' ) . ' ' . $base_title
		);

		$post_data = [
			'post_type'    => \Vandrekalender\Event::CUSTOMPOSTTYPE,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => wp_kses_post( $event['post_content'] ?? '' ),
			'post_excerpt' => sanitize_textarea_field( $event['post_excerpt'] ?? '' ),
		];

		// Scraped events are owned by the bot user (claimed events are skipped above).
		if ( $author_id > 0 ) {
			$post_data['post_author'] = $author_id;
		}

		if ( $existing ) {
			$post_data['ID'] = $existing[0]->ID;
			wp_update_post( $post_data );
			$post_id = $existing[0]->ID;
		} else {
			$post_id = wp_insert_post( $post_data );
		}

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		// Read before the meta loop below overwrites them with the scraped
		// routes, which never carry a gpx_id.
		$stored_routes = $existing ? get_post_meta( $post_id, \Vandrekalender\Event::META_ROUTES, true ) : [];

		$reserved = [ 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_type', 'ID', 'featured_image_url', 'tax_terms' ];
		foreach ( $event as $key => $value ) {
			if ( ! in_array( $key, $reserved, true ) ) {
				update_post_meta( $post_id, $key, $value );
			}
		}

		if ( isset( $event[ \Vandrekalender\Event::META_ROUTES ] ) && is_array( $event[ \Vandrekalender\Event::META_ROUTES ] ) ) {
			$this->attach_route_gpx_files(
				$post_id,
				$event[ \Vandrekalender\Event::META_ROUTES ],
				is_array( $stored_routes ) ? $stored_routes : []
			);
		}

		// Taxonomy terms a scraper assigns directly, e.g. a region derived from
		// the source URL when geocoding could not supply a municipality. Terms
		// normally derive on save from meta (municipality -> region, routes ->
		// length); this is the escape hatch for what meta cannot express.
		if ( ! empty( $event['tax_terms'] ) && is_array( $event['tax_terms'] ) ) {
			$has_municipality = '' !== (string) get_post_meta( $post_id, \Vandrekalender\Event::META_MUNICIPALITY, true );
			foreach ( $event['tax_terms'] as $taxonomy => $terms ) {
				// The URL-derived region is only a stand-in for a missing
				// municipality. If the post already has a resolved municipality
				// (from this run or preserved from a prior successful geocode
				// when the current run failed), its municipality-derived region
				// is authoritative — don't let the coarser fallback overwrite it.
				if ( \Vandrekalender\Event::TAX_REGION === $taxonomy && $has_municipality ) {
					continue;
				}
				wp_set_object_terms( $post_id, $terms, (string) $taxonomy );
			}
		}

		// One organiser term per organisation, from the source (#32).
		$organiser = \Vandrekalender\Event::organiser_term_for_source( (string) ( $event[ \Vandrekalender\Event::META_SOURCE_NAME ] ?? '' ) );
		wp_set_object_terms( $post_id, '' === $organiser ? [] : [ $organiser ], \Vandrekalender\Event::TAX_ORGANISER );

		// Source-tracking fields, set by the pipeline rather than each scraper.
		update_post_meta( $post_id, \Vandrekalender\Event::META_SOURCE, 'scraped' );
		update_post_meta( $post_id, \Vandrekalender\Event::META_SCRAPED_AT, current_time( 'mysql' ) );

		if ( ! $existing ) {
			update_post_meta( $post_id, \Vandrekalender\Event::META_CLAIMED, false );
		}

		// Download the source image into the media library as the featured image.
		// Re-sideload only when the source image URL changes, so we do not refetch
		// every run but still update if the source swaps the image.
		if ( ! empty( $event['featured_image_url'] ) ) {
			$stored_image = get_post_meta( $post_id, '_event_source_image', true );
			if ( ! has_post_thumbnail( $post_id ) || $stored_image !== $event['featured_image_url'] ) {
				if ( $this->set_featured_image_from_url( $post_id, (string) $event['featured_image_url'] ) ) {
					update_post_meta( $post_id, '_event_source_image', $event['featured_image_url'] );
				}
			}
		}

		return true;
	}

	/**
	 * Download the GPX files the scraped routes link to and attach them.
	 *
	 * Scrapers only set a route's `gpx_source_url`; this fills in `gpx_id` and
	 * `gpx_name` and writes the routes back once. Stored routes are matched by
	 * route `id`, so a source is not downloaded again on every run (same idea
	 * as `_event_source_image` for featured images).
	 *
	 * Per route:
	 * - Same source URL as the stored route (both may be empty): keep the
	 *   route's file. That is the scraped file, a replacement chosen in the
	 *   editor, or a file attached by hand to a route the source has no GPX for.
	 * - New or changed source URL: reuse a file of this event for the same URL
	 *   (one GPX linked from several routes is downloaded once), otherwise
	 *   download it. If that fails, keep the route's previous file and its
	 *   previous source URL, so the next run tries again.
	 * - The source no longer links a file: drop the scraped one.
	 *
	 * Files of this event that no route uses any more are deleted, so
	 * replacements do not pile up in the media library.
	 *
	 * @param int   $post_id       Event post ID.
	 * @param array $routes        Routes as scraped, already saved to event_routes.
	 * @param array $stored_routes Routes stored on the post before this run.
	 * @return void
	 */
	protected function attach_route_gpx_files( int $post_id, array $routes, array $stored_routes ): void {
		$stored_by_id = [];
		$known        = []; // Source URL => attachment ID.
		$names        = []; // Source URL => gpx_name.

		foreach ( $stored_routes as $stored ) {
			if ( ! is_array( $stored ) || ! $this->is_attachment( (int) ( $stored['gpx_id'] ?? 0 ) ) ) {
				continue;
			}
			$stored_by_id[ (string) ( $stored['id'] ?? '' ) ] = $stored;
			if ( '' !== (string) ( $stored['gpx_source_url'] ?? '' ) ) {
				$known[ (string) $stored['gpx_source_url'] ] = (int) $stored['gpx_id'];
				$names[ (string) $stored['gpx_source_url'] ] = (string) ( $stored['gpx_name'] ?? '' );
			}
		}

		$updated = $routes;
		$in_use  = [];

		foreach ( $updated as $i => $route ) {
			if ( ! is_array( $route ) ) {
				continue;
			}

			$source_url = (string) ( $route['gpx_source_url'] ?? '' );
			$stored     = $stored_by_id[ (string) ( $route['id'] ?? '' ) ] ?? null;
			$gpx_id     = 0;

			if ( null !== $stored && (string) ( $stored['gpx_source_url'] ?? '' ) === $source_url ) {
				$gpx_id = (int) $stored['gpx_id'];
				$route  = array_merge(
					$route,
					[
						'gpx_id'   => (string) $gpx_id,
						'gpx_name' => (string) ( $stored['gpx_name'] ?? '' ),
					]
				);
			} elseif ( '' !== $source_url ) {
				if ( ! isset( $known[ $source_url ] ) ) {
					$attachment_id = $this->sideload_gpx( $post_id, $source_url );
					if ( $attachment_id > 0 ) {
						$known[ $source_url ] = $attachment_id;
						$names[ $source_url ] = wp_basename( (string) get_attached_file( $attachment_id ) );
					}
				}

				if ( isset( $known[ $source_url ] ) ) {
					$gpx_id = $known[ $source_url ];
					$route  = array_merge(
						$route,
						[
							'gpx_id'   => (string) $gpx_id,
							'gpx_name' => $names[ $source_url ],
						]
					);
				} elseif ( null !== $stored ) {
					// Download failed: keep the previous file and its source
					// URL, so the next run tries again.
					$gpx_id = (int) $stored['gpx_id'];
					$route  = array_merge(
						$route,
						[
							'gpx_id'         => (string) $gpx_id,
							'gpx_source_url' => (string) ( $stored['gpx_source_url'] ?? '' ),
							'gpx_name'       => (string) ( $stored['gpx_name'] ?? '' ),
						]
					);
				}
				// Otherwise no file yet: the route keeps its gpx_source_url,
				// so the next run tries again.
			}
			// No source URL: a previously scraped file is dropped (deleted
			// below), and the route is left as scraped, so routes without a
			// GPX are not rewritten on every run.

			if ( $gpx_id > 0 ) {
				$in_use[ $gpx_id ] = true;
			}
			$updated[ $i ] = $route;
		}

		if ( $updated !== $routes ) {
			update_post_meta( $post_id, \Vandrekalender\Event::META_ROUTES, $updated );
		}

		foreach ( $stored_by_id as $stored ) {
			$old_id = (int) $stored['gpx_id'];
			// Only files uploaded to this event, never one shared from elsewhere.
			if ( ! isset( $in_use[ $old_id ] ) && (int) wp_get_post_parent_id( $old_id ) === $post_id ) {
				wp_delete_attachment( $old_id, true );
			}
		}
	}

	/**
	 * Download a GPX file into the media library, attached to an event.
	 *
	 * Goes through media_handle_sideload(), so the GPX filters in
	 * Vandrekalender_Gpx_Uploads apply: the file type is accepted, and the
	 * file is rejected unless it is a GPX track under 5 MB.
	 *
	 * @param int    $post_id Event post the file belongs to.
	 * @param string $url     GPX file URL at the source.
	 * @return int Attachment ID, or 0 on failure (recorded as a warning).
	 */
	protected function sideload_gpx( int $post_id, string $url ): int {
		$this->require_media_functions();

		$tmp = download_url( $url, 15 );
		if ( is_wp_error( $tmp ) ) {
			$this->record_warning(
				/* translators: 1: GPX file URL, 2: error message. */
				sprintf( __( 'Could not download the GPX file %1$s: %2$s', 'vandrekalender-events' ), $url, $tmp->get_error_message() )
			);
			return 0;
		}

		// The type checks go by extension, and a download link need not end in .gpx.
		$name = sanitize_file_name( wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		if ( ! preg_match( '/\.gpx$/i', $name ) ) {
			$name = ( '' !== $name ? $name : 'route' ) . '.gpx';
		}

		$attachment_id = media_handle_sideload(
			[
				'name'     => $name,
				'tmp_name' => $tmp,
			],
			$post_id
		);

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			$this->record_warning(
				/* translators: 1: GPX file URL, 2: error message. */
				sprintf( __( 'Could not add the GPX file %1$s: %2$s', 'vandrekalender-events' ), $url, $attachment_id->get_error_message() )
			);
			return 0;
		}

		return (int) $attachment_id;
	}

	/**
	 * Whether an ID belongs to an existing attachment.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	private function is_attachment( int $id ): bool {
		return $id > 0 && 'attachment' === get_post_type( $id );
	}

	/**
	 * Load the admin media functions, which cron and WP-CLI runs do not have.
	 *
	 * @return void
	 */
	private function require_media_functions(): void {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
	}

	/**
	 * Move an existing event to a new source URL after the source changed its URLs.
	 *
	 * Events are deduplicated by source URL (upsert_event()), so when a
	 * source moves its event pages, the next run would create a duplicate of every upcoming
	 * event and draft the original (its URL "disappeared"), losing the
	 * original's permalink. Call this before returning an event from parse():
	 * if no post has the new URL yet, a post of this source with the same
	 * date whose URL matches the old format is switched to the new URL, so
	 * upsert_event() updates it in place. Once every such post has moved, it
	 * finds nothing and does nothing.
	 *
	 * @param string $new_url        The event's source URL in the new format.
	 * @param string $date           Event date (Y-m-d).
	 * @param string $legacy_pattern Regex the event's old source URL matches.
	 * @return void
	 */
	protected function adopt_legacy_source_url( string $new_url, string $date, string $legacy_pattern ): void {
		if ( '' === $date || ! defined( static::class . '::SOURCE_NAME' ) ) {
			return;
		}

		$same_day = get_posts(
			[
				'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => \Vandrekalender\Event::META_SOURCE_NAME,
						'value' => (string) constant( static::class . '::SOURCE_NAME' ),
					],
					[
						'key'   => \Vandrekalender\Event::META_DATE,
						'value' => $date,
					],
				],
			]
		);

		$legacy_id = 0;
		foreach ( $same_day as $post_id ) {
			$url = (string) get_post_meta( $post_id, \Vandrekalender\Event::META_SOURCE_URL, true );
			if ( $url === $new_url ) {
				return;
			}
			if ( 0 === $legacy_id && preg_match( $legacy_pattern, $url ) ) {
				$legacy_id = (int) $post_id;
			}
		}

		if ( $legacy_id > 0 ) {
			update_post_meta( $legacy_id, \Vandrekalender\Event::META_SOURCE_URL, $new_url );
		}
	}

	/**
	 * Append the event date to the title when another event already uses it.
	 *
	 * Sources like DVL publish recurring walks as one page per occurrence,
	 * with identical titles and descriptions. Left as-is, each occurrence
	 * becomes a near-identical public page that Google reports as duplicate
	 * content. Appending the date — "Motionstur fra Hareskov St. – 23. juli
	 * 2026" — makes the title, and the slug WordPress derives from it for new
	 * posts, unique per occurrence. Only occurrences after the first are
	 * suffixed, so events with unique titles keep their clean URLs.
	 *
	 * @param string $title   Scraped event title.
	 * @param string $date    Event date (Y-m-d), may be empty.
	 * @param int    $self_id Existing post ID for this source URL, or 0.
	 * @return string Title, possibly suffixed with the localised date.
	 */
	protected function disambiguate_title( string $title, string $date, int $self_id ): string {
		if ( '' === $title || '' === $date ) {
			return $title;
		}

		$timestamp = strtotime( $date );
		if ( false === $timestamp ) {
			return $title;
		}

		$twins = get_posts(
			[
				'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
				'post_status'    => [ 'publish', 'draft', 'future', 'pending' ],
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'title'          => $title,
				'exclude'        => $self_id > 0 ? [ $self_id ] : [],
			]
		);

		if ( empty( $twins ) ) {
			return $title;
		}

		return $title . ' – ' . date_i18n( 'j. F Y', $timestamp );
	}

	/**
	 * Get the dedicated scraper author user ID, creating the user if needed.
	 *
	 * Scraped events are owned by a single system user so they are clearly
	 * distinguishable from manually authored events. The user is created on
	 * first use, so it appears automatically in every environment.
	 *
	 * @return int User ID, or 0 on failure.
	 */
	protected function get_scraper_author_id(): int {
		$login = 'vandrekalender_bot';

		$user = get_user_by( 'login', $login );
		if ( $user instanceof \WP_User ) {
			return $user->ID;
		}

		$user_id = wp_insert_user(
			[
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 32, true, true ),
				'user_email'   => 'robot@allevandreture.dk',
				'display_name' => 'Alle Vandreture Robot',
				'nickname'     => 'Alle Vandreture Robot',
				'role'         => 'author',
			]
		);

		return is_wp_error( $user_id ) ? 0 : (int) $user_id;
	}

	/**
	 * Download an image by URL and set it as the post's featured image.
	 *
	 * @param int    $post_id Post to attach the image to.
	 * @param string $url     Image URL.
	 * @return bool True on success, false on failure.
	 */
	protected function set_featured_image_from_url( int $post_id, string $url ): bool {
		$this->require_media_functions();

		$attachment_id = media_sideload_image( $url, $post_id, null, 'id' );
		if ( is_wp_error( $attachment_id ) ) {
			return false;
		}

		$previous = (int) get_post_thumbnail_id( $post_id );
		set_post_thumbnail( $post_id, (int) $attachment_id );

		// Remove the previous scraped image so replacements do not pile up.
		if ( $previous > 0 && $previous !== (int) $attachment_id ) {
			wp_delete_attachment( $previous, true );
		}

		return true;
	}

	/**
	 * Fetch a URL via wp_remote_get and return the body, or empty string on failure.
	 *
	 * @param string $url     URL to fetch.
	 * @param array  $headers Extra request headers, e.g. a JSON API's locale header.
	 * @return string Response body.
	 */
	protected function remote_get( string $url, array $headers = [] ): string {
		$response = wp_remote_get(
			$url,
			[
				'timeout'    => 15,
				'user-agent' => 'Vandrekalender/1.0 (+https://allevandreture.dk)',
				'headers'    => $headers,
			]
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return '';
		}

		return wp_remote_retrieve_body( $response );
	}
}
