<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Time {

	/**
	 * Parse a `Y-m-d H:i:s` string as UTC and return unix timestamp.
	 *
	 * Important: we store auction timers in UTC (via `gmdate` / `current_time( 'mysql', 1 )`).
	 * `strtotime()` interprets strings in server local timezone, which causes premature expiry.
	 */
	public static function parse_utc_mysql_datetime_to_timestamp( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return 0;
		}

		try {
			$dt = new DateTimeImmutable( $value, new DateTimeZone( 'UTC' ) );
			return (int) $dt->getTimestamp();
		} catch ( Exception $e ) {
			return 0;
		}
	}

	public static function format_timestamp_local_mysql( $ts ) {
		$ts = (int) $ts;
		if ( $ts <= 0 ) {
			return '';
		}
		if ( function_exists( 'wp_date' ) && function_exists( 'wp_timezone' ) ) {
			return wp_date( 'Y-m-d H:i:s', $ts, wp_timezone() );
		}
		return gmdate( 'Y-m-d H:i:s', $ts );
	}

	public static function format_utc_mysql_datetime_as_local_mysql( $value ) {
		$ts = self::parse_utc_mysql_datetime_to_timestamp( $value );
		return self::format_timestamp_local_mysql( $ts );
	}
}
