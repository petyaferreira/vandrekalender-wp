<?php
/**
 * Server render for the Organiser Stats block.
 *
 * The panel beside the organiser page's intro
 * (docs/design/organiser-page/arrangoer-header-1b.html): upcoming walks,
 * the regions they are in and the shortest to longest route, one row each,
 * computed from the walks on every render (Organiser_Walks). The distance
 * row is left out when no route has a distance.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$vk_term    = $vk_post_id ? \Vandrekalender\Organiser::term_for_post( $vk_post_id ) : null;
$vk_walks   = $vk_term ? \Vandrekalender\Organiser_Walks::for_term( $vk_term->term_id ) : [];
$vk_range   = \Vandrekalender\Organiser_Walks::distance_range( \Vandrekalender\Organiser_Walks::routes( $vk_walks ) );

$vk_stats = [
	__( 'Upcoming walks', 'vandrekalender-events' ) => number_format_i18n( count( $vk_walks ) ),
	__( 'Regions', 'vandrekalender-events' )        => number_format_i18n( count( \Vandrekalender\Organiser_Walks::regions( $vk_walks ) ) ),
];

if ( $vk_range ) {
	[ $vk_min, $vk_max ] = array_map( [ \Vandrekalender\Organiser_Walks::class, 'format_km' ], $vk_range );

	$vk_stats[ __( 'Distances', 'vandrekalender-events' ) ] = $vk_min === $vk_max
		/* translators: %s: route distance in km. */
		? sprintf( __( '%s km', 'vandrekalender-events' ), $vk_min )
		/* translators: 1: shortest route in km, 2: longest route in km. */
		: sprintf( __( '%1$s–%2$s km', 'vandrekalender-events' ), $vk_min, $vk_max );
}
?>
<dl <?php echo get_block_wrapper_attributes( [ 'class' => 'vk-organiser-stats' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-label="<?php esc_attr_e( 'The organiser in numbers', 'vandrekalender-events' ); ?>">
	<?php foreach ( $vk_stats as $vk_label => $vk_value ) : ?>
		<div class="vk-organiser-stats__row">
			<dt class="vk-organiser-stats__label"><?php echo esc_html( $vk_label ); ?></dt>
			<dd class="vk-organiser-stats__value"><?php echo esc_html( $vk_value ); ?></dd>
		</div>
	<?php endforeach; ?>
</dl>
