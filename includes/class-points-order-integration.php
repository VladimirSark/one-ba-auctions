<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Points_Order_Integration {

	private $points;

	public function __construct() {
		$this->points = new OBA_Points_Service();
	}

	public function hooks() {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_membership_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_membership_fields' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'grant_points_on_complete' ) );
	}

	public function render_membership_fields() {
		echo '<div class="options_group">';
		woocommerce_wp_checkbox(
			array(
				'id'          => '_is_membership_plan_points',
				'label'       => __( 'Is membership (grants points)', 'one-ba-auctions' ),
				'description' => __( 'When purchased, grants points and marks user as having membership.', 'one-ba-auctions' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_points_amount',
				'label'       => __( 'Points granted', 'one-ba-auctions' ),
				'type'        => 'number',
				'custom_attributes' => array(
					'step' => '1',
					'min'  => '0',
				),
			)
		);
		echo '</div>';
	}

	public function save_membership_fields( $product ) {
		$is_membership = isset( $_POST['_is_membership_plan_points'] ) ? 'yes' : 'no';
		$points        = isset( $_POST['_points_amount'] ) ? (float) wc_clean( wp_unslash( $_POST['_points_amount'] ) ) : 0;
		$product->update_meta_data( '_is_membership_plan_points', $is_membership );
		$product->update_meta_data( '_points_amount', $points );
	}

	public function grant_points_on_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$is_membership = $product->get_meta( '_is_membership_plan_points' );
			if ( 'yes' !== $is_membership ) {
				continue;
			}
			$points = (float) $product->get_meta( '_points_amount' );
			if ( $points > 0 ) {
				$this->points->add_points( $user_id, $points );
			}
			update_user_meta( $user_id, '_oba_has_membership', 1 );
		}
	}
}
