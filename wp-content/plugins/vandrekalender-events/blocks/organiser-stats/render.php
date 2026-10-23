<?php
/**
 * Server render for the Organiser Stats block.
 *
 * "Arrangøren i tal": the organiser's upcoming walks, the regions they are
 * in and the shortest to longest route, computed from the walks on every
 * render (Organiser_Walks). The km tile is left out when no route has a
 * distance.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$vk_term    = $vk_post_id ? \Vandrekalender\Organiser::term_for_post( $vk_post_id ) : null;
$vk_walks   = $vk_term ? \Vandrekalender\Organiser_Walks::for_term( $vk_term->term_id ) : [];
$vk_regions = count( \Vandrekalender\Organiser_Walks::regions( $vk_walks ) );
$vk_range   = \Vandrekalender\Organiser_Walks::distance_range( \Vandrekalender\Organiser_Walks::routes( $vk_walks ) );

$vk_stats = [
	[
		'value' => number_format_i18n( count( $vk_walks ) ),
		'label' => _n( 'upcoming walk', 'upcoming walks', count( $vk_walks ), 'vandrekalender-events' ),
	],
	[
		'value' => number_format_i18n( $vk_regions ),
		'label' => _n( 'region', 'regions', $vk_regions, 'vandrekalender-events' ),
	],
];

if ( $vk_range ) {
	[ $vk_min, $vk_max ] = array_map( [ \Vandrekalender\Organiser_Walks::class, 'format_km' ], $vk_range );
	$vk_stats[]          = $vk_min === $vk_max
		? [
			'value' => $vk_min,
			'label' => __( 'km', 'vandrekalender-events' ),
		]
		: [
			/* translators: 1: shortest route in km, 2: longest route in km. */
			'value' => sprintf( __( '%1$s to %2$s', 'vandrekalender-events' ), $vk_min, $vk_max ),
			'label' => __( 'km, shortest to longest walk', 'vandrekalender-events' ),
		];
}
?>
<dl <?php echo get_block_wrapper_attributes( [ 'class' => 'vk-organiser-stats' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php foreach ( $vk_stats as $vk_stat ) : ?>
		<div class="vk-organiser-stats__tile">
			<dt class="vk-organiser-stats__label"><?php echo esc_html( $vk_stat['label'] ); ?></dt>
			<dd class="vk-organiser-stats__value"><?php echo esc_html( $vk_stat['value'] ); ?></dd>
		</div>
	<?php endforeach; ?>
</dl>
