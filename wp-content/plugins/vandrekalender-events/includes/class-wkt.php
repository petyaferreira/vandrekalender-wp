<?php

defined( 'ABSPATH' ) || exit;

/**
 * Read areas from WKT (well-known text) geometry, as Datafordeler returns it.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Wkt {

	/**
	 * The centre of an area: the centroid of its largest ring.
	 *
	 * A postcode area can be several polygons (a town and its islands), so
	 * the largest one is used. Its holes are ignored, and for a strongly
	 * bent area the centroid can lie just outside it; good enough for a pin
	 * marked approximate.
	 *
	 * @param string $wkt POLYGON or MULTIPOLYGON, in metres (e.g. EPSG:25832).
	 * @return array{x: float, y: float}|null Null when there is no ring with an area.
	 */
	public static function area_centre( string $wkt ): ?array {
		if ( ! preg_match_all( '/\(([^()]+)\)/', $wkt, $rings ) ) {
			return null;
		}

		$best = null;
		foreach ( $rings[1] as $ring ) {
			$points = [];
			foreach ( explode( ',', $ring ) as $pair ) {
				$xy = preg_split( '/\s+/', trim( $pair ) );
				if ( isset( $xy[0], $xy[1] ) && is_numeric( $xy[0] ) && is_numeric( $xy[1] ) ) {
					$points[] = [ (float) $xy[0], (float) $xy[1] ];
				}
			}

			$centre = self::ring_centroid( $points );
			if ( null !== $centre && ( null === $best || $centre['area'] > $best['area'] ) ) {
				$best = $centre;
			}
		}

		return null !== $best ? [
			'x' => $best['x'],
			'y' => $best['y'],
		] : null;
	}

	/**
	 * Centroid and area of one closed ring (shoelace formula).
	 *
	 * @param array<int, array{float, float}> $points The ring's points; the last may repeat the first.
	 * @return array{x: float, y: float, area: float}|null Null for a ring without area.
	 */
	private static function ring_centroid( array $points ): ?array {
		$count = count( $points );
		if ( $count < 3 ) {
			return null;
		}

		// Relative to the first point, so UTM's millions of metres do not
		// cost precision in the products.
		[ $x0, $y0 ] = $points[0];
		$twice_area  = 0.0;
		$cx          = 0.0;
		$cy          = 0.0;

		for ( $i = 0; $i < $count; $i++ ) {
			$x1 = $points[ $i ][0] - $x0;
			$y1 = $points[ $i ][1] - $y0;
			$x2 = $points[ ( $i + 1 ) % $count ][0] - $x0;
			$y2 = $points[ ( $i + 1 ) % $count ][1] - $y0;

			$cross       = $x1 * $y2 - $x2 * $y1;
			$twice_area += $cross;
			$cx         += ( $x1 + $x2 ) * $cross;
			$cy         += ( $y1 + $y2 ) * $cross;
		}

		if ( 0.0 === $twice_area ) {
			return null;
		}

		return [
			'x'    => $x0 + $cx / ( 3 * $twice_area ),
			'y'    => $y0 + $cy / ( 3 * $twice_area ),
			'area' => abs( $twice_area ) / 2,
		];
	}
}
