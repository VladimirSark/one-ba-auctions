<?php
/**
 * Minimal custom layout shell for [oba_auction] shortcode.
 *
 * Renders empty, styled containers so we can progressively fill them.
 *
 * @var WC_Product $product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="oba-shortcode-custom" data-auction-id="<?php echo esc_attr( $product->get_id() ); ?>">
	<div class="oba-sc-left">
		<div class="oba-sc-card oba-sc-gallery">
			<!-- gallery will go here -->
		</div>
		<div class="oba-sc-card oba-sc-info">
			<!-- description / additional info will go here -->
		</div>
	</div>
	<div class="oba-sc-right">
		<div class="oba-sc-card oba-sc-buy">
			<!-- buy panel -->
		</div>
		<div class="oba-sc-card oba-sc-auction">
			<!-- auction panel -->
		</div>
	</div>
</div>
