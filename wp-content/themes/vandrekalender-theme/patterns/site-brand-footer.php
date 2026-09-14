<?php
/**
 * Title: Site Brand (Footer)
 * Slug: vandrekalender-theme/site-brand-footer
 * Inserter: no
 * Description: The full badge including the wordmark, linked to the homepage. Used in the footer, where there is room to render it large enough for the arc text to read.
 *
 * @package Vandrekalender
 */

?>
<!-- wp:html -->
<a
	class="site-brand site-brand-footer"
	href="<?php echo esc_url( home_url( '/' ) ); ?>"
	rel="home"
	aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
>
	<?php
	/* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme-owned SVG file. */
	echo vandrekalender_inline_svg( 'assets/img/allevandreture-logo.svg' );
	?>
</a>
<!-- /wp:html -->
