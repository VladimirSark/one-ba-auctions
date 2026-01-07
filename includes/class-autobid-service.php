<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autobid V2 (cron-safe):
 * - Users configure max bids (max_bids).
 * - Allowed only after user registration.
 * - Charges points per enable.
 * - Runs from cron/polling frequently; places at most 1 autobid per run (round-robin).
 */
class OBA_Autobid_Service {

	private $repo;
	private $engine;
	private $points;
	private $settings;

	public function __construct() {
		$this->repo     = new OBA_Auction_Repository();
		$this->engine   = new OBA_Auction_Engine();
		$this->points   = new OBA_Points_Service();
		$this->settings = OBA_Settings::get_settings();
	}
	
	private function get_bid_cost( $auction_id ) {
		$meta = $this->repo->get_auction_meta( $auction_id );
		if ( empty( $meta['bid_product_id'] ) ) {
			return 0;
		}
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $meta['bid_product_id'] ) : null;
		if ( $product && '' !== $product->get_price() ) {
			return (float) $product->get_price();
		}
		return 0;
	}

	public function is_globally_enabled() {
		return ! empty( $this->settings['autobid_enabled'] );
	}

	public function is_enabled_for_auction( $auction_id ) {
		return (bool) get_post_meta( $auction_id, '_oba_autobid_enabled', true );
	}

	public function get_activation_cost_points() {
		return isset( $this->settings['autobid_activation_cost_points'] ) ? (int) $this->settings['autobid_activation_cost_points'] : 0;
	}

	public function can_user_pay( $user_id ) {
		$cost = $this->get_activation_cost_points();
		if ( $cost <= 0 ) {
			return true;
		}
		return $this->points->get_balance( $user_id ) >= $cost;
	}

	public function charge_user( $user_id ) {
		$cost = $this->get_activation_cost_points();
		if ( $cost <= 0 ) {
			return true;
		}
		$res = $this->points->deduct_points( $user_id, $cost );
		return ! is_wp_error( $res );
	}

	public function get_user_settings( $auction_id, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_autobid';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT enabled, max_bids, enabled_at FROM {$table} WHERE auction_id = %d AND user_id = %d",
				$auction_id,
				$user_id
			),
			ARRAY_A
		);
		$bid_cost = $this->get_bid_cost( $auction_id );
		if ( ! $row ) {
			return array(
				'enabled'    => 0,
				'max_bids'   => 0,
				'enabled_at' => null,
				'max_spend'  => 0,
				'limitless'  => false,
			);
		}
		$is_limitless = (int) $row['max_bids'] === 0;
		return array(
			'enabled'    => (int) $row['enabled'],
			'max_bids'   => (int) $row['max_bids'],
			'enabled_at' => $row['enabled_at'],
			'max_spend'  => ( ! $is_limitless && $bid_cost ) ? (float) $row['max_bids'] * (float) $bid_cost : 0,
			'limitless'  => $is_limitless,
		);
	}

	public function set_user_settings( $auction_id, $user_id, $enabled, $max_bids ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'auction_autobid';
		$enabled = $enabled ? 1 : 0;
		$is_limitless = (int) $max_bids === 0;
		$max_bids = $is_limitless ? 0 : max( 1, (int) $max_bids );

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE auction_id = %d AND user_id = %d",
				$auction_id,
				$user_id
			)
		);

		$data = array(
			'enabled'    => $enabled,
			'max_bids'   => $max_bids,
			'enabled_at' => $enabled ? current_time( 'mysql' ) : null,
		);
		$formats = array( '%d', '%d', '%s' );

		if ( $exists ) {
			$wpdb->update(
				$table,
				$data,
				array(
					'auction_id' => $auction_id,
					'user_id'    => $user_id,
				),
				$formats,
				array( '%d', '%d' )
			);
		} else {
			$wpdb->insert(
				$table,
				array_merge(
					array(
						'auction_id' => $auction_id,
						'user_id'    => $user_id,
					),
					$data
				),
				array_merge( array( '%d', '%d' ), $formats )
			);
		}

		return $this->get_user_settings( $auction_id, $user_id );
	}

	public function maybe_run_autobids( $auction_id ) {
		if ( ! $this->is_globally_enabled() || ! $this->is_enabled_for_auction( $auction_id ) ) {
			return;
		}

		$meta = $this->repo->get_auction_meta( $auction_id );
		if ( 'live' !== $meta['auction_status'] ) {
			return;
		}

		// If timer already hit zero, finalize instead of looping autobid attempts.
		$expires_ts = OBA_Time::parse_utc_mysql_datetime_to_timestamp( $meta['live_expires_at'] );
		if ( $expires_ts && $expires_ts <= time() ) {
			$this->engine->end_auction_if_expired( $auction_id, 'autobid_check' );
			return;
		}

		// Fire shortly after a timer reset (about 10s after the timer restarts) so bids land earlier.
		$seconds_left   = $expires_ts ? max( 0, $expires_ts - time() ) : 0;
		$live_duration  = isset( $meta['live_timer_seconds'] ) ? (int) $meta['live_timer_seconds'] : 0;
		$elapsed        = $live_duration ? max( 0, $live_duration - $seconds_left ) : 0;
		$fire_after_sec = 10; // start autobids once 10s have elapsed after a reset.
		if ( $elapsed < $fire_after_sec ) {
			return;
		}

		$lock_key = 'oba:auction:' . $auction_id;
		if ( ! OBA_Lock::acquire( $lock_key, 2 ) ) {
			OBA_Audit_Log::log( 'lock_fail', array( 'auction_id' => $auction_id, 'caller' => 'autobid_cron' ), $auction_id );
			return;
		}

		try {
			// Per-second idempotency guard to prevent multiple runs in the same second (poll + cron overlap).
			$current_tick = (int) floor( time() );
			$last_tick    = (int) get_post_meta( $auction_id, '_oba_last_autobid_tick', true );
			if ( $last_tick >= $current_tick ) {
				OBA_Audit_Log::log(
					'autobid_skip_tick_guard',
					array(
						'auction_id' => $auction_id,
						'last_tick'  => $last_tick,
						'tick'       => $current_tick,
					),
					$auction_id
				);
				return;
			}
			update_post_meta( $auction_id, '_oba_last_autobid_tick', $current_tick );

			global $wpdb;
			$table = $wpdb->prefix . 'auction_autobid';
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, enabled, max_bids FROM {$table} WHERE auction_id = %d AND enabled = 1 ORDER BY enabled_at ASC, user_id ASC",
					$auction_id
				),
				ARRAY_A
			);
			if ( empty( $rows ) ) {
				return;
			}

			$candidates     = array();
			$current_winner = $this->repo->get_current_winner( $auction_id );

			foreach ( $rows as $row ) {
				$user_id = (int) $row['user_id'];
				if ( ! $this->repo->is_user_registered( $auction_id, $user_id ) ) {
					continue;
				}
				$limitless = (int) $row['max_bids'] === 0;
				$bids      = $this->repo->get_user_bids( $auction_id, $user_id );
				if ( ! $limitless && $bids >= (int) $row['max_bids'] ) {
					// Auto-disable when quota is reached so user can reconfigure.
					$wpdb->update(
						$table,
						array( 'enabled' => 0 ),
						array(
							'auction_id' => $auction_id,
							'user_id'    => $user_id,
						),
						array( '%d' ),
						array( '%d', '%d' )
					);
					OBA_Audit_Log::log(
						'autobid_max_reached',
						array(
							'auction_id' => $auction_id,
							'user_id'    => $user_id,
							'bids'       => $bids,
							'max_bids'   => (int) $row['max_bids'],
						),
						$auction_id
					);
					continue;
				}
				$candidates[] = $user_id;
			}

			if ( empty( $candidates ) ) {
				return;
			}

			// If only the current winner remains, let the timer expire (do not self-bid forever).
			if ( 1 === count( $candidates ) && $current_winner && (int) $current_winner === (int) $candidates[0] ) {
				OBA_Audit_Log::log(
					'autobid_skip_only_leader',
					array(
						'auction_id' => $auction_id,
						'user_id'    => $current_winner,
					),
					$auction_id
				);
				return;
			}

			// Round-robin pointer stored as transient.
			$pointer_key = '_oba_autobid_pointer_' . $auction_id;
			$pointer     = (int) get_transient( $pointer_key );
			$pointer     = $pointer % count( $candidates );
			$user_id     = $candidates[ $pointer ];

			// Place exactly 1 autobid per cron run.
			$res = $this->engine->process_bid( $auction_id, $user_id, true );
			OBA_Audit_Log::log(
				'autobid_bid_placed',
				array(
					'auction_id' => $auction_id,
					'user_id'    => $user_id,
					'result'     => is_wp_error( $res ) ? $res->get_error_code() : 'ok',
					'expires_at' => get_post_meta( $auction_id, '_live_expires_at', true ), // UTC (storage format).
					'expires_at_local' => class_exists( 'OBA_Time' ) ? OBA_Time::format_utc_mysql_datetime_as_local_mysql( get_post_meta( $auction_id, '_live_expires_at', true ) ) : '',
				),
				$auction_id
			);

			set_transient( $pointer_key, $pointer + 1, MINUTE_IN_SECONDS * 10 );
		} finally {
			OBA_Lock::release( $lock_key );
		}

		$this->maybe_send_reminders( $auction_id, $meta );
	}

	private function maybe_send_reminders( $auction_id, $meta ) {
		if ( ! class_exists( 'OBA_Email' ) ) {
			return;
		}
		// Compute live start (approx): pre_live_start + prelive_timer_seconds.
		$live_start = 0;
		if ( ! empty( $meta['pre_live_start'] ) && isset( $meta['prelive_timer_seconds'] ) ) {
			$start_ts = OBA_Time::parse_utc_mysql_datetime_to_timestamp( $meta['pre_live_start'] );
			if ( $start_ts ) {
				$live_start = $start_ts + (int) $meta['prelive_timer_seconds'];
			}
		}
		if ( ! $live_start || time() < $live_start ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'auction_autobid';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, max_bids FROM {$table} WHERE auction_id = %d AND enabled = 1",
				$auction_id
			),
			ARRAY_A
		);
		if ( empty( $rows ) ) {
			return;
		}

		$mailer = new OBA_Email();
		$interval = isset( $this->settings['autobid_reminder_minutes'] ) ? max( 1, (int) $this->settings['autobid_reminder_minutes'] ) : 10;
		foreach ( $rows as $row ) {
			$user_id = (int) $row['user_id'];
			// Rate limit: once every 10 minutes per user+auction.
			$key           = '_oba_autobid_reminder_' . $auction_id;
			$last_reminded = (int) get_user_meta( $user_id, $key, true );
			if ( $last_reminded && ( time() - $last_reminded ) < MINUTE_IN_SECONDS * $interval ) {
				continue;
			}

			$used = $this->repo->get_user_bids( $auction_id, $user_id );

			$mailer->notify_autobid_on_reminder(
				$user_id,
				$auction_id,
				array(
					'autobid_max_bids'  => (int) $row['max_bids'],
					'autobid_bids_used' => (int) $used,
				)
			);
			update_user_meta( $user_id, $key, time() );
		}
	}
}
