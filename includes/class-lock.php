<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Lock {
	/**
	 * Acquire a named MySQL lock.
	 *
	 * @param string $key     Lock key.
	 * @param int    $timeout Seconds to wait.
	 * @return bool
	 */
	public static function acquire( $key, $timeout = 2 ) {
		global $wpdb;
		// Ensure we only attempt on MySQL/MariaDB where GET_LOCK is available.
		if ( ! method_exists( $wpdb, 'dbh' ) && ! isset( $wpdb->dbh ) ) {
			self::log_lock_event( 'lock_not_available', $key, 'wpdb dbh missing' );
			return false;
		}
		$result = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $key, $timeout ) );
		if ( null === $result ) {
			self::log_lock_event( 'lock_not_available', $key, 'GET_LOCK returned NULL' );
			return false;
		}
		return (int) $result === 1;
	}

	/**
	 * Release a named MySQL lock.
	 *
	 * @param string $key Lock key.
	 * @return void
	 */
	public static function release( $key ) {
		global $wpdb;
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
	}

	private static function log_lock_event( $type, $key, $message = '' ) {
		if ( class_exists( 'OBA_Audit_Log' ) ) {
			OBA_Audit_Log::log(
				$type,
				array(
					'lock'    => $key,
					'message' => $message,
				),
				0
			);
		}
	}
}
