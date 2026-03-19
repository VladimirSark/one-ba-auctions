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
			<div class="oba-media-main">
				<?php
				// Render main image only.
				if ( function_exists( 'wc_get_gallery_image_html' ) && $product instanceof WC_Product ) {
					$attachment_ids = $product->get_gallery_image_ids();
					$main_id        = $product->get_image_id();
					if ( $main_id ) {
						echo wc_get_gallery_image_html( $main_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} elseif ( ! empty( $attachment_ids ) ) {
						echo wc_get_gallery_image_html( $attachment_ids[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					} else {
						echo '<div class="oba-sc-placeholder"></div>';
					}
				}
				?>
			</div>
			<div class="oba-media-thumbs">
				<?php
				if ( function_exists( 'wc_get_gallery_image_html' ) && $product instanceof WC_Product ) {
					$thumb_ids = $product->get_gallery_image_ids();
					if ( $product->get_image_id() ) {
						$thumb_ids = array_diff( $thumb_ids, array( $product->get_image_id() ) );
					}
					if ( ! empty( $thumb_ids ) ) {
						echo '<div class="oba-thumb-list">';
						foreach ( $thumb_ids as $tid ) {
							echo wc_get_gallery_image_html( $tid, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
			<div class="oba-sc-label">DETAILS</div>
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
		<div class="oba-sc-card oba-sc-buy">
			<div class="oba-sc-label">BUY</div>
			<div class="oba-buy-block">
				<h2 class="oba-buy-title"><?php echo esc_html( $product->get_title() ); ?></h2>
				<div class="oba-buy-price">
					<?php
					if ( function_exists( 'woocommerce_template_single_price' ) ) {
						woocommerce_template_single_price();
					} else {
						echo wp_kses_post( $product->get_price_html() );
					}
					?>
				</div>
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
		<div class="oba-sc-card oba-sc-auction">
			<div class="oba-sc-label">AUCTION</div>
			<?php
			// Reuse legacy auction UI inside the panel for full functionality.
			echo do_shortcode( '[oba_auction id="' . $product->get_id() . '" layout="legacy"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
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
</script>
