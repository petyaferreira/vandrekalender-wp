<?php
/**
 * Fake geocoder for tests.
 *
 * @package Vandrekalender
 */

// phpcs:disable Generic.Classes.DuplicateClassName.Found -- replaces the real class, which the tests never load.

/**
 * Reverse geocoding without Datafordeler: returns a fixed municipality, so
 * scraper tests check that the lookup happens and its result is stored, not
 * the geocoder itself.
 */
class Vandrekalender_Geocoder {

	const TEST_MUNICIPALITY = 'Testkommune';

	/**
	 * Coordinates asked about, in order.
	 *
	 * @var array<int, array{float, float}>
	 */
	public static array $lookups = [];

	/**
	 * Record the lookup and return the fixed municipality.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return string
	 */
	public function municipality_from_coords( float $lat, float $lng ): string {
		self::$lookups[] = [ $lat, $lng ];
		return self::TEST_MUNICIPALITY;
	}
}
