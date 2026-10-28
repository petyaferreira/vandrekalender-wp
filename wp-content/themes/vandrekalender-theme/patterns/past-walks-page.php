<?php
/**
 * Title: Past walks page
 * Slug: vandrekalender-theme/past-walks-page
 * Categories: featured
 * Post Types: page
 * Description: The whole "Tidligere vandreture" page (/tidligere-ture/): breadcrumbs, heading, the Past Walks block and the call to action. Use it on a page with the default template.
 *
 * @package vandrekalender-theme
 */

?>
<!-- wp:group {"metadata":{"name":"Past walks page"},"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|medium"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--medium)"><!-- wp:breadcrumbs {"fontSize":"small"} /-->

<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Tidligere vandreture</h1>
<!-- /wp:heading -->

<!-- wp:vandrekalender/past-walks /-->

<!-- wp:group {"className":"vk-past-walks-cta","style":{"spacing":{"padding":{"top":"var:preset|spacing|large","bottom":"var:preset|spacing|large","left":"var:preset|spacing|large","right":"var:preset|spacing|large"}},"border":{"width":"1px","radius":"12px"}},"backgroundColor":"cream","borderColor":"meadow","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group vk-past-walks-cta has-border-color has-meadow-border-color has-cream-background-color has-background" style="border-width:1px;border-radius:12px;padding-top:var(--wp--preset--spacing--large);padding-right:var(--wp--preset--spacing--large);padding-bottom:var(--wp--preset--spacing--large);padding-left:var(--wp--preset--spacing--large)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|small"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:heading -->
<h2 class="wp-block-heading">Lyst til at gå selv?</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Mange af de samme arrangører går igen hver uge eller hver måned. Se hvad der kommer.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"forest","textColor":"white","style":{"elements":{"link":{"color":{"text":"var:preset|color|white"}}}}} -->
<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-forest-background-color has-text-color has-background has-link-color wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>">Se kommende ture</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
