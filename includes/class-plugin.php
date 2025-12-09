<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once OBA_PLUGIN_DIR . 'includes/class-settings.php';
require_once OBA_PLUGIN_DIR . 'includes/class-credits-service.php';
require_once OBA_PLUGIN_DIR . 'includes/class-points-service.php';
require_once OBA_PLUGIN_DIR . 'includes/class-auction-repository.php';
require_once OBA_PLUGIN_DIR . 'includes/class-auction-engine.php';
require_once OBA_PLUGIN_DIR . 'includes/class-product-type.php';
require_once OBA_PLUGIN_DIR . 'includes/class-ajax-controller.php';
require_once OBA_PLUGIN_DIR . 'includes/class-frontend.php';
require_once OBA_PLUGIN_DIR . 'includes/class-credits-order-integration.php';
require_once OBA_PLUGIN_DIR . 'includes/class-points-order-integration.php';
require_once OBA_PLUGIN_DIR . 'includes/class-audit.php';
require_once OBA_PLUGIN_DIR . 'includes/class-ledger.php';
require_once OBA_PLUGIN_DIR . 'includes/class-email.php';
require_once OBA_PLUGIN_DIR . 'includes/class-admin.php';
require_once OBA_PLUGIN_DIR . 'includes/class-payment-gateway.php';
require_once OBA_PLUGIN_DIR . 'includes/class-claim-checkout.php';

class OBA_Plugin {

	public function init() {
		OBA_Activator::maybe_upgrade();
		$this->register_hooks();
	}

	private function register_hooks() {
		$product_type = new OBA_Product_Type();
		$product_type->hooks();

		$credits_integration = new OBA_Credits_Order_Integration();
		$credits_integration->hooks();
		$points_integration = new OBA_Points_Order_Integration();
		$points_integration->hooks();

		$ajax = new OBA_Ajax_Controller();
		$ajax->hooks();

		$frontend = new OBA_Frontend();
		$frontend->hooks();

		$claim_checkout = new OBA_Claim_Checkout();
		$claim_checkout->hooks();

		if ( is_admin() ) {
			$admin = new OBA_Admin();
			$admin->hooks();
		}

		add_action( 'oba_run_expiry_check', array( $this, 'check_expired_auctions' ) );
		add_action( 'wp', array( $this, 'maybe_schedule_cron' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'ensure_claim_gateway_available' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'claim_address_prompt' ), 5 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'output_checkout_notices' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_helpers' ) );
	}

	public function check_expired_auctions() {
		$engine = new OBA_Auction_Engine();
		$repo   = new OBA_Auction_Repository();

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => 20,
			'meta_query'     => array(
				array(
					'key'   => '_product_type',
					'value' => 'auction',
				),
				array(
					'key'   => '_auction_status',
					'value' => 'live',
				),
			),
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		$q = new WP_Query( $args );
		if ( $q->have_posts() ) {
			foreach ( $q->posts as $auction_id ) {
				$engine->end_auction_if_expired( $auction_id );
			}
		}
	}

	public function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( 'oba_run_expiry_check' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'minute', 'oba_run_expiry_check' );
		}
	}

	public function register_gateway( $gateways ) {
		$gateways[] = 'OBA_Credits_Gateway';
		return $gateways;
	}

	public function ensure_claim_gateway_available( $gateways ) {
		if ( is_admin() ) {
			return $gateways;
		}

		$order_id = absint( get_query_var( 'order-pay' ) );
		if ( ! $order_id && isset( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$key = sanitize_text_field( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$order_id = wc_get_order_id_by_order_key( $key );
		}
		if ( ! $order_id ) {
			return $gateways;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || 'yes' !== $order->get_meta( '_oba_is_claim' ) ) {
			return $gateways;
		}

		if ( ! isset( $gateways['oba_credits_gateway'] ) && class_exists( 'OBA_Credits_Gateway' ) ) {
			$gateways['oba_credits_gateway'] = new OBA_Credits_Gateway();
		}

		return $gateways;
	}

	public function claim_address_prompt() {
		if ( ! is_checkout_pay_page() ) {
			return;
		}
		$order_id = absint( get_query_var( 'order-pay' ) );
		if ( ! $order_id && isset( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$key      = sanitize_text_field( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$order_id = wc_get_order_id_by_order_key( $key );
		}
		if ( ! $order_id ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || 'yes' !== $order->get_meta( '_oba_is_claim' ) ) {
			return;
		}

		$address_url = $this->get_edit_address_url();
		$message     = __( 'Please confirm your billing and shipping address below. Update if needed before you pay.', 'one-ba-auctions' );
		$link_label  = __( 'Edit address', 'one-ba-auctions' );
		wc_print_notice(
			wp_kses_post(
				sprintf(
					'%s <a class="button" href="%s">%s</a>',
					$message,
					esc_url( $address_url ),
					$link_label
				)
			),
			'notice'
		);
	}

	private function get_edit_address_url() {
		$account_page = wc_get_page_permalink( 'myaccount' );
		if ( ! $account_page ) {
			return wp_login_url();
		}
		return wc_get_endpoint_url( 'edit-address', '', $account_page );
	}

	public function output_checkout_notices() {
		if ( function_exists( 'wc_print_notices' ) ) {
			wc_print_notices();
		}
	}

	public function enqueue_checkout_helpers() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		if ( wp_script_is( 'wc-checkout', 'registered' ) ) {
			$script = "
			jQuery(function($){
				function scrollToNotices(){
					var wrap = $('.woocommerce-notices-wrapper, .woocommerce-error, .woocommerce-info').first();
					if (wrap.length){
						$('html, body').animate({scrollTop: wrap.offset().top - 40}, 200);
					}
				}
				scrollToNotices();
				$(document.body).on('checkout_error', scrollToNotices);
			});";
			wp_add_inline_script( 'wc-checkout', $script );
		}
	}
}
