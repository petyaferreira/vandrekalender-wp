<?php
/**
 * Server render for the Event Filters block.
 *
 * Renders the filter controls. Region options come from the event_region
 * taxonomy; organiser options are the event_organiser terms that have an
 * upcoming event, so the list never offers a filter that shows nothing. Length
 * options are the fixed kort/mellem/lang buckets. The view script reads the
 * controls, writes them to the URL query string, and tells the calendar block
 * to re-fetch.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_regions = get_terms(
	[
		'taxonomy'   => \Vandrekalender\Event::TAX_REGION,
		'hide_empty' => true,
	]
);
$vk_regions = is_wp_error( $vk_regions ) ? [] : $vk_regions;

// Upcoming = the same default floor the cards use (build_query_args() with no
// date params starts at today).
$vk_upcoming_ids = get_posts(
	array_merge(
		Vandrekalender_Event_Rest_Api::build_query_args( [] ),
		[
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		]
	)
);
$vk_organisers   = $vk_upcoming_ids ? wp_get_object_terms(
	$vk_upcoming_ids,
	\Vandrekalender\Event::TAX_ORGANISER,
	[
		'orderby' => 'name',
		'order'   => 'ASC',
	]
) : [];
$vk_organisers   = is_wp_error( $vk_organisers ) ? [] : $vk_organisers;

$vk_lengths = [
	'kort'   => __( 'Short (0–10 km)', 'vandrekalender-events' ),
	'mellem' => __( 'Medium (10–25 km)', 'vandrekalender-events' ),
	'lang'   => __( 'Long (25+ km)', 'vandrekalender-events' ),
];
?>
<form <?php echo get_block_wrapper_attributes( [ 'class' => 'vk-filters' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="vk-filters__field">
		<label class="vk-filters__label" for="vk-filter-region"><?php esc_html_e( 'Region', 'vandrekalender-events' ); ?></label>
		<select class="vk-filters__select" id="vk-filter-region" data-filter="region">
			<option value=""><?php esc_html_e( 'All regions', 'vandrekalender-events' ); ?></option>
			<?php foreach ( $vk_regions as $vk_region ) : ?>
				<option value="<?php echo esc_attr( $vk_region->slug ); ?>"><?php echo esc_html( $vk_region->name ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<?php if ( $vk_organisers ) : ?>
		<div class="vk-filters__field">
			<label class="vk-filters__label" for="vk-filter-organiser"><?php esc_html_e( 'Organiser', 'vandrekalender-events' ); ?></label>
			<select class="vk-filters__select" id="vk-filter-organiser" data-filter="organiser">
				<option value=""><?php esc_html_e( 'All organisers', 'vandrekalender-events' ); ?></option>
				<?php foreach ( $vk_organisers as $vk_organiser ) : ?>
					<option value="<?php echo esc_attr( $vk_organiser->slug ); ?>"><?php echo esc_html( $vk_organiser->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	<?php endif; ?>

	<div class="vk-filters__field">
		<span class="vk-filters__label"><?php esc_html_e( 'Length', 'vandrekalender-events' ); ?></span>
		<div class="vk-filters__pills" role="group" aria-label="<?php esc_attr_e( 'Length', 'vandrekalender-events' ); ?>">
			<?php foreach ( $vk_lengths as $vk_slug => $vk_label ) : ?>
				<button type="button" class="vk-filters__pill" data-filter="length" data-value="<?php echo esc_attr( $vk_slug ); ?>" aria-pressed="false">
					<?php echo esc_html( $vk_label ); ?>
				</button>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="vk-filters__date-range">
		<div class="vk-filters__field">
			<label class="vk-filters__label" for="vk-filter-from"><?php esc_html_e( 'From date', 'vandrekalender-events' ); ?></label>
			<input class="vk-filters__input" type="date" id="vk-filter-from" data-filter="date_from" />
		</div>

		<div class="vk-filters__field">
			<label class="vk-filters__label" for="vk-filter-to"><?php esc_html_e( 'To date', 'vandrekalender-events' ); ?></label>
			<input class="vk-filters__input" type="date" id="vk-filter-to" data-filter="date_to" />
		</div>
	</div>

	<div class="vk-filters__field vk-filters__field--check">
		<label class="vk-filters__checkbox">
			<input type="checkbox" data-filter="is_free" value="true" />
			<?php esc_html_e( 'Free only', 'vandrekalender-events' ); ?>
		</label>
	</div>

	<button type="button" class="vk-filters__reset" data-filter-reset>
		<?php esc_html_e( 'Reset', 'vandrekalender-events' ); ?>
	</button>
</form>
