<?php
/**
 * Server render for the Month Quick Filter block.
 *
 * Six chips, worked out from today on every render. Each chip is a plain link
 * to this page with date_from and date_to set to the month (other filters
 * kept, `side` dropped); the view script turns the click into a filter change
 * without a reload. The chip whose month is in the URL is marked active, and
 * links to the page without the two dates.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_direction = 'past' === ( $attributes['direction'] ?? 'future' ) ? 'past' : 'future';
$vk_months    = Vandrekalender_Month_Quick_Filter::month_options( $vk_direction, current_time( 'Y-m-d' ) );
if ( ! $vk_months ) {
	return;
}

$vk_page    = is_singular() ? (string) get_permalink() : home_url( '/' );
$vk_current = Vandrekalender_Event_Rest_Api::filters_from_query();
$vk_kept    = array_diff_key(
	$vk_current,
	[
		'date_from' => 1,
		'date_to'   => 1,
	]
);
?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'vk-months' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<span class="vk-months__heading"><?php esc_html_e( 'Jump to month', 'vandrekalender-events' ); ?></span>
	<ul class="vk-months__list">
		<?php
		foreach ( $vk_months as $vk_month ) :
			$vk_active = ( $vk_current['date_from'] ?? '' ) === $vk_month['from'] && ( $vk_current['date_to'] ?? '' ) === $vk_month['to'];
			$vk_args   = $vk_active ? $vk_kept : array_merge(
				$vk_kept,
				[
					'date_from' => $vk_month['from'],
					'date_to'   => $vk_month['to'],
				]
			);
			$vk_url    = $vk_args ? add_query_arg( array_map( 'rawurlencode', $vk_args ), $vk_page ) : $vk_page;
			?>
			<li>
				<a class="vk-months__chip" href="<?php echo esc_url( $vk_url ); ?>" data-from="<?php echo esc_attr( $vk_month['from'] ); ?>" data-to="<?php echo esc_attr( $vk_month['to'] ); ?>"<?php echo $vk_active ? ' aria-current="true"' : ''; ?>>
					<?php echo esc_html( Vandrekalender_Month_Quick_Filter::label( $vk_month['label_time'] ) ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
