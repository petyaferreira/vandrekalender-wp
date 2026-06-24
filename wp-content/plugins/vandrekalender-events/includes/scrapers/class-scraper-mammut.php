<?php

defined( 'ABSPATH' ) || exit;

/**
 * Scraper for walking events from mammutmarch.dk.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Scraper_Mammut extends Vandrekalender_Scraper_Base {

	const SOURCE_URL = 'https://mammutmarch.dk/shop/';

	/**
	 * Fetch the mammutmarch.dk events page.
	 *
	 * @return string HTML body.
	 */
	protected function fetch(): string {
		return $this->remote_get( self::SOURCE_URL );
	}

	/**
	 * Parse mammutmarch.dk events HTML into event arrays.
	 *
	 * @param string $html Raw HTML from fetch().
	 * @return array
	 */
	protected function parse( string $html ): array {
		// TODO: implement parsing once source HTML structure is confirmed.
		// Return an array of event arrays using the canonical schema keys
		// (see Vandrekalender\Event::META_* and docs/scrapers.md):
		// post_title, post_content,
		// event_date         (YYYY-MM-DD),
		// event_routes       (array of [ 'distance_km', 'start_time', 'cutoff_time', 'price' ]),
		// event_place_name, event_address,
		// event_organiser_name,
		// event_source_url   (required — dedup key),
		// event_source_name.
		// event_lat/lng/municipality are geocoded from event_address via DAWA;
		// event_length and event_region taxonomies are derived on save.
		// event_source, event_scraped_at and event_claimed are set by upsert_event().
		//
		// The site is WooCommerce: events are products listed at /shop/. Distances
		// vary per event and appear in the title (e.g. "75/100 KM", "30/50 KM",
		// "30/42/55 KM") — emit one event_routes entry per distance.
		return [];
	}
}
