<?php
/**
 * How the filter bar's params become a WP_Query (#32).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Event;

/**
 * Vandrekalender_Event_Rest_Api::build_query_args().
 */
class QueryArgsTest extends TestCase {

	/**
	 * The tax_query clause for one taxonomy, or null when there is none.
	 *
	 * @param array  $args     WP_Query args.
	 * @param string $taxonomy Taxonomy name.
	 * @return array|null
	 */
	private function tax_clause( array $args, string $taxonomy ): ?array {
		foreach ( $args['tax_query'] ?? [] as $clause ) {
			if ( $clause['taxonomy'] === $taxonomy ) {
				return $clause;
			}
		}
		return null;
	}

	/**
	 * One organiser slug filters on the event_organiser taxonomy by slug.
	 */
	public function test_organiser_filters_by_term_slug(): void {
		$args = Vandrekalender_Event_Rest_Api::build_query_args( [ 'organiser' => 'dansk-vandrelaug' ] );

		$this->assertSame(
			[
				'taxonomy' => Event::TAX_ORGANISER,
				'field'    => 'slug',
				'terms'    => [ 'dansk-vandrelaug' ],
			],
			$this->tax_clause( $args, Event::TAX_ORGANISER )
		);
	}

	/**
	 * Several organisers, comma separated, match any of them; each slug is
	 * sanitised, since the value comes straight from the URL.
	 */
	public function test_several_organisers_are_split_and_sanitised(): void {
		$args = Vandrekalender_Event_Rest_Api::build_query_args( [ 'organiser' => 'mammutmarch,<b>Opdag Verden</b>' ] );

		$this->assertSame( [ 'mammutmarch', 'opdag-verden' ], $this->tax_clause( $args, Event::TAX_ORGANISER )['terms'] );
	}

	/**
	 * Organiser and region together narrow the query: both clauses are there.
	 */
	public function test_organiser_and_region_combine(): void {
		$args = Vandrekalender_Event_Rest_Api::build_query_args(
			[
				'organiser' => 'mammutmarch',
				'region'    => 'midtjylland',
			]
		);

		$this->assertNotNull( $this->tax_clause( $args, Event::TAX_ORGANISER ) );
		$this->assertNotNull( $this->tax_clause( $args, Event::TAX_REGION ) );
	}

	/**
	 * No filters: no tax_query, and only upcoming events (from today).
	 */
	public function test_no_filters_means_upcoming_events_only(): void {
		$args = Vandrekalender_Event_Rest_Api::build_query_args( [] );

		$this->assertArrayNotHasKey( 'tax_query', $args );
		$this->assertSame( current_time( 'Y-m-d' ), $args['meta_query'][0]['value'] );
		$this->assertSame( '>=', $args['meta_query'][0]['compare'] );
	}
}
