<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit needs the file named like the class.
/**
 * Page template migration of #255: every page keeps the look it had.
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;

/**
 * Vandrekalender_Page_Templates::migrated_template() and the template files.
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
	 * Pages on the old no-title default move to page-without-title.
	 */
	public function test_default_moves_to_without_title(): void {
		$this->assertSame( 'page-without-title', Vandrekalender_Page_Templates::migrated_template( '' ) );
		$this->assertSame( 'page-without-title', Vandrekalender_Page_Templates::migrated_template( 'default' ) );
	}

	/**
	 * Pages on the old title template move to the default, which now has the title.
	 */
	public function test_with_title_moves_to_default(): void {
		$this->assertSame( 'default', Vandrekalender_Page_Templates::migrated_template( 'page-with-title' ) );
	}

	/**
	 * Every other template stays.
	 */
	public function test_other_templates_stay(): void {
		foreach ( [ 'page-without-gaps', 'page-spaced-no-title', 'page-without-title' ] as $template ) {
			$this->assertNull( Vandrekalender_Page_Templates::migrated_template( $template ), $template );
		}
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
