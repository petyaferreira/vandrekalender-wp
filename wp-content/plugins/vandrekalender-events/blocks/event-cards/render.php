<?php
/**
 * Server render for the Event Cards block.
 *
 * Renders the matching events as real HTML so crawlers index them on first
 * paint. Filter changes and "Vis flere" re-render through
 * `@wordpress/interactivity-router` (see view.js): the router re-fetches the
 * page with new query params and swaps the `data-wp-router-region` below.
 * Pagination is cumulative — the `side` URL param renders pages 1..N.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_per_page = 50;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination param.
$vk_page = isset( $_GET['side'] ) ? max( 1, (int) $_GET['side'] ) : 1;

// On a taxonomy archive, pin the queried term so the cards only show that
// region/length regardless of URL query params.
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

$vk_query_args                   = Vandrekalender_Event_Rest_Api::build_query_args( $vk_filters );
$vk_query_args['posts_per_page'] = -1;
$vk_query_args['fields']         = 'ids';
$vk_query_args['orderby']        = 'meta_value';
$vk_query_args['meta_key']       = \Vandrekalender\Event::META_DATE;
$vk_query_args['order']          = 'ASC';

$vk_ids = get_posts( $vk_query_args );

// Price lives inside the routes JSON, so the free/paid filter runs in PHP.
if ( isset( $vk_filters['is_free'] ) && '' !== $vk_filters['is_free'] ) {
	update_meta_cache( 'post', $vk_ids );
	$vk_want_free = rest_sanitize_boolean( $vk_filters['is_free'] );
	$vk_ids       = array_values(
		array_filter(
			$vk_ids,
			fn( $id ) => Vandrekalender_Event_Rest_Api::is_event_free( $id ) === $vk_want_free
		)
	);
}

$vk_total     = count( $vk_ids );
$vk_shown_ids = array_slice( $vk_ids, 0, $vk_page * $vk_per_page );

// One query each for posts, meta, and terms instead of several per card.
_prime_post_caches( $vk_shown_ids, true, true );

$vk_wrapper_attributes = [
	'class'                     => 'vk-cards',
	'data-wp-interactive'       => 'vandrekalender/event-cards',
	'data-wp-class--is-loading' => 'state.isNavigating',
	'data-wp-init'              => 'callbacks.init',
];
?>
<div <?php echo get_block_wrapper_attributes( $vk_wrapper_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div
		class="vk-cards__region"
		data-wp-interactive="vandrekalender/event-cards"
		data-wp-router-region="vk-event-cards"
	>
		<?php if ( empty( $vk_shown_ids ) ) : ?>
			<p class="vk-cards__empty"><?php esc_html_e( 'No walks match your filters.', 'vandrekalender-events' ); ?></p>
		<?php else : ?>
			<ul class="vk-cards__list">
				<?php
				foreach ( $vk_shown_ids as $vk_event_id ) :
					$vk_date  = get_post_meta( $vk_event_id, \Vandrekalender\Event::META_DATE, true );
					$vk_place = implode(
						', ',
						array_filter(
							[
								get_post_meta( $vk_event_id, \Vandrekalender\Event::META_PLACE_NAME, true ),
								get_post_meta( $vk_event_id, \Vandrekalender\Event::META_MUNICIPALITY, true ),
							]
						)
					);

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
						$vk_price_label = __( 'Free', 'vandrekalender-events' );
					} else {
						/* translators: %d: lowest route price in kroner. */
						$vk_price_label = sprintf( __( 'from %d kr', 'vandrekalender-events' ), (int) round( $vk_price_from ) );
					}

					$vk_regions   = wp_get_post_terms( $vk_event_id, \Vandrekalender\Event::TAX_REGION, [ 'fields' => 'names' ] );
					$vk_region    = ! is_wp_error( $vk_regions ) && ! empty( $vk_regions ) ? $vk_regions[0] : '';
					$vk_image_url = get_the_post_thumbnail_url( $vk_event_id, 'large' );
					?>
					<li class="vk-card">
						<a class="vk-card__link" href="<?php echo esc_url( get_permalink( $vk_event_id ) ); ?>">
							<div
								class="vk-card__image"
								<?php if ( $vk_image_url ) : ?>
									style="background-image:url('<?php echo esc_url( $vk_image_url ); ?>')"
								<?php endif; ?>
							></div>
							<div class="vk-card__body">
								<?php if ( $vk_date ) : ?>
									<span class="vk-card__date"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $vk_date ), new DateTimeZone( 'UTC' ) ) ); ?></span>
								<?php endif; ?>
								<span class="vk-card__title"><?php echo esc_html( get_the_title( $vk_event_id ) ); ?></span>
								<?php if ( $vk_place ) : ?>
									<span class="vk-card__place"><?php echo esc_html( $vk_place ); ?></span>
								<?php endif; ?>
								<span class="vk-card__meta">
									<?php if ( ! empty( $vk_distances ) ) : ?>
										<span class="vk-card__distance"><?php echo esc_html( implode( ', ', $vk_distances ) . ' km' ); ?></span>
									<?php endif; ?>
									<span class="vk-card__price"><?php echo esc_html( $vk_price_label ); ?></span>
									<?php if ( $vk_region ) : ?>
										<span class="vk-card__region"><?php echo esc_html( $vk_region ); ?></span>
									<?php endif; ?>
								</span>
							</div>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $vk_total > count( $vk_shown_ids ) ) : ?>
			<button
				type="button"
				class="vk-cards__more"
				data-wp-on--click="actions.loadMore"
				data-wp-bind--disabled="state.isNavigating"
			><?php esc_html_e( 'Show more walks', 'vandrekalender-events' ); ?></button>
		<?php endif; ?>
	</div>
</div>
