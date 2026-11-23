<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * Deleting archived walks (#269): which walks, which of their files, and
 * when a file is kept because something else uses it.
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Purge_Job.
 */
class PurgeJobTest extends TestCase {

	/**
	 * The walk being deleted.
	 */
	private const WALK = 10;

	/**
	 * Its image: attachment 55, uploads/2026/11/bjerge.jpg.
	 */
	private const IMAGE = 55;
	private const STEM  = '2026/11/bjerge';

	/**
	 * Walks archived more than 3 years ago are deleted.
	 */
	public function test_purge_cutoff_is_three_years_back(): void {
		$this->assertSame( '2023-11-23', Vandrekalender_Purge_Job::purge_cutoff( '2026-11-23' ) );
	}

	/**
	 * Only archived walks with an archive date before the cutoff.
	 */
	public function test_selection(): void {
		$args = Vandrekalender_Purge_Job::selection_args( '2026-11-23' );

		$this->assertSame( 'event', $args['post_type'] );
		$this->assertSame( 'archived', $args['post_status'] );
		$this->assertSame( 'event_archived_at', $args['meta_query'][0]['key'] );
		$this->assertSame( '<', $args['meta_query'][0]['compare'] );
		$this->assertSame( '2023-11-23', $args['meta_query'][0]['value'] );
		$this->assertSame( '!=', $args['meta_query'][1]['compare'], 'Never a walk without an archive date.' );
	}

	/**
	 * A file only the walk itself uses is deleted.
	 */
	public function test_unused_file_is_deleted(): void {
		$users = $this->users(
			[ $this->content( self::WALK, '<!-- wp:image {"id":55} --><img class="wp-image-55" src="https://allevandreture.dk/wp-content/uploads/2026/11/bjerge-300x200.jpg"><!-- /wp:image -->' ) ],
			[ $this->meta( self::WALK, '_thumbnail_id', '55' ) ]
		);

		$this->assertSame( [], $users );
	}

	/**
	 * Another walk with the same featured image keeps it.
	 */
	public function test_shared_featured_image_is_kept(): void {
		$this->assertSame( [ 'post 20' ], $this->users( [], [ $this->meta( 20, '_thumbnail_id', '55' ) ] ) );
	}

	/**
	 * An image in another walk's content is kept, by ID or by URL.
	 */
	public function test_image_in_another_walks_content_is_kept(): void {
		$this->assertSame( [ 'post 21' ], $this->users( [ $this->content( 21, '<figure><img class="wp-image-55"></figure>' ) ] ) );
		$this->assertSame( [ 'post 22' ], $this->users( [ $this->content( 22, '<img src="/wp-content/uploads/2026/11/bjerge-1024x683.jpg">' ) ] ) );
		$this->assertSame( [ 'post 23' ], $this->users( [ $this->content( 23, '<!-- wp:gallery {"ids":[54,55,56]} -->' ) ] ) );
		$this->assertSame( [ 'post 24' ], $this->users( [ $this->content( 24, '[gallery ids="12,55"]' ) ] ) );
	}

	/**
	 * An organiser page's logo (its featured image) is kept.
	 */
	public function test_organiser_logo_is_kept(): void {
		$this->assertSame( [ 'post 30' ], $this->users( [], [ $this->meta( 30, '_thumbnail_id', '55' ) ] ) );
	}

	/**
	 * A GPX file another walk's route uses is kept.
	 */
	public function test_gpx_in_another_walks_route_is_kept(): void {
		$route  = [
			'id'     => 'a',
			'gpx_id' => '55',
		];
		$routes = serialize( [ $route ] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- as stored in postmeta.

		$this->assertSame( [ 'post 25' ], $this->users( [], [ $this->meta( 25, 'event_routes', $routes ) ] ) );
	}

	/**
	 * The site logo in the theme settings, and the site icon, are kept.
	 */
	public function test_site_logo_and_icon_are_kept(): void {
		$theme_mods = serialize( [ 'custom_logo' => 55 ] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- as stored in options.

		$users = Vandrekalender_Purge_Job::users_from_rows(
			self::IMAGE,
			self::STEM,
			[ self::WALK ],
			[],
			[],
			[
				(object) [
					'option_name'  => 'theme_mods_vandrekalender-theme',
					'option_value' => $theme_mods,
				],
				(object) [
					'option_name'  => 'site_icon',
					'option_value' => '55',
				],
			]
		);

		$this->assertSame( [ 'option theme_mods_vandrekalender-theme', 'option site_icon' ], $users );
	}

	/**
	 * Caches in transients do not keep a file.
	 */
	public function test_transients_do_not_count(): void {
		$paths = serialize( [ '/var/www/html/wp-content/uploads/2026/11/bjerge.jpg' ] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- as stored in options.

		$users = Vandrekalender_Purge_Job::users_from_rows(
			self::IMAGE,
			self::STEM,
			[ self::WALK ],
			[],
			[],
			[
				(object) [
					'option_name'  => '_site_transient_webpc_cron_paths',
					'option_value' => $paths,
				],
				(object) [
					'option_name'  => '_transient_some_cache',
					'option_value' => '55',
				],
			]
		);

		$this->assertSame( [], $users );
	}

	/**
	 * Other walks deleted in the same run do not keep a file.
	 */
	public function test_walks_deleted_in_the_same_run_do_not_count(): void {
		$users = Vandrekalender_Purge_Job::users_from_rows(
			self::IMAGE,
			self::STEM,
			[ self::WALK, 20 ],
			[],
			[ $this->meta( 20, '_thumbnail_id', '55' ) ],
			[]
		);

		$this->assertSame( [], $users );
	}

	/**
	 * Other IDs and files that only look alike do not keep it.
	 */
	public function test_similar_ids_do_not_count(): void {
		$this->assertSame(
			[],
			$this->users(
				[ $this->content( 26, '<img class="wp-image-550"><!-- wp:image {"id":155} -->' ) ],
				[ $this->meta( 27, '_thumbnail_id', '550' ), $this->meta( 28, 'event_price', '5500' ) ]
			)
		);
	}

	/**
	 * IDs named in block markup and shortcodes.
	 */
	public function test_attachment_ids_in_content(): void {
		$ids = Vandrekalender_Purge_Job::attachment_ids_in_content(
			'<img class="wp-image-7 size-large"><!-- wp:image {"id":7} --><!-- wp:cover {"id":8} --><!-- wp:media-text {"mediaId":9} --><!-- wp:gallery {"ids":[10, 11]} -->[gallery ids="12,13"]'
		);
		sort( $ids );

		$this->assertSame( [ 7, 8, 9, 10, 11, 12, 13 ], $ids );
	}

	/**
	 * The stem matches every size of the file, also of a scaled one.
	 */
	public function test_file_stem(): void {
		$this->assertSame( '2026/11/bjerge', Vandrekalender_Purge_Job::file_stem( '2026/11/bjerge.jpg' ) );
		$this->assertSame( '2026/11/bjerge', Vandrekalender_Purge_Job::file_stem( '2026/11/bjerge-scaled.jpg' ) );
		$this->assertSame( '2026/11/route', Vandrekalender_Purge_Job::file_stem( '2026/11/route.gpx' ) );
		$this->assertSame( '', Vandrekalender_Purge_Job::file_stem( '' ) );
	}

	/**
	 * Route GPX IDs, skipping routes without one.
	 */
	public function test_route_gpx_ids(): void {
		$this->assertSame(
			[ 5, 6 ],
			Vandrekalender_Purge_Job::route_gpx_ids( [ [ 'gpx_id' => '5' ], [ 'gpx_id' => '' ], [ 'id' => 'x' ], [ 'gpx_id' => '6' ] ] )
		);
	}

	/**
	 * The users of IMAGE among these rows, ignoring WALK.
	 *
	 * @param object[] $content_rows Content rows.
	 * @param object[] $meta_rows    Meta rows.
	 * @return string[]
	 */
	private function users( array $content_rows, array $meta_rows = [] ): array {
		return Vandrekalender_Purge_Job::users_from_rows( self::IMAGE, self::STEM, [ self::WALK ], $content_rows, $meta_rows, [] );
	}

	/**
	 * A content row.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Content.
	 * @return object
	 */
	private function content( int $post_id, string $content ): object {
		return (object) [
			'ID'           => $post_id,
			'post_content' => $content,
		];
	}

	/**
	 * A meta row.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param string $value   Meta value as stored.
	 * @return object
	 */
	private function meta( int $post_id, string $key, string $value ): object {
		return (object) [
			'post_id'    => $post_id,
			'meta_key'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- a test row, not a query.
			'meta_value' => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- a test row, not a query.
		];
	}
}
