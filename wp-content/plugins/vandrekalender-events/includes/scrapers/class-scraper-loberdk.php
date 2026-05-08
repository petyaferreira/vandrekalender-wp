<?php

defined( 'ABSPATH' ) || exit;

class Vandrekalender_Scraper_Loberdk extends Vandrekalender_Scraper_Base {

	const SOURCE_URL = 'https://www.lober.dk/events';

	protected function fetch(): string {
		return $this->remote_get( self::SOURCE_URL );
	}

	protected function parse( string $html ): array {
		// TODO: implement parsing once source HTML structure is confirmed.
		// Map each event to an array with: post_title, post_content, _event_date,
		// _event_distance_km, _event_location_name, _event_organiser, _event_source_url,
		// _event_region, _event_difficulty.
		return [];
	}
}
