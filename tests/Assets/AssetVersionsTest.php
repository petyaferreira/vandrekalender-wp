<?php
/**
 * Plugin script and style URLs map to their files (#118), so their `ver`
 * can be the file time.
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Asset_Versions::path_of().
 */
class AssetVersionsTest extends TestCase {

	const URL = 'https://allevandreture.dk/wp-content/plugins/vandrekalender-events/';
	const DIR = __DIR__ . '/../../wp-content/plugins/vandrekalender-events/';

	/**
	 * The filtered-count script, as WordPress passes it with its registered
	 * version, is the file in assets/js/.
	 */
	public function test_plugin_url_with_ver_maps_to_its_file(): void {
		$this->assertSame(
			self::DIR . 'assets/js/filtered-count-view.js',
			Vandrekalender_Asset_Versions::path_of( self::URL . 'assets/js/filtered-count-view.js?ver=1.0.0', self::URL, self::DIR )
		);
	}

	/**
	 * A CDN file (Leaflet) is not ours.
	 */
	public function test_cdn_url_is_left_alone(): void {
		$this->assertNull( Vandrekalender_Asset_Versions::path_of( 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js?ver=1.9.4', self::URL, self::DIR ) );
	}

	/**
	 * Another plugin's file, even one whose folder name starts the same.
	 */
	public function test_other_plugin_url_is_left_alone(): void {
		$this->assertNull( Vandrekalender_Asset_Versions::path_of( 'https://allevandreture.dk/wp-content/plugins/vandrekalender-events-extra/x.js', self::URL, self::DIR ) );
	}

	/**
	 * A plugin URL without a file behind it, or one that leaves the plugin
	 * folder, keeps its version.
	 */
	public function test_missing_or_outside_file_gives_null(): void {
		$this->assertNull( Vandrekalender_Asset_Versions::path_of( self::URL . 'assets/js/missing.js?ver=1.0.0', self::URL, self::DIR ) );
		$this->assertNull( Vandrekalender_Asset_Versions::path_of( self::URL . '../../../wp-config.php', self::URL, self::DIR ) );
		$this->assertNull( Vandrekalender_Asset_Versions::path_of( self::URL, self::URL, self::DIR ) );
	}
}
