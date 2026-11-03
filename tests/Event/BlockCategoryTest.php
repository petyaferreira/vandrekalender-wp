<?php
/**
 * The project's block category in the inserter.
 *
 * @package Vandrekalender
 */

use PHPUnit\Framework\TestCase;
use Vandrekalender\Event;

/**
 * Event::register_block_category().
 */
class BlockCategoryTest extends TestCase {

	/**
	 * The project category comes first, the core ones keep their order after it.
	 */
	public function test_project_category_is_first(): void {
		// The constructor registers WordPress hooks the stand-ins do not have.
		$event      = ( new ReflectionClass( Event::class ) )->newInstanceWithoutConstructor();
		$categories = $event->register_block_category(
			[
				[
					'slug'  => 'widgets',
					'title' => 'Widgets',
				],
			]
		);

		$this->assertCount( 2, $categories );
		$this->assertSame( 'allevandreture', $categories[0]['slug'] );
		$this->assertSame( 'Alle Vandreture', $categories[0]['title'] );
		$this->assertSame( 'widgets', $categories[1]['slug'] );
	}
}
