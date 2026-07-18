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

		$html   = $this->fetch();
		$events = $this->parse( $html );
		$count  = 0;

		foreach ( $events as $event ) {
			$this->mark_source_url_seen( (string) ( $event[ \Vandrekalender\Event::META_SOURCE_URL ] ?? '' ) );
			if ( $this->upsert_event( $event ) ) {
				++$count;
			}
		}

		$this->unpublish_stale_events();

		return $count;
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

		$title = $this->disambiguate_title(
			sanitize_text_field( $event['post_title'] ?? '' ),
			(string) ( $event[ \Vandrekalender\Event::META_DATE ] ?? '' ),
			$existing ? (int) $existing[0]->ID : 0
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

		$reserved = [ 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_type', 'ID', 'featured_image_url' ];
		foreach ( $event as $key => $value ) {
			if ( ! in_array( $key, $reserved, true ) ) {
				update_post_meta( $post_id, $key, $value );
			}
		}

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
				'user_email'   => 'robot@vandrekalender.dk',
				'display_name' => 'Vandrekalender Robot',
				'nickname'     => 'Vandrekalender Robot',
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
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

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
	 * @param string $url URL to fetch.
	 * @return string Response body.
	 */
	protected function remote_get( string $url ): string {
		$response = wp_remote_get(
			$url,
			[
				'timeout'    => 15,
				'user-agent' => 'Vandrekalender/1.0 (+https://vandrekalender.dk)',
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
