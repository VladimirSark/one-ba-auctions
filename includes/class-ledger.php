<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Ledger {

	public static function record( $user_id, $amount, $balance_after, $reason, $reference_id = null, $meta = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_credit_ledger';

		$wpdb->insert(
			$table,
			array(
				'user_id'       => $user_id,
				'amount'        => $amount,
				'balance_after' => $balance_after,
				'reason'        => sanitize_text_field( $reason ),
				'reference_id'  => $reference_id,
				'meta'          => maybe_serialize( $meta ),
			),
			array( '%d', '%f', '%f', '%s', '%d', '%s' )
		);
	}

	public static function get_user_entries( $user_id, $limit = 100 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_credit_ledger';
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d", $user_id, $limit ),
			ARRAY_A
		);
	}

	public static function latest( $limit = 200 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_credit_ledger';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}
}
