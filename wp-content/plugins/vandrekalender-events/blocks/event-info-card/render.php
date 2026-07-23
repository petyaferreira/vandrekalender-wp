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

// Events created on vandrekalender.dk sign people up here instead of linking
// out to an organiser's booking page.
$vk_joinable = Vandrekalender_Event_Attendees::is_joinable( $vk_post_id );

// A card with no routes has no price, start time or cutoff to show — but if
// people can sign up on it, the card still has to exist to hold the button.
if ( empty( $vk_routes ) && ! $vk_joinable ) {
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

$vk_date_label     = $vk_date ? date_i18n( get_option( 'date_format' ), strtotime( $vk_date ) ) : '';
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

$vk_first_route = $vk_routes ? $vk_routes[0] : [];

$vk_wrapper_attributes = [ 'class' => 'vk-info-card' ];

if ( $vk_joinable ) {
	$vk_logged_in = is_user_logged_in();
	$vk_attending = $vk_logged_in && Vandrekalender_Event_Attendees::is_attending( $vk_post_id, get_current_user_id() );

	$vk_join_label      = __( "I'm going", 'vandrekalender-events' );
	$vk_attending_label = __( 'Attending', 'vandrekalender-events' );
	$vk_joined_note     = __( 'You are signed up — we have emailed you a confirmation.', 'vandrekalender-events' );

	wp_interactivity_state(
		'vandrekalender/event-info-card',
		[
			'joinLabel'      => $vk_join_label,
			'attendingLabel' => $vk_attending_label,
			'joinedNote'     => $vk_joined_note,
			'joinError'      => __( 'Sign-up failed. Please try again.', 'vandrekalender-events' ),
			'cancelError'    => __( 'Cancellation failed. Please try again.', 'vandrekalender-events' ),
			'restUrl'        => rest_url( Vandrekalender_Event_Rest_Api::NAMESPACE . '/events/' ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
		]
	);

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display flag set by our own redirect.
	$vk_just_joined = isset( $_GET[ Vandrekalender_Event_Join::JOINED_ARG ] );

	if ( $vk_attending ) {
		$vk_note = $vk_just_joined ? $vk_joined_note : __( 'You are signed up for this walk.', 'vandrekalender-events' );
	} elseif ( ! $vk_logged_in ) {
		$vk_note = __( 'You need an account to sign up. We will bring you straight back here.', 'vandrekalender-events' );
	} else {
		$vk_note = '';
	}

	$vk_wrapper_attributes['data-wp-interactive'] = 'vandrekalender/event-info-card';
	$vk_wrapper_attributes['data-wp-context']     = wp_json_encode(
		[
			'eventId'    => $vk_post_id,
			'attending'  => $vk_attending,
			'busy'       => false,
			'confirming' => false,
			'error'      => '',
			'note'       => $vk_note,
		]
	);
}
?>
<div <?php echo get_block_wrapper_attributes( $vk_wrapper_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> data-vk-info-card>
	<?php if ( $vk_routes ) : ?>
		<div class="vk-info-card__price">
			<span data-vk-info-field="price"><?php echo esc_html( $vk_format_price( $vk_first_route ) ); ?></span>
			<span class="vk-info-card__price-unit"><?php esc_html_e( 'per participant', 'vandrekalender-events' ); ?></span>
		</div>
	<?php endif; ?>

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

		<?php if ( $vk_routes ) : ?>
			<div class="vk-info-card__row">
				<dt><?php esc_html_e( 'Start time', 'vandrekalender-events' ); ?></dt>
				<dd data-vk-info-field="start-time"><?php echo esc_html( $vk_format_start_time( $vk_first_route ) ); ?></dd>
			</div>

			<div class="vk-info-card__row">
				<dt><?php esc_html_e( 'Cutoff time', 'vandrekalender-events' ); ?></dt>
				<dd data-vk-info-field="cutoff"><?php echo esc_html( $vk_format_cutoff( $vk_first_route ) ); ?></dd>
			</div>
		<?php endif; ?>

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

	<?php if ( $vk_joinable ) : ?>
		<form
			class="vk-info-card__join"
			method="post"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			<?php if ( $vk_logged_in ) : ?>
				data-wp-on--submit="actions.submit"
			<?php endif; ?>
		>
			<input type="hidden" name="action" value="vk_join_event" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $vk_post_id ); ?>" />
			<?php wp_nonce_field( Vandrekalender_Event_Join::nonce_action( $vk_post_id ) ); ?>

			<button
				type="submit"
				class="vk-info-card__cta vk-info-card__cta--join<?php echo $vk_attending ? ' is-attending' : ''; ?>"
				data-wp-class--is-attending="context.attending"
				data-wp-class--is-busy="context.busy"
				data-wp-bind--aria-busy="context.busy"
			>
				<span data-wp-text="state.ctaLabel"><?php echo esc_html( $vk_attending ? $vk_attending_label : $vk_join_label ); ?></span>
			</button>
		</form>

		<p
			class="vk-info-card__note"
			role="status"
			data-wp-text="context.note"
			data-wp-bind--hidden="!context.note"
			<?php echo $vk_note ? '' : 'hidden'; ?>
		><?php echo esc_html( $vk_note ); ?></p>

		<p class="vk-info-card__error" role="alert" data-wp-text="context.error" data-wp-bind--hidden="!context.error" hidden></p>

		<?php if ( $vk_logged_in ) : ?>
			<dialog
				class="vk-cancel-dialog"
				data-wp-watch="callbacks.toggleDialog"
				data-wp-on--close="actions.dismissCancel"
			>
				<h2 class="vk-cancel-dialog__title"><?php esc_html_e( 'Cancel your sign-up?', 'vandrekalender-events' ); ?></h2>
				<p class="vk-cancel-dialog__text"><?php esc_html_e( 'You will be taken off the list for this walk. You can sign up again later if you change your mind.', 'vandrekalender-events' ); ?></p>

				<div class="vk-cancel-dialog__actions">
					<button type="button" class="vk-cancel-dialog__keep" data-wp-on--click="actions.dismissCancel">
						<?php esc_html_e( 'No, keep my place', 'vandrekalender-events' ); ?>
					</button>
					<button
						type="button"
						class="vk-cancel-dialog__confirm"
						data-wp-on--click="actions.confirmCancel"
						data-wp-bind--disabled="context.busy"
					>
						<?php esc_html_e( 'Yes, cancel', 'vandrekalender-events' ); ?>
					</button>
				</div>
			</dialog>
		<?php endif; ?>
	<?php elseif ( $vk_book_url ) : ?>
		<a class="vk-info-card__cta" href="<?php echo esc_url( $vk_book_url ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Book your place', 'vandrekalender-events' ); ?> →
		</a>
		<p class="vk-info-card__note"><?php esc_html_e( 'Registration is handled by the organiser', 'vandrekalender-events' ); ?></p>
	<?php endif; ?>
</div>
