<?php
/**
 * Title: Organiser: walks
 * Slug: vandrekalender-theme/organiser-walks
 * Categories: featured
 * Post Types: organiser
 * Description: Kommende ture: the front page's filter bar, list, calendar and map, limited to the organiser.
 *
 * @package vandrekalender-theme
 */

?>
<!-- wp:group {"metadata":{"name":"Organiser walks"},"align":"wide","anchor":"kommende-ture","templateLock":"contentOnly","layout":{"type":"constrained"}} -->
<div id="kommende-ture" class="wp-block-group alignwide"><!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","verticalAlignment":"bottom"}} -->
<div class="wp-block-group alignwide"><!-- wp:heading -->
<h2 class="wp-block-heading">Kommende ture</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"pebble","fontSize":"small"} -->
<p class="has-pebble-color has-text-color has-small-font-size">[vk_filtered_count] ture</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:vandrekalender/event-filters {"align":"wide","className":"is-style-rbc-sm-align--full"} /-->

<!-- wp:vandrekalender/tabs {"activeTabId":"liste","align":"wide"} -->
<!-- wp:vandrekalender/tab {"id":"liste","label":"Liste"} -->
<!-- wp:vandrekalender/event-cards /-->
<!-- /wp:vandrekalender/tab -->

<!-- wp:vandrekalender/tab {"id":"kalender","label":"Kalender"} -->
<!-- wp:vandrekalender/event-calendar /-->
<!-- /wp:vandrekalender/tab -->

<!-- wp:vandrekalender/tab {"id":"kortvisning","label":"Kortvisning"} -->
<!-- wp:vandrekalender/event-map /-->
<!-- /wp:vandrekalender/tab -->
<!-- /wp:vandrekalender/tabs --></div>
<!-- /wp:group -->
