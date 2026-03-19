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
	<div class="oba-sc-header oba-sc-card">
		<div class="oba-sc-label">HEADER</div>
		<h1 class="oba-sc-title"><?php echo esc_html( $product->get_title() ); ?></h1>
	</div>
	<div class="oba-sc-left">
		<div class="oba-sc-card oba-sc-gallery">
			<div class="oba-sc-label">MEDIA</div>
			<?php
			// WooCommerce product images (main + thumbnails)
			if ( function_exists( 'woocommerce_show_product_images' ) ) {
				ob_start();
				woocommerce_show_product_images();
				$gallery = ob_get_clean();
				echo $gallery; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} else {
				echo '<div class="oba-sc-placeholder"></div>'; // fallback
			}
			?>
		</div>
		<div class="oba-sc-card oba-sc-info">
			<div class="oba-sc-label">DETAILS</div>
			<div class="oba-sc-placeholder"></div>
		</div>
	</div>
	<div class="oba-sc-right">
		<div class="oba-sc-card oba-sc-buy">
			<div class="oba-sc-label">BUY</div>
			<div class="oba-sc-placeholder"></div>
		</div>
		<div class="oba-sc-card oba-sc-auction">
			<div class="oba-sc-label">AUCTION</div>
			<div class="oba-sc-placeholder"></div>
		</div>
	</div>
</div>
