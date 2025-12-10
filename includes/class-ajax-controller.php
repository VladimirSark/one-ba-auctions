<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Ajax_Controller {

	private $engine;
	private $repo;
	private $credits;
	private $settings;

	public function __construct() {
		$this->engine  = new OBA_Auction_Engine();
		$this->repo    = new OBA_Auction_Repository();
		$this->credits = new OBA_Credits_Service();
		$this->settings = OBA_Settings::get_settings();
	}

	public function hooks() {
		add_action( 'wp_ajax_auction_get_state', array( $this, 'auction_get_state' ) );
		add_action( 'wp_ajax_nopriv_auction_get_state', array( $this, 'auction_get_state' ) );
		add_action( 'wp_ajax_auction_register_for_auction', array( $this, 'auction_register_for_auction' ) );
		add_action( 'wp_ajax_auction_place_bid', array( $this, 'auction_place_bid' ) );
		add_action( 'wp_ajax_auction_claim_prize', array( $this, 'auction_claim_prize' ) );
	}

	private function validate_nonce() {
		if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'oba_auction' ) ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_nonce',
					'message' => __( 'Invalid request.', 'one-ba-auctions' ),
				)
			);
		}
	}

	private function get_request_auction_id() {
		return isset( $_REQUEST['auction_id'] ) ? absint( $_REQUEST['auction_id'] ) : 0;
	}

	public function auction_get_state() {
		$this->validate_nonce();
		$auction_id = $this->get_request_auction_id();

		$this->engine->maybe_move_to_live( $auction_id );
		$this->engine->end_auction_if_expired( $auction_id );

		wp_send_json_success( $this->serialize_state( $auction_id ) );
	}

	public function auction_register_for_auction() {
		$this->validate_nonce();
		$auction_id = $this->get_request_auction_id();
		$user_id    = get_current_user_id();
		$accepted   = isset( $_POST['accepted_terms'] ) ? (int) $_POST['accepted_terms'] : 0;

		if ( ! empty( $this->settings['terms_text'] ) && ! $accepted ) {
			wp_send_json(
				array(
					'success' => false,
					'code'    => 'TERMS_REQUIRED',
					'message' => __( 'You must accept the terms to register.', 'one-ba-auctions' ),
				)
			);
		}

		$result = $this->engine->process_registration( $auction_id, $user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json(
				array(
					'success' => false,
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				)
			);
		}

		$meta    = $this->repo->get_auction_meta( $auction_id );

		$this->engine->enroll_participant( $auction_id, $user_id, 0 );
		$state                    = $this->serialize_state( $auction_id );
		$state['success_message'] = __( 'Registered for auction.', 'one-ba-auctions' );
		wp_send_json_success( $state );
	}

	public function auction_place_bid() {
		$this->validate_nonce();
		$auction_id = $this->get_request_auction_id();
		$user_id    = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json(
				array(
					'success' => false,
					'code'    => 'not_logged_in',
					'message' => __( 'You must be logged in to bid.', 'one-ba-auctions' ),
				)
			);
		}

		if ( isset( $_POST['force_end'] ) && current_user_can( 'manage_woocommerce' ) ) {
			$this->engine->calculate_winner_and_resolve_credits( $auction_id, 'admin_force_end' );
			wp_send_json_success( $this->serialize_state( $auction_id ) );
		}

		$result = $this->engine->process_bid( $auction_id, $user_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json(
				array(
					'success' => false,
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				)
			);
		}

		$state                    = $this->serialize_state( $auction_id );
		$state['success_message'] = __( 'Bid placed.', 'one-ba-auctions' );
		wp_send_json_success( $state );
	}

	public function auction_claim_prize() {
		$this->validate_nonce();
		$auction_id = $this->get_request_auction_id();
		$user_id    = get_current_user_id();

		if ( ! $user_id ) {
			wp_send_json_error(
				array(
					'code'    => 'not_logged_in',
					'message' => __( 'You must be logged in to claim.', 'one-ba-auctions' ),
				)
			);
		}

		$meta = $this->repo->get_auction_meta( $auction_id );
		if ( $meta['auction_status'] !== 'ended' ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_state',
					'message' => __( 'Auction has not ended yet.', 'one-ba-auctions' ),
				)
			);
		}

		$winner_row = $this->repo->get_winner_row( $auction_id );

		if ( ! $winner_row || (int) $winner_row['winner_user_id'] !== $user_id ) {
			wp_send_json_error(
				array(
					'code'    => 'not_winner',
					'message' => __( 'Only the winner can claim this prize.', 'one-ba-auctions' ),
				)
			);
		}

		if ( ! empty( $winner_row['wc_order_id'] ) ) {
			wp_send_json_error(
				array(
					'code'    => 'already_claimed',
					'message' => __( 'Prize already claimed.', 'one-ba-auctions' ),
				)
			);
		}

		$claim_price = (float) $winner_row['claim_price_credits'];
		$bid_count   = $this->repo->get_user_bids( $auction_id, $user_id );
		$meta        = $this->repo->get_auction_meta( $auction_id );
		$bid_fee     = $this->get_bid_fee_amount( $meta );

		$added = $this->add_claim_to_cart( $auction_id, $claim_price, $winner_row['id'], $meta['bid_product_id'], $bid_count, $bid_fee );
		if ( ! $added ) {
			wp_send_json_error(
				array(
					'code'    => 'add_to_cart_failed',
					'message' => __( 'Could not prepare checkout for this claim. Please try again.', 'one-ba-auctions' ),
				)
			);
		}

		if ( class_exists( 'OBA_Email' ) ) {
			$mailer = new OBA_Email();
			$mailer->notify_end_winner(
				$auction_id,
				$user_id,
				array(
					'claim_price' => $claim_price,
				)
			);
		}

		wp_send_json_success(
			array(
				'mode'         => 'checkout',
				'redirect_url' => wc_get_checkout_url(),
				'cart_url'     => wc_get_cart_url(),
			)
		);
	}

	private function serialize_state( $auction_id ) {
		$meta               = $this->repo->get_auction_meta( $auction_id );
		$user_id            = get_current_user_id();
		$is_registered      = $user_id ? $this->repo->is_user_registered( $auction_id, $user_id ) : false;
		$registration_pending = false;
		$claim_pending      = false;
		$participant_count  = $this->repo->get_participant_count( $auction_id );
		$user_bids          = $user_id ? $this->repo->get_user_bids( $auction_id, $user_id ) : 0;
		$history            = $this->repo->get_last_bids( $auction_id );
		$last_bidder        = $history ? $history[0]['user_id'] : null;
		$anon_name          = $last_bidder ? $this->mask_user_name( $last_bidder ) : null;
		$current_winner     = $this->repo->get_current_winner( $auction_id );
		$winner_row         = $this->repo->get_winner_row( $auction_id );
		$user_is_winning    = $user_id && $current_winner && $current_winner === $user_id;
		$auction_ended      = 'ended' === $meta['auction_status'];
		$total_bids_all     = $this->repo->get_total_bid_count( $auction_id );
		$bid_fee_amount     = $this->get_bid_fee_amount( $meta );
		$user_bid_total     = $user_bids * $bid_fee_amount;
		$registration_pending = $user_id ? $this->has_pending_registration_order( $auction_id, $user_id ) : false;

		if ( $winner_row ) {
			$current_winner  = (int) $winner_row['winner_user_id'];
			$user_is_winning = $user_id && $current_winner === $user_id;
		}

		return array(
			'status'                    => $meta['auction_status'],
			'lobby_percent'             => $this->engine->calculate_lobby_percent( $auction_id ),
			'user_registered'           => $is_registered,
			'registration_fee'          => $meta['registration_points'],
			'registration_fee_formatted'=> $meta['registration_points'],
			'registration_fee_plain'    => $meta['registration_points'],
			'bid_cost'                  => $this->get_bid_fee_amount( $meta ),
			'bid_cost_formatted'        => $this->get_bid_fee_formatted( $meta ),
			'bid_cost_plain'            => $this->get_bid_fee_plain( $meta ),
			'claim_price'               => 0,
			'required_participants'     => (int) $meta['required_participants'],
			'current_participants'      => $participant_count,
			'pre_live_seconds_left'     => $this->calculate_seconds_left( $meta['pre_live_start'], $meta['prelive_timer_seconds'] ),
			'pre_live_total'            => (int) $meta['prelive_timer_seconds'],
			'live_seconds_left'         => $this->calculate_seconds_left( $meta['live_expires_at'], $meta['live_timer_seconds'], true ),
			'live_total'                => (int) $meta['live_timer_seconds'],
			'user_bids_count'           => $user_bids,
			'user_cost'                 => $user_bid_total,
			'user_cost_formatted'       => $user_bids ? wc_price( $user_bid_total ) : '',
			'user_cost_plain'           => $user_bids ? wp_strip_all_tags( wc_price( $user_bid_total ) ) : '',
			'user_cost_num'             => $user_bid_total,
			'user_is_winning'           => $user_is_winning,
			'last_bidder_name'          => $anon_name,
			'history'                   => $this->map_history( $history ),
			'current_user_is_winner'    => $user_is_winning && $auction_ended,
			'claim_amount'              => ( $user_is_winning && $auction_ended ) ? ( $user_bids ? wp_strip_all_tags( wc_price( $user_bid_total ) ) : '' ) : '',
			'winner_stats'              => array(
				'bid_count'      => $user_bids,
				'bid_value'      => $user_bids * $bid_fee_amount,
				'bid_value_fmt'  => $user_bids ? wc_price( $user_bids * $bid_fee_amount ) : '',
				'bid_value_plain'=> $user_bids ? wp_strip_all_tags( wc_price( $user_bids * $bid_fee_amount ) ) : '',
				'bid_value_num'  => $user_bids * $bid_fee_amount,
			),
			'can_bid'                   => 'live' === $meta['auction_status'] && ! ( $user_id && $current_winner && $current_winner === $user_id ),
			'has_enough_credits'        => true,
			'has_enough_credits_to_claim' => true,
			'wc_order_id'               => $winner_row ? (int) $winner_row['wc_order_id'] : null,
			'is_admin'                  => current_user_can( 'manage_woocommerce' ),
			'error_message'             => '',
			'user_points_balance'       => $user_id ? ( new OBA_Points_Service() )->get_balance( $user_id ) : 0,
			'membership_active'         => $user_id ? $this->engine->user_has_membership( $user_id ) : false,
			'registration_unlocked'     => $is_registered || ( $user_id ? $this->engine->user_has_membership( $user_id ) : false ),
			'live_expires_at'           => $meta['live_expires_at'],
			'success_message'           => '',
			'total_bids_all'            => $total_bids_all,
			'total_bids_value'          => $total_bids_all * $bid_fee_amount,
			'total_bids_value_formatted'=> $total_bids_all ? wc_price( $total_bids_all * $bid_fee_amount ) : '',
			'total_bids_value_plain'    => $total_bids_all ? wp_strip_all_tags( wc_price( $total_bids_all * $bid_fee_amount ) ) : '',
			'registration_pending'      => $registration_pending,
			'claim_pending'             => $claim_pending,
		);
	}

	private function get_user_bid_value( $auction_id, $user_id, $formatted = false ) {
		$count = $this->repo->get_user_bids( $auction_id, $user_id );
		$meta  = $this->repo->get_auction_meta( $auction_id );
		$fee   = $this->get_bid_fee_amount( $meta );
		$total = $count * $fee;
		if ( $formatted ) {
			return $total ? wc_price( $total ) : '';
		}
		return $total;
	}

	private function has_pending_registration_order( $auction_id, $user_id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}
		$orders = wc_get_orders(
			array(
				'status'     => array( 'pending', 'on-hold', 'processing' ),
				'customer'   => $user_id,
				'meta_key'   => '_oba_is_registration_order',
				'meta_value' => 'yes',
				'limit'      => 20,
			)
		);
		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$aid = (int) $item->get_meta( '_oba_registration_auction_id', true );
				if ( $aid === (int) $auction_id ) {
					return true;
				}
			}
		}
		return false;
	}

	private function has_pending_claim_order( $auction_id, $user_id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}
		$orders = wc_get_orders(
			array(
				'status'     => array( 'pending', 'on-hold', 'processing' ),
				'customer'   => $user_id,
				'meta_key'   => '_oba_claim_auction_id',
				'meta_value' => $auction_id,
				'limit'      => 10,
			)
		);
		return ! empty( $orders );
	}

	private function get_bid_fee_amount( $meta ) {
		if ( empty( $meta['bid_product_id'] ) ) {
			return 0;
		}
		$product = wc_get_product( $meta['bid_product_id'] );
		if ( $product && '' !== $product->get_price() ) {
			return (float) $product->get_price();
		}
		return 0;
	}

	private function get_bid_fee_formatted( $meta ) {
		$amount = $this->get_bid_fee_amount( $meta );
		return $amount ? wc_price( $amount ) : '';
	}

	private function get_bid_fee_plain( $meta ) {
		$formatted = $this->get_bid_fee_formatted( $meta );
		return $formatted ? wp_strip_all_tags( $formatted ) : '';
	}

	private function calculate_seconds_left( $start_time, $duration, $absolute = false ) {
		if ( ! $start_time ) {
			return (int) $duration;
		}

		$start = strtotime( $start_time );

		$end = $absolute ? $start : $start + (int) $duration;

		return max( 0, $end - time() );
	}

	private function map_history( $rows ) {
		$history = array();

		foreach ( $rows as $row ) {
			$cost = (float) $row['credits_reserved'];
			$history[] = array(
				'time' => $row['created_at'],
				'name' => $this->mask_user_name( $row['user_id'] ),
				'total_bids_value' => $this->get_user_bid_value( $row['auction_id'], $row['user_id'] ),
				'total_bids_value_formatted' => $this->get_user_bid_value( $row['auction_id'], $row['user_id'], true ),
			);
		}

		return $history;
	}

	private function mask_user_name( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return __( 'Anon', 'one-ba-auctions' );
		}

		$name = $user->display_name ? $user->display_name : $user->user_login;

		return substr( $name, 0, 3 ) . '***' . substr( $user_id, -1 );
	}

	private function store_winner_order( $winner_row_id, $order_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_winners';
		$wpdb->update(
			$table,
			array( 'wc_order_id' => $order_id ),
			array( 'id' => $winner_row_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	private function create_order( $auction_id, $user_id, $line_price, $payment_method, $payment_title, $addresses = null ) {
		$order = wc_create_order(
			array(
				'customer_id' => $user_id,
			)
		);

		$product = wc_get_product( $auction_id );
		if ( $product ) {
			$item = new WC_Order_Item_Product();
			$item->set_product( $product );
			$item->set_quantity( 1 );
			$item->set_subtotal( $line_price );
			$item->set_total( $line_price );
			$order->add_item( $item );
		}

		if ( $payment_method ) {
			$order->set_payment_method( $payment_method );
		}
		if ( $payment_title ) {
			$order->set_payment_method_title( $payment_title );
		}
		$order->update_meta_data( '_oba_auction_id', $auction_id );
		$order->update_meta_data( '_oba_is_claim', 'yes' );

		if ( is_array( $addresses ) ) {
			if ( ! empty( $addresses['billing'] ) ) {
				$order->set_address( $addresses['billing'], 'billing' );
			}
			if ( ! empty( $addresses['shipping'] ) ) {
				$order->set_address( $addresses['shipping'], 'shipping' );
			} elseif ( ! empty( $addresses['billing'] ) ) {
				$order->set_address( $addresses['billing'], 'shipping' );
			}
		} else {
			$this->hydrate_user_addresses( $order, $user_id );
		}

		$order->calculate_totals( true );

		$order->save();

		return $order;
	}

	private function add_claim_to_cart( $auction_id, $claim_price, $winner_row_id, $bid_product_id = 0, $bid_qty = 0, $bid_price = 0 ) {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}
		if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( ! WC()->cart ) {
			return false;
		}

		WC()->cart->empty_cart();

		$allow_ids = array_filter( array( (int) $bid_product_id ) );
		$allow = function( $purchasable, $product ) use ( $allow_ids ) {
			if ( $product && in_array( (int) $product->get_id(), $allow_ids, true ) ) {
				return true;
			}
			return $purchasable;
		};
		add_filter( 'woocommerce_is_purchasable', $allow, 10, 2 );

		$added = false;
		$order_meta = array(
			'oba_is_claim'          => true,
			'oba_claim_price'       => $claim_price,
			'oba_claim_auction_id'  => $auction_id,
			'oba_winner_row_id'     => $winner_row_id,
		);

		if ( $bid_product_id && $bid_qty > 0 ) {
			$added_key = WC()->cart->add_to_cart(
				$bid_product_id,
				max( 1, (int) $bid_qty ),
				0,
				array(),
				array(
					'oba_is_claim'           => true,
					'oba_is_bid_fee'         => true,
					'oba_bid_fee_auction'    => $auction_id,
					'oba_bid_fee_total'      => $bid_price * $bid_qty,
					'oba_claim_price'        => $claim_price,
					'oba_claim_auction_id'   => $auction_id,
					'oba_winner_row_id'      => $winner_row_id,
				)
			);
			if ( $added_key && isset( WC()->cart->cart_contents[ $added_key ]['data'] ) && WC()->cart->cart_contents[ $added_key ]['data'] instanceof WC_Product ) {
				WC()->cart->cart_contents[ $added_key ]['data']->set_price( $bid_price );
			}
			$added = (bool) $added_key;
		}

		remove_filter( 'woocommerce_is_purchasable', $allow, 10 );

		return (bool) $added;
	}

	private function add_registration_to_cart( $product_id, $auction_id, $plan_id ) {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}
		if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		if ( ! WC()->cart ) {
			return false;
		}

		$allow = function( $purchasable, $product ) use ( $product_id ) {
			if ( $product && (int) $product->get_id() === (int) $product_id ) {
				return true;
			}
			return $purchasable;
		};
		add_filter( 'woocommerce_is_purchasable', $allow, 10, 2 );

		WC()->cart->add_to_cart(
			$product_id,
			1,
			0,
			array(),
			array(
				'oba_is_registration' => true,
				'oba_registration_auction_id' => $auction_id,
				'oba_registration_user_id' => get_current_user_id(),
			)
		);

		remove_filter( 'woocommerce_is_purchasable', $allow, 10 );

		return true;
	}

	private function hydrate_user_addresses( WC_Order $order, $user_id ) {
		$billing  = $this->get_address_from_user_meta( $user_id, 'billing' );
		$shipping = $this->get_address_from_user_meta( $user_id, 'shipping' );

		if ( empty( $billing ) || empty( $shipping ) ) {
			$last_order = $this->get_last_customer_order( $user_id );
			if ( $last_order ) {
				if ( empty( $billing ) ) {
					$billing = $last_order->get_address( 'billing' );
				}
				if ( empty( $shipping ) ) {
					$shipping = $last_order->get_address( 'shipping' );
				}
			}
		}

		if ( ! empty( $billing ) ) {
			$order->set_address( $billing, 'billing' );
		}
		if ( ! empty( $shipping ) ) {
			$order->set_address( $shipping, 'shipping' );
		} elseif ( ! empty( $billing ) ) {
			// Fallback: use billing for shipping if shipping is missing.
			$order->set_address( $billing, 'shipping' );
		}
	}

	private function get_address_from_user_meta( $user_id, $type = 'billing' ) {
		$fields = array(
			"{$type}_first_name",
			"{$type}_last_name",
			"{$type}_company",
			"{$type}_address_1",
			"{$type}_address_2",
			"{$type}_city",
			"{$type}_state",
			"{$type}_postcode",
			"{$type}_country",
		);
		if ( 'billing' === $type ) {
			$fields[] = 'billing_email';
			$fields[] = 'billing_phone';
		}

		$data = array();
		foreach ( $fields as $field ) {
			$value = get_user_meta( $user_id, $field, true );
			if ( $value ) {
				$data[ $field ] = $value;
			}
		}
		if ( 'billing' === $type && empty( $data['billing_email'] ) ) {
			$user = get_userdata( $user_id );
			if ( $user && $user->user_email ) {
				$data['billing_email'] = $user->user_email;
			}
		}
		return $data;
	}

	private function prepare_user_addresses( $user_id ) {
		$billing  = $this->get_address_from_user_meta( $user_id, 'billing' );
		$shipping = $this->get_address_from_user_meta( $user_id, 'shipping' );

		if ( empty( $billing ) || empty( $shipping ) ) {
			$last_order = $this->get_last_customer_order( $user_id );
			if ( $last_order ) {
				if ( empty( $billing ) ) {
					$billing = $this->normalize_address_keys( $last_order->get_address( 'billing' ), 'billing' );
				}
				if ( empty( $shipping ) ) {
					$shipping = $this->normalize_address_keys( $last_order->get_address( 'shipping' ), 'shipping' );
				}
			}
		}

		if ( ! $this->has_minimum_address( $billing ) ) {
			$billing = array();
		}
		if ( empty( $shipping ) && ! empty( $billing ) ) {
			$shipping = $billing;
		}
		if ( ! $this->has_minimum_address( $shipping ) ) {
			$shipping = array();
		}

		return array(
			'billing'  => $billing,
			'shipping' => $shipping,
		);
	}

	private function has_minimum_address( $address ) {
		if ( empty( $address ) || ! is_array( $address ) ) {
			return false;
		}
		$required = array( 'first_name', 'address_1', 'city', 'country' );
		$prefixed = array( 'billing_first_name', 'billing_address_1', 'billing_city', 'billing_country' );
		$prefixed_shipping = array( 'shipping_first_name', 'shipping_address_1', 'shipping_city', 'shipping_country' );

		// Normalize lookup to both unprefixed and prefixed keys.
		foreach ( $required as $key ) {
			if ( ! empty( $address[ $key ] ) ) {
				continue;
			}
			// Try prefixed billing.
			$billing_key = 'billing_' . $key;
			if ( ! empty( $address[ $billing_key ] ) ) {
				continue;
			}
			$shipping_key = 'shipping_' . $key;
			if ( ! empty( $address[ $shipping_key ] ) ) {
				continue;
			}
			return false;
		}
		return true;
	}

	private function normalize_address_keys( $address, $type ) {
		if ( empty( $address ) || ! is_array( $address ) ) {
			return array();
		}
		$mapped = array();
		$fields = array(
			'first_name',
			'last_name',
			'company',
			'address_1',
			'address_2',
			'city',
			'state',
			'postcode',
			'country',
			'phone',
			'email',
		);
		foreach ( $fields as $field ) {
			$key = "{$type}_{$field}";
			if ( isset( $address[ $field ] ) ) {
				$mapped[ $key ] = $address[ $field ];
			}
		}
		return $mapped;
	}

	private function get_last_customer_order( $user_id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}
		$orders = wc_get_orders(
			array(
				'customer' => $user_id,
				'limit'    => 1,
				'orderby'  => 'date',
				'order'    => 'DESC',
				'status'   => array( 'completed', 'processing', 'on-hold' ),
				'return'   => 'objects',
			)
		);
		if ( empty( $orders ) ) {
			return false;
		}
		return $orders[0];
	}

	private function get_edit_address_url() {
		$account_page = wc_get_page_permalink( 'myaccount' );
		if ( ! $account_page ) {
			return wp_login_url();
		}
		return wc_get_endpoint_url( 'edit-address', '', $account_page );
	}
}
