<?php
/**
 * Title: Site Brand
 * Slug: vandrekalender-theme/site-brand
 * Inserter: no
 * Description: The badge mark and the site name, together inside a single link to the homepage. Used in the header.
 *
 * @package Vandrekalender
 */

?>
<!-- wp:html -->
<a class="site-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
	<?php
	/* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme-owned SVG file. */
	echo vandrekalender_inline_svg( 'assets/img/allevandreture-mark.svg' );
	?>
	<span class="site-brand__name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
</a>
<!-- /wp:html -->
