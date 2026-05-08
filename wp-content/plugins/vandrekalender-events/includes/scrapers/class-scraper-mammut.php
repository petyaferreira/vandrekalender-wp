<?php

defined( 'ABSPATH' ) || exit;

/**
 * Scraper for walking events from mammut-march.dk.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Scraper_Mammut extends Vandrekalender_Scraper_Base {

	const SOURCE_URL = 'https://www.mammut-march.dk/events';

	/**
	 * Fetch the mammut-march.dk events page.
	 *
	 * @return string HTML body.
	 */
	protected function fetch(): string {
		return $this->remote_get( self::SOURCE_URL );
	}

	/**
	 * Parse mammut-march.dk events HTML into event arrays.
	 *
	 * @param string $html Raw HTML from fetch().
	 * @return array
	 */
	protected function parse( string $html ): array {
		// TODO: implement parsing once source HTML structure is confirmed.
		return [];
	}
}
