<?php
/**
 * Custom single product layout for auction products.
 * Layout:
 *  - Title full width
 *  - Row 1: two columns (left: gallery, right: buy-it-now card)
 *  - Row 2: full-width auction block via shortcode [oba_auction]
 */

defined( 'ABSPATH' ) || exit;

global $product;
if ( ! $product || 'auction' !== $product->get_type() ) {
	wc_get_template( 'single-product.php' );
	return;
}

get_header( 'shop' );

?>
<div class="oba-auction-single">
	<div class="oba-auction-title">
		<?php the_title( '<h1 class="product_title entry-title">', '</h1>' ); ?>
	</div>

	<div class="oba-auction-row oba-auction-top">
		<div class="oba-auction-col oba-auction-media">
			<?php
			/**
			 * WooCommerce hook: product images
			 */
			do_action( 'woocommerce_before_single_product_summary' );
			?>
		</div>
		<div class="oba-auction-col oba-auction-buy">
			<?php
			// Price + add to cart (buy now).
			woocommerce_template_single_price();
			woocommerce_simple_add_to_cart();

			// Points granted info, if set.
			$buy_points = (int) $product->get_meta( '_oba_buy_now_points' );
			if ( $buy_points > 0 ) :
				?>
				<p class="oba-buy-points-hint">
					<?php
					printf(
						esc_html__( 'Earn %d points with this purchase.', 'one-ba-auctions' ),
						$buy_points
					);
					?>
				</p>
			<?php endif; ?>

			<div class="product_meta">
				<?php do_action( 'woocommerce_product_meta_start' ); ?>
				<?php do_action( 'woocommerce_product_meta_end' ); ?>
			</div>
		</div>
	</div>

	<div class="oba-auction-row oba-auction-bottom">
		<div class="oba-auction-col-full">
			<?php echo do_shortcode( '[oba_auction]' ); ?>
		</div>
	</div>

	<div class="oba-auction-row oba-auction-desc">
		<div class="oba-auction-col-full">
			<?php
			// Product description.
			the_content();
			?>
		</div>
	</div>
</div>

<?php get_footer( 'shop' ); ?>

<style>
.oba-auction-single{max-width:1200px;margin:0 auto;padding:16px;}
.oba-auction-title h1{margin:0 0 16px;font-size:28px;font-weight:800;}
.oba-auction-row{display:flex;flex-wrap:wrap;gap:20px;margin-bottom:20px;}
.oba-auction-col{flex:1 1 320px;min-width:0;}
.oba-auction-col-full{flex:1 1 100%;}
.oba-auction-media .woocommerce-product-gallery{margin:0;}
.oba-auction-buy{border:1px solid #e5e7eb;border-radius:12px;padding:16px;background:#fff;box-shadow:0 10px 24px rgba(15,23,42,0.05);}
.oba-auction-buy .price{font-size:26px;font-weight:800;margin-bottom:12px;display:block;}
.oba-buy-points-hint{margin-top:8px;font-weight:600;color:#0f172a;}
.oba-auction-bottom .oba-auction-col-full{width:100%;}
@media (max-width:768px){
	.oba-auction-row{flex-direction:column;}
}
</style>
