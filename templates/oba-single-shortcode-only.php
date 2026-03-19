<?php
/**
 * Single auction product template that renders only the OBA shortcode layout.
 *
 * This bypasses all default WooCommerce single-product output for auction products.
 *
 * @var WC_Product $product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header( 'shop' );

// Ensure we have the current product.
global $product;
if ( ! ( $product instanceof WC_Product ) ) {
	$product = wc_get_product( get_queried_object_id() );
}

if ( $product instanceof WC_Product && 'auction' === $product->get_type() ) {
	// Render custom layout; shortcode auto-detects the current product.
	echo do_shortcode( '[oba_auction layout="custom"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} else {
	// Fallback: show nothing (should not happen, filter guards by type).
	echo '<div class="oba-auction-notice">Product not available.</div>';
}

get_footer( 'shop' );
