<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * Page template files of #255.
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * The page template files.
 */
class PageTemplatesTest extends TestCase {

	/**
	 * The theme's templates directory.
	 *
	 * @return string
	 */
	private function templates_dir(): string {
		return dirname( __DIR__, 2 ) . '/wp-content/themes/vandrekalender-theme/templates/';
	}

	/**
	 * The default renders the title, page-without-title does not, and the old title template is gone.
	 */
	public function test_template_files_match_the_mapping(): void {
		$dir = $this->templates_dir();
		// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local files in a test.
		$this->assertStringContainsString( 'wp:post-title', (string) file_get_contents( $dir . 'page.html' ) );
		$this->assertStringNotContainsString( 'wp:post-title', (string) file_get_contents( $dir . 'page-without-title.html' ) );
		// phpcs:enable
		$this->assertFileDoesNotExist( $dir . 'page-with-title.html' );
	}
}
