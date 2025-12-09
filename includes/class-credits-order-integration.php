<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Credits_Order_Integration {

	private $credits;

	public function __construct() {
		$this->credits = new OBA_Credits_Service();
	}

	public function hooks() {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_fields' ) );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'show_meta_on_order' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'flag_registration_on_create' ), 10, 2 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_tabs' ) );
		add_action( 'woocommerce_account_oba-credits_endpoint', array( $this, 'render_account_endpoint' ) );
		add_action( 'woocommerce_account_oba-registrations_endpoint', array( $this, 'render_registrations_endpoint' ) );
		add_action( 'init', array( $this, 'register_endpoint' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_order_badge' ), 10, 2 );
	}

	public function render_fields() {
		echo '<div class="options_group show_if_simple show_if_virtual">';

		woocommerce_wp_checkbox(
			array(
				'id'          => '_is_bid_product',
				'label'       => __( 'Is bid fee product', 'one-ba-auctions' ),
				'description' => __( 'Mark this product to be used as the bid fee.', 'one-ba-auctions' ),
			)
		);

		echo '</div>';
	}

	public function save_fields( $product_id ) {
		$is_bid          = isset( $_POST['_is_bid_product'] ) ? 'yes' : 'no';
		update_post_meta( $product_id, '_is_bid_product', $is_bid );
	}

	public function register_endpoint() {
		add_rewrite_endpoint( 'oba-credits', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'oba-registrations', EP_ROOT | EP_PAGES );
	}

	public function grant_membership_on_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$is_member  = get_post_meta( $product_id, '_is_membership_plan', true );
			$limit      = (int) get_post_meta( $product_id, '_membership_limit', true );
			if ( 'yes' !== $is_member || $limit <= 0 ) {
				continue;
			}
			$this->grant_membership_slots( $user_id, $product_id, $limit );
		}
	}

	public function flag_registration_on_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}
		$is_reg_order = false;
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$is_reg     = get_post_meta( $product_id, '_is_registration_product', true );
			if ( 'yes' === $is_reg ) {
				$is_reg_order = true;
				$item->add_meta_data( '_oba_registration_user_id', $user_id, true );
				break;
			}
		}
		if ( $is_reg_order ) {
			$order->update_meta_data( '_oba_is_registration_order', 'yes' );
			$order->update_meta_data( '_oba_registration_user_id', $user_id );
			$order->save();
		}
	}

	public function flag_registration_on_create( $order, $data ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$cart = function_exists( 'WC' ) ? WC()->cart : null;
		if ( ! $cart ) {
			return;
		}
		$has_reg = false;
		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item['oba_is_registration'] ) ) {
				$has_reg = true;
				if ( isset( $item['oba_registration_user_id'] ) ) {
					$order->update_meta_data( '_oba_registration_user_id', (int) $item['oba_registration_user_id'] );
				}
				if ( isset( $item['oba_registration_auction_id'] ) ) {
					$order->update_meta_data( '_oba_registration_auction_id', (int) $item['oba_registration_auction_id'] );
				}
			}
		}
		if ( $has_reg ) {
			$order->update_meta_data( '_oba_is_registration_order', 'yes' );
			// Notify user registration started/pending.
			if ( class_exists( 'OBA_Email' ) ) {
				$mailer = new OBA_Email();
				$mailer->notify_registration_pending(
					$order->get_user_id(),
					array(
						'auction_id' => $order->get_meta( '_oba_registration_auction_id' ),
						'order_id'   => $order->get_id(),
					)
				);
			}
		}
	}

	public function process_registration_on_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		$engine = new OBA_Auction_Engine();

		foreach ( $order->get_items() as $item ) {
			$is_reg = $item->get_meta( '_oba_is_registration', true );
			if ( 'yes' !== $is_reg ) {
				continue;
			}
			$auction_id = (int) $item->get_meta( '_oba_registration_auction_id', true );
			$item->add_meta_data( '_oba_registration_user_id', $user_id, true );
			$engine->enroll_participant( $auction_id, $user_id, 0 );
		}
		$order->update_meta_data( '_oba_registration_user_id', $user_id );
		$order->save();

		if ( class_exists( 'OBA_Email' ) ) {
			$mailer = new OBA_Email();
			$mailer->notify_registration_approved(
				$user_id,
				array(
					'auction_id' => $order->get_meta( '_oba_registration_auction_id' ),
					'order_id'   => $order_id,
				)
			);
		}
	}

	public function add_account_tabs( $items ) {
		$items['oba-registrations'] = __( 'My Auctions', 'one-ba-auctions' );
		$items['oba-credits']       = __( 'My Credits', 'one-ba-auctions' );
		return $items;
	}

	public function render_account_endpoint() {
		$balance = $this->credits->get_balance( get_current_user_id() );

		echo '<h3>' . esc_html__( 'My Credits', 'one-ba-auctions' ) . '</h3>';
		echo '<p>' . sprintf( esc_html__( 'Your current balance: %s credits', 'one-ba-auctions' ), esc_html( $balance ) ) . '</p>';
	}

	public function render_registrations_endpoint() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			echo esc_html__( 'Please log in.', 'one-ba-auctions' );
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'auction_participants';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT auction_id, status, registered_at FROM {$table} WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);
		echo '<h3>' . esc_html__( 'My Auctions', 'one-ba-auctions' ) . '</h3>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No auctions yet.', 'one-ba-auctions' ) . '</p>';
			return;
		}
		echo '<table class="shop_table shop_table_responsive my_account_orders">';
		echo '<thead><tr><th>' . esc_html__( 'Auction', 'one-ba-auctions' ) . '</th><th>' . esc_html__( 'Status', 'one-ba-auctions' ) . '</th><th>' . esc_html__( 'Registered', 'one-ba-auctions' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$aid    = (int) $row['auction_id'];
			$meta_status = get_post_meta( $aid, '_auction_status', true ) ?: 'registration';
			$label  = $this->map_status_label( $meta_status );
			$link   = get_permalink( $aid );
			$title  = get_the_title( $aid );
			echo '<tr>';
			echo '<td>' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . '</td>';
			echo '<td><span class="oba-badge ' . esc_attr( $meta_status ) . '">' . esc_html( $label ) . '</span></td>';
			echo '<td>' . esc_html( $row['registered_at'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	public function show_meta_on_order( $hidden ) {
		$keys = array(
			'_oba_registration_auction_id',
			'_oba_membership_plan_id',
			'_oba_registration_user_id',
			'_oba_is_claim',
			'_oba_claim_auction_id',
			'_oba_winner_row_id',
		);
		return array_diff( $hidden, $keys );
	}

	public function add_order_badge( $actions, $order ) {
		if ( 'yes' !== $order->get_meta( '_oba_is_registration_order' ) ) {
			return $actions;
		}
		$aid  = $order->get_meta( '_oba_registration_auction_id' );
		$link = $aid ? get_permalink( $aid ) : $order->get_view_order_url();
		$status = $order->get_status();
		$label  = in_array( $status, array( 'pending', 'on-hold', 'processing' ), true ) ? __( 'Registration pending', 'one-ba-auctions' ) : __( 'Registration active', 'one-ba-auctions' );
		$actions['oba_registration'] = array(
			'url'  => $link,
			'name' => $label,
		);
		return $actions;
	}

	private function grant_membership_slots( $user_id, $product_id, $limit ) {
		$meta = get_user_meta( $user_id, '_oba_membership_slots', true );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}
		$current = isset( $meta[ $product_id ] ) ? (int) $meta[ $product_id ] : 0;
		$meta[ $product_id ] = $current + $limit;
		update_user_meta( $user_id, '_oba_membership_slots', $meta );
	}

	private function map_status_label( $status ) {
		switch ( $status ) {
			case 'registration':
				return __( 'Registered', 'one-ba-auctions' );
			case 'pre_live':
				return __( 'Upcoming', 'one-ba-auctions' );
			case 'live':
				return __( 'Live', 'one-ba-auctions' );
			case 'ended':
				return __( 'Ended', 'one-ba-auctions' );
			default:
				return ucfirst( $status );
		}
	}
}
