<?php
/**
 * Fake geocoder for tests.
 *
 * @package Vandrekalender
 */

// phpcs:disable Generic.Classes.DuplicateClassName.Found -- replaces the real class, which the tests never load.

/**
 * Geocoding without Adressevælger or Datafordeler: returns fixed coordinates
 * and a fixed municipality, so scraper tests check that the lookup happens and
 * its result is stored, not the geocoder itself.
 */
class Vandrekalender_Geocoder {

	const TEST_MUNICIPALITY = 'Testkommune';
	const TEST_LAT          = 56.1;
	const TEST_LNG          = 10.2;

	/**
	 * Coordinates asked about, in order.
	 *
	 * @var array<int, array{float, float}>
	 */
	public static array $lookups = [];

	/**
	 * Addresses asked about, in order.
	 *
	 * @var string[]
	 */
	public static array $addresses = [];

	/**
	 * Whether geocode() answers as if only the postcode area was found.
	 *
	 * @var bool
	 */
	public static bool $approximate = false;

	/**
	 * Municipality to answer for a point ("lat,lng"), where a test needs a
	 * real one; any other point gets TEST_MUNICIPALITY.
	 *
	 * @var array<string, string>
	 */
	public static array $municipalities = [];

	/**
	 * Record the address and return the fixed coordinates and municipality.
	 *
	 * @param string $address Free-text address.
	 * @return array{lat: float, lng: float, municipality: string, approximate: bool}
	 */
	public function geocode( string $address ): array {
		self::$addresses[] = $address;
		return [
			'lat'          => self::TEST_LAT,
			'lng'          => self::TEST_LNG,
			'municipality' => self::TEST_MUNICIPALITY,
			'approximate'  => self::$approximate,
		];
	}

	/**
	 * Record the lookup and return the point's municipality.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return string
	 */
	public function municipality_from_coords( float $lat, float $lng ): string {
		self::$lookups[] = [ $lat, $lng ];
		return self::$municipalities[ $lat . ',' . $lng ] ?? self::TEST_MUNICIPALITY;
	}
}
