<?php

defined( 'ABSPATH' ) || exit;

/**
 * Deletes walks for good ARCHIVE_PURGE_YEARS after they were archived (#37,
 * step 5: #269; decision 0018), with their attendees and the files only they
 * use.
 *
 * A walk's files are its featured image, the images and files its content
 * refers to (wp-image-<id> classes, block "id"/"ids" attributes, gallery
 * shortcodes, upload URLs), the GPX files of its routes (gpx_id) and the
 * attachments uploaded to it. A file is deleted only when nothing else
 * refers to it: no other post of any type or status (content or meta, e.g.
 * another walk's featured image or an organiser page's logo) and no option
 * (site logo, site icon, theme settings) other than transients, which are
 * caches. Walks deleted in the same run do
 * not count as users. When in doubt, the file is kept: a kept file costs
 * disk space, a deleted one breaks a page.
 *
 * Runs with the weekly archive job (Vandrekalender_Archive_Job), and by hand
 * with `wp vandrekalender purge-archived [--dry-run]`. Every real run is
 * recorded in the Scraper Log.
 *
 * @package Vandrekalender
 */
class Vandrekalender_Purge_Job {

	/**
	 * Years an archived walk is kept before it is deleted.
	 */
	const ARCHIVE_PURGE_YEARS = 3;

	/**
	 * Name of the job in the Scraper Log.
	 */
	const LOG_NAME = 'Delete archived walks';

	/**
	 * Deletes every archived walk that is due, or with $dry_run only reports
	 * what it would delete. A real run is recorded in the Scraper Log.
	 *
	 * @param string $trigger 'cron' or 'manual'.
	 * @param bool   $dry_run Report only, change nothing, log nothing.
	 * @return array{
	 *     walks: array<int, array{id: int, title: string, archived_at: string, attendees: int}>,
	 *     delete: array<int, string>,
	 *     keep: array<int, array{file: string, users: string[]}>,
	 *     failed: array<int, string>
	 * } The walks, the files deleted (ID => file) and the ones kept with
	 *   what uses them, and failures by walk or file ID.
	 */
	public static function execute( string $trigger, bool $dry_run ): array {
		$started  = microtime( true );
		$walk_ids = array_map( 'intval', get_posts( self::selection_args( current_time( 'Y-m-d' ) ) ) );

		$walks      = [];
		$files      = [];
		$walk_files = [];
		$failed     = [];

		foreach ( $walk_ids as $walk_id ) {
			$walks[ $walk_id ] = [
				'id'          => $walk_id,
				'title'       => get_the_title( $walk_id ),
				'archived_at' => (string) get_post_meta( $walk_id, Vandrekalender_Archived_Walks::META_ARCHIVED_AT, true ),
				'attendees'   => Vandrekalender_Event_Attendees::count( $walk_id ),
			];

			$walk_files[ $walk_id ] = self::walk_files( $walk_id );
			foreach ( $walk_files[ $walk_id ] as $file_id ) {
				$files[ $file_id ] = true;
			}
		}

		// Decide for every file before anything is deleted, so the answer
		// does not depend on the order the walks are deleted in.
		$delete = [];
		$keep   = [];
		foreach ( array_keys( $files ) as $file_id ) {
			$file  = (string) get_post_meta( $file_id, '_wp_attached_file', true );
			$users = self::find_users( $file_id, $file, $walk_ids );

			if ( $users ) {
				$keep[ $file_id ] = [
					'file'  => $file,
					'users' => $users,
				];
			} else {
				$delete[ $file_id ] = $file;
			}
		}

		if ( ! $dry_run ) {
			$failed_walks = [];
			foreach ( $walk_ids as $walk_id ) {
				if ( ! wp_delete_post( $walk_id, true ) ) {
					$failed[ $walk_id ] = 'wp_delete_post failed';
					$failed_walks[]     = $walk_id;
					unset( $walks[ $walk_id ] );
					continue;
				}

				// Only once the walk is gone: a walk that stays keeps its attendees.
				Vandrekalender_Event_Attendees::delete_for_event( $walk_id );
			}

			// The files were decided with every due walk as deleted. A walk
			// that is still there still uses its files, so they stay.
			$split  = self::keep_files_of_failed_walks( $delete, $walk_files, $failed_walks );
			$delete = $split['delete'];
			$keep  += $split['keep'];

			foreach ( array_keys( $delete ) as $file_id ) {
				if ( ! wp_delete_attachment( $file_id, true ) ) {
					$failed[ $file_id ] = 'wp_delete_attachment failed';
					unset( $delete[ $file_id ] );
				}
			}

			Vandrekalender_Scraper_Log::record(
				Vandrekalender_Archive_Job::log_entry( $trigger, count( $walks ), $failed, microtime( true ) - $started, self::LOG_NAME )
			);
		}

		return [
			'walks'  => array_values( $walks ),
			'delete' => $delete,
			'keep'   => $keep,
			'failed' => $failed,
		];
	}

	/**
	 * Moves the files of walks that could not be deleted from $delete to
	 * kept: those walks still exist and still use them.
	 *
	 * @param array<int, string> $delete       File ID => file, decided as unused.
	 * @param array<int, int[]>  $walk_files   Walk ID => its file IDs.
	 * @param int[]              $failed_walks Walks wp_delete_post() did not delete.
	 * @return array{delete: array<int, string>, keep: array<int, array{file: string, users: string[]}>}
	 */
	public static function keep_files_of_failed_walks( array $delete, array $walk_files, array $failed_walks ): array {
		$keep = [];

		foreach ( $failed_walks as $walk_id ) {
			foreach ( $walk_files[ $walk_id ] ?? [] as $file_id ) {
				if ( ! isset( $delete[ $file_id ] ) ) {
					continue;
				}

				$keep[ $file_id ]['file']    = $delete[ $file_id ];
				$keep[ $file_id ]['users'][] = 'post ' . $walk_id . ' (could not be deleted)';
				unset( $delete[ $file_id ] );
			}
		}

		return [
			'delete' => $delete,
			'keep'   => $keep,
		];
	}

	/**
	 * The oldest archive date still kept: ARCHIVE_PURGE_YEARS before $today.
	 * Walks archived before it are deleted.
	 *
	 * @param string $today Today as Y-m-d.
	 * @return string Y-m-d.
	 */
	public static function purge_cutoff( string $today ): string {
		return ( new DateTimeImmutable( $today ) )->modify( '-' . self::ARCHIVE_PURGE_YEARS . ' years' )->format( 'Y-m-d' );
	}

	/**
	 * The query for walks due for deletion: archived before purge_cutoff().
	 * A walk without event_archived_at is never deleted.
	 *
	 * @param string $today Today as Y-m-d.
	 * @return array<string,mixed> get_posts() arguments.
	 */
	public static function selection_args( string $today ): array {
		return [
			'post_type'      => \Vandrekalender\Event::CUSTOMPOSTTYPE,
			'post_status'    => Vandrekalender_Archived_Walks::STATUS,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- weekly job, not a request-time query.
				'relation' => 'AND',
				[
					'key'     => Vandrekalender_Archived_Walks::META_ARCHIVED_AT,
					'value'   => self::purge_cutoff( $today ),
					'compare' => '<',
					'type'    => 'DATE',
				],
				[
					'key'     => Vandrekalender_Archived_Walks::META_ARCHIVED_AT,
					'value'   => '',
					'compare' => '!=',
				],
			],
		];
	}

	/**
	 * The attachment IDs a walk uses: featured image, route GPX files,
	 * attachments in its content and attachments uploaded to it.
	 *
	 * @param int $walk_id Walk ID.
	 * @return int[]
	 */
	public static function walk_files( int $walk_id ): array {
		$ids = [ (int) get_post_thumbnail_id( $walk_id ) ];

		$routes = get_post_meta( $walk_id, \Vandrekalender\Event::META_ROUTES, true );
		$ids    = array_merge( $ids, self::route_gpx_ids( is_array( $routes ) ? $routes : [] ) );

		$content = (string) get_post_field( 'post_content', $walk_id );
		$ids     = array_merge( $ids, self::attachment_ids_in_content( $content ) );
		foreach ( self::upload_urls_in_content( $content ) as $url ) {
			$ids[] = (int) attachment_url_to_postid( $url );
		}

		$ids = array_merge(
			$ids,
			get_posts(
				[
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'post_parent'    => $walk_id,
					'posts_per_page' => -1,
					'fields'         => 'ids',
				]
			)
		);

		$files = [];
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			if ( $id > 0 && 'attachment' === get_post_type( $id ) ) {
				$files[] = $id;
			}
		}

		return $files;
	}

	/**
	 * The gpx_id of each route that has one.
	 *
	 * @param array<int, mixed> $routes event_routes.
	 * @return int[]
	 */
	public static function route_gpx_ids( array $routes ): array {
		$ids = [];
		foreach ( $routes as $route ) {
			if ( is_array( $route ) && ! empty( $route['gpx_id'] ) && (int) $route['gpx_id'] > 0 ) {
				$ids[] = (int) $route['gpx_id'];
			}
		}

		return $ids;
	}

	/**
	 * Attachment IDs that block markup or shortcodes in $content name:
	 * wp-image-<id> classes, block "id", "ids" and "mediaId" attributes, and
	 * [gallery ids="…"].
	 *
	 * @param string $content Post content.
	 * @return int[]
	 */
	public static function attachment_ids_in_content( string $content ): array {
		$ids = [];

		preg_match_all( '/\bwp-image-(\d+)\b/', $content, $m );
		$ids = array_merge( $ids, $m[1] );

		preg_match_all( '/"(?:id|mediaId)":\s*(\d+)/', $content, $m );
		$ids = array_merge( $ids, $m[1] );

		preg_match_all( '/"ids":\s*\[([\d,\s]*)\]/', $content, $m );
		foreach ( $m[1] as $list ) {
			$ids = array_merge( $ids, preg_split( '/[\s,]+/', $list, -1, PREG_SPLIT_NO_EMPTY ) );
		}

		preg_match_all( '/\[gallery[^\]]*\bids="([\d,\s]*)"/', $content, $m );
		foreach ( $m[1] as $list ) {
			$ids = array_merge( $ids, preg_split( '/[\s,]+/', $list, -1, PREG_SPLIT_NO_EMPTY ) );
		}

		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * URLs of files under wp-content/uploads in $content (src, href).
	 *
	 * @param string $content Post content.
	 * @return string[]
	 */
	public static function upload_urls_in_content( string $content ): array {
		preg_match_all( '#https?://[^\s"\'<>]+/wp-content/uploads/[^\s"\'<>?]+#', $content, $m );

		return array_values( array_unique( $m[0] ) );
	}

	/**
	 * The part every URL of a file and its sizes share: the path under
	 * uploads without the extension and without "-scaled", e.g.
	 * "2026/11/walk" for "2026/11/walk-scaled.jpg", which also matches
	 * "walk-300x200.jpg". '' when the file is unknown.
	 *
	 * @param string $file _wp_attached_file.
	 * @return string
	 */
	public static function file_stem( string $file ): string {
		if ( '' === $file ) {
			return '';
		}

		$stem = preg_replace( '/\.[A-Za-z0-9]+$/', '', $file );

		return (string) preg_replace( '/-scaled$/', '', (string) $stem );
	}

	/**
	 * Whether post content refers to attachment $id: by ID in block markup
	 * or shortcodes, or by URL.
	 *
	 * @param string $content Post content.
	 * @param int    $id      Attachment ID.
	 * @param string $stem    file_stem() of the attachment.
	 * @return bool
	 */
	public static function content_uses( string $content, int $id, string $stem ): bool {
		if ( in_array( $id, self::attachment_ids_in_content( $content ), true ) ) {
			return true;
		}

		return '' !== $stem && str_contains( $content, $stem );
	}

	/**
	 * Whether a meta or option value refers to attachment $id: the ID itself
	 * (featured image, organiser logo, site icon), the ID as any value inside
	 * an array (a route's gpx_id, the theme's custom_logo), or the file's URL
	 * anywhere in the value (e.g. an SEO plugin's share image). Deliberately
	 * broad: a false match only keeps a file.
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value, unserialized.
	 * @param int    $id    Attachment ID.
	 * @param string $stem  file_stem() of the attachment.
	 * @return bool
	 */
	public static function meta_uses( string $key, $value, int $id, string $stem ): bool {
		if ( \Vandrekalender\Event::META_ROUTES === $key && is_array( $value ) && in_array( $id, self::route_gpx_ids( $value ), true ) ) {
			return true;
		}

		if ( is_scalar( $value ) && (string) $id === trim( (string) $value ) ) {
			return true;
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$found = false;
			array_walk_recursive(
				$value,
				function ( $item ) use ( $id, &$found ) {
					if ( is_scalar( $item ) && (string) $id === trim( (string) $item ) ) {
						$found = true;
					}
				}
			);
			if ( $found ) {
				return true;
			}
		}

		$flat = is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );

		return '' !== $stem && str_contains( str_replace( '\\/', '/', $flat ), $stem );
	}

	/**
	 * What else uses attachment $id, as "post <ID>" and "option <name>"
	 * labels; [] when nothing does. Posts in $ignore (the walks deleted in
	 * this run), revisions and attachments themselves do not count.
	 *
	 * The SQL only narrows the rows down; content_uses() and meta_uses()
	 * decide.
	 *
	 * @param int    $id     Attachment ID.
	 * @param string $file   _wp_attached_file of the attachment.
	 * @param int[]  $ignore Post IDs that do not count as users.
	 * @return string[]
	 */
	public static function find_users( int $id, string $file, array $ignore ): array {
		global $wpdb;

		$stem    = self::file_stem( $file );
		$like_id = '%' . $wpdb->esc_like( (string) $id ) . '%';
		$like_st = '' !== $stem ? '%' . $wpdb->esc_like( $stem ) . '%' : $like_id;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- weekly job; must see every post type and status, which WP_Query does not.
		$content_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts}
				WHERE post_type NOT IN ('revision', 'attachment')
				AND ( post_content LIKE %s OR post_content LIKE %s )",
				$like_id,
				$like_st
			)
		);

		$meta_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.post_id, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m
				INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				WHERE p.post_type NOT IN ('revision', 'attachment')
				AND ( m.meta_value LIKE %s OR m.meta_value LIKE %s )",
				$like_id,
				$like_st
			)
		);

		$option_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options}
				WHERE option_value LIKE %s OR option_value LIKE %s",
				$like_id,
				$like_st
			)
		);
		// phpcs:enable

		return self::users_from_rows( $id, $stem, $ignore, (array) $content_rows, (array) $meta_rows, (array) $option_rows );
	}

	/**
	 * The decision behind find_users(), on rows already loaded.
	 *
	 * @param int      $id           Attachment ID.
	 * @param string   $stem         file_stem() of the attachment.
	 * @param int[]    $ignore       Post IDs that do not count as users.
	 * @param object[] $content_rows Rows with ID, post_content.
	 * @param object[] $meta_rows    Rows with post_id, meta_key, meta_value (serialized).
	 * @param object[] $option_rows  Rows with option_name, option_value (serialized).
	 * @return string[]
	 */
	public static function users_from_rows( int $id, string $stem, array $ignore, array $content_rows, array $meta_rows, array $option_rows ): array {
		$ignore = array_flip( array_map( 'intval', $ignore ) );
		$users  = [];

		foreach ( $content_rows as $row ) {
			if ( ! isset( $ignore[ (int) $row->ID ] ) && self::content_uses( (string) $row->post_content, $id, $stem ) ) {
				$users[] = 'post ' . (int) $row->ID;
			}
		}

		foreach ( $meta_rows as $row ) {
			if ( ! isset( $ignore[ (int) $row->post_id ] ) && self::meta_uses( (string) $row->meta_key, maybe_unserialize( $row->meta_value ), $id, $stem ) ) {
				$users[] = 'post ' . (int) $row->post_id;
			}
		}

		foreach ( $option_rows as $row ) {
			// Caches (e.g. an image plugin's list of new uploads) are not uses.
			if ( preg_match( '/^_(?:site_)?transient_/', (string) $row->option_name ) ) {
				continue;
			}
			if ( self::meta_uses( (string) $row->option_name, maybe_unserialize( $row->option_value ), $id, $stem ) ) {
				$users[] = 'option ' . (string) $row->option_name;
			}
		}

		return array_values( array_unique( $users ) );
	}
}
