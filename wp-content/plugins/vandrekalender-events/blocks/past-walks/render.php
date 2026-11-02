<?php
/**
 * Server render for the Past Walks block: the results of the past walks
 * page (#176), below the Event Filters block set to Past.
 *
 * The count line, the walks grouped by month (newest first) and "Viser 10
 * af 212 ture" with "Vis flere ture". The filters are the calendar's, read
 * from the URL. Like Event Cards, the list is server-rendered and view.js
 * re-renders it through `@wordpress/interactivity-router` when the filters
 * change or "Vis flere ture" is clicked: ?side=N shows the first N times
 * PER_PAGE walks. "Vis flere ture" is a real link, so it works without
 * JavaScript and crawlers can follow it. Design:
 * docs/design/passed-events-page/.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_filters = Vandrekalender_Past_Walks::filters_from_request();
$vk_ids     = Vandrekalender_Past_Walks::ids( $vk_filters );
$vk_total   = count( $vk_ids );
$vk_page    = Vandrekalender_Past_Walks::current_page();
$vk_shown   = Vandrekalender_Past_Walks::shown_count( $vk_total, $vk_page );

// Walks per month over the whole filtered list, for the group headers.
$vk_month_totals = array_map( 'count', Vandrekalender_Past_Walks::group_by_month( Vandrekalender_Past_Walks::dates( $vk_ids ) ) );

$vk_page_ids = array_slice( $vk_ids, 0, $vk_shown );
$vk_groups   = Vandrekalender_Past_Walks::group_by_month( Vandrekalender_Past_Walks::dates( $vk_page_ids ) );

// One query each for posts, meta and terms instead of several per row.
_prime_post_caches( $vk_page_ids, true, true );

$vk_more_url = $vk_shown < $vk_total ? Vandrekalender_Past_Walks::url( $vk_page + 1, $vk_filters ) : '';

$vk_wrapper_attributes = [
	'class'                     => 'vk-past-walks',
	'data-wp-interactive'       => 'vandrekalender/past-walks',
	'data-wp-class--is-loading' => 'state.isNavigating',
	'data-wp-init'              => 'callbacks.init',
];
?>
<div <?php echo get_block_wrapper_attributes( $vk_wrapper_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php
	// data-wp-interactive repeats the wrapper's on purpose: the router only
	// finds regions that carry it themselves
	// ([data-wp-interactive][data-wp-router-region]); without it filter
	// changes and "Vis flere ture" stop updating the list. Event Cards does
	// the same.
	?>
	<div
		class="vk-past-walks__results"
		data-wp-interactive="vandrekalender/past-walks"
		data-wp-router-region="vk-past-walks"
	>
	<?php if ( ! $vk_total ) : ?>
		<p class="vk-past-walks__empty">
			<?php
			echo esc_html(
				Vandrekalender_Past_Walks::is_filtered( $vk_filters )
					? __( 'No walks match your filters.', 'vandrekalender-events' )
					: __( 'There are no past walks yet.', 'vandrekalender-events' )
			);
			?>
		</p>
	<?php else : ?>
		<p class="vk-past-walks__count">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: number of past walks that match the filters. */
					_n( '%s past walk matches your choices', '%s past walks match your choices', $vk_total, 'vandrekalender-events' ),
					number_format_i18n( $vk_total )
				)
			);
			?>
		</p>

		<?php foreach ( $vk_groups as $vk_month => $vk_month_ids ) : ?>
			<?php $vk_month_time = strtotime( $vk_month . '-01' ); ?>
			<section class="vk-past-walks__month" aria-labelledby="vk-past-walks-<?php echo esc_attr( $vk_month ); ?>">
				<div class="vk-past-walks__month-head">
					<h2 class="vk-past-walks__month-title" id="vk-past-walks-<?php echo esc_attr( $vk_month ); ?>"><?php echo esc_html( ucfirst( wp_date( 'F Y', $vk_month_time, new DateTimeZone( 'UTC' ) ) ) ); ?></h2>
					<span class="vk-past-walks__month-count">
						<?php
						$vk_month_total = $vk_month_totals[ $vk_month ] ?? count( $vk_month_ids );
						/* translators: %s: number of walks in the month. */
						echo esc_html( sprintf( _n( '%s walk', '%s walks', $vk_month_total, 'vandrekalender-events' ), number_format_i18n( $vk_month_total ) ) );
						?>
					</span>
				</div>

				<div class="vk-past-walks__columns" aria-hidden="true">
					<span><?php esc_html_e( 'Date', 'vandrekalender-events' ); ?></span>
					<span><?php esc_html_e( 'Walk and organiser', 'vandrekalender-events' ); ?></span>
					<span><?php esc_html_e( 'Place', 'vandrekalender-events' ); ?></span>
					<span><?php esc_html_e( 'Length', 'vandrekalender-events' ); ?></span>
					<span><?php esc_html_e( 'Region', 'vandrekalender-events' ); ?></span>
				</div>

				<ul class="vk-past-walks__list">
					<?php
					foreach ( $vk_month_ids as $vk_event_id ) :
						$vk_date  = (string) get_post_meta( $vk_event_id, \Vandrekalender\Event::META_DATE, true );
						$vk_place = implode(
							', ',
							array_filter(
								[
									get_post_meta( $vk_event_id, \Vandrekalender\Event::META_PLACE_NAME, true ),
									get_post_meta( $vk_event_id, \Vandrekalender\Event::META_MUNICIPALITY, true ),
								]
							)
						);
						$vk_place = $vk_place ? $vk_place : \Vandrekalender\Event::coordinates_label( $vk_event_id );

						$vk_routes    = get_post_meta( $vk_event_id, \Vandrekalender\Event::META_ROUTES, true );
						$vk_distances = [];
						foreach ( ( is_array( $vk_routes ) ? $vk_routes : [] ) as $vk_route ) {
							if ( isset( $vk_route['distance_km'] ) && '' !== $vk_route['distance_km'] ) {
								$vk_distances[] = (float) $vk_route['distance_km'];
							}
						}
						sort( $vk_distances );
						$vk_length = $vk_distances ? implode( ', ', $vk_distances ) . ' km' : '';

						$vk_regions_of = wp_get_post_terms( $vk_event_id, \Vandrekalender\Event::TAX_REGION, [ 'fields' => 'names' ] );
						$vk_region     = ! is_wp_error( $vk_regions_of ) && $vk_regions_of ? $vk_regions_of[0] : '';

						// The organiser term links to its page (or the calendar
						// filtered by it); an organiser without a term is plain text.
						$vk_terms          = get_the_terms( $vk_event_id, \Vandrekalender\Event::TAX_ORGANISER );
						$vk_organiser_term = is_array( $vk_terms ) ? $vk_terms[0] : null;
						$vk_organiser_name = $vk_organiser_term ? $vk_organiser_term->name : (string) get_post_meta( $vk_event_id, \Vandrekalender\Event::META_ORGANISER_NAME, true );
						$vk_organiser_link = $vk_organiser_term ? get_term_link( $vk_organiser_term ) : '';
						$vk_organiser_link = is_string( $vk_organiser_link ) ? $vk_organiser_link : '';

						$vk_place_region = implode( ', ', array_filter( [ $vk_place, $vk_region ] ) );
						?>
						<li class="vk-past-walks__row">
							<?php if ( $vk_date ) : ?>
								<?php /* translators: past walk date, PHP date format. */ ?>
								<time class="vk-past-walks__date" datetime="<?php echo esc_attr( $vk_date ); ?>"><?php echo esc_html( wp_date( _x( 'D j M', 'past walk date', 'vandrekalender-events' ), strtotime( $vk_date ), new DateTimeZone( 'UTC' ) ) ); ?></time>
							<?php endif; ?>
							<a class="vk-past-walks__title" href="<?php echo esc_url( get_permalink( $vk_event_id ) ); ?>"><?php echo esc_html( get_the_title( $vk_event_id ) ); ?></a>
							<?php if ( $vk_organiser_name ) : ?>
								<?php if ( $vk_organiser_link ) : ?>
									<a class="vk-past-walks__organiser" href="<?php echo esc_url( $vk_organiser_link ); ?>"><?php echo esc_html( $vk_organiser_name ); ?></a>
								<?php else : ?>
									<span class="vk-past-walks__organiser"><?php echo esc_html( $vk_organiser_name ); ?></span>
								<?php endif; ?>
							<?php endif; ?>
							<span class="vk-past-walks__place"><?php echo esc_html( $vk_place ); ?></span>
							<span class="vk-past-walks__place-region"><?php echo esc_html( $vk_place_region ); ?></span>
							<?php if ( $vk_length ) : ?>
								<span class="vk-past-walks__length"><?php echo esc_html( $vk_length ); ?></span>
							<?php endif; ?>
							<span class="vk-past-walks__region"><?php echo esc_html( $vk_region ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>

		<?php if ( $vk_total > Vandrekalender_Past_Walks::PER_PAGE ) : ?>
			<div class="vk-past-walks__more">
				<p class="vk-past-walks__shown">
					<?php
					/* translators: 1: number of walks shown, 2: number of matching walks. */
					echo esc_html( sprintf( __( 'Showing %1$s of %2$s walks', 'vandrekalender-events' ), number_format_i18n( $vk_shown ), number_format_i18n( $vk_total ) ) );
					?>
				</p>
				<?php if ( $vk_more_url ) : ?>
					<a
						class="vk-past-walks__more-link"
						href="<?php echo esc_url( $vk_more_url ); ?>"
						data-wp-on--click="actions.loadMore"
					><?php esc_html_e( 'Show more walks', 'vandrekalender-events' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
	</div>
</div>
