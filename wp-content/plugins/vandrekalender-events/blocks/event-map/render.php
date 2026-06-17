<?php
/**
 * Server render for the Event Map block.
 *
 * Outputs the map container and a hydration root. The view script lazy-loads
 * Leaflet + OpenStreetMap from a CDN, fetches event coordinates from the REST
 * API, and drops a pin per event. Reacts to filter changes.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_rest_url = esc_url_raw( rest_url( 'vandrekalender/v1/events' ) );
?>
<div
	<?php echo get_block_wrapper_attributes( [ 'class' => 'vk-map' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-rest-url="<?php echo esc_attr( $vk_rest_url ); ?>"
	data-lat="56.0"
	data-lng="11.4"
	data-zoom="7"
>
	<div class="vk-map__canvas" role="application" aria-label="<?php esc_attr_e( 'Kort over vandreture i Danmark', 'vandrekalender-events' ); ?>"></div>
	<button type="button" class="vk-map__reset" hidden><?php esc_html_e( 'Nulstil kort', 'vandrekalender-events' ); ?></button>
	<p class="vk-map__status" role="status"><?php esc_html_e( 'Indlæser kort…', 'vandrekalender-events' ); ?></p>
</div>
