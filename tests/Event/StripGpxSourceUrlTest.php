<?php
/**
 * The private GPX source URL never reaches a public REST response.
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Event;

/**
 * Event::strip_gpx_source_url().
 */
class StripGpxSourceUrlTest extends TestCase {

	/**
	 * Every route loses gpx_source_url; the other fields stay.
	 */
	public function test_removes_gpx_source_url_from_every_route(): void {
		$routes = [
			[
				'distance_km'    => 30.0,
				'gpx_id'         => '12',
				'gpx_source_url' => 'https://example.com/a.gpx',
			],
			[
				'distance_km'    => 50.0,
				'gpx_source_url' => 'https://example.com/b.gpx',
			],
		];

		$this->assertSame(
			[
				[
					'distance_km' => 30.0,
					'gpx_id'      => '12',
				],
				[ 'distance_km' => 50.0 ],
			],
			Event::strip_gpx_source_url( $routes )
		);
	}

	/**
	 * Malformed entries are left alone instead of causing an error.
	 */
	public function test_skips_non_array_routes(): void {
		$this->assertSame( [ 'junk' ], Event::strip_gpx_source_url( [ 'junk' ] ) );
	}
}
