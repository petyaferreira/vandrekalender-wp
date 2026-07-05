<?php
/**
 * Server render for the Event Map block.
 *
 * The map itself has to be client-rendered (Leaflet), so this block only
 * partially follows the server-render pattern: the wrapper is an Interactivity
 * API island whose context carries the map config *and* the initial pin
 * payload, so first paint needs no REST request. The view module lazy-loads
 * Leaflet from a CDN, drops the embedded pins, and only calls the
 * /events/locations endpoint again when the filters change.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

// On a taxonomy archive, pin the queried term so the map only shows that
// region/length regardless of URL query params. The presets ride along in the
// context so client-side refetches apply them too.
$vk_presets = [];
foreach ( [
	'region' => \Vandrekalender\Event::TAX_REGION,
	'length' => \Vandrekalender\Event::TAX_LENGTH,
] as $vk_filter_key => $vk_taxonomy ) {
	if ( is_tax( $vk_taxonomy ) ) {
		$vk_presets[ $vk_filter_key ] = get_queried_object()->slug;
	}
}

$vk_filters = array_merge( Vandrekalender_Event_Rest_Api::filters_from_query(), $vk_presets );

$vk_context = [
	'restUrl'      => esc_url_raw( rest_url( 'vandrekalender/v1/events/locations' ) ),
	'lat'          => 56.0,
	'lng'          => 11.4,
	'zoom'         => 7,
	'presets'      => (object) $vk_presets,
	'locations'    => Vandrekalender_Event_Rest_Api::locations_payload( $vk_filters ),
	'statusText'   => __( 'Indlæser kort…', 'vandrekalender-events' ),
	'statusHidden' => false,
	'resetHidden'  => true,
];

$vk_wrapper_attributes = [
	'class'               => 'vk-map',
	'data-wp-interactive' => 'vandrekalender/event-map',
	'data-wp-init'        => 'callbacks.init',
];
?>
<div
	<?php echo get_block_wrapper_attributes( $vk_wrapper_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php echo wp_interactivity_data_wp_context( $vk_context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
>
	<div class="vk-map__canvas" role="application" aria-label="<?php esc_attr_e( 'Kort over vandreture i Danmark', 'vandrekalender-events' ); ?>"></div>
	<button
		type="button"
		class="vk-map__reset"
		data-wp-on--click="actions.resetView"
		data-wp-bind--hidden="context.resetHidden"
		hidden
	><?php esc_html_e( 'Nulstil kort', 'vandrekalender-events' ); ?></button>
	<p
		class="vk-map__status"
		role="status"
		data-wp-bind--hidden="context.statusHidden"
		data-wp-text="context.statusText"
	><?php esc_html_e( 'Indlæser kort…', 'vandrekalender-events' ); ?></p>
</div>
