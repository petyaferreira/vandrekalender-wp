<?php
/**
 * Server render for the Official Organiser Badge block.
 *
 * "Officiel arrangør" when the organiser page is flagged as official
 * (organiser_official, set by admins), nothing otherwise.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );

if ( ! $vk_post_id || ! get_post_meta( $vk_post_id, \Vandrekalender\Organiser::META_OFFICIAL, true ) ) {
	return;
}
?>
<p <?php echo get_block_wrapper_attributes( [ 'class' => 'vk-organiser-badge' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Official organiser', 'vandrekalender-events' ); ?></p>
