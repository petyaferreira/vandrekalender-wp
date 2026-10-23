<?php
/**
 * Server render for the Organiser Regions block.
 *
 * "Ture i hele landet": the regions with the organiser's upcoming walks and
 * how many are in each, most first. Each links to this page with that
 * region filtered, at the walks. Computed on every render (Organiser_Walks).
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$vk_term    = $vk_post_id ? \Vandrekalender\Organiser::term_for_post( $vk_post_id ) : null;
$vk_regions = $vk_term ? \Vandrekalender\Organiser_Walks::regions( \Vandrekalender\Organiser_Walks::for_term( $vk_term->term_id ) ) : [];
$vk_wrapper = get_block_wrapper_attributes( [ 'class' => 'vk-organiser-regions' ] );
?>
<?php if ( ! $vk_regions ) : ?>
	<p <?php echo $vk_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'There are no upcoming walks right now.', 'vandrekalender-events' ); ?></p>
<?php else : ?>
	<ul <?php echo $vk_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php foreach ( $vk_regions as $vk_region ) : ?>
			<li class="vk-organiser-regions__item">
				<a class="vk-organiser-regions__link" href="<?php echo esc_url( \Vandrekalender\Organiser_Walks::region_url( (string) get_permalink( $vk_post_id ), $vk_region['slug'] ) ); ?>">
					<span><?php echo esc_html( $vk_region['name'] ); ?></span>
					<span class="vk-organiser-regions__count">
						<?php
						/* translators: %s: number of upcoming walks in the region. */
						echo esc_html( sprintf( _n( '%s walk', '%s walks', $vk_region['count'], 'vandrekalender-events' ), number_format_i18n( $vk_region['count'] ) ) );
						?>
						<span aria-hidden="true">›</span>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
