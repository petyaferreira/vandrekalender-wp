<?php

defined( 'ABSPATH' ) || exit;

/**
 * Base class for all event scrapers.
 *
 * @package Vandrekalender
 */
abstract class Vandrekalender_Scraper_Base {

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
		$html   = $this->fetch();
		$events = $this->parse( $html );
		$count  = 0;

		foreach ( $events as $event ) {
			if ( $this->upsert_event( $event ) ) {
				++$count;
			}
		}

		return $count;
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

		$post_data = [
			'post_type'    => \Vandrekalender\Event::CUSTOMPOSTTYPE,
			'post_status'  => 'publish',
			'post_title'   => sanitize_text_field( $event['post_title'] ?? '' ),
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
