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

// Leaflet CSS is enqueued server-side, NOT injected from view.js: the
// interactivity router manages the <head> across client-side navigations,
// and JS-injected <link> tags come out of that morphing present but no
// longer applied — the map's tiles and controls silently lose all layout.
// Enqueued styles are part of every server render, so the router keeps them.
wp_enqueue_style(
	'vandrekalender-leaflet',
	'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css',
	[],
	'1.9.4'
);
wp_enqueue_style(
	'vandrekalender-leaflet-cluster',
	'https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.min.css',
	[],
	'1.5.3'
);
wp_enqueue_style(
	'vandrekalender-leaflet-cluster-default',
	'https://cdnjs.cloudflare.com/ajax/libs/leaflet.markercluster/1.5.3/MarkerCluster.Default.min.css',
	[],
	'1.5.3'
);

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

// The context is read-only config — the view module must never write to it.
// A context mutation re-renders the island, and the runtime's virtual DOM
// (which knows the canvas as an empty div) then wipes Leaflet's injected DOM.
// Status/reset visibility is mutated directly on the elements instead.
$vk_context = [
	'restUrl'   => esc_url_raw( rest_url( 'vandrekalender/v1/events/locations' ) ),
	'lat'       => 56.0,
	'lng'       => 11.4,
	'zoom'      => 7,
	'presets'   => (object) $vk_presets,
	'locations' => Vandrekalender_Event_Rest_Api::locations_payload( $vk_filters ),
	// Strings the view module writes into popups and the status line. They are
	// translated here rather than in JS because view.js is a script *module*,
	// and script modules have no wp_set_script_translations() equivalent — so
	// the server hands them over already in the visitor's language.
	'i18n'      => [
		'free'      => __( 'Free', 'vandrekalender-events' ),
		/* translators: %d: lowest price in kroner. */
		'priceFrom' => __( 'from %d kr', 'vandrekalender-events' ),
		'details'   => __( 'See details', 'vandrekalender-events' ),
		'noMatches' => __( 'No walks with a location match your filters.', 'vandrekalender-events' ),
		'loading'   => __( 'Loading map…', 'vandrekalender-events' ),
		'loadError' => __( 'Could not load the map. Please try again.', 'vandrekalender-events' ),
		'initError' => __( 'The map could not be loaded.', 'vandrekalender-events' ),
	],
];

?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'vk-map' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php
	// The canvas deliberately lives OUTSIDE the interactivity island below.
	// Router navigations re-render islands, and the runtime's virtual DOM —
	// which knows the canvas only as an empty div — would wipe the panes and
	// controls Leaflet injected into it. Out here the runtime never touches it.
	?>
	<div class="vk-map__canvas" role="application" aria-label="<?php esc_attr_e( 'Map of walking events in Denmark', 'vandrekalender-events' ); ?>"></div>
	<div
		class="vk-map__ui"
		data-wp-interactive="vandrekalender/event-map"
		data-wp-init="callbacks.init"
		<?php echo wp_interactivity_data_wp_context( $vk_context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	>
		<button
			type="button"
			class="vk-map__reset"
			data-wp-on--click="actions.resetView"
			hidden
		><?php esc_html_e( 'Reset map', 'vandrekalender-events' ); ?></button>
		<p class="vk-map__status" role="status"><?php esc_html_e( 'Loading map…', 'vandrekalender-events' ); ?></p>
	</div>
</div>
