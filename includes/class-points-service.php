<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Points_Service {

	private $table;

	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'auction_user_points';
	}

	public function get_balance( $user_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT points_balance FROM {$this->table} WHERE user_id = %d", $user_id ), ARRAY_A );
		return $row ? (float) $row['points_balance'] : 0;
	}

	public function add_points( $user_id, $amount ) {
		global $wpdb;
		$current = $this->get_balance( $user_id );
		$new     = $current + (float) $amount;
		$this->set_balance( $user_id, $new );
		return $new;
	}

	public function deduct_points( $user_id, $amount ) {
		$current = $this->get_balance( $user_id );
		$amount  = (float) $amount;
		if ( $amount <= 0 ) {
			return $current;
		}
		if ( $current < $amount ) {
			return new WP_Error( 'insufficient_points', __( 'Not enough points.', 'one-ba-auctions' ) );
		}
		$new = $current - $amount;
		$this->set_balance( $user_id, $new );
		return $new;
	}

	public function set_balance( $user_id, $amount ) {
		global $wpdb;
		$amount = (float) $amount;
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$this->table} WHERE user_id = %d", $user_id ) );
		if ( $exists ) {
			$wpdb->update(
				$this->table,
				array( 'points_balance' => $amount ),
				array( 'user_id' => $user_id ),
				array( '%f' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$this->table,
				array(
					'user_id'         => $user_id,
					'points_balance'  => $amount,
				),
				array( '%d', '%f' )
			);
		}
		return $amount;
	}
}
