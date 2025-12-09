<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Auction_Repository {

	public function get_auction_meta( $auction_id ) {
		$meta = array(
			'registration_fee_credits' => 0.0,
			'bid_cost_credits'         => 0.0,
			'required_participants'    => (int) get_post_meta( $auction_id, '_required_participants', true ),
			'live_timer_seconds'       => (int) get_post_meta( $auction_id, '_live_timer_seconds', true ),
			'prelive_timer_seconds'    => (int) get_post_meta( $auction_id, '_prelive_timer_seconds', true ),
			'claim_price_credits'      => 0.0,
			'bid_product_id'           => (int) get_post_meta( $auction_id, '_bid_product_id', true ),
			'registration_points'      => (float) get_post_meta( $auction_id, '_registration_points', true ),
			'auction_status'           => get_post_meta( $auction_id, '_auction_status', true ) ?: 'registration',
			'pre_live_start'           => get_post_meta( $auction_id, '_pre_live_start', true ),
			'live_expires_at'          => get_post_meta( $auction_id, '_live_expires_at', true ),
		);

		return $meta;
	}

	public function get_participant_count( $auction_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'auction_participants';

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE auction_id = %d AND status = %s", $auction_id, 'active' )
		);
	}

	public function is_user_registered( $auction_id, $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'auction_participants';

		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT 1 FROM {$table} WHERE auction_id = %d AND user_id = %d AND status = %s", $auction_id, $user_id, 'active' )
		);
	}

	public function get_user_bids( $auction_id, $user_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'auction_bids';

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE auction_id = %d AND user_id = %d", $auction_id, $user_id )
		);
	}

	public function get_last_bids( $auction_id, $limit = 5 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'auction_bids';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, credits_reserved, created_at, auction_id FROM {$table} WHERE auction_id = %d ORDER BY sequence_number DESC LIMIT %d",
				$auction_id,
				$limit
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	public function get_current_winner( $auction_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'auction_bids';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id FROM {$table} WHERE auction_id = %d ORDER BY sequence_number DESC LIMIT 1",
				$auction_id
			),
			ARRAY_A
		);

		return $row ? (int) $row['user_id'] : null;
	}

	public function get_winner_row( $auction_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'auction_winners';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE auction_id = %d ORDER BY id DESC LIMIT 1", $auction_id ),
			ARRAY_A
		);
	}

	public function get_bid_totals_by_user( $auction_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'auction_bids';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, COUNT(*) AS total_bids, SUM(credits_reserved) AS total_credits
				FROM {$table}
				WHERE auction_id = %d
				GROUP BY user_id",
				$auction_id
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	public function get_participant_user_ids( $auction_id, $statuses = array( 'active' ) ) {
		global $wpdb;

		$statuses = (array) $statuses;
		if ( empty( $statuses ) ) {
			$statuses = array( 'active' );
		}

		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$table        = $wpdb->prefix . 'auction_participants';

		$query = $wpdb->prepare(
			"SELECT user_id FROM {$table} WHERE auction_id = %d AND status IN ({$placeholders})",
			array_merge( array( $auction_id ), $statuses )
		);

		$rows = $wpdb->get_col( $query );

		return array_map( 'intval', $rows ?: array() );
	}

	public function get_total_bid_count( $auction_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_bids';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE auction_id = %d",
				$auction_id
			)
		);
	}

	public function get_pending_registrations_for_user( $auction_id, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_participants';

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM {$table} WHERE auction_id = %d AND user_id = %d AND status = %s",
				$auction_id,
				$user_id,
				'pending'
			)
		);
	}
}
