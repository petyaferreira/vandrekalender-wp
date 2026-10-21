<?php
/**
 * Server render for the Organiser Content block.
 *
 * On an organiser page (taxonomy-event_organiser), renders one part of that
 * organiser's own content from the theme's pattern file
 * patterns/organiser-<term slug>-<part>.php, e.g.
 * organiser-dansk-vandrelaug-about.php. The page layout is shared by all
 * organisers; only these parts differ. Why pattern files:
 * docs/decisions/0008-organiser-content-in-patterns.md.
 *
 * Renders nothing outside an organiser page, or when the organiser has no
 * file for this part yet.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_tax( \Vandrekalender\Event::TAX_ORGANISER ) ) {
	return;
}

$vk_slug    = \Vandrekalender\Event::organiser_pattern_slug( get_queried_object()->slug, (string) ( $attributes['part'] ?? '' ) );
$vk_pattern = $vk_slug ? WP_Block_Patterns_Registry::get_instance()->get_registered( $vk_slug ) : null;

if ( ! $vk_pattern ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core escapes the attributes. ?>>
	<?php echo do_blocks( $vk_pattern['content'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- block markup from the theme's own pattern file. ?>
</div>
