<?php
/**
 * Server render for the Tabs block.
 *
 * Renders the tab navigation from each child Tab's label, plus every tab's
 * content. The first tab is active on load; the view script handles switching.
 * Without JS the first panel shows (progressive enhancement).
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 * @package Vandrekalender
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$vk_inner = isset( $block->parsed_block['innerBlocks'] ) ? $block->parsed_block['innerBlocks'] : [];
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-vk-tabs>
	<div class="wp-block-vandrekalender-tabs__navigation">
		<div class="wp-block-vandrekalender-tabs__navigation-inner" role="tablist">
			<?php foreach ( $vk_inner as $vk_key => $vk_innerblock ) : ?>
				<button
					type="button"
					role="tab"
					class="wp-block-vandrekalender-tabs__navigation-item<?php echo 0 === $vk_key ? ' wp-block-vandrekalender-tabs__navigation-item--active' : ''; ?>"
					data-vk-tab="<?php echo esc_attr( (string) $vk_key ); ?>"
					aria-selected="<?php echo 0 === $vk_key ? 'true' : 'false'; ?>"
				>
					<?php echo esc_html( $vk_innerblock['attrs']['label'] ?? __( 'Tab', 'vandrekalender-events' ) ); ?>
				</button>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="wp-block-vandrekalender-tabs__content">
		<?php foreach ( $vk_inner as $vk_key => $vk_innerblock ) : ?>
			<div
				class="wp-block-vandrekalender-tabs__content-item<?php echo 0 === $vk_key ? ' wp-block-vandrekalender-tabs__content-item--active' : ''; ?>"
				role="tabpanel"
				data-vk-panel="<?php echo esc_attr( (string) $vk_key ); ?>"
			>
				<?php foreach ( $vk_innerblock['innerBlocks'] as $vk_content_block ) : ?>
					<?php echo render_block( $vk_content_block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
