<?php
/**
 * Server render for the Event Filters block.
 *
 * Renders the filter controls. Region options come from the event_region
 * taxonomy; length options are the fixed short/medium/long buckets. The view
 * script reads the controls, writes them to the URL query string, and tells the
 * calendar block to re-fetch.
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

$vk_lengths = [
	'short'  => __( 'Kort (0–10 km)', 'vandrekalender-events' ),
	'medium' => __( 'Mellem (10–25 km)', 'vandrekalender-events' ),
	'long'   => __( 'Lang (25+ km)', 'vandrekalender-events' ),
];
?>
<form <?php echo get_block_wrapper_attributes( [ 'class' => 'vk-filters' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="vk-filters__field">
		<label class="vk-filters__label" for="vk-filter-region"><?php esc_html_e( 'Region', 'vandrekalender-events' ); ?></label>
		<select class="vk-filters__select" id="vk-filter-region" data-filter="region">
			<option value=""><?php esc_html_e( 'Alle regioner', 'vandrekalender-events' ); ?></option>
			<?php foreach ( $vk_regions as $vk_region ) : ?>
				<option value="<?php echo esc_attr( $vk_region->slug ); ?>"><?php echo esc_html( $vk_region->name ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<div class="vk-filters__field">
		<span class="vk-filters__label"><?php esc_html_e( 'Længde', 'vandrekalender-events' ); ?></span>
		<div class="vk-filters__pills" role="group" aria-label="<?php esc_attr_e( 'Længde', 'vandrekalender-events' ); ?>">
			<?php foreach ( $vk_lengths as $vk_slug => $vk_label ) : ?>
				<button type="button" class="vk-filters__pill" data-filter="length" data-value="<?php echo esc_attr( $vk_slug ); ?>" aria-pressed="false">
					<?php echo esc_html( $vk_label ); ?>
				</button>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="vk-filters__date-range">
		<div class="vk-filters__field">
			<label class="vk-filters__label" for="vk-filter-from"><?php esc_html_e( 'Fra dato', 'vandrekalender-events' ); ?></label>
			<input class="vk-filters__input" type="date" id="vk-filter-from" data-filter="date_from" />
		</div>

		<div class="vk-filters__field">
			<label class="vk-filters__label" for="vk-filter-to"><?php esc_html_e( 'Til dato', 'vandrekalender-events' ); ?></label>
			<input class="vk-filters__input" type="date" id="vk-filter-to" data-filter="date_to" />
		</div>
	</div>

	<div class="vk-filters__field vk-filters__field--check">
		<label class="vk-filters__checkbox">
			<input type="checkbox" data-filter="is_free" value="true" />
			<?php esc_html_e( 'Kun gratis', 'vandrekalender-events' ); ?>
		</label>
	</div>

	<button type="button" class="vk-filters__reset" data-filter-reset>
		<?php esc_html_e( 'Nulstil', 'vandrekalender-events' ); ?>
	</button>
</form>
