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
