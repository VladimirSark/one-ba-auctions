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
<?php
$gallery_ids = array();
if ( $product instanceof WC_Product ) {
	$main_id     = $product->get_image_id();
	$gallery_ids = $product->get_gallery_image_ids();
	// Ensure main image is first and unique.
	$ordered_ids = array_values( array_filter( array_unique( array_merge( array( $main_id ), $gallery_ids ) ) ) );
} else {
	$ordered_ids = array();
}
$gallery_urls = array();
foreach ( $ordered_ids as $oid ) {
	$url = wp_get_attachment_image_url( $oid, 'full' );
	if ( $url ) {
		$gallery_urls[] = $url;
	}
}
$gallery_json = esc_attr( wp_json_encode( $gallery_urls ) );
$main_id      = $ordered_ids[0] ?? 0;
?>
<div class="oba-shortcode-custom" data-auction-id="<?php echo esc_attr( $product->get_id() ); ?>">
	<div class="oba-sc-header oba-sc-card">
		<div class="oba-header-inline">
			<h1 class="oba-sc-title"><?php echo esc_html( $product->get_title() ); ?></h1>
			<div class="oba-buy-price">
				<?php
				$price_html = $product->get_price_html();
				echo '<span class="oba-price-pill"><span class="oba-price-prefix">' . esc_html__( 'Reguliari kaina:', 'one-ba-auctions' ) . '</span> ' . wp_kses_post( $price_html ) . '</span>';
				?>
			</div>
		</div>
	</div>
	<div class="oba-sc-left">
		<div class="oba-sc-card oba-sc-gallery" data-gallery="<?php echo $gallery_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">
			<div class="oba-media-main">
				<?php
				// Render main image only.
				if ( function_exists( 'wc_get_gallery_image_html' ) && $product instanceof WC_Product ) {
					if ( $main_id ) {
						echo wp_get_attachment_image( $main_id, 'large', false, array( 'class' => 'oba-main-image', 'data-index' => 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} elseif ( ! empty( $ordered_ids ) ) {
						echo wp_get_attachment_image( $ordered_ids[0], 'large', false, array( 'class' => 'oba-main-image', 'data-index' => 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						echo '<div class="oba-sc-placeholder"></div>';
					}
				}
				?>
			</div>
			<div class="oba-media-thumbs">
				<?php
				if ( ! empty( $ordered_ids ) ) {
					$thumb_ids = $ordered_ids;
					// Drop the first one (main) for thumbs.
					array_shift( $thumb_ids );
					if ( ! empty( $thumb_ids ) ) {
						echo '<div class="oba-thumb-list">';
						$thumb_index = 1;
						foreach ( $thumb_ids as $tid ) {
							echo wp_get_attachment_image( $tid, 'thumbnail', false, array( 'class' => 'oba-thumb-image', 'data-index' => $thumb_index ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							$thumb_index++;
						}
						echo '</div>';
					} else {
						echo '<div class="oba-sc-placeholder thumb-placeholder"></div>';
					}
				}
				?>
			</div>
		</div>
		<div class="oba-sc-card oba-sc-info">
			<div class="oba-details-tabs">
				<ul class="oba-tabs-nav">
					<li class="is-active" data-tab="description"><?php esc_html_e( 'Description', 'one-ba-auctions' ); ?></li>
					<li data-tab="additional"><?php esc_html_e( 'Additional Information', 'one-ba-auctions' ); ?></li>
					<li data-tab="reviews"><?php esc_html_e( 'Reviews', 'one-ba-auctions' ); ?></li>
				</ul>
				<div class="oba-tabs-body">
					<div class="oba-tab-panel is-active" data-tab="description">
						<?php
						$desc = apply_filters( 'the_content', $product->get_description() );
						echo $desc ? $desc : '<p>' . esc_html__( 'No description available.', 'one-ba-auctions' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</div>
					<div class="oba-tab-panel" data-tab="additional">
						<?php
						// WooCommerce attributes/weight table
						if ( function_exists( 'woocommerce_product_additional_information_tab' ) ) {
							ob_start();
							remove_all_actions( 'woocommerce_product_additional_information_heading' );
							woocommerce_product_additional_information_tab();
							$additional = ob_get_clean();
							echo $additional; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} else {
							echo '<p>' . esc_html__( 'No additional information.', 'one-ba-auctions' ) . '</p>';
						}
						?>
					</div>
					<div class="oba-tab-panel" data-tab="reviews">
						<?php
						if ( comments_open( $product->get_id() ) || get_comments_number( $product->get_id() ) ) {
							ob_start();
							add_filter( 'woocommerce_product_reviews_heading', '__return_empty_string' );
							comments_template();
							remove_filter( 'woocommerce_product_reviews_heading', '__return_empty_string' );
							$reviews = ob_get_clean();
							echo $reviews; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} else {
							echo '<p>' . esc_html__( 'Reviews are closed.', 'one-ba-auctions' ) . '</p>';
						}
						?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="oba-sc-right">
		<div class="oba-sc-card oba-sc-auction">
			<?php
			// Reuse legacy auction UI inside the panel for full functionality.
			echo do_shortcode( '[oba_auction id="' . $product->get_id() . '" layout="legacy"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
		<div class="oba-sc-divider inline"><span><?php esc_html_e( 'or', 'one-ba-auctions' ); ?></span></div>
		<div class="oba-sc-card oba-sc-buy">
			<div class="oba-buy-block">
				<div class="oba-buy-form">
					<?php
					if ( function_exists( 'woocommerce_template_single_add_to_cart' ) ) {
						woocommerce_template_single_add_to_cart();
					} else {
						do_action( 'woocommerce_simple_add_to_cart' );
					}
					?>
				</div>
				<?php
				$pts = (int) $product->get_meta( '_oba_buy_now_points' );
				if ( $pts > 0 ) :
					?>
					<div class="oba-buy-points-line">
						<?php printf( esc_html__( 'Earn %d pts with this purchase.', 'one-ba-auctions' ), $pts ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
	const navItems = document.querySelectorAll('.oba-tabs-nav li');
	const panels = document.querySelectorAll('.oba-tab-panel');
	if (!navItems.length || !panels.length) return;
	navItems.forEach(item => {
		item.addEventListener('click', () => {
			const tab = item.getAttribute('data-tab');
			navItems.forEach(li => li.classList.remove('is-active'));
			panels.forEach(p => p.classList.remove('is-active'));
			item.classList.add('is-active');
			const active = document.querySelector('.oba-tab-panel[data-tab="'+tab+'"]');
			if (active) active.classList.add('is-active');
		});
	});
});

// Change button text to "Buy it now" inside buy panel
document.addEventListener('DOMContentLoaded', function(){
	const btn = document.querySelector('.oba-shortcode-custom .oba-sc-buy .single_add_to_cart_button');
	if(btn){
		btn.textContent = btn.textContent.replace(/add to cart/i,'Buy it now');
	}
});
</script>
