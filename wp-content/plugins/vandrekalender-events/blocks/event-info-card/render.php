<?php
/**
 * Server render for the Event Info Card block.
 *
 * Renders the booking summary for the current event: price, date, place, and
 * a tab per route so price, start time and cutoff time switch with the
 * selected distance. All values come from this event's post meta — nothing is
 * fetched client-side.
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

if ( empty( $vk_routes ) ) {
	return;
}

$vk_date          = get_post_meta( $vk_post_id, \Vandrekalender\Event::META_DATE, true );
$vk_place_name    = get_post_meta( $vk_post_id, \Vandrekalender\Event::META_PLACE_NAME, true );
$vk_municipality  = get_post_meta( $vk_post_id, \Vandrekalender\Event::META_MUNICIPALITY, true );
$vk_address       = get_post_meta( $vk_post_id, \Vandrekalender\Event::META_ADDRESS, true );
$vk_source_url    = get_post_meta( $vk_post_id, \Vandrekalender\Event::META_SOURCE_URL, true );
$vk_organiser_url = get_post_meta( $vk_post_id, \Vandrekalender\Event::META_ORGANISER_URL, true );

$vk_book_url = $vk_source_url ? $vk_source_url : $vk_organiser_url;
$vk_place    = $vk_place_name ? $vk_place_name : $vk_municipality;

$vk_date_label     = $vk_date ? date_i18n( 'D. j. M. Y', strtotime( $vk_date ) ) : '';
$vk_directions_url = $vk_address ? 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $vk_address ) : '';

/**
 * Format a route's price as "Gratis" or "{n} kr".
 *
 * @param array $vk_route Route data.
 * @return string
 */
$vk_format_price = function ( array $vk_route ) {
	$vk_price = isset( $vk_route['price'] ) ? (float) $vk_route['price'] : 0.0;

	if ( $vk_price <= 0.0 ) {
		return __( 'Free', 'vandrekalender-events' );
	}

	/* translators: %s: price in Danish kroner */
	return sprintf( __( '%s kr', 'vandrekalender-events' ), number_format_i18n( $vk_price ) );
};

/**
 * Format a route's start time as "kl. HH:MM".
 *
 * @param array $vk_route Route data.
 * @return string
 */
$vk_format_start_time = function ( array $vk_route ) {
	if ( empty( $vk_route['start_time'] ) ) {
		return '—';
	}

	/* translators: %s: start time, e.g. 09:00 */
	return sprintf( __( 'at %s', 'vandrekalender-events' ), $vk_route['start_time'] );
};

/**
 * Format a route's cutoff time as "N timer".
 *
 * @param array $vk_route Route data.
 * @return string
 */
$vk_format_cutoff = function ( array $vk_route ) {
	if ( empty( $vk_route['cutoff_time'] ) ) {
		return '—';
	}

	/* translators: %s: number of hours */
	return sprintf( _n( '%s hour', '%s hours', (int) $vk_route['cutoff_time'], 'vandrekalender-events' ), $vk_route['cutoff_time'] );
};

/**
 * Format a route's distance as "N km", trimming trailing zeroes.
 *
 * @param array $vk_route Route data.
 * @return string
 */
$vk_format_distance = function ( array $vk_route ) {
	if ( empty( $vk_route['distance_km'] ) ) {
		return __( 'Route', 'vandrekalender-events' );
	}

	$vk_km = rtrim( rtrim( sprintf( '%.1f', (float) $vk_route['distance_km'] ), '0' ), '.' );

	/* translators: %s: distance in kilometres */
	return sprintf( __( '%s km', 'vandrekalender-events' ), $vk_km );
};

$vk_first_route = $vk_routes[0];
?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'vk-info-card' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-vk-info-card>
	<div class="vk-info-card__price">
		<span data-vk-info-field="price"><?php echo esc_html( $vk_format_price( $vk_first_route ) ); ?></span>
		<span class="vk-info-card__price-unit"><?php esc_html_e( 'per participant', 'vandrekalender-events' ); ?></span>
	</div>

	<?php if ( count( $vk_routes ) > 1 ) : ?>
		<div class="vk-info-card__tabs" role="tablist">
			<?php foreach ( $vk_routes as $vk_key => $vk_route ) : ?>
				<button
					type="button"
					role="tab"
					class="vk-info-card__tab<?php echo 0 === $vk_key ? ' vk-info-card__tab--active' : ''; ?>"
					aria-selected="<?php echo 0 === $vk_key ? 'true' : 'false'; ?>"
					data-vk-price="<?php echo esc_attr( $vk_format_price( $vk_route ) ); ?>"
					data-vk-start-time="<?php echo esc_attr( $vk_format_start_time( $vk_route ) ); ?>"
					data-vk-cutoff="<?php echo esc_attr( $vk_format_cutoff( $vk_route ) ); ?>"
				>
					<?php echo esc_html( $vk_format_distance( $vk_route ) ); ?>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<dl class="vk-info-card__rows">
		<?php if ( $vk_date_label ) : ?>
			<div class="vk-info-card__row">
				<dt><?php esc_html_e( 'Date', 'vandrekalender-events' ); ?></dt>
				<dd><?php echo esc_html( $vk_date_label ); ?></dd>
			</div>
		<?php endif; ?>

		<div class="vk-info-card__row">
			<dt><?php esc_html_e( 'Start time', 'vandrekalender-events' ); ?></dt>
			<dd data-vk-info-field="start-time"><?php echo esc_html( $vk_format_start_time( $vk_first_route ) ); ?></dd>
		</div>

		<div class="vk-info-card__row">
			<dt><?php esc_html_e( 'Cutoff time', 'vandrekalender-events' ); ?></dt>
			<dd data-vk-info-field="cutoff"><?php echo esc_html( $vk_format_cutoff( $vk_first_route ) ); ?></dd>
		</div>

		<?php if ( $vk_place ) : ?>
			<div class="vk-info-card__row">
				<dt><?php esc_html_e( 'Place', 'vandrekalender-events' ); ?></dt>
				<dd>
					<?php echo esc_html( $vk_place ); ?>
					<?php if ( $vk_address && 0 !== strcasecmp( trim( $vk_address ), trim( $vk_place ) ) ) : ?>
						<span class="vk-info-card__address"><?php echo esc_html( $vk_address ); ?></span>
					<?php endif; ?>
					<?php if ( $vk_directions_url ) : ?>
						<br />
						<a class="vk-info-card__directions" href="<?php echo esc_url( $vk_directions_url ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Show directions', 'vandrekalender-events' ); ?> ›
						</a>
					<?php endif; ?>
				</dd>
			</div>
		<?php endif; ?>
	</dl>

	<?php if ( $vk_book_url ) : ?>
		<a class="vk-info-card__cta" href="<?php echo esc_url( $vk_book_url ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Book your place', 'vandrekalender-events' ); ?> →
		</a>
		<p class="vk-info-card__note"><?php esc_html_e( 'Registration is handled by the organiser', 'vandrekalender-events' ); ?></p>
	<?php endif; ?>
</div>
