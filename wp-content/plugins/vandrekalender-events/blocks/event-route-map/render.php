<?php
/**
 * Server render for the Event Route Map block.
 *
 * Shows the GPX track(s) attached to the current event's routes on a Leaflet
 * map, with a download link per route. This is a different block from
 * `event-map` (the Denmark-wide event calendar map) — this one draws a
 * single event's own route track(s).
 *
 * Much simpler than Event Map: no filters, no clustering, and no
 * Interactivity API router involvement — the single event page is not a
 * router region, so a plain classic view script is enough (see
 * docs/route-gpx-plan.md, PR 3b).
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_post_id = get_the_ID();

if ( ! $vk_post_id || \Vandrekalender\Event::CUSTOMPOSTTYPE !== get_post_type( $vk_post_id ) ) {
	return;
}

$vk_routes = get_post_meta( $vk_post_id, \Vandrekalender\Event::META_ROUTES, true );
$vk_routes = is_array( $vk_routes ) ? array_values( array_filter( $vk_routes ) ) : [];
$vk_routes = \Vandrekalender\Event::sort_routes_by_distance( $vk_routes );
$vk_routes = \Vandrekalender\Event::add_gpx_urls( $vk_routes );

$vk_gpx_routes = array_values(
	array_filter(
		$vk_routes,
		static function ( $vk_route ) {
			return is_array( $vk_route ) && ! empty( $vk_route['gpx_url'] );
		}
	)
);

// No route has a track: render nothing rather than an empty map and gap.
if ( empty( $vk_gpx_routes ) ) {
	return;
}

// Leaflet CSS is enqueued server-side, NOT injected from JS — see
// docs/frontend.md: on a page using the interactivity router, a JS-injected
// <link> tag comes out of <head> morphing present in the DOM but no longer
// applied. This block does not use the router, but the rule is followed
// here too for consistency with the rest of the codebase.
wp_enqueue_style(
	'vandrekalender-leaflet',
	'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css',
	[],
	'1.9.4'
);

/**
 * Format a route's distance as "N km", trimming trailing zeroes.
 *
 * @param array $vk_route Route data.
 * @return string
 */
$vk_format_distance = function ( array $vk_route ) {
	if ( empty( $vk_route['distance_km'] ) ) {
		return '';
	}

	return rtrim( rtrim( sprintf( '%.1f', (float) $vk_route['distance_km'] ), '0' ), '.' );
};

$vk_payload = array_map(
	static function ( $vk_route ) {
		return [
			'id'          => (string) ( $vk_route['id'] ?? '' ),
			'distance_km' => (string) ( $vk_route['distance_km'] ?? '' ),
			'gpx_url'     => $vk_route['gpx_url'],
			'gpx_name'    => (string) ( $vk_route['gpx_name'] ?? '' ),
		];
	},
	$vk_gpx_routes
);

// The Event Info Card always marks the first route (in full event_routes
// order, not just the ones with a file) as its active tab on load — see
// $vk_first_route in blocks/event-info-card/render.php, same $vk_routes
// filtering. The map matches that on first paint instead of showing every
// track until the first click, which looked like the two blocks disagreed.
$vk_initial_route_id = (string) ( $vk_routes[0]['id'] ?? '' );
?>
<div
	<?php echo get_block_wrapper_attributes( [ 'class' => 'vk-route-map' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-vk-routes="<?php echo esc_attr( wp_json_encode( $vk_payload ) ); ?>"
	data-vk-initial-route-id="<?php echo esc_attr( $vk_initial_route_id ); ?>"
>
	<?php if ( count( $vk_gpx_routes ) > 1 ) : ?>
		<button
			type="button"
			class="vk-route-map__toggle"
			data-vk-route-map-toggle
			aria-pressed="false"
		>
			<?php esc_html_e( 'Show all routes', 'vandrekalender-events' ); ?>
		</button>
	<?php endif; ?>

	<div
		class="vk-route-map__canvas"
		role="application"
		aria-label="<?php esc_attr_e( 'Map of the route track', 'vandrekalender-events' ); ?>"
	></div>

	<ul class="vk-route-map__downloads">
		<?php foreach ( $vk_gpx_routes as $vk_route ) : ?>
			<li>
				<a href="<?php echo esc_url( $vk_route['gpx_url'] ); ?>" download>
					<?php
					$vk_km = $vk_format_distance( $vk_route );
					if ( $vk_km ) {
						/* translators: %s: distance in kilometres */
						printf( esc_html__( 'Download GPX (%s km)', 'vandrekalender-events' ), esc_html( $vk_km ) );
					} else {
						esc_html_e( 'Download GPX', 'vandrekalender-events' );
					}
					?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
