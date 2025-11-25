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
		add_action( 'woocommerce_order_status_completed', array( $this, 'grant_credits_on_complete' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_tab' ) );
		add_action( 'woocommerce_account_oba-credits_endpoint', array( $this, 'render_account_endpoint' ) );
		add_action( 'init', array( $this, 'register_endpoint' ) );
	}

	public function render_fields() {
		echo '<div class="options_group show_if_simple show_if_virtual">';

		woocommerce_wp_checkbox(
			array(
				'id'          => '_is_credit_pack',
				'label'       => __( 'Is credit pack', 'one-ba-auctions' ),
				'description' => __( 'Mark this product as a credit pack.', 'one-ba-auctions' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_credits_amount',
				'label'             => __( 'Credits amount', 'one-ba-auctions' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		echo '</div>';
	}

	public function save_fields( $product_id ) {
		$is_credit_pack  = isset( $_POST['_is_credit_pack'] ) ? 'yes' : 'no';
		$credits_amount  = isset( $_POST['_credits_amount'] ) ? wc_clean( wp_unslash( $_POST['_credits_amount'] ) ) : 0;

		update_post_meta( $product_id, '_is_credit_pack', $is_credit_pack );
		update_post_meta( $product_id, '_credits_amount', $credits_amount );
	}

	public function grant_credits_on_complete( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product_id   = $item->get_product_id();
			$is_credit    = get_post_meta( $product_id, '_is_credit_pack', true );
			$credits      = (float) get_post_meta( $product_id, '_credits_amount', true );
			$quantity     = (int) $item->get_quantity();

			if ( 'yes' !== $is_credit || $credits <= 0 ) {
				continue;
			}

			$this->credits->add_credits( $user_id, $credits * $quantity );
		}
	}

	public function register_endpoint() {
		add_rewrite_endpoint( 'oba-credits', EP_ROOT | EP_PAGES );
	}

	public function add_account_tab( $items ) {
		$items['oba-credits'] = __( 'My Credits', 'one-ba-auctions' );
		return $items;
	}

	public function render_account_endpoint() {
		$balance = $this->credits->get_balance( get_current_user_id() );

		echo '<h3>' . esc_html__( 'My Credits', 'one-ba-auctions' ) . '</h3>';
		echo '<p>' . sprintf( esc_html__( 'Your current balance: %s credits', 'one-ba-auctions' ), esc_html( $balance ) ) . '</p>';
	}
}
