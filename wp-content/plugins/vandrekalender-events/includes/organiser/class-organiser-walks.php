<?php
/**
 * Organiser Walks Class
 *
 * The numbers on an organiser page, computed from the upcoming walks of its
 * event_organiser term on every render, never typed by hand (step 5c of
 * docs/plans/organisers-plan.md): the Organiser Stats, Organiser Regions and
 * Other Organisers blocks.
 *
 * @package Vandrekalender
 */

namespace Vandrekalender;

/**
 * Upcoming walks per organiser, and what the organiser blocks show of them.
 */
class Organiser_Walks {
	// "Andre arrangører" shows this many organisers.
	public const RELATED_LIMIT = 6;

	/**
	 * Upcoming walks per event_organiser term ID, each walk as
	 * ['id' => post ID, 'regions' => [slug => name]]. Loaded once per
	 * request by by_term(): the three blocks on a page share it.
	 *
	 * @var array<int,array<int,array{id:int,regions:array<string,string>}>>|null
	 */
	private static $by_term = null;

	/**
	 * Every organiser's upcoming walks: one query for all upcoming events
	 * (the cards' default floor of today), since "Andre arrangører" needs
	 * the other organisers' too.
	 *
	 * @return array<int,array<int,array{id:int,regions:array<string,string>}>>
	 */
	public static function by_term(): array {
		if ( null !== self::$by_term ) {
			return self::$by_term;
		}

		$ids = get_posts(
			array_merge(
				\Vandrekalender_Event_Rest_Api::build_query_args( [] ),
				[
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				]
			)
		);
		update_object_term_cache( $ids, Event::CUSTOMPOSTTYPE );

		self::$by_term = [];
		foreach ( $ids as $id ) {
			$organisers = get_the_terms( (int) $id, Event::TAX_ORGANISER );
			if ( ! is_array( $organisers ) ) {
				continue;
			}

			$regions = get_the_terms( (int) $id, Event::TAX_REGION );
			$walk    = [
				'id'      => (int) $id,
				'regions' => is_array( $regions ) ? wp_list_pluck( $regions, 'name', 'slug' ) : [],
			];
			foreach ( $organisers as $organiser ) {
				self::$by_term[ $organiser->term_id ][] = $walk;
			}
		}

		return self::$by_term;
	}

	/**
	 * One organiser's upcoming walks.
	 *
	 * @param int $term_id event_organiser term ID.
	 * @return array<int,array{id:int,regions:array<string,string>}>
	 */
	public static function for_term( int $term_id ): array {
		return self::by_term()[ $term_id ] ?? [];
	}

	/**
	 * The routes of each walk, for distance_range().
	 *
	 * @param array<int,array{id:int}> $walks Walks from for_term().
	 * @return array<int,array<int,array<string,mixed>>>
	 */
	public static function routes( array $walks ): array {
		$ids = array_column( $walks, 'id' );
		update_meta_cache( 'post', $ids );

		return array_map(
			static function ( int $id ): array {
				$routes = get_post_meta( $id, Event::META_ROUTES, true );
				return is_array( $routes ) ? $routes : [];
			},
			$ids
		);
	}

	/**
	 * The shortest and longest route of the walks, null when no route has a
	 * distance.
	 *
	 * @param array<int,array<int,array<string,mixed>>> $routes_per_walk Routes of each walk.
	 * @return array{0:float,1:float}|null
	 */
	public static function distance_range( array $routes_per_walk ): ?array {
		$distances = [];
		foreach ( $routes_per_walk as $routes ) {
			foreach ( $routes as $route ) {
				$km = isset( $route['distance_km'] ) ? (float) $route['distance_km'] : 0.0;
				if ( $km > 0 ) {
					$distances[] = $km;
				}
			}
		}

		return $distances ? [ min( $distances ), max( $distances ) ] : null;
	}

	/**
	 * A distance as the event pages write it: one decimal at most, "5.5",
	 * "25".
	 *
	 * @param float $km Distance in km.
	 * @return string
	 */
	public static function format_km( float $km ): string {
		return rtrim( rtrim( sprintf( '%.1f', $km ), '0' ), '.' );
	}

	/**
	 * The regions with walks and the number of walks in each, most walks
	 * first, then by name. A walk in two regions counts in both.
	 *
	 * @param array<int,array{regions:array<string,string>}> $walks Walks from for_term().
	 * @return array<int,array{slug:string,name:string,count:int}>
	 */
	public static function regions( array $walks ): array {
		$regions = [];
		foreach ( $walks as $walk ) {
			foreach ( $walk['regions'] as $slug => $name ) {
				$slug = (string) $slug;
				if ( ! isset( $regions[ $slug ] ) ) {
					$regions[ $slug ] = [
						'slug'  => $slug,
						'name'  => $name,
						'count' => 0,
					];
				}
				++$regions[ $slug ]['count'];
			}
		}

		$regions = array_values( $regions );
		usort(
			$regions,
			static function ( array $a, array $b ): int {
				return [ $b['count'], $a['name'] ] <=> [ $a['count'], $b['name'] ];
			}
		);

		return $regions;
	}

	/**
	 * The organiser page filtered by one region, at its walks.
	 *
	 * @param string $permalink Organiser page URL.
	 * @param string $slug      event_region term slug.
	 * @return string
	 */
	public static function region_url( string $permalink, string $slug ): string {
		return $permalink . ( str_contains( $permalink, '?' ) ? '&' : '?' ) . 'region=' . rawurlencode( $slug ) . '#kommende-ture';
	}

	/**
	 * The other organisers to show under "Andre arrangører": those sharing
	 * the most regions with this one first, then those with the most
	 * upcoming walks, then by name.
	 *
	 * @param array<int,array<int,array{regions:array<string,string>}>> $by_term    Walks per term ID, from by_term().
	 * @param int                                                       $term_id    This organiser's term ID.
	 * @param array<int,string>                                         $candidates Name per term ID of the organisers with a page.
	 * @param int                                                       $limit      How many to return.
	 * @return array<int,array{term_id:int,walks:int}>
	 */
	public static function related( array $by_term, int $term_id, array $candidates, int $limit = self::RELATED_LIMIT ): array {
		$own_regions = array_keys( array_column( self::regions( $by_term[ $term_id ] ?? [] ), 'name', 'slug' ) );

		$related = [];
		foreach ( $candidates as $candidate_id => $name ) {
			if ( $candidate_id === $term_id ) {
				continue;
			}

			$walks     = $by_term[ $candidate_id ] ?? [];
			$regions   = array_keys( array_column( self::regions( $walks ), 'name', 'slug' ) );
			$related[] = [
				'term_id' => (int) $candidate_id,
				'walks'   => count( $walks ),
				'shared'  => count( array_intersect( $own_regions, $regions ) ),
				'name'    => $name,
			];
		}

		usort(
			$related,
			static function ( array $a, array $b ): int {
				return [ $b['shared'], $b['walks'], $a['name'] ] <=> [ $a['shared'], $a['walks'], $b['name'] ];
			}
		);

		return array_map(
			static function ( array $organiser ): array {
				return [
					'term_id' => $organiser['term_id'],
					'walks'   => $organiser['walks'],
				];
			},
			array_slice( $related, 0, $limit )
		);
	}
}
