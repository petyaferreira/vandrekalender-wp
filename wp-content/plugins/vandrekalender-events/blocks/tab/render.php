<?php
/**
 * Server render for the Tab block.
 *
 * Note: the parent Tabs block renders each tab's inner content directly, so
 * this file is a fallback for any standalone render. It simply outputs the
 * tab's content.
 *
 * @package Vandrekalender
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;
?>
<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="wp-block-vandrekalender-tab__content">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
</div>
