<?php

defined( 'ABSPATH' ) || exit;

/**
 * Convert EPSG:25832 (ETRS89 / UTM zone 32N) coordinates, as returned by
 * Adressevælger, to latitude/longitude.
 *
 * A line-for-line port of resources/event-meta-fields/utm-to-latlng.js, the
 * editor's helper, so the editor and the scrapers produce exactly the same
 * coordinates for the same address. Keep the two in sync and check both
 * against the reference coordinates in docs/data-model.md → Coordinate
 * conversion after any change. Why it is our own
 * helper and not a library: docs/data-model.md → Coordinate conversion.
 *
 * Inverse transverse Mercator with Krüger's series on the GRS80 ellipsoid.
 * ETRS89 and WGS84 differ by under a metre, so the result is used as WGS84.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Utm_Converter {

	const A             = 6378137; // GRS80 semi-major axis, metres.
	const F             = 1 / 298.257222101; // GRS80 flattening.
	const K0            = 0.9996; // UTM scale factor on the central meridian.
	const LON0          = 9; // Central meridian of zone 32, degrees.
	const FALSE_EASTING = 500000;

	/**
	 * Convert a UTM 32N point to latitude/longitude.
	 *
	 * @param float $x Easting in metres.
	 * @param float $y Northing in metres.
	 * @return array{lat: float, lng: float} Degrees, rounded to 7 decimals (about 1 cm).
	 */
	public static function to_lat_lng( float $x, float $y ): array {
		$n                 = self::F / ( 2 - self::F );
		$n2                = $n * $n;
		$n3                = $n2 * $n;
		$rectifying_radius = ( self::A / ( 1 + $n ) ) * ( 1 + $n2 / 4 + ( $n2 * $n2 ) / 64 );
		$beta              = [
			$n / 2 - ( 2 / 3 ) * $n2 + ( 37 / 96 ) * $n3,
			$n2 / 48 + $n3 / 15,
			( 17 / 480 ) * $n3,
		];
		$delta             = [
			2 * $n - ( 2 / 3 ) * $n2 - 2 * $n3,
			( 7 / 3 ) * $n2 - ( 8 / 5 ) * $n3,
			( 56 / 15 ) * $n3,
		];

		$xi  = $y / ( self::K0 * $rectifying_radius );
		$eta = ( $x - self::FALSE_EASTING ) / ( self::K0 * $rectifying_radius );

		$xi_prime  = $xi;
		$eta_prime = $eta;
		foreach ( $beta as $i => $b ) {
			$j          = 2 * ( $i + 1 );
			$xi_prime  -= $b * sin( $j * $xi ) * cosh( $j * $eta );
			$eta_prime -= $b * cos( $j * $xi ) * sinh( $j * $eta );
		}

		// Conformal latitude, then the geodetic latitude from it.
		$chi = asin( sin( $xi_prime ) / cosh( $eta_prime ) );
		$lat = $chi;
		foreach ( $delta as $i => $d ) {
			$lat += $d * sin( 2 * ( $i + 1 ) * $chi );
		}

		$lng = self::LON0 + self::to_degrees( atan2( sinh( $eta_prime ), cos( $xi_prime ) ) );

		return [
			'lat' => self::round7( self::to_degrees( $lat ) ),
			'lng' => self::round7( $lng ),
		];
	}

	/**
	 * Convert latitude/longitude to a UTM 32N point (the inverse of
	 * to_lat_lng()), for Datafordeler queries, which take EPSG:25832.
	 *
	 * Server-only: the editor never needs this direction, so it has no JS
	 * twin. Krüger's forward series on GRS80, the counterpart of the
	 * inverse series above; a round trip is accurate to well under 1 cm.
	 *
	 * @param float $lat Latitude in degrees.
	 * @param float $lng Longitude in degrees.
	 * @return array{x: float, y: float} Easting/northing in metres, rounded to 2 decimals.
	 */
	public static function from_lat_lng( float $lat, float $lng ): array {
		$n                 = self::F / ( 2 - self::F );
		$n2                = $n * $n;
		$n3                = $n2 * $n;
		$rectifying_radius = ( self::A / ( 1 + $n ) ) * ( 1 + $n2 / 4 + ( $n2 * $n2 ) / 64 );
		$alpha             = [
			$n / 2 - ( 2 / 3 ) * $n2 + ( 5 / 16 ) * $n3,
			( 13 / 48 ) * $n2 - ( 3 / 5 ) * $n3,
			( 61 / 240 ) * $n3,
		];

		$phi    = deg2rad( $lat );
		$lambda = deg2rad( $lng - self::LON0 );
		$e2n    = 2 * sqrt( $n ) / ( 1 + $n );

		$t         = sinh( atanh( sin( $phi ) ) - $e2n * atanh( $e2n * sin( $phi ) ) );
		$xi_prime  = atan( $t / cos( $lambda ) );
		$eta_prime = atanh( sin( $lambda ) / sqrt( 1 + $t * $t ) );

		$xi  = $xi_prime;
		$eta = $eta_prime;
		foreach ( $alpha as $i => $a ) {
			$j    = 2 * ( $i + 1 );
			$xi  += $a * sin( $j * $xi_prime ) * cosh( $j * $eta_prime );
			$eta += $a * cos( $j * $xi_prime ) * sinh( $j * $eta_prime );
		}

		return [
			'x' => round( self::FALSE_EASTING + self::K0 * $rectifying_radius * $eta, 2 ),
			'y' => round( self::K0 * $rectifying_radius * $xi, 2 ),
		];
	}

	/**
	 * Radians to degrees in the JS helper's order of operations
	 * (rad * 180 / PI). PHP's rad2deg() divides first, which can differ in
	 * the last bit.
	 *
	 * @param float $rad Radians.
	 * @return float
	 */
	private static function to_degrees( float $rad ): float {
		return ( $rad * 180 ) / M_PI;
	}

	/**
	 * Round to 7 decimals the same way the JS helper does
	 * (Math.round( value * 1e7 ) / 1e7), so both give identical floats.
	 *
	 * @param float $value Degrees.
	 * @return float
	 */
	private static function round7( float $value ): float {
		return round( $value * 1e7 ) / 1e7;
	}
}
