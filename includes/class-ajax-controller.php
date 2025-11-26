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
		$method     = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : 'credits';

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

		if ( 'credits' === $method ) {
			$balance = $this->credits->get_balance( $user_id );

			if ( $balance < $claim_price ) {
				wp_send_json(
					array(
						'success' => false,
						'code'    => 'INSUFFICIENT_CREDITS',
						'message' => __( 'Not enough credits to claim.', 'one-ba-auctions' ),
					)
				);
			}

			$this->credits->subtract_credits( $user_id, $claim_price );
			if ( class_exists( 'OBA_Ledger' ) ) {
				OBA_Ledger::record( $user_id, - $claim_price, $this->credits->get_balance( $user_id ), 'claim', $auction_id );
			}

			$order = $this->create_order( $auction_id, $user_id, 0, 'auction_credits', __( 'Auction credits', 'one-ba-auctions' ) );
			$order->update_meta_data( '_oba_paid_with_credits', $claim_price );
			$order->add_order_note( __( 'Paid with auction credits.', 'one-ba-auctions' ) );
			$order->payment_complete( 'auction_credits' );

			$this->store_winner_order( $winner_row['id'], $order->get_id() );
			OBA_Audit_Log::log(
				'auction_claim',
				array(
					'mode'        => 'credits',
					'wc_order_id' => $order->get_id(),
					'claim_price' => $claim_price,
					'winner_id'   => $user_id,
				),
				$auction_id
			);
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
					'mode'         => 'credits',
					'wc_order_id'  => $order->get_id(),
					'redirect_url' => $order->get_view_order_url(),
				)
			);
		}

		$order = $this->create_order( $auction_id, $user_id, $claim_price, 'auction_gateway', __( 'Auction gateway', 'one-ba-auctions' ) );

		$this->store_winner_order( $winner_row['id'], $order->get_id() );
		OBA_Audit_Log::log(
			'auction_claim',
			array(
				'mode'        => 'gateway',
				'wc_order_id' => $order->get_id(),
				'claim_price' => $claim_price,
				'winner_id'   => $user_id,
			),
			$auction_id
		);
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
				'mode'         => 'gateway',
				'wc_order_id'  => $order->get_id(),
				'redirect_url' => $order->get_checkout_payment_url(),
			)
		);
	}

	private function serialize_state( $auction_id ) {
		$meta               = $this->repo->get_auction_meta( $auction_id );
		$user_id            = get_current_user_id();
		$is_registered      = $user_id ? $this->repo->is_user_registered( $auction_id, $user_id ) : false;
		$participant_count  = $this->repo->get_participant_count( $auction_id );
		$user_bids          = $user_id ? $this->repo->get_user_bids( $auction_id, $user_id ) : 0;
		$history            = $this->repo->get_last_bids( $auction_id );
		$last_bidder        = $history ? $history[0]['user_id'] : null;
		$anon_name          = $last_bidder ? $this->mask_user_name( $last_bidder ) : null;
		$current_winner     = $this->repo->get_current_winner( $auction_id );
		$winner_row         = $this->repo->get_winner_row( $auction_id );
		$user_is_winning    = $user_id && $current_winner && $current_winner === $user_id;
		$auction_ended      = 'ended' === $meta['auction_status'];

		if ( $winner_row ) {
			$current_winner  = (int) $winner_row['winner_user_id'];
			$user_is_winning = $user_id && $current_winner === $user_id;
		}

		return array(
			'status'                    => $meta['auction_status'],
			'lobby_percent'             => $this->engine->calculate_lobby_percent( $auction_id ),
			'user_registered'           => $is_registered,
			'registration_fee'          => $meta['registration_fee_credits'],
			'bid_cost'                  => $meta['bid_cost_credits'],
			'claim_price'               => $meta['claim_price_credits'],
			'required_participants'     => (int) $meta['required_participants'],
			'current_participants'      => $participant_count,
			'pre_live_seconds_left'     => $this->calculate_seconds_left( $meta['pre_live_start'], $meta['prelive_timer_seconds'] ),
			'pre_live_total'            => (int) $meta['prelive_timer_seconds'],
			'live_seconds_left'         => $this->calculate_seconds_left( $meta['live_expires_at'], $meta['live_timer_seconds'], true ),
			'live_total'                => (int) $meta['live_timer_seconds'],
			'user_bids_count'           => $user_bids,
			'user_cost'                 => $user_bids * (float) $meta['bid_cost_credits'],
			'user_is_winning'           => $user_is_winning,
			'last_bidder_name'          => $anon_name,
			'history'                   => $this->map_history( $history ),
			'current_user_is_winner'    => $user_is_winning && $auction_ended,
			'claim_amount'              => $meta['claim_price_credits'],
			'can_bid'                   => 'live' === $meta['auction_status'] && ! ( $user_id && $current_winner && $current_winner === $user_id ),
			'has_enough_credits'        => $user_id ? $this->credits->get_balance( $user_id ) >= (float) $meta['bid_cost_credits'] : false,
			'has_enough_credits_to_claim' => $user_id ? $this->credits->get_balance( $user_id ) >= (float) $meta['claim_price_credits'] : false,
			'wc_order_id'               => $winner_row ? (int) $winner_row['wc_order_id'] : null,
			'is_admin'                  => current_user_can( 'manage_woocommerce' ),
			'error_message'             => '',
			'user_credits_balance'      => $user_id ? $this->credits->get_balance( $user_id ) : 0,
			'live_expires_at'           => $meta['live_expires_at'],
			'success_message'           => '',
		);
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
			$history[] = array(
				'time' => $row['created_at'],
				'name' => $this->mask_user_name( $row['user_id'] ),
				'cost' => (float) $row['credits_reserved'],
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

	private function create_order( $auction_id, $user_id, $line_price, $payment_method, $payment_title ) {
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

		$order->set_payment_method( $payment_method );
		$order->set_payment_method_title( $payment_title );
		$order->calculate_totals( true );

		$this->hydrate_user_addresses( $order, $user_id );

		$order->save();

		return $order;
	}

	private function hydrate_user_addresses( WC_Order $order, $user_id ) {
		$fields = array(
			'billing_first_name',
			'billing_last_name',
			'billing_email',
			'billing_phone',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_country',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_state',
			'shipping_postcode',
			'shipping_country',
		);

		$data = array();
		foreach ( $fields as $field ) {
			$value = get_user_meta( $user_id, $field, true );
			if ( $value ) {
				$data[ $field ] = $value;
			}
		}

		if ( isset( $data['billing_first_name'] ) ) {
			$order->set_address( $data, 'billing' );
		}
		if ( isset( $data['shipping_first_name'] ) ) {
			$order->set_address( $data, 'shipping' );
		}
	}
}
