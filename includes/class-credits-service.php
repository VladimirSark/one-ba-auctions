<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Credits_Service {

	public function get_balance( $user_id ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'auction_user_credits';
		$credit = $wpdb->get_var( $wpdb->prepare( "SELECT credits_balance FROM {$table} WHERE user_id = %d", $user_id ) );

		return $credit !== null ? (float) $credit : 0;
	}

	public function add_credits( $user_id, $amount ) {
		global $wpdb;

		$table = $wpdb->prefix . 'auction_user_credits';

		$existing = $this->get_balance( $user_id );
		$new      = $existing + (float) $amount;

		$wpdb->replace(
			$table,
			array(
				'user_id'         => $user_id,
				'credits_balance' => $new,
			),
			array( '%d', '%f' )
		);

		$this->log_ledger( $user_id, (float) $amount, $new, 'add_credits' );

		return $new;
	}

	public function subtract_credits( $user_id, $amount ) {
		global $wpdb;

		$current = $this->get_balance( $user_id );
		$new     = max( 0, $current - (float) $amount );
		$table   = $wpdb->prefix . 'auction_user_credits';

		$wpdb->replace(
			$table,
			array(
				'user_id'         => $user_id,
				'credits_balance' => $new,
			),
			array( '%d', '%f' )
		);

		$this->log_ledger( $user_id, - (float) $amount, $new, 'subtract_credits' );

		return $new;
	}

	public function set_balance( $user_id, $amount ) {
		global $wpdb;

		$table = $wpdb->prefix . 'auction_user_credits';
		$new   = max( 0, (float) $amount );

		$wpdb->replace(
			$table,
			array(
				'user_id'         => $user_id,
				'credits_balance' => $new,
			),
			array( '%d', '%f' )
		);

		$this->log_ledger( $user_id, $new, $new, 'set_balance' );

		return $new;
	}

	private function log_ledger( $user_id, $amount, $balance_after, $reason ) {
		if ( class_exists( 'OBA_Ledger' ) ) {
			OBA_Ledger::record( $user_id, $amount, $balance_after, $reason );
		}
	}
}
