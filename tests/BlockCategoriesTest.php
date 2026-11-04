<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * Every project block and pattern is in the "allevandreture" category (#200).
 *
 * Reads the files only, no WordPress needed.
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Block and pattern categories, see docs/frontend.md.
 */
class BlockCategoriesTest extends TestCase {

	const CATEGORY = 'allevandreture';

	/**
	 * Every block.json names the project category.
	 */
	public function test_every_block_is_in_the_project_category(): void {
		$files = glob( dirname( __DIR__ ) . '/wp-content/plugins/vandrekalender-events/blocks/*/block.json' );
		$this->assertNotEmpty( $files, 'No block.json files found.' );

		foreach ( $files as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file in a test.
			$block = json_decode( (string) file_get_contents( $file ), true );
			$this->assertIsArray( $block, $file . ' is not valid JSON.' );
			$this->assertSame(
				self::CATEGORY,
				$block['category'] ?? null,
				$file . ' must have "category": "' . self::CATEGORY . '".'
			);
		}
	}

	/**
	 * Every pattern that shows in the inserter names the project category.
	 */
	public function test_every_pattern_is_in_the_project_category(): void {
		$files = glob( dirname( __DIR__ ) . '/wp-content/themes/vandrekalender-theme/patterns/*.php' );
		$this->assertNotEmpty( $files, 'No pattern files found.' );

		foreach ( $files as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file in a test.
			$header = substr( (string) file_get_contents( $file ), 0, 2000 );
			if ( preg_match( '/^\s*\*\s*Inserter:\s*no\s*$/mi', $header ) ) {
				continue;
			}
			$found = preg_match( '/^\s*\*\s*Categories:\s*(.+?)\s*$/mi', $header, $match ) ? $match[1] : null;
			$this->assertSame(
				self::CATEGORY,
				$found,
				$file . ' must have "Categories: ' . self::CATEGORY . '" in its header.'
			);
		}
	}
}
