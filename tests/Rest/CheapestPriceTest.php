<?php
/**
 * The free / unknown / paid rule behind is_free and the price labels (#243).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Event_Rest_Api::cheapest_price().
 */
class CheapestPriceTest extends TestCase {

	/**
	 * A route with an empty price records nothing: the price is unknown, not free.
	 */
	public function test_empty_price_is_unknown(): void {
		$this->assertNull( Vandrekalender_Event_Rest_Api::cheapest_price( [ [ 'price' => '' ] ] ) );
	}

	/**
	 * Missing price key, no routes and a non-array meta value are unknown too.
	 */
	public function test_missing_price_or_routes_is_unknown(): void {
		$this->assertNull( Vandrekalender_Event_Rest_Api::cheapest_price( [ [ 'distance_km' => '10' ] ] ) );
		$this->assertNull( Vandrekalender_Event_Rest_Api::cheapest_price( [] ) );
		$this->assertNull( Vandrekalender_Event_Rest_Api::cheapest_price( '' ) );
	}

	/**
	 * An explicit price of 0 is free.
	 */
	public function test_zero_price_is_free(): void {
		$this->assertSame( 0.0, Vandrekalender_Event_Rest_Api::cheapest_price( [ [ 'price' => '0' ] ] ) );
	}

	/**
	 * Mixed routes follow the cheapest known price; unknown routes are ignored.
	 */
	public function test_mixed_routes_use_cheapest_known_price(): void {
		$routes = [
			[ 'price' => '' ],
			[ 'price' => '150' ],
			[ 'price' => '90' ],
		];
		$this->assertSame( 90.0, Vandrekalender_Event_Rest_Api::cheapest_price( $routes ) );

		$routes[] = [ 'price' => '0' ];
		$this->assertSame( 0.0, Vandrekalender_Event_Rest_Api::cheapest_price( $routes ) );
	}
}
