<?php
/**
 * Server render for the Organiser Logo block.
 *
 * The organiser page's featured image as a round logo, or the organiser's
 * initials in the same circle until it has one (as the event info card's
 * badge does). The name is the H1 next to it, so the logo's alt is empty.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );

if ( ! $vk_post_id || \Vandrekalender\Organiser::CUSTOMPOSTTYPE !== get_post_type( $vk_post_id ) ) {
	return;
}
?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'vk-organiser-logo' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( has_post_thumbnail( $vk_post_id ) ) : ?>
		<?php
		echo get_the_post_thumbnail(
			$vk_post_id,
			'medium',
			[
				'class'   => 'vk-organiser-logo__image',
				'alt'     => '',
				'loading' => false,
			]
		);
		?>
	<?php else : ?>
		<span class="vk-organiser-logo__initials" aria-hidden="true"><?php echo esc_html( \Vandrekalender\Event::organiser_initials( get_the_title( $vk_post_id ) ) ); ?></span>
	<?php endif; ?>
</div>
