<?php
/**
 * Title: Organiser: regions and past walks
 * Slug: vandrekalender-theme/organiser-regions-past
 * Categories: featured
 * Post Types: organiser
 * Description: Two columns: Ture i hele landet (the regions with the organiser's upcoming walks) beside Tidligere ture (its last past walks).
 *
 * @package vandrekalender-theme
 */

?>
<!-- wp:group {"metadata":{"name":"Organiser regions and past walks"},"align":"wide","templateLock":"contentOnly","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide"><!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|extra-large"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading -->
<h2 class="wp-block-heading">Ture i hele landet</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"placeholder":"Fx: Arrangøren har kommende ture i fire regioner. Vælg en region for at se turene i kalenderen."} -->
<p></p>
<!-- /wp:paragraph -->

<!-- wp:vandrekalender/organiser-regions /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading -->
<h2 class="wp-block-heading">Tidligere ture</h2>
<!-- /wp:heading -->

<!-- wp:vandrekalender/organiser-past-tours /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
