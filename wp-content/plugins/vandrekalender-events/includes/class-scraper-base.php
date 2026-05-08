<?php

defined( 'ABSPATH' ) || exit;

abstract class Vandrekalender_Scraper_Base {

	abstract protected function fetch(): string;

	abstract protected function parse( string $html ): array;

	public function run() {
		$html   = $this->fetch();
		$events = $this->parse( $html );
		$count  = 0;

		foreach ( $events as $event ) {
			if ( $this->upsert_event( $event ) ) {
				$count++;
			}
		}

		return $count;
	}

	protected function upsert_event( array $event ): bool {
		if ( empty( $event['_event_source_url'] ) ) {
			return false;
		}

		// Deduplicate by source URL.
		$existing = get_posts(
			[
				'post_type'      => 'vandrekalender_event',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_query'     => [
					[
						'key'   => '_event_source_url',
						'value' => $event['_event_source_url'],
					],
				],
			]
		);

		// Skip if already claimed — organiser manages their own data.
		if ( $existing ) {
			$claim_status = get_post_meta( $existing[0]->ID, '_event_claim_status', true );
			if ( 'claimed' === $claim_status ) {
				return false;
			}
		}

		$post_data = [
			'post_type'    => 'vandrekalender_event',
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

		$meta_fields = array_keys( Vandrekalender_Event_Meta::META_FIELDS );
		foreach ( $meta_fields as $key ) {
			if ( isset( $event[ $key ] ) ) {
				update_post_meta( $post_id, $key, $event[ $key ] );
			}
		}

		if ( ! $existing ) {
			update_post_meta( $post_id, '_event_claim_status', 'unclaimed' );
		}

		return true;
	}

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
