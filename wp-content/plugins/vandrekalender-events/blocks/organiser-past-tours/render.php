<?php
/**
 * Server render for the Past Tours block.
 *
 * "Tidligere ture" on an organiser page (docs/design/organiser-page/): the
 * organiser's last Organiser_Walks::PAST_LIMIT past walks, newest first, each
 * with its date, name (linked to the walk) and "place, length", then
 * "Se flere tidligere ture" to the past walks page filtered to the organiser,
 * once that page is published. The heading is the editor's, above the block.
 *
 * @package Vandrekalender
 */

defined( 'ABSPATH' ) || exit;

$vk_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$vk_term    = $vk_post_id ? \Vandrekalender\Organiser::term_for_post( $vk_post_id ) : null;
$vk_walks   = $vk_term ? \Vandrekalender\Organiser_Walks::past( $vk_term->term_id ) : [];

// The past walks page (#176) filtered to this organiser; no link while it
// doesn't exist.
$vk_all_page = get_page_by_path( 'tidligere-ture' );
$vk_all_url  = $vk_term && $vk_all_page && 'publish' === $vk_all_page->post_status
	? add_query_arg( 'organiser', rawurlencode( $vk_term->slug ), (string) get_permalink( $vk_all_page ) )
	: '';

_prime_post_caches( $vk_walks, false, true );
$vk_routes = \Vandrekalender\Organiser_Walks::routes( array_map( static fn( $id ) => [ 'id' => $id ], $vk_walks ) );
?>
<div <?php echo get_block_wrapper_attributes( [ 'class' => 'vk-past-tours' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( ! $vk_walks ) : ?>
		<p class="vk-past-tours__empty"><?php esc_html_e( 'There are no past walks yet.', 'vandrekalender-events' ); ?></p>
	<?php else : ?>
		<ul class="vk-past-tours__list">
			<?php foreach ( $vk_walks as $vk_index => $vk_id ) : ?>
				<?php
				$vk_date  = (string) get_post_meta( $vk_id, \Vandrekalender\Event::META_DATE, true );
				$vk_place = (string) get_post_meta( $vk_id, \Vandrekalender\Event::META_MUNICIPALITY, true );
				$vk_place = '' !== $vk_place ? $vk_place : (string) get_post_meta( $vk_id, \Vandrekalender\Event::META_PLACE_NAME, true );
				$vk_line  = \Vandrekalender\Organiser_Walks::past_detail( $vk_place, \Vandrekalender\Organiser_Walks::distance_range( [ $vk_routes[ $vk_index ] ] ) );
				?>
				<li class="vk-past-tours__item">
					<?php if ( $vk_date ) : ?>
						<?php /* translators: card date with year, PHP date format. */ ?>
						<time class="vk-past-tours__date" datetime="<?php echo esc_attr( $vk_date ); ?>"><?php echo esc_html( wp_date( _x( 'D j M Y', 'event card date', 'vandrekalender-events' ), strtotime( $vk_date ), new DateTimeZone( 'UTC' ) ) ); ?></time>
					<?php endif; ?>
					<a class="vk-past-tours__title" href="<?php echo esc_url( get_permalink( $vk_id ) ); ?>"><?php echo esc_html( get_the_title( $vk_id ) ); ?></a>
					<?php if ( '' !== $vk_line ) : ?>
						<span class="vk-past-tours__detail"><?php echo esc_html( $vk_line ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $vk_walks && $vk_all_url ) : ?>
		<a class="vk-past-tours__all" href="<?php echo esc_url( $vk_all_url ); ?>"><?php esc_html_e( 'See more past walks', 'vandrekalender-events' ); ?> <span aria-hidden="true">›</span></a>
	<?php endif; ?>
</div>
