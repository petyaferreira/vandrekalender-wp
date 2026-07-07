<?php
/**
 * Server render for the Link Box block.
 *
 * Wraps the inner blocks in a single anchor. Hover/active colors arrive as
 * CSS custom properties consumed by style.scss.
 *
 * @package Vandrekalender
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Rendered inner blocks.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

$link_box_color_map = [
	'hoverTextColor'       => [ 'has-hover-text', '--link-box-hover-text' ],
	'hoverBackgroundColor' => [ 'has-hover-background', '--link-box-hover-bg' ],
];

$link_box_classes = [];
$link_box_styles  = [];

foreach ( $link_box_color_map as $link_box_attr => $link_box_target ) {
	if ( ! empty( $attributes[ $link_box_attr ] ) ) {
		$link_box_classes[] = $link_box_target[0];
		$link_box_styles[]  = $link_box_target[1] . ': ' . $attributes[ $link_box_attr ];
	}
}

$link_box_wrapper = get_block_wrapper_attributes(
	[
		'class' => implode( ' ', $link_box_classes ),
		'style' => implode( '; ', $link_box_styles ),
	]
);

$link_box_url = $attributes['url'] ?? '';
?>
<?php if ( $link_box_url ) : ?>
	<a <?php echo $link_box_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		href="<?php echo esc_url( $link_box_url ); ?>"
		<?php if ( ! empty( $attributes['opensInNewTab'] ) ) : ?>
			target="_blank" rel="noopener noreferrer"
		<?php endif; ?>
	>
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</a>
<?php else : ?>
	<div <?php echo $link_box_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
<?php endif; ?>
