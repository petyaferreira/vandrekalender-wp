<?php
/**
 * Server render for the Event Calendar block.
 *
 * Outputs a hydration root. The view script fetches events from the
 * REST API and renders the cards client-side, reacting to filter changes.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_rest_url = esc_url_raw( rest_url( 'vandrekalender/v1/events' ) );
?>
<div
	<?php echo get_block_wrapper_attributes( [ 'class' => 'vk-calendar' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-rest-url="<?php echo esc_attr( $vk_rest_url ); ?>"
>
	<p class="vk-calendar__status" role="status"><?php esc_html_e( 'Indlæser vandreture…', 'vandrekalender-events' ); ?></p>
	<ul class="vk-calendar__list" hidden></ul>
	<p class="vk-calendar__empty" hidden><?php esc_html_e( 'Ingen vandreture matcher dine filtre.', 'vandrekalender-events' ); ?></p>
</div>
