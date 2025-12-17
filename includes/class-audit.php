<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Audit_Log {

	public static function log( $action, $details = array(), $auction_id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_audit_log';

		$wpdb->insert(
			$table,
			array(
				'actor_id'   => get_current_user_id(),
				'auction_id' => $auction_id,
				'action'     => sanitize_text_field( $action ),
				'details'    => maybe_serialize( $details ),
			),
			array( '%d', '%d', '%s', '%s' )
		);
	}

	public static function latest( $limit = 100 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_audit_log';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A );
	}

	public static function ended_logs( $limit = 200, $auction_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_audit_log';
		$sql   = "SELECT * FROM {$table} WHERE action = %s";
		$args  = array( 'auction_end' );

		if ( $auction_id ) {
			$sql   .= ' AND auction_id = %d';
			$args[] = $auction_id;
		}

		$sql   .= ' ORDER BY id DESC LIMIT %d';
		$args[] = $limit;

		return $wpdb->get_results( call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $args ) ), ARRAY_A );
	}

	public static function latest_for_auction( $auction_id, $limit = 50 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_audit_log';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE auction_id = %d ORDER BY id DESC LIMIT %d",
				$auction_id,
				$limit
			),
			ARRAY_A
		);
	}
}
