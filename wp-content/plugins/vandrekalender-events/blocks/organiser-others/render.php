<?php
/**
 * Server render for the Other Organisers block.
 *
 * "Andre arrangører" under an organiser page (single-organiser.html): up to
 * six other organisers with a published page, those with upcoming walks in
 * the same regions first (Organiser_Walks::related()). Each shows its logo
 * or initials, its name and its number of upcoming walks. Nothing when no
 * other organiser has a page.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$vk_term    = $vk_post_id ? \Vandrekalender\Organiser::term_for_post( $vk_post_id ) : null;
$vk_pages   = \Vandrekalender\Organiser::instance()->published_posts();

$vk_names = [];
foreach ( $vk_pages as $vk_term_id => $vk_page_id ) {
	$vk_names[ $vk_term_id ] = get_the_title( $vk_page_id );
}

$vk_others = \Vandrekalender\Organiser_Walks::related(
	\Vandrekalender\Organiser_Walks::by_term(),
	$vk_term ? $vk_term->term_id : 0,
	$vk_names
);

if ( ! $vk_others ) {
	return;
}
?>
<section <?php echo get_block_wrapper_attributes( [ 'class' => 'vk-organiser-others' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-labelledby="vk-organiser-others-heading">
	<h2 class="vk-organiser-others__heading" id="vk-organiser-others-heading"><?php esc_html_e( 'Other organisers', 'vandrekalender-events' ); ?></h2>
	<ul class="vk-organiser-others__list">
		<?php foreach ( $vk_others as $vk_other ) : ?>
			<?php $vk_page_id = $vk_pages[ $vk_other['term_id'] ]; ?>
			<li>
				<a class="vk-organiser-others__link" href="<?php echo esc_url( get_permalink( $vk_page_id ) ); ?>">
					<span class="vk-organiser-others__logo" aria-hidden="true">
						<?php if ( has_post_thumbnail( $vk_page_id ) ) : ?>
							<?php echo get_the_post_thumbnail( $vk_page_id, 'thumbnail', [ 'alt' => '' ] ); ?>
						<?php else : ?>
							<?php echo esc_html( \Vandrekalender\Event::organiser_initials( $vk_names[ $vk_other['term_id'] ] ) ); ?>
						<?php endif; ?>
					</span>
					<span class="vk-organiser-others__text">
						<span class="vk-organiser-others__name"><?php echo esc_html( $vk_names[ $vk_other['term_id'] ] ); ?></span>
						<span class="vk-organiser-others__walks">
							<?php
							echo esc_html(
								$vk_other['walks']
									/* translators: %s: number of upcoming walks. */
									? sprintf( _n( '%s upcoming walk', '%s upcoming walks', $vk_other['walks'], 'vandrekalender-events' ), number_format_i18n( $vk_other['walks'] ) )
									: __( 'No upcoming walks', 'vandrekalender-events' )
							);
							?>
						</span>
					</span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
