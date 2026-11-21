<?php
/**
 * Organiser Schema Class
 *
 * Prints one schema.org JSON-LD block (Organization, WebPage, BreadcrumbList
 * and an ItemList of upcoming walks) in the <head> of a published organiser
 * page, for search-engine crawlers. Like Vandrekalender_Event_Schema it is our
 * own class, prunes empty values and prints nothing when there is nothing to
 * say (decision 0004). It only reads; it writes nothing.
 *
 * The functions that build the arrays take arrays and return arrays, so they
 * are unit tested without WordPress (decision 0005).
 *
 * @package Vandrekalender
 */

namespace Vandrekalender;

defined( 'ABSPATH' ) || exit;

/**
 * Organiser_Schema class to print the organiser page's JSON-LD.
 */
class Organiser_Schema {
	// The ItemList holds at most this many upcoming walks.
	public const WALKS_LIMIT = 12;

	/**
	 * Constructor — hooks the output late in wp_head, like the event schema.
	 */
	public function __construct() {
		add_action( 'wp_head', [ $this, 'render' ], 99 );
	}

	/**
	 * Print the JSON-LD block on a published organiser page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! is_singular( Organiser::CUSTOMPOSTTYPE ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id || 'publish' !== get_post_status( $post_id ) ) {
			return;
		}

		$term = Organiser::term_for_post( $post_id );
		if ( null === $term ) {
			return;
		}

		$walk_urls = [];
		foreach ( array_slice( Organiser_Walks::for_term( $term->term_id ), 0, self::WALKS_LIMIT ) as $walk ) {
			$walk_urls[] = (string) get_permalink( $walk['id'] );
		}

		$archive_url = get_post_type_archive_link( Organiser::CUSTOMPOSTTYPE );
		$post        = get_post( $post_id );

		$graph = self::build_graph(
			[
				'name'          => $term->name,
				'url'           => (string) get_permalink( $post_id ),
				'description'   => self::description(
					(string) get_post_meta( $post_id, 'rank_math_description', true ),
					(string) get_the_excerpt( $post_id ),
					$post ? (string) $post->post_content : ''
				),
				'image'         => self::first_image_url( $post ? (string) $post->post_content : '' ),
				'website'       => self::most_common_url( self::organiser_urls( $term->term_id ) ),
				'home_url'      => home_url( '/' ),
				'home_label'    => __( 'Calendar', 'vandrekalender-events' ),
				'archive_url'   => $archive_url ? $archive_url : '',
				'archive_label' => __( 'Organisers', 'vandrekalender-events' ),
				'walk_urls'     => $walk_urls,
			]
		);

		if ( empty( $graph ) ) {
			return;
		}

		// wp_json_encode escapes correctly for a <script> context; running the
		// result through esc_html()/wp_kses() would corrupt the JSON.
		echo '<script type="application/ld+json">'
			. wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode escapes for a script context; further escaping breaks the JSON.
			. '</script>' . "\n";
	}

	/**
	 * The organiser websites typed on a term's events (all statuses that
	 * show on the site are fine: the most common one wins).
	 *
	 * @param int $term_id event_organiser term ID.
	 * @return string[]
	 */
	private static function organiser_urls( int $term_id ): array {
		$ids = get_posts(
			[
				'post_type'      => Event::CUSTOMPOSTTYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- one page-load query on organiser pages.
					[
						'taxonomy' => Event::TAX_ORGANISER,
						'terms'    => [ $term_id ],
					],
				],
			]
		);

		$urls = [];
		foreach ( $ids as $id ) {
			$urls[] = (string) get_post_meta( (int) $id, Event::META_ORGANISER_URL, true );
		}

		return $urls;
	}

	/**
	 * The graph, without WordPress.
	 *
	 * @param array<string,mixed> $data name, url, description, image, website,
	 *                                  home_url, home_label, archive_url,
	 *                                  archive_label, walk_urls.
	 * @return array<string,mixed> The schema, or [] when there is nothing to say.
	 */
	public static function build_graph( array $data ): array {
		$name = trim( (string) ( $data['name'] ?? '' ) );
		$url  = trim( (string) ( $data['url'] ?? '' ) );

		if ( '' === $name || '' === $url ) {
			return [];
		}

		$org_id        = $url . '#organization';
		$breadcrumb_id = $url . '#breadcrumb';
		$website       = trim( (string) ( $data['website'] ?? '' ) );

		$organization = [
			'@type'       => 'Organization',
			'@id'         => $org_id,
			'name'        => $name,
			'url'         => $url,
			'description' => trim( (string) ( $data['description'] ?? '' ) ),
			'image'       => trim( (string) ( $data['image'] ?? '' ) ),
			'sameAs'      => '' !== $website ? [ $website ] : [],
		];

		$webpage = [
			'@type'      => 'WebPage',
			'@id'        => $url,
			'url'        => $url,
			'name'       => $name,
			'about'      => [ '@id' => $org_id ],
			'breadcrumb' => [ '@id' => $breadcrumb_id ],
		];

		$graph = [
			$organization,
			$webpage,
			self::breadcrumb_list( $breadcrumb_id, $name, $url, $data ),
		];

		$walks = self::item_list( (array) ( $data['walk_urls'] ?? [] ) );
		if ( [] !== $walks ) {
			$graph[] = $walks;
		}

		return [
			'@context' => 'https://schema.org',
			'@graph'   => array_map( [ self::class, 'prune' ], $graph ),
		];
	}

	/**
	 * Kalender → Arrangører → organiser name.
	 *
	 * @param string              $id   The list's @id.
	 * @param string              $name Organiser name.
	 * @param string              $url  Organiser page URL.
	 * @param array<string,mixed> $data Input of build_graph().
	 * @return array<string,mixed>
	 */
	private static function breadcrumb_list( string $id, string $name, string $url, array $data ): array {
		$trail = [
			[
				(string) ( $data['home_label'] ?? '' ),
				(string) ( $data['home_url'] ?? '' ),
			],
			[
				(string) ( $data['archive_label'] ?? '' ),
				(string) ( $data['archive_url'] ?? '' ),
			],
			[ $name, $url ],
		];

		$items = [];
		foreach ( $trail as $crumb ) {
			if ( '' === $crumb[0] || '' === $crumb[1] ) {
				continue;
			}
			$items[] = [
				'@type'    => 'ListItem',
				'position' => count( $items ) + 1,
				'name'     => $crumb[0],
				'item'     => $crumb[1],
			];
		}

		return [
			'@type'           => 'BreadcrumbList',
			'@id'             => $id,
			'itemListElement' => $items,
		];
	}

	/**
	 * The upcoming walks as Google's summary-page ItemList: position and url
	 * only (the Event data sits on each event page). [] without walks.
	 *
	 * @param string[] $urls Event page URLs, in date order.
	 * @return array<string,mixed>
	 */
	public static function item_list( array $urls ): array {
		$urls = array_slice( array_values( array_filter( array_map( 'strval', $urls ) ) ), 0, self::WALKS_LIMIT );

		if ( [] === $urls ) {
			return [];
		}

		$items = [];
		foreach ( $urls as $index => $walk_url ) {
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'url'      => $walk_url,
			];
		}

		return [
			'@type'           => 'ItemList',
			'itemListElement' => $items,
		];
	}

	/**
	 * The description: Rank Math's (what the search snippet shows), else the
	 * excerpt, else the first paragraph of the content. A Rank Math text with
	 * variables (%…%) is not the final text, so it does not count.
	 *
	 * @param string $rank_math Post meta rank_math_description.
	 * @param string $excerpt   Post excerpt.
	 * @param string $content   Post content (block markup).
	 * @return string
	 */
	public static function description( string $rank_math, string $excerpt, string $content ): string {
		$rank_math = self::plain( $rank_math );
		if ( '' !== $rank_math && ! preg_match( '/%[^%\s]+%/u', $rank_math ) ) {
			return $rank_math;
		}

		$excerpt = self::plain( $excerpt );
		if ( '' !== $excerpt ) {
			return $excerpt;
		}

		if ( preg_match( '#<p\b[^>]*>(.*?)</p>#su', $content, $match ) ) {
			return self::plain( $match[1] );
		}

		return '';
	}

	/**
	 * The URL of the first core/image in the content, at full size (the
	 * block's class "size-full" or a sized file name is not relied on: the
	 * saved src is taken as is).
	 *
	 * @param string $content Post content (block markup).
	 * @return string
	 */
	public static function first_image_url( string $content ): string {
		if ( ! preg_match( '#<!--\s*wp:image\b.*?-->(.*?)<!--\s*/wp:image\s*-->#su', $content, $block ) ) {
			return '';
		}

		if ( ! preg_match( '#<img\b[^>]*\ssrc=["\']([^"\']+)["\']#iu', $block[1], $img ) ) {
			return '';
		}

		return html_entity_decode( $img[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * The most common valid URL; the first one seen wins a tie.
	 *
	 * @param string[] $urls Typed URLs, some empty or invalid.
	 * @return string '' when none is valid.
	 */
	public static function most_common_url( array $urls ): string {
		$counts = [];
		foreach ( $urls as $url ) {
			$url = trim( (string) $url );
			if ( '' === $url || ! preg_match( '#^https?://#i', $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				continue;
			}
			$counts[ $url ] = ( $counts[ $url ] ?? 0 ) + 1;
		}

		if ( [] === $counts ) {
			return '';
		}

		arsort( $counts ); // Stable since PHP 8: equal counts keep their order.

		return (string) array_key_first( $counts );
	}

	/**
	 * Plain text: tags and entities removed, white space collapsed.
	 *
	 * @param string $text HTML or text.
	 * @return string
	 */
	private static function plain( string $text ): string {
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Recursively drop null, empty-string and empty-array values.
	 *
	 * @param array<mixed> $data Schema fragment.
	 * @return array<mixed>
	 */
	private static function prune( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$value        = self::prune( $value );
				$data[ $key ] = $value;
			}

			if ( null === $value || '' === $value || [] === $value ) {
				unset( $data[ $key ] );
			}
		}

		return $data;
	}
}
