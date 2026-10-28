<?php
/**
 * Server render for the Past Walks block (the past walks page, #176).
 *
 * Intro, the "Kommende ture" / "Tidligere ture" tabs, the filters (search,
 * region, organiser) as a GET form, the count line, the walks of the page
 * grouped by month (newest first, PER_PAGE per page) and the "Nyere" /
 * "Ældre" links. Everything is plain HTML and links, so it works without
 * JavaScript. Design: docs/design/passed-events-page/.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_filters = Vandrekalender_Past_Walks::filters_from_request();
$vk_ids     = Vandrekalender_Past_Walks::ids( $vk_filters );
$vk_total   = count( $vk_ids );
$vk_page    = Vandrekalender_Past_Walks::current_page();
$vk_pages   = Vandrekalender_Past_Walks::page_count( $vk_total );

// Walks per month over the whole filtered list, for the group headers.
$vk_month_totals = array_map( 'count', Vandrekalender_Past_Walks::group_by_month( Vandrekalender_Past_Walks::dates( $vk_ids ) ) );

$vk_page_ids = array_slice( $vk_ids, ( $vk_page - 1 ) * Vandrekalender_Past_Walks::PER_PAGE, Vandrekalender_Past_Walks::PER_PAGE );
$vk_groups   = Vandrekalender_Past_Walks::group_by_month( Vandrekalender_Past_Walks::dates( $vk_page_ids ) );

// One query each for posts, meta and terms instead of several per row.
_prime_post_caches( $vk_page_ids, true, true );

$vk_regions = get_terms(
	[
		'taxonomy'   => \Vandrekalender\Event::TAX_REGION,
		'hide_empty' => true,
	]
);
$vk_regions = is_wp_error( $vk_regions ) ? [] : $vk_regions;

// Organisers with a walk in the window, so the list never offers one that
// shows nothing.
$vk_window_ids = Vandrekalender_Past_Walks::ids( [] );
$vk_organisers = $vk_window_ids ? wp_get_object_terms(
	$vk_window_ids,
	\Vandrekalender\Event::TAX_ORGANISER,
	[
		'orderby' => 'name',
		'order'   => 'ASC',
	]
) : [];
$vk_organisers = is_wp_error( $vk_organisers ) ? [] : $vk_organisers;
?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'vk-past-walks' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<p class="vk-past-walks__intro">
		<?php
		echo esc_html(
			sprintf(
				/* translators: %d: number of months the page lists. */
				_n(
					'Here you find walks that have already taken place. The archive shows the last %d month. Use it to find a walk again, see where others have walked, or get ideas for next year.',
					'Here you find walks that have already taken place. The archive shows the last %d months. Use it to find a walk again, see where others have walked, or get ideas for next year.',
					Vandrekalender_Past_Walks::RETENTION_MONTHS,
					'vandrekalender-events'
				),
				Vandrekalender_Past_Walks::RETENTION_MONTHS
			)
		);
		?>
	</p>

	<nav class="vk-past-walks__tabs" aria-label="<?php esc_attr_e( 'Show walks', 'vandrekalender-events' ); ?>">
		<a class="vk-past-walks__tab" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Upcoming walks', 'vandrekalender-events' ); ?></a>
		<a class="vk-past-walks__tab" href="<?php echo esc_url( Vandrekalender_Past_Walks::url() ); ?>" aria-current="page"><?php esc_html_e( 'Past walks', 'vandrekalender-events' ); ?></a>
	</nav>

	<form class="vk-past-walks__filters" method="get" action="<?php echo esc_url( Vandrekalender_Past_Walks::url() ); ?>" aria-label="<?php esc_attr_e( 'Filters', 'vandrekalender-events' ); ?>">
		<div class="vk-past-walks__field vk-past-walks__field--search">
			<label class="vk-past-walks__label" for="vk-past-walks-q"><?php esc_html_e( 'Search name or place', 'vandrekalender-events' ); ?></label>
			<input class="vk-past-walks__input" type="search" id="vk-past-walks-q" name="q" value="<?php echo esc_attr( $vk_filters['q'] ); ?>" placeholder="<?php esc_attr_e( 'E.g. Tirsdagstraveren or Viborg', 'vandrekalender-events' ); ?>" />
		</div>

		<div class="vk-past-walks__field">
			<label class="vk-past-walks__label" for="vk-past-walks-region"><?php esc_html_e( 'Region', 'vandrekalender-events' ); ?></label>
			<select class="vk-past-walks__input" id="vk-past-walks-region" name="region">
				<option value=""><?php esc_html_e( 'All regions', 'vandrekalender-events' ); ?></option>
				<?php foreach ( $vk_regions as $vk_region ) : ?>
					<option value="<?php echo esc_attr( $vk_region->slug ); ?>" <?php selected( $vk_filters['region'], $vk_region->slug ); ?>><?php echo esc_html( $vk_region->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<?php if ( $vk_organisers ) : ?>
			<div class="vk-past-walks__field">
				<label class="vk-past-walks__label" for="vk-past-walks-organiser"><?php esc_html_e( 'Organiser', 'vandrekalender-events' ); ?></label>
				<select class="vk-past-walks__input" id="vk-past-walks-organiser" name="organiser">
					<option value=""><?php esc_html_e( 'All organisers', 'vandrekalender-events' ); ?></option>
					<?php foreach ( $vk_organisers as $vk_organiser ) : ?>
						<option value="<?php echo esc_attr( $vk_organiser->slug ); ?>" <?php selected( $vk_filters['organiser'], $vk_organiser->slug ); ?>><?php echo esc_html( $vk_organiser->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<button class="vk-past-walks__submit" type="submit"><?php esc_html_e( 'Show walks', 'vandrekalender-events' ); ?></button>
		<a class="vk-past-walks__reset" href="<?php echo esc_url( Vandrekalender_Past_Walks::url() ); ?>"><?php esc_html_e( 'Reset', 'vandrekalender-events' ); ?></a>
	</form>

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
					/* translators: %s: number of past walks. */
					_n( '%s past walk, newest first', '%s past walks, newest first', $vk_total, 'vandrekalender-events' ),
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

		<?php if ( $vk_pages > 1 ) : ?>
			<nav class="vk-past-walks__pages" aria-label="<?php esc_attr_e( 'Pages', 'vandrekalender-events' ); ?>">
				<?php if ( $vk_page > 1 ) : ?>
					<a class="vk-past-walks__page-link" href="<?php echo esc_url( Vandrekalender_Past_Walks::url( $vk_page - 1, $vk_filters ) ); ?>" rel="prev">‹ <?php esc_html_e( 'Newer', 'vandrekalender-events' ); ?></a>
				<?php else : ?>
					<span class="vk-past-walks__page-link is-disabled">‹ <?php esc_html_e( 'Newer', 'vandrekalender-events' ); ?></span>
				<?php endif; ?>

				<span class="vk-past-walks__page-count">
					<?php
					/* translators: 1: current page, 2: number of pages. */
					echo esc_html( sprintf( __( 'Page %1$d of %2$d', 'vandrekalender-events' ), $vk_page, $vk_pages ) );
					?>
				</span>

				<?php if ( $vk_page < $vk_pages ) : ?>
					<a class="vk-past-walks__page-link" href="<?php echo esc_url( Vandrekalender_Past_Walks::url( $vk_page + 1, $vk_filters ) ); ?>" rel="next"><?php esc_html_e( 'Older', 'vandrekalender-events' ); ?> ›</a>
				<?php else : ?>
					<span class="vk-past-walks__page-link is-disabled"><?php esc_html_e( 'Older', 'vandrekalender-events' ); ?> ›</span>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
