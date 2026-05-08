<?php

defined( 'ABSPATH' ) || exit;

class Vandrekalender_Scraper_Mammut extends Vandrekalender_Scraper_Base {

	const SOURCE_URL = 'https://www.mammut-march.dk/events';

	protected function fetch(): string {
		return $this->remote_get( self::SOURCE_URL );
	}

	protected function parse( string $html ): array {
		// TODO: implement parsing once source HTML structure is confirmed.
		return [];
	}
}
