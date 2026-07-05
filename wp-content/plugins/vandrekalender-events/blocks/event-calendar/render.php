<?php
/**
 * Server render for the Event Calendar (month grid) block.
 *
 * The whole calendar is server-rendered: the month grid with a dot per day
 * and, when a day is selected, that day's events. Month navigation, day
 * selection, and filter changes re-render through
 * `@wordpress/interactivity-router` (see view.js) using the `maaned` (YYYY-MM)
 * and `dag` (YYYY-MM-DD) URL params, so the markup lives in one place — here.
 *
 * The filter bar's date range is ignored on purpose: this view has its own
 * month navigation.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view params.
$vk_month_param = isset( $_GET['maaned'] ) ? sanitize_text_field( wp_unslash( $_GET['maaned'] ) ) : '';
$vk_day_param   = isset( $_GET['dag'] ) ? sanitize_text_field( wp_unslash( $_GET['dag'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $vk_month_param ) ) {
	$vk_month_param = current_time( 'Y-m' );
}

$vk_first_ts      = strtotime( $vk_month_param . '-01' );
$vk_days_in_month = (int) gmdate( 't', $vk_first_ts );
$vk_first_offset  = (int) gmdate( 'N', $vk_first_ts ) - 1; // Monday-first weekday offset.
$vk_total_cells   = (int) ceil( ( $vk_first_offset + $vk_days_in_month ) / 7 ) * 7;
$vk_prev_month    = gmdate( 'Y-m', strtotime( '-1 month', $vk_first_ts ) );
$vk_next_month    = gmdate( 'Y-m', strtotime( '+1 month', $vk_first_ts ) );
$vk_today_key     = current_time( 'Y-m-d' );

// Only honour a selected day that is a real date inside the shown month.
$vk_selected = '';
if ( preg_match( '/^\d{4}-\d{2}-(\d{2})$/', $vk_day_param, $vk_day_match )
	&& 0 === strpos( $vk_day_param, $vk_month_param . '-' )
	&& (int) $vk_day_match[1] >= 1 && (int) $vk_day_match[1] <= $vk_days_in_month ) {
	$vk_selected = $vk_day_param;
}

// On a taxonomy archive, pin the queried term so the calendar only counts
// that region/length regardless of URL query params.
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
unset( $vk_filters['date_from'], $vk_filters['date_to'] );

$vk_counts = Vandrekalender_Event_Rest_Api::count_events_by_day(
	array_merge(
		$vk_filters,
		[
			'date_from' => $vk_month_param . '-01',
			'date_to'   => sprintf( '%s-%02d', $vk_month_param, $vk_days_in_month ),
		]
	)
);

// The selected day's events, if any day is selected.
$vk_day_event_ids = [];
if ( $vk_selected ) {
	$vk_day_args = Vandrekalender_Event_Rest_Api::build_query_args(
		array_merge(
			$vk_filters,
			[
				'date_from' => $vk_selected,
				'date_to'   => $vk_selected,
			]
		)
	);

	$vk_day_args['posts_per_page'] = -1;
	$vk_day_args['fields']         = 'ids';

	$vk_day_event_ids = get_posts( $vk_day_args );

	// Price lives inside the routes JSON, so the free/paid filter runs in PHP.
	if ( isset( $vk_filters['is_free'] ) && '' !== $vk_filters['is_free'] ) {
		update_meta_cache( 'post', $vk_day_event_ids );
		$vk_want_free     = rest_sanitize_boolean( $vk_filters['is_free'] );
		$vk_day_event_ids = array_values(
			array_filter(
				$vk_day_event_ids,
				fn( $id ) => Vandrekalender_Event_Rest_Api::is_event_free( $id ) === $vk_want_free
			)
		);
	}

	// One query each for posts and meta instead of several per event.
	_prime_post_caches( $vk_day_event_ids, false, true );
}

$vk_month_label = wp_date( 'F Y', $vk_first_ts, new DateTimeZone( 'UTC' ) );
$vk_month_label = function_exists( 'mb_convert_case' )
	? mb_convert_case( mb_substr( $vk_month_label, 0, 1 ), MB_CASE_UPPER ) . mb_substr( $vk_month_label, 1 )
	: ucfirst( $vk_month_label );

$vk_weekdays = [
	__( 'Man', 'vandrekalender-events' ),
	__( 'Tir', 'vandrekalender-events' ),
	__( 'Ons', 'vandrekalender-events' ),
	__( 'Tor', 'vandrekalender-events' ),
	__( 'Fre', 'vandrekalender-events' ),
	__( 'Lør', 'vandrekalender-events' ),
	__( 'Søn', 'vandrekalender-events' ),
];

$vk_wrapper_attributes = [
	'class'                     => 'vk-calendar',
	'data-wp-interactive'       => 'vandrekalender/event-calendar',
	'data-wp-class--is-loading' => 'state.isNavigating',
	'data-wp-init'              => 'callbacks.init',
];
?>
<div <?php echo get_block_wrapper_attributes( $vk_wrapper_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div
		class="vk-calendar__region"
		data-wp-interactive="vandrekalender/event-calendar"
		data-wp-router-region="vk-event-calendar"
	>
		<div class="vk-calendar__header">
			<?php
			// The target month/day ride along as plain data attributes rather
			// than data-wp-context: the router morphs reused elements in place
			// and their context keeps its initial value, so a context-based
			// payload would go stale after the first navigation.
			?>
			<button
				type="button"
				class="vk-calendar__nav"
				data-month="<?php echo esc_attr( $vk_prev_month ); ?>"
				data-wp-on--click="actions.goToMonth"
				data-wp-bind--disabled="state.isNavigating"
				aria-label="<?php esc_attr_e( 'Forrige måned', 'vandrekalender-events' ); ?>"
			>&lsaquo;</button>
			<h3 class="vk-calendar__month"><?php echo esc_html( $vk_month_label ); ?></h3>
			<button
				type="button"
				class="vk-calendar__nav"
				data-month="<?php echo esc_attr( $vk_next_month ); ?>"
				data-wp-on--click="actions.goToMonth"
				data-wp-bind--disabled="state.isNavigating"
				aria-label="<?php esc_attr_e( 'Næste måned', 'vandrekalender-events' ); ?>"
			>&rsaquo;</button>
		</div>

		<div class="vk-calendar__grid">
			<?php foreach ( $vk_weekdays as $vk_weekday ) : ?>
				<div class="vk-calendar__weekday"><?php echo esc_html( $vk_weekday ); ?></div>
			<?php endforeach; ?>

			<?php for ( $vk_cell = 0; $vk_cell < $vk_total_cells; $vk_cell++ ) : ?>
				<?php
				$vk_day_num = $vk_cell - $vk_first_offset + 1;

				if ( $vk_day_num < 1 || $vk_day_num > $vk_days_in_month ) :
					?>
					<div class="vk-calendar__day is-other-month"></div>
					<?php
					continue;
				endif;

				$vk_date_key = sprintf( '%s-%02d', $vk_month_param, $vk_day_num );
				$vk_count    = $vk_counts[ $vk_date_key ] ?? 0;

				$vk_day_classes = [ 'vk-calendar__day' ];
				if ( $vk_date_key === $vk_today_key ) {
					$vk_day_classes[] = 'is-today';
				}
				if ( $vk_date_key === $vk_selected ) {
					$vk_day_classes[] = 'is-selected';
				}
				if ( $vk_count ) {
					$vk_day_classes[] = 'has-events';
				}

				$vk_dot_class = 'vk-calendar__dot';
				if ( $vk_count && $vk_count <= 2 ) {
					$vk_dot_class .= ' vk-calendar__dot--sm';
				} elseif ( $vk_count > 6 ) {
					$vk_dot_class .= ' vk-calendar__dot--lg';
				}
				?>
				<button
					type="button"
					class="<?php echo esc_attr( implode( ' ', $vk_day_classes ) ); ?>"
					<?php if ( $vk_count ) : ?>
						data-day="<?php echo esc_attr( $vk_date_key ); ?>"
						data-wp-on--click="actions.selectDay"
					<?php else : ?>
						disabled
					<?php endif; ?>
				>
					<span class="vk-calendar__num"><?php echo esc_html( (string) $vk_day_num ); ?></span>
					<?php if ( $vk_count ) : ?>
						<span class="<?php echo esc_attr( $vk_dot_class ); ?>"><?php echo esc_html( (string) $vk_count ); ?></span>
					<?php endif; ?>
				</button>
			<?php endfor; ?>
		</div>

		<div class="vk-calendar__day-events">
			<?php if ( $vk_selected ) : ?>
				<p class="vk-calendar__day-label">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: long date, 2: number of events. */
							_n( '%1$s — %2$d vandretur', '%1$s — %2$d vandreture', count( $vk_day_event_ids ), 'vandrekalender-events' ),
							wp_date( 'l j. F', strtotime( $vk_selected ), new DateTimeZone( 'UTC' ) ),
							count( $vk_day_event_ids )
						)
					);
					?>
				</p>
				<?php
				foreach ( $vk_day_event_ids as $vk_event_id ) :
					$vk_routes    = get_post_meta( $vk_event_id, \Vandrekalender\Event::META_ROUTES, true );
					$vk_distances = [];
					$vk_prices    = [];
					foreach ( ( is_array( $vk_routes ) ? $vk_routes : [] ) as $vk_route ) {
						if ( isset( $vk_route['distance_km'] ) && '' !== $vk_route['distance_km'] ) {
							$vk_distances[] = (float) $vk_route['distance_km'];
						}
						if ( isset( $vk_route['price'] ) && '' !== $vk_route['price'] ) {
							$vk_prices[] = (float) $vk_route['price'];
						}
					}
					sort( $vk_distances );

					$vk_price_from = ! empty( $vk_prices ) ? min( $vk_prices ) : null;
					if ( null === $vk_price_from || 0.0 === $vk_price_from ) {
						$vk_price_label = __( 'Gratis', 'vandrekalender-events' );
					} else {
						/* translators: %d: lowest route price in kroner. */
						$vk_price_label = sprintf( __( 'fra %d kr', 'vandrekalender-events' ), (int) round( $vk_price_from ) );
					}
					?>
					<a class="vk-calendar__event" href="<?php echo esc_url( get_permalink( $vk_event_id ) ); ?>">
						<span class="vk-calendar__event-title"><?php echo esc_html( get_the_title( $vk_event_id ) ); ?></span>
						<?php if ( ! empty( $vk_distances ) ) : ?>
							<span class="vk-calendar__event-dist"><?php echo esc_html( implode( ', ', $vk_distances ) . ' km' ); ?></span>
						<?php endif; ?>
						<span class="vk-calendar__event-price"><?php echo esc_html( $vk_price_label ); ?></span>
					</a>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
