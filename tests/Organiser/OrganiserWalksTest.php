<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * The numbers on an organiser page, computed from its walks (#32, step 5c).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Organiser_Walks;

/**
 * Organiser_Walks: regions, distances, region links and related organisers.
 */
class OrganiserWalksTest extends TestCase {

	/**
	 * A walk in the given regions.
	 *
	 * @param array<string,string> $regions Name per region slug.
	 * @return array{id:int,regions:array<string,string>}
	 */
	private static function walk( array $regions ): array {
		return [
			'id'      => 0,
			'regions' => $regions,
		];
	}

	/**
	 * Regions are counted per walk, most walks first, then by name.
	 */
	public function test_regions_counted_and_sorted(): void {
		$walks = [
			self::walk( [ 'syddanmark' => 'Syddanmark' ] ),
			self::walk( [ 'midtjylland' => 'Midtjylland' ] ),
			self::walk( [ 'hovedstaden' => 'Hovedstaden' ] ),
			self::walk( [ 'midtjylland' => 'Midtjylland' ] ),
		];

		$this->assertSame(
			[
				[
					'slug'  => 'midtjylland',
					'name'  => 'Midtjylland',
					'count' => 2,
				],
				[
					'slug'  => 'hovedstaden',
					'name'  => 'Hovedstaden',
					'count' => 1,
				],
				[
					'slug'  => 'syddanmark',
					'name'  => 'Syddanmark',
					'count' => 1,
				],
			],
			Organiser_Walks::regions( $walks )
		);
	}

	/**
	 * A walk in two regions counts in both; one without a region in none.
	 */
	public function test_walk_in_two_regions_counts_in_both(): void {
		$regions = Organiser_Walks::regions(
			[
				self::walk(
					[
						'hovedstaden' => 'Hovedstaden',
						'sjaelland'   => 'Sjælland',
					]
				),
				self::walk( [] ),
			]
		);

		$this->assertSame( [ 1, 1 ], array_column( $regions, 'count' ) );
	}

	/**
	 * No walks, no regions.
	 */
	public function test_no_walks_no_regions(): void {
		$this->assertSame( [], Organiser_Walks::regions( [] ) );
	}

	/**
	 * The range spans every route of every walk; routes without a distance
	 * are skipped.
	 */
	public function test_distance_range(): void {
		$routes = [
			[ [ 'distance_km' => '12.5' ], [ 'distance_km' => '' ] ],
			[ [ 'distance_km' => 4 ], [ 'distance_km' => '25' ] ],
			[ [ 'price' => '0' ] ],
		];

		$this->assertSame( [ 4.0, 25.0 ], Organiser_Walks::distance_range( $routes ) );
	}

	/**
	 * No distance anywhere, no range.
	 */
	public function test_no_distances_no_range(): void {
		$this->assertNull( Organiser_Walks::distance_range( [] ) );
		$this->assertNull( Organiser_Walks::distance_range( [ [ [ 'distance_km' => '' ] ], [] ] ) );
	}

	/**
	 * Distances drop a trailing ".0", as on the event pages.
	 */
	public function test_format_km(): void {
		$this->assertSame( '25', Organiser_Walks::format_km( 25.0 ) );
		$this->assertSame( '5.5', Organiser_Walks::format_km( 5.5 ) );
		$this->assertSame( '4.3', Organiser_Walks::format_km( 4.26 ) );
	}

	/**
	 * A region link filters this page and jumps to its walks.
	 */
	public function test_region_url(): void {
		$this->assertSame(
			'https://example.com/arrangoerer/dvl/?region=midtjylland#kommende-ture',
			Organiser_Walks::region_url( 'https://example.com/arrangoerer/dvl/', 'midtjylland' )
		);
		$this->assertSame(
			'https://example.com/?p=5&region=midtjylland#kommende-ture',
			Organiser_Walks::region_url( 'https://example.com/?p=5', 'midtjylland' )
		);
	}

	/**
	 * Others sharing more regions come first, then those with more walks,
	 * then by name; the organiser itself is left out.
	 */
	public function test_related_order(): void {
		$by_term = [
			1 => [ self::walk( [ 'midt' => 'Midt' ] ), self::walk( [ 'hoved' => 'Hoved' ] ) ],
			2 => [ self::walk( [ 'nord' => 'Nord' ] ), self::walk( [ 'nord' => 'Nord' ] ), self::walk( [ 'nord' => 'Nord' ] ) ],
			3 => [ self::walk( [ 'midt' => 'Midt' ] ) ],
			4 => [ self::walk( [ 'midt' => 'Midt' ] ), self::walk( [ 'hoved' => 'Hoved' ] ) ],
			5 => [ self::walk( [ 'sjael' => 'Sjæl' ] ) ],
		];
		$names   = [
			1 => 'DVL',
			2 => 'Nordjyder',
			3 => 'Bjerg',
			4 => 'Kyst',
			5 => 'Amager',
			6 => 'Uden ture',
		];

		$this->assertSame(
			[
				[
					'term_id' => 4,
					'walks'   => 2,
				],
				[
					'term_id' => 3,
					'walks'   => 1,
				],
				[
					'term_id' => 2,
					'walks'   => 3,
				],
				[
					'term_id' => 5,
					'walks'   => 1,
				],
				[
					'term_id' => 6,
					'walks'   => 0,
				],
			],
			Organiser_Walks::related( $by_term, 1, $names )
		);
	}

	/**
	 * At most the limit is returned.
	 */
	public function test_related_limit(): void {
		$names = array_fill_keys( range( 1, 10 ), 'Name' );

		$this->assertCount( 6, Organiser_Walks::related( [], 1, $names ) );
		$this->assertCount( 2, Organiser_Walks::related( [], 1, $names, 2 ) );
	}

	/**
	 * With no other organiser page there is nothing to show.
	 */
	public function test_related_none(): void {
		$this->assertSame( [], Organiser_Walks::related( [], 1, [ 1 => 'DVL' ] ) );
	}
}
