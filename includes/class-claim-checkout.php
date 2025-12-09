<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Claim_Checkout {

	public function hooks() {
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'force_claim_price' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_item_meta' ), 10, 4 );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'store_winner_order' ), 10, 3 );
	}

	public function force_claim_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		if ( ! $cart || empty( $cart->get_cart() ) ) {
			return;
		}
		// Allow claim items to be treated as purchasable during checkout.
		add_filter( 'woocommerce_is_purchasable', array( $this, 'allow_purchasable_claim' ), 10, 2 );
		foreach ( $cart->get_cart() as $cart_item_key => $item ) {
			if ( ! empty( $item['oba_is_claim'] ) && isset( $item['oba_claim_price'] ) ) {
				if ( isset( $item['data'] ) && $item['data'] instanceof WC_Product ) {
					$item['data']->set_price( (float) $item['oba_claim_price'] );
				}
			}
			if ( ! empty( $item['oba_is_bid_fee'] ) && isset( $item['oba_bid_fee_total'] ) ) {
				$qty  = isset( $item['quantity'] ) ? max( 1, (int) $item['quantity'] ) : 1;
				$unit = (float) $item['oba_bid_fee_total'] / $qty;
				if ( isset( $item['data'] ) && $item['data'] instanceof WC_Product ) {
					$item['data']->set_price( $unit );
				}
			}
		}
	}

	public function allow_purchasable_claim( $purchasable, $product ) {
		if ( ! $product instanceof WC_Product ) {
			return $purchasable;
		}
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $purchasable;
		}
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! empty( $item['oba_is_claim'] ) && isset( $item['product_id'] ) && (int) $item['product_id'] === (int) $product->get_id() ) {
				return true;
			}
		}
		return $purchasable;
	}

	public function add_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['oba_is_claim'] ) ) {
			// Handle registration items as well.
			if ( ! empty( $values['oba_is_registration'] ) ) {
				$item->add_meta_data( '_oba_is_registration', 'yes', true );
				if ( isset( $values['oba_registration_auction_id'] ) ) {
					$item->add_meta_data( '_oba_registration_auction_id', (int) $values['oba_registration_auction_id'], true );
				}
				if ( isset( $values['oba_registration_user_id'] ) ) {
					$item->add_meta_data( '_oba_registration_user_id', (int) $values['oba_registration_user_id'], true );
				}
				// Mark order as registration order so pending can be detected.
				$order->update_meta_data( '_oba_is_registration_order', 'yes' );
				$order->update_meta_data( '_oba_registration_user_id', $order->get_user_id() );
			}
			return;
		}
		$item->add_meta_data( '_oba_is_claim', 'yes', true );
		if ( isset( $values['oba_claim_price'] ) ) {
			$item->add_meta_data( '_oba_claim_price', (float) $values['oba_claim_price'], true );
		}
		if ( isset( $values['oba_claim_auction_id'] ) ) {
			$item->add_meta_data( '_oba_claim_auction_id', (int) $values['oba_claim_auction_id'], true );
		}
		if ( isset( $values['oba_winner_row_id'] ) ) {
			$item->add_meta_data( '_oba_winner_row_id', (int) $values['oba_winner_row_id'], true );
		}
		$order->update_meta_data( '_oba_is_claim', 'yes' );
		if ( isset( $values['oba_claim_auction_id'] ) ) {
			$order->update_meta_data( '_oba_auction_id', (int) $values['oba_claim_auction_id'] );
		}
		if ( isset( $values['oba_winner_row_id'] ) ) {
			$order->update_meta_data( '_oba_winner_row_id', (int) $values['oba_winner_row_id'] );
		}
	}

	public function store_winner_order( $order_id, $posted, $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		remove_filter( 'woocommerce_is_purchasable', array( $this, 'allow_purchasable_claim' ), 10 );
		if ( 'yes' !== $order->get_meta( '_oba_is_claim' ) ) {
			return;
		}
		$auction_id = (int) $order->get_meta( '_oba_auction_id' );
		if ( ! $auction_id ) {
			foreach ( $order->get_items() as $item ) {
				$auction_id = (int) $item->get_meta( '_oba_claim_auction_id', true );
				if ( $auction_id ) {
					break;
				}
			}
		}
		if ( ! $auction_id ) {
			return;
		}
		$winner_row_id = (int) $order->get_meta( '_oba_winner_row_id' );
		$repo          = new OBA_Auction_Repository();
		$winner_row    = $winner_row_id ? $repo->get_winner_row( $auction_id ) : $repo->get_winner_row( $auction_id );
		$user_id       = $order->get_user_id();
		if ( ! $winner_row || (int) $winner_row['winner_user_id'] !== (int) $user_id ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'auction_winners';
		if ( empty( $winner_row['wc_order_id'] ) ) {
			$wpdb->update(
				$table,
				array( 'wc_order_id' => $order_id ),
				array( 'id' => $winner_row['id'] ),
				array( '%d' ),
				array( '%d' )
			);
		}

		OBA_Audit_Log::log(
			'auction_claim',
			array(
				'mode'        => 'checkout',
				'wc_order_id' => $order_id,
				'claim_price' => $order->get_total(),
				'winner_id'   => $user_id,
			),
			$auction_id
		);
	}
}
