<?php
/**
 * The centre of a postcode area, from its WKT outline (#112).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Wkt::area_centre().
 */
class WktTest extends TestCase {

	/**
	 * A square's centre, at UTM size, so large coordinates keep their precision.
	 */
	public function test_square(): void {
		$this->assertSame(
			[
				'x' => 712000.0,
				'y' => 6178500.0,
			],
			Vandrekalender_Wkt::area_centre( 'POLYGON ((711000 6177500, 713000 6177500, 713000 6179500, 711000 6179500, 711000 6177500))' )
		);
	}

	/**
	 * An L shape: the centroid, not the middle of the bounding box.
	 */
	public function test_l_shape(): void {
		// A 2×1 bar (centre 1, 0.5) plus a 1×1 square on top of its left end
		// (centre 0.5, 1.5): x = (2·1 + 1·0.5) / 3 = 5/6, y = (2·0.5 + 1·1.5) / 3 = 5/6.
		// The bounding box's middle would be 1, 1.
		$centre = Vandrekalender_Wkt::area_centre( 'POLYGON ((0 0, 2 0, 2 1, 1 1, 1 2, 0 2, 0 0))' );

		$this->assertEqualsWithDelta( 5 / 6, $centre['x'], 1e-9 );
		$this->assertEqualsWithDelta( 5 / 6, $centre['y'], 1e-9 );
	}

	/**
	 * Several polygons (a town and an island): the largest one counts.
	 */
	public function test_multipolygon_uses_largest_polygon(): void {
		$centre = Vandrekalender_Wkt::area_centre( 'MULTIPOLYGON (((100 100, 101 100, 101 101, 100 101, 100 100)), ((0 0, 10 0, 10 10, 0 10, 0 0)))' );

		$this->assertSame(
			[
				'x' => 5.0,
				'y' => 5.0,
			],
			$centre
		);
	}

	/**
	 * Clockwise rings give the same centre as anticlockwise ones.
	 */
	public function test_ring_direction_does_not_matter(): void {
		$this->assertSame(
			Vandrekalender_Wkt::area_centre( 'POLYGON ((0 0, 4 0, 4 2, 0 2, 0 0))' ),
			Vandrekalender_Wkt::area_centre( 'POLYGON ((0 0, 0 2, 4 2, 4 0, 0 0))' )
		);
	}

	/**
	 * No area: nothing to place a pin at.
	 */
	public function test_no_area_gives_null(): void {
		$this->assertNull( Vandrekalender_Wkt::area_centre( '' ) );
		$this->assertNull( Vandrekalender_Wkt::area_centre( 'POINT (1 2)' ) );
		$this->assertNull( Vandrekalender_Wkt::area_centre( 'POLYGON ((0 0, 1 1, 2 2, 0 0))' ) );
	}
}
