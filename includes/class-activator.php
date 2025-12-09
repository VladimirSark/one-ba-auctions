<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Activator {

	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix . 'auction_';

		$user_credits_table = $prefix . 'user_credits';
		$participants_table = $prefix . 'participants';
		$bids_table         = $prefix . 'bids';
		$winners_table      = $prefix . 'winners';
		$audit_table        = $prefix . 'audit_log';
		$ledger_table       = $prefix . 'credit_ledger';
		$points_table       = $prefix . 'user_points';
		$points_ledger      = $prefix . 'points_ledger';

		$sql_user_credits = "CREATE TABLE {$user_credits_table} (
		  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
		  credits_balance DECIMAL(10,2) NOT NULL DEFAULT 0,
		  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
		) {$charset_collate};";

		$sql_participants = "CREATE TABLE {$participants_table} (
		  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  auction_id BIGINT UNSIGNED NOT NULL,
		  user_id BIGINT UNSIGNED NOT NULL,
		  registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  registration_fee_credits DECIMAL(10,2) NOT NULL,
		  status ENUM('active','removed','banned') NOT NULL DEFAULT 'active',
		  KEY auction_id (auction_id),
		  KEY user_id (user_id)
		) {$charset_collate};";

		$sql_bids = "CREATE TABLE {$bids_table} (
		  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  auction_id BIGINT UNSIGNED NOT NULL,
		  user_id BIGINT UNSIGNED NOT NULL,
		  credits_reserved DECIMAL(10,2) NOT NULL,
		  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  sequence_number BIGINT UNSIGNED NOT NULL,
		  KEY auction_id (auction_id),
		  KEY user_id (user_id),
		  KEY auction_sequence (auction_id, sequence_number)
		) {$charset_collate};";

		$sql_winners = "CREATE TABLE {$winners_table} (
		  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  auction_id BIGINT UNSIGNED NOT NULL,
		  winner_user_id BIGINT UNSIGNED NOT NULL,
		  total_bids INT UNSIGNED NOT NULL,
		  total_credits_consumed DECIMAL(10,2) NOT NULL,
		  claim_price_credits DECIMAL(10,2) NOT NULL,
		  wc_order_id BIGINT UNSIGNED DEFAULT NULL,
		  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  KEY auction_id (auction_id),
		  KEY winner_user_id (winner_user_id)
		) {$charset_collate};";

		$sql_audit = "CREATE TABLE {$audit_table} (
		  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  actor_id BIGINT UNSIGNED DEFAULT NULL,
		  auction_id BIGINT UNSIGNED DEFAULT NULL,
		  action VARCHAR(100) NOT NULL,
		  details TEXT DEFAULT NULL,
		  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  KEY auction_id (auction_id),
		  KEY actor_id (actor_id),
		  KEY action (action)
		) {$charset_collate};";

		$sql_ledger = "CREATE TABLE {$ledger_table} (
		  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  user_id BIGINT UNSIGNED NOT NULL,
		  amount DECIMAL(10,2) NOT NULL,
		  balance_after DECIMAL(10,2) NOT NULL,
		  reason VARCHAR(100) NOT NULL,
		  reference_id BIGINT UNSIGNED DEFAULT NULL,
		  meta TEXT DEFAULT NULL,
		  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  KEY user_id (user_id),
		  KEY reason (reason),
		  KEY reference_id (reference_id)
		) {$charset_collate};";

		$sql_points = "CREATE TABLE {$points_table} (
		  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
		  points_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
		  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
		) {$charset_collate};";

		$sql_points_ledger = "CREATE TABLE {$points_ledger} (
		  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
		  user_id BIGINT UNSIGNED NOT NULL,
		  amount DECIMAL(12,2) NOT NULL,
		  balance_after DECIMAL(12,2) NOT NULL,
		  reason VARCHAR(100) NOT NULL,
		  reference_id BIGINT UNSIGNED DEFAULT NULL,
		  meta TEXT DEFAULT NULL,
		  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  KEY user_id (user_id),
		  KEY reason (reason),
		  KEY reference_id (reference_id)
		) {$charset_collate};";

		dbDelta( $sql_user_credits );
		dbDelta( $sql_participants );
		dbDelta( $sql_bids );
		dbDelta( $sql_winners );
		dbDelta( $sql_audit );
		dbDelta( $sql_ledger );
		dbDelta( $sql_points );
		dbDelta( $sql_points_ledger );
	}

	public static function maybe_upgrade() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'auction_';
		$tables = array(
			$prefix . 'user_credits',
			$prefix . 'participants',
			$prefix . 'bids',
			$prefix . 'winners',
			$prefix . 'audit_log',
			$prefix . 'credit_ledger',
			$prefix . 'user_points',
		);

		$missing = false;
		foreach ( $tables as $table ) {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( ! $exists ) {
				$missing = true;
				break;
			}
		}

		if ( $missing ) {
			self::activate();
		}
	}
}
