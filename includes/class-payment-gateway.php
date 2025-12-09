<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	// WooCommerce not active yet; avoid fatal.
	return;
}

class OBA_Credits_Gateway extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'oba_credits_gateway';
		$this->method_title       = __( 'Auction Credits', 'one-ba-auctions' );
		$this->method_description = __( 'Pay auction claim orders with your credits balance.', 'one-ba-auctions' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );
		$this->title              = __( 'Pay with credits', 'one-ba-auctions' );
		$this->enabled            = 'yes';

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'one-ba-auctions' ),
				'label'   => __( 'Enable Auction Credits payment', 'one-ba-auctions' ),
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			'title'   => array(
				'title'       => __( 'Title', 'one-ba-auctions' ),
				'type'        => 'text',
				'description' => __( 'Displayed at checkout', 'one-ba-auctions' ),
				'default'     => __( 'Pay with credits', 'one-ba-auctions' ),
			),
			'description' => array(
				'title'       => __( 'Description', 'one-ba-auctions' ),
				'type'        => 'textarea',
				'description' => __( 'Shown next to the payment option.', 'one-ba-auctions' ),
				'default'     => __( 'Use your auction credits to pay for the claim.', 'one-ba-auctions' ),
			),
		);
	}

	public function is_available() {
		if ( 'yes' !== $this->enabled || ! is_user_logged_in() ) {
			return false;
		}
		$credits = new OBA_Credits_Service();
		$balance = $credits->get_balance( get_current_user_id() );

		// Pay page flow.
		$order = $this->get_current_order();
		if ( $order ) {
			if ( 'yes' !== $order->get_meta( '_oba_is_claim' ) ) {
				return false;
			}
			if ( (int) $order->get_user_id() !== get_current_user_id() ) {
				return false;
			}
			$total = (float) $order->get_total();
			return $balance >= $total;
		}

		// Checkout flow with claim item in cart.
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$has_claim = false;
			$total     = 0;
			foreach ( WC()->cart->get_cart() as $item ) {
				if ( ! empty( $item['oba_is_claim'] ) ) {
					$has_claim = true;
				}
				if ( isset( $item['oba_claim_price'] ) ) {
					$total += (float) $item['oba_claim_price'] * ( isset( $item['quantity'] ) ? (int) $item['quantity'] : 1 );
				} elseif ( isset( $item['line_total'] ) ) {
					$total += (float) $item['line_total'];
				} elseif ( isset( $item['data'] ) && $item['data'] instanceof WC_Product ) {
					$total += (float) $item['data']->get_price() * ( isset( $item['quantity'] ) ? (int) $item['quantity'] : 1 );
				}
			}
			if ( ! $has_claim ) {
				return false;
			}
			return $balance >= $total;
		}

		return false;
	}

	public function process_payment( $order_id ) {
		$order   = wc_get_order( $order_id );
		$user_id = $order ? $order->get_user_id() : 0;
		if ( ! $order || ! $user_id ) {
			return array(
				'result'   => 'failure',
				'redirect' => '',
			);
		}

		$credits = new OBA_Credits_Service();
		$total   = (float) $order->get_total();
		$balance = $credits->get_balance( $user_id );

		if ( $balance < $total ) {
			wc_add_notice( __( 'Not enough credits to complete payment.', 'one-ba-auctions' ), 'error' );
			return array(
				'result'   => 'failure',
				'redirect' => '',
			);
		}

		$credits->subtract_credits( $user_id, $total );
		if ( class_exists( 'OBA_Ledger' ) ) {
			OBA_Ledger::record( $user_id, - $total, $credits->get_balance( $user_id ), 'claim_payment', $order_id );
		}

		$order->payment_complete( $this->id );
		$order->add_order_note( __( 'Paid with auction credits.', 'one-ba-auctions' ) );

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	private function get_current_order() {
		$order_id = absint( get_query_var( 'order-pay' ) );
		if ( ! $order_id ) {
			return false;
		}
		return wc_get_order( $order_id );
	}
}
