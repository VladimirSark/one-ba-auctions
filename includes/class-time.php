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
}

