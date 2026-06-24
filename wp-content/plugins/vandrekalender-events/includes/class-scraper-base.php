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

		$post_data = [
			'post_type'    => \Vandrekalender\Event::CUSTOMPOSTTYPE,
			'post_status'  => 'publish',
			'post_title'   => sanitize_text_field( $event['post_title'] ?? '' ),
			'post_content' => wp_kses_post( $event['post_content'] ?? '' ),
		];

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

		$reserved = [ 'post_title', 'post_content', 'post_status', 'post_type', 'ID' ];
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
