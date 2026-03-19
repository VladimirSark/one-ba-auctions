<?php
/**
 * Title: OBA Auction Landing
 * Slug: one-ba-auctions/oba-auction-landing
 * Categories: pages
 * Description: Two-column auction landing layout with Buy panel and Auction panel on the right.
 * Inserter: yes
 */

?>
<!-- wp:group {"layout":{"type":"constrained","wideSize":"1200px"},"style":{"spacing":{"padding":{"top":"20px","right":"20px","bottom":"20px","left":"20px"}}}} -->
<div class="wp-block-group" style="padding-top:20px;padding-right:20px;padding-bottom:20px;padding-left:20px"><!-- wp:columns {"align":"wide","style":{"spacing":{"columnGap":"16px"}}} -->
<div class="wp-block-columns alignwide" style="column-gap:16px"><!-- wp:column {"width":"60%","style":{"border":{"color":"#e2e8f0","radius":"12px","width":"1px"},"spacing":{"padding":{"top":"16px","right":"16px","bottom":"16px","left":"16px"},"blockGap":"12px"},"shadow":"0 10px 30px rgba(15, 23, 42, 0.08)"}} -->
<div class="wp-block-column" style="flex-basis:60%;border-width:1px;border-radius:12px;padding-top:16px;padding-right:16px;padding-bottom:16px;padding-left:16px"><!-- wp:group {"style":{"spacing":{"blockGap":"12px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:shortcode -->
[product_gallery]
<!-- /wp:shortcode -->

<!-- wp:shortcode -->
[product_tabs]
<!-- /wp:shortcode --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"35%","style":{"border":{"color":"#e2e8f0","radius":"12px","width":"1px"},"spacing":{"padding":{"top":"12px","right":"12px","bottom":"12px","left":"12px"},"blockGap":"10px"},"shadow":"0 10px 30px rgba(15, 23, 42, 0.08)"}} -->
<div class="wp-block-column" style="flex-basis:35%;border-width:1px;border-radius:12px;padding-top:12px;padding-right:12px;padding-bottom:12px;padding-left:12px"><!-- wp:group {"style":{"spacing":{"blockGap":"10px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:shortcode -->
[oba_auction]
<!-- /wp:shortcode --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
