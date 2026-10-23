<?php
/**
 * Server render for the Organiser List block.
 *
 * Organisers with a published page as cards: logo or initials, name and
 * number of upcoming walks, computed on every render (Organiser_Walks).
 *
 * - show "others" ("Andre arrangører", single-organiser.html): up to six
 *   organisers other than the page shown, those sharing the most regions
 *   first, then "Se alle arrangører". Nothing when no other page exists.
 * - show "all" (the overview /arrangoerer/, archive-organiser.html): every
 *   organiser with a page, by name, with its regions.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_all   = 'all' === ( $attributes['show'] ?? 'others' );
$vk_pages = \Vandrekalender\Organiser::instance()->published_posts();

if ( ! $vk_all ) {
	$vk_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
	$vk_term    = $vk_post_id ? \Vandrekalender\Organiser::term_for_post( $vk_post_id ) : null;
	$vk_pages   = \Vandrekalender\Organiser_Walks::other_pages( $vk_pages, $vk_post_id );
}

$vk_names = [];
foreach ( $vk_pages as $vk_term_id => $vk_page_id ) {
	$vk_names[ $vk_term_id ] = get_the_title( $vk_page_id );
}

$vk_organisers = $vk_all
	? \Vandrekalender\Organiser_Walks::overview( \Vandrekalender\Organiser_Walks::by_term(), $vk_names )
	: \Vandrekalender\Organiser_Walks::related( \Vandrekalender\Organiser_Walks::by_term(), $vk_term ? $vk_term->term_id : 0, $vk_names );

if ( ! $vk_organisers && ! $vk_all ) {
	return;
}

$vk_wrapper = get_block_wrapper_attributes( [ 'class' => 'vk-organiser-list' ] );
?>
<?php if ( $vk_all ) : ?>
	<div <?php echo $vk_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
<?php else : ?>
	<section <?php echo $vk_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> aria-labelledby="vk-organiser-list-heading">
		<h2 class="vk-organiser-list__heading" id="vk-organiser-list-heading"><?php esc_html_e( 'Other organisers', 'vandrekalender-events' ); ?></h2>
<?php endif; ?>

	<?php if ( ! $vk_organisers ) : ?>
		<p><?php esc_html_e( 'There are no organiser pages yet.', 'vandrekalender-events' ); ?></p>
	<?php else : ?>
		<ul class="vk-organiser-list__list">
			<?php foreach ( $vk_organisers as $vk_organiser ) : ?>
				<?php $vk_page_id = $vk_pages[ $vk_organiser['term_id'] ]; ?>
				<li>
					<a class="vk-organiser-list__link" href="<?php echo esc_url( get_permalink( $vk_page_id ) ); ?>">
						<span class="vk-organiser-list__logo" aria-hidden="true">
							<?php if ( has_post_thumbnail( $vk_page_id ) ) : ?>
								<?php echo get_the_post_thumbnail( $vk_page_id, 'thumbnail', [ 'alt' => '' ] ); ?>
							<?php else : ?>
								<?php echo esc_html( \Vandrekalender\Event::organiser_initials( $vk_names[ $vk_organiser['term_id'] ] ) ); ?>
							<?php endif; ?>
						</span>
						<span class="vk-organiser-list__text">
							<span class="vk-organiser-list__name"><?php echo esc_html( $vk_names[ $vk_organiser['term_id'] ] ); ?></span>
							<span class="vk-organiser-list__walks">
								<?php
								echo esc_html(
									$vk_organiser['walks']
										/* translators: %s: number of upcoming walks. */
										? sprintf( _n( '%s upcoming walk', '%s upcoming walks', $vk_organiser['walks'], 'vandrekalender-events' ), number_format_i18n( $vk_organiser['walks'] ) )
										: __( 'No upcoming walks', 'vandrekalender-events' )
								);
								?>
							</span>
							<?php if ( ! empty( $vk_organiser['regions'] ) ) : ?>
								<span class="vk-organiser-list__regions"><?php echo esc_html( implode( ', ', $vk_organiser['regions'] ) ); ?></span>
							<?php endif; ?>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

<?php if ( $vk_all ) : ?>
	</div>
<?php else : ?>
		<a class="vk-organiser-list__all" href="<?php echo esc_url( (string) get_post_type_archive_link( \Vandrekalender\Organiser::CUSTOMPOSTTYPE ) ); ?>"><?php esc_html_e( 'See all organisers', 'vandrekalender-events' ); ?> <span aria-hidden="true">›</span></a>
	</section>
<?php endif; ?>
