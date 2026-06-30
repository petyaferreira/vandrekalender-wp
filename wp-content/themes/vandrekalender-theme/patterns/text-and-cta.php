<?php
/**
 * Title: Text and CTA
 * Slug: vandrekalender-theme/text-and-cta
 * Categories: featured
 * Description: A block pattern with a text and a call to action button.
 *
 * @package vandrekalender-theme
 */

?>

<!-- wp:group {"metadata":{"name":"Text and CTA"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|extra-large","bottom":"var:preset|spacing|extra-large"}}},"backgroundColor":"meadow","layout":{"type":"constrained"}} -->
<div
	class="wp-block-group alignfull has-meadow-background-color has-background"
	style="padding-top:var(--wp--preset--spacing--extra-large);padding-bottom:var(--wp--preset--spacing--extra-large)"
>
	<!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group alignwide">
	<!-- wp:group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:heading {"level":3} -->
		<h3 class="wp-block-heading">Vil du arrangere en tur?</h3>
		<!-- /wp:heading -->

		<!-- wp:paragraph -->
		<p>
		Det er gratis og åbent for alle. Opret din egen vandretur på få minutter
		og del den med vandrere i hele Danmark.
		</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:buttons -->
	<div class="wp-block-buttons">
		<!-- wp:button {"backgroundColor":"forest","textColor":"white","className":"","style":{"elements":{"link":{"color":{"text":"var:preset|color|white"}}}}} -->
		<div class="wp-block-button">
		<a
			class="wp-block-button__link has-white-color has-forest-background-color has-text-color has-background has-link-color wp-element-button"
			href="<?php echo esc_url( home_url( '/login' ) ); ?>"
			>Opret en begivenhed</a
		>
		</div>
		<!-- /wp:button -->
	</div>
	<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
