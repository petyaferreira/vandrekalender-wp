<?php
/**
 * Server render for the Event Cards block.
 *
 * Outputs a hydration root. The view script fetches events from the
 * REST API and renders the cards client-side, reacting to filter changes.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_rest_url = esc_url_raw( rest_url( 'vandrekalender/v1/events' ) );

// On a taxonomy archive, pin the queried term so the cards only show that
// region/length regardless of URL query params.
$vk_wrapper_attributes = [ 'class' => 'vk-cards' ];
$vk_preset_taxonomies  = [
	'region' => \Vandrekalender\Event::TAX_REGION,
	'length' => \Vandrekalender\Event::TAX_LENGTH,
];
foreach ( $vk_preset_taxonomies as $vk_filter_key => $vk_taxonomy ) {
	if ( is_tax( $vk_taxonomy ) ) {
		$vk_wrapper_attributes[ 'data-preset-' . $vk_filter_key ] = get_queried_object()->slug;
	}
}
?>
<div
	<?php echo get_block_wrapper_attributes( $vk_wrapper_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-rest-url="<?php echo esc_attr( $vk_rest_url ); ?>"
>
	<p class="vk-cards__status" role="status"><?php esc_html_e( 'Indlæser vandreture…', 'vandrekalender-events' ); ?></p>
	<ul class="vk-cards__list" hidden></ul>
	<button type="button" class="vk-cards__more" hidden><?php esc_html_e( 'Vis flere vandreture', 'vandrekalender-events' ); ?></button>
	<p class="vk-cards__empty" hidden><?php esc_html_e( 'Ingen vandreture matcher dine filtre.', 'vandrekalender-events' ); ?></p>
</div>
