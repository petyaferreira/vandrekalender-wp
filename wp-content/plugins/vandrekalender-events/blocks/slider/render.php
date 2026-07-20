<?php
/**
 * PHP file to use when rendering the block type on the server to show on the front end.
 *
 * The following variables are exposed to the file:
 *     $attributes (array): The block attributes.
 *     $content (string): The block default content.
 *     $block (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 * @package Vandrekalender
 */

	$behavior           = $attributes['behavior'] ?? 'normal';
	$classes            = 'swiper is-' . $behavior;
	$progress_bar_color = sanitize_hex_color( $attributes['progressBarColor'] ?? '#C2D9B0' );
if ( ! $progress_bar_color ) {
	$progress_bar_color = '#C2D9B0';
}

?>

<div
	<?php
	// get_block_wrapper_attributes() returns a string of safe, escaped HTML attributes.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo get_block_wrapper_attributes(
		[
			'class' => $classes,
			'style' => $behavior === 'hero-progress'
				? '--slider-progress-color:' . esc_attr( $progress_bar_color ) . ';'
				: '',
		]
	);
	?>
		data-wp-interactive="vandrekalender/slider"
		data-wp-init--setup="callbacks.setup"
	<?php
		// wp_interactivity_data_wp_context() returns safe, JSON-encoded/escaped interactivity context attributes.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_interactivity_data_wp_context(
			[
				'slidesPerViewDesktop' => $attributes['slidesPerViewDesktop'] ?? 2.5,
				'slidesPerViewMobile'  => $attributes['slidesPerViewMobile'] ?? 1.5,
				'behavior'             => $behavior,
			]
		);
		?>

>
	<div class="swiper-wrapper wp-block-vandrekalender-slider-slides">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $content;
		?>
	</div>

	<?php if ( $behavior === 'vertical' ) : ?>
	<!-- JS will populate this -->
	<ul
		class="swiper-text-nav swiper-no-swiping"
		aria-label="<?php esc_attr_e( 'Slider navigation', 'vandrekalender-events' ); ?>"
	></ul>
	<?php elseif ( $behavior === 'normal' ) : ?>
		<div class="swiper-controls">
			<div class="swiper-scrollbar"></div>

			<div class="swiper-nav">
				<button
					class="swiper-button-prev"
					type="button"
					aria-label="<?php esc_attr_e( 'Previous slide', 'vandrekalender-events' ); ?>"
				>
					<svg class="swiper-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>

				<button
					class="swiper-button-next"
					type="button"
					aria-label="<?php esc_attr_e( 'Next slide', 'vandrekalender-events' ); ?>"
				>
					<svg class="swiper-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>
			</div>
		</div>
	<?php elseif ( $behavior === 'hero-progress' ) : ?>
		<div class="swiper-progress-bar" aria-hidden="true"></div>

		<div class="swiper-controls swiper-controls--hero-progress">
			<div class="swiper-nav">
				<button
					class="swiper-button-prev"
					type="button"
					aria-label="<?php esc_attr_e( 'Previous slide', 'vandrekalender-events' ); ?>"
				>
					<svg class="swiper-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>

				<span class="swiper-page-counter"></span>

				<button
					class="swiper-button-next"
					type="button"
					aria-label="<?php esc_attr_e( 'Next slide', 'vandrekalender-events' ); ?>"
				>
					<svg class="swiper-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
				</button>
			</div>
		</div>
	<?php endif; ?>
</div>
