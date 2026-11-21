<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * The JSON-LD on an organiser page (#224).
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Organiser_Schema;

/**
 * Organiser_Schema: the graph, the description, the image and the website.
 */
class OrganiserSchemaTest extends TestCase {

	/**
	 * A complete input.
	 *
	 * @param array<string,mixed> $override Values to change.
	 * @return array<string,mixed>
	 */
	private static function data( array $override = [] ): array {
		return array_merge(
			[
				'name'          => 'Mammutmarch',
				'url'           => 'https://example.com/arrangoerer/mammutmarch/',
				'description'   => 'Danmarks største gåtur.',
				'image'         => 'https://example.com/uploads/mammut.jpg',
				'website'       => 'https://mammutmarch.dk/',
				'home_url'      => 'https://example.com/',
				'home_label'    => 'Kalender',
				'archive_url'   => 'https://example.com/arrangoerer/',
				'archive_label' => 'Arrangører',
				'walk_urls'     => [ 'https://example.com/begivenhed/a/', 'https://example.com/begivenhed/b/' ],
			],
			$override
		);
	}

	/**
	 * The graph node of a type.
	 *
	 * @param array<string,mixed> $schema The schema.
	 * @param string              $type   @type.
	 * @return array<string,mixed>|null
	 */
	private static function node( array $schema, string $type ): ?array {
		foreach ( $schema['@graph'] as $node ) {
			if ( $type === $node['@type'] ) {
				return $node;
			}
		}

		return null;
	}

	/**
	 * Organization, WebPage, BreadcrumbList and ItemList in one graph.
	 */
	public function test_full_graph(): void {
		$schema = Organiser_Schema::build_graph( self::data() );

		$this->assertSame( 'https://schema.org', $schema['@context'] );
		$this->assertCount( 4, $schema['@graph'] );

		$org = self::node( $schema, 'Organization' );
		$this->assertSame( 'https://example.com/arrangoerer/mammutmarch/#organization', $org['@id'] );
		$this->assertSame( 'Mammutmarch', $org['name'] );
		$this->assertSame( 'Danmarks største gåtur.', $org['description'] );
		$this->assertSame( 'https://example.com/uploads/mammut.jpg', $org['image'] );
		$this->assertSame( [ 'https://mammutmarch.dk/' ], $org['sameAs'] );

		$page = self::node( $schema, 'WebPage' );
		$this->assertSame( 'https://example.com/arrangoerer/mammutmarch/', $page['@id'] );
		$this->assertSame( [ '@id' => $org['@id'] ], $page['about'] );
		$this->assertSame( [ '@id' => 'https://example.com/arrangoerer/mammutmarch/#breadcrumb' ], $page['breadcrumb'] );

		$crumbs = self::node( $schema, 'BreadcrumbList' );
		$this->assertSame( $page['breadcrumb']['@id'], $crumbs['@id'] );
		$this->assertSame(
			[ 'Kalender', 'Arrangører', 'Mammutmarch' ],
			array_column( $crumbs['itemListElement'], 'name' )
		);
		$this->assertSame( [ 1, 2, 3 ], array_column( $crumbs['itemListElement'], 'position' ) );

		$list = self::node( $schema, 'ItemList' );
		$this->assertSame(
			[
				[
					'@type'    => 'ListItem',
					'position' => 1,
					'url'      => 'https://example.com/begivenhed/a/',
				],
				[
					'@type'    => 'ListItem',
					'position' => 2,
					'url'      => 'https://example.com/begivenhed/b/',
				],
			],
			$list['itemListElement']
		);
	}

	/**
	 * No upcoming walks: no ItemList.
	 */
	public function test_no_walks_no_item_list(): void {
		$schema = Organiser_Schema::build_graph( self::data( [ 'walk_urls' => [] ] ) );

		$this->assertNull( self::node( $schema, 'ItemList' ) );
		$this->assertCount( 3, $schema['@graph'] );
	}

	/**
	 * No website: no sameAs.
	 */
	public function test_no_website_no_same_as(): void {
		$org = self::node( Organiser_Schema::build_graph( self::data( [ 'website' => '' ] ) ), 'Organization' );

		$this->assertArrayNotHasKey( 'sameAs', $org );
	}

	/**
	 * No image: no image key.
	 */
	public function test_no_image(): void {
		$org = self::node( Organiser_Schema::build_graph( self::data( [ 'image' => '' ] ) ), 'Organization' );

		$this->assertArrayNotHasKey( 'image', $org );
	}

	/**
	 * More than 12 walks: the list stops at 12.
	 */
	public function test_walks_capped_at_twelve(): void {
		$urls = [];
		for ( $i = 1; $i <= 15; $i++ ) {
			$urls[] = 'https://example.com/begivenhed/' . $i . '/';
		}

		$list = self::node( Organiser_Schema::build_graph( self::data( [ 'walk_urls' => $urls ] ) ), 'ItemList' );

		$this->assertCount( 12, $list['itemListElement'] );
		$this->assertSame( 12, $list['itemListElement'][11]['position'] );
		$this->assertSame( 'https://example.com/begivenhed/12/', $list['itemListElement'][11]['url'] );
	}

	/**
	 * Without a name or URL there is nothing to say.
	 */
	public function test_nothing_without_name_or_url(): void {
		$this->assertSame( [], Organiser_Schema::build_graph( self::data( [ 'name' => '' ] ) ) );
		$this->assertSame( [], Organiser_Schema::build_graph( self::data( [ 'url' => '' ] ) ) );
	}

	/**
	 * A missing overview URL leaves that crumb out and renumbers.
	 */
	public function test_breadcrumb_skips_missing_archive(): void {
		$crumbs = self::node( Organiser_Schema::build_graph( self::data( [ 'archive_url' => '' ] ) ), 'BreadcrumbList' );

		$this->assertSame( [ 'Kalender', 'Mammutmarch' ], array_column( $crumbs['itemListElement'], 'name' ) );
		$this->assertSame( [ 1, 2 ], array_column( $crumbs['itemListElement'], 'position' ) );
	}

	/**
	 * The description is Rank Math's, then the excerpt, then the first paragraph.
	 */
	public function test_description_fallbacks(): void {
		$content = "<!-- wp:paragraph -->\n<p>Første <strong>afsnit</strong> &amp; mere.</p>\n<!-- /wp:paragraph -->\n<p>Andet.</p>";

		$this->assertSame( 'Rank Math tekst', Organiser_Schema::description( ' Rank Math tekst ', 'Uddrag', $content ) );
		$this->assertSame( 'Uddrag', Organiser_Schema::description( '', 'Uddrag', $content ) );
		$this->assertSame( 'Uddrag', Organiser_Schema::description( '%excerpt% | %sitename%', 'Uddrag', $content ) );
		$this->assertSame( 'Første afsnit & mere.', Organiser_Schema::description( '', '', $content ) );
		$this->assertSame( '', Organiser_Schema::description( '', '', 'Ingen afsnit' ) );
	}

	/**
	 * The first core/image, as saved.
	 */
	public function test_first_image_url(): void {
		$content = '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->'
			. '<!-- wp:image {"id":5} --><figure class="wp-block-image"><img src="https://example.com/a.jpg?x=1&amp;y=2" alt=""/></figure><!-- /wp:image -->'
			. '<!-- wp:image --><figure><img src="https://example.com/b.jpg" alt=""/></figure><!-- /wp:image -->';

		$this->assertSame( 'https://example.com/a.jpg?x=1&y=2', Organiser_Schema::first_image_url( $content ) );
		$this->assertSame( '', Organiser_Schema::first_image_url( '<p>ingen</p>' ) );
	}

	/**
	 * The most common valid website wins; invalid ones are ignored.
	 */
	public function test_most_common_url(): void {
		$this->assertSame(
			'https://b.dk/',
			Organiser_Schema::most_common_url( [ 'https://a.dk/', 'https://b.dk/', 'nonsense', '', 'https://b.dk/' ] )
		);
		$this->assertSame( 'https://a.dk/', Organiser_Schema::most_common_url( [ 'https://a.dk/', 'https://b.dk/' ] ) );
		$this->assertSame( '', Organiser_Schema::most_common_url( [ 'nonsense', 'javascript:alert(1)', '' ] ) );
	}
}
