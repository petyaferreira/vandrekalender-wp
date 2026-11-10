<?php
/**
 * Title: Page hero: text and image
 * Slug: vandrekalender-theme/page-hero
 * Categories: allevandreture
 * Description: Top of a page on the "Page (without title)" template: the H1, an intro and a link beside a photo. The photo follows the height of the text (cropped); on phones it is a short strip above the text.
 *
 * @package vandrekalender-theme
 */

?>
<!-- wp:group {"templateLock":"contentOnly","metadata":{"name":"Page hero"},"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|medium"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--medium)"><!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|extra-large"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"className":"vk-page-hero__text"} -->
<div class="wp-block-column vk-page-hero__text"><!-- wp:heading {"level":1,"placeholder":"Sidens titel"} -->
<h1 class="wp-block-heading"></h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"placeholder":"Tre eller fire sætninger om, hvad siden byder på."} -->
<p></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"placeholder":"Et link videre, for eksempel Se kommende ture ›"} -->
<p></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"className":"vk-page-hero__media"} -->
<div class="wp-block-column vk-page-hero__media"><!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"vk-page-hero__image"} -->
<figure class="wp-block-image size-large vk-page-hero__image"><img alt=""/></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
