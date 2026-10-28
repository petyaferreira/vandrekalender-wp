<?php
/**
 * Title: Organiser: about
 * Slug: vandrekalender-theme/organiser-about
 * Categories: featured
 * Post Types: organiser
 * Description: Two columns: Om arrangøren (text and the source line) beside Ofte stillede spørgsmål (questions and answers in an accordion).
 *
 * @package vandrekalender-theme
 */

?>
<!-- wp:group {"metadata":{"name":"Organiser about"},"align":"wide","templateLock":"contentOnly","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide"><!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|extra-large"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading -->
<h2 class="wp-block-heading">Om arrangøren</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"placeholder":"Hvem står bag, hvilke ture de arrangerer, og hvor. 150 til 250 ord."} -->
<p></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"placeholder":"Kilde: dvl.dk, hentet 19. oktober 2026.","textColor":"pebble","fontSize":"small"} -->
<p class="has-pebble-color has-text-color has-small-font-size"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading -->
<h2 class="wp-block-heading">Ofte stillede spørgsmål</h2>
<!-- /wp:heading -->

<!-- wp:accordion -->
<div role="group" class="wp-block-accordion"><!-- wp:accordion-item -->
<div class="wp-block-accordion-item"><!-- wp:accordion-heading -->
<h3 class="wp-block-accordion-heading has-icon has-icon-right"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">Hvordan tilmelder jeg mig en tur?</span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3>
<!-- /wp:accordion-heading -->

<!-- wp:accordion-panel -->
<div role="region" class="wp-block-accordion-panel"><!-- wp:paragraph -->
<p>Åbn turen og følg linket til arrangørens egen side. Nogle ture kræver ikke tilmelding.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:accordion-panel --></div>
<!-- /wp:accordion-item -->

<!-- wp:accordion-item -->
<div class="wp-block-accordion-item"><!-- wp:accordion-heading -->
<h3 class="wp-block-accordion-heading has-icon has-icon-right"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">Hvor finder jeg turene tættest på mig?</span><span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span></button></h3>
<!-- /wp:accordion-heading -->

<!-- wp:accordion-panel -->
<div role="region" class="wp-block-accordion-panel"><!-- wp:paragraph -->
<p>Brug regionsfilteret ved Kommende ture, eller vælg en region under Ture i hele landet.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:accordion-panel --></div>
<!-- /wp:accordion-item --></div>
<!-- /wp:accordion --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
