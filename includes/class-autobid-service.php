<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Autobid_Service {

	const TRIGGER_THRESHOLD = 4;

	private $repo;
	private $engine;
	private $points;
	private $settings;

	public function __construct() {
		$this->repo   = new OBA_Auction_Repository();
		$this->engine = new OBA_Auction_Engine();
		$this->points = new OBA_Points_Service();
		$this->settings = OBA_Settings::get_settings();
	}

	public function get_settings( $auction_id, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_autobid';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT enabled, max_bids, remaining_bids, window_started_at, window_ends_at, reminder_sent, max_spend FROM {$table} WHERE auction_id = %d AND user_id = %d",
				$auction_id,
				$user_id
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return array(
				'enabled'         => false,
				'max_bids'        => 0,
				'remaining_bids'  => 0,
				'window_started_at' => null,
				'window_ends_at'  => null,
				'reminder_sent'   => 0,
				'max_spend'       => 0,
				'auction_id'      => $auction_id,
				'user_id'         => $user_id,
			);
		}
		return array(
			'enabled'        => (bool) $row['enabled'],
			'max_bids'       => (int) $row['max_bids'],
			'remaining_bids' => (int) $row['remaining_bids'],
			'window_started_at' => $row['window_started_at'],
			'window_ends_at'    => $row['window_ends_at'],
			'reminder_sent'     => isset( $row['reminder_sent'] ) ? (int) $row['reminder_sent'] : 0,
			'max_spend'         => isset( $row['max_spend'] ) ? (float) $row['max_spend'] : 0,
			'auction_id'    => $auction_id,
			'user_id'       => $user_id,
		);
	}

	public function set_settings( $auction_id, $user_id, $enabled, $max_bids ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_autobid';
		$enabled = $enabled ? 1 : 0;
		$max_bids = max( 1, (int) $max_bids );

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE auction_id = %d AND user_id = %d",
				$auction_id,
				$user_id
			)
		);

		if ( $exists ) {
			$wpdb->update(
				$table,
				array(
					'enabled'        => $enabled,
					'max_bids'       => $max_bids,
					'remaining_bids' => $enabled ? $max_bids : 0,
					'enabled_at'     => current_time( 'mysql' ),
					'window_started_at' => current_time( 'mysql' ),
					'window_ends_at'    => gmdate( 'Y-m-d H:i:s', time() + $this->get_window_seconds() ),
					'reminder_sent'     => 0,
				),
				array(
					'auction_id' => $auction_id,
					'user_id'    => $user_id,
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%d' ),
				array( '%d', '%d' )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'auction_id'     => $auction_id,
					'user_id'        => $user_id,
					'enabled'        => $enabled,
					'max_bids'       => $max_bids,
					'remaining_bids' => $max_bids,
					'enabled_at'     => current_time( 'mysql' ),
					'window_started_at' => current_time( 'mysql' ),
					'window_ends_at'    => gmdate( 'Y-m-d H:i:s', time() + $this->get_window_seconds() ),
					'reminder_sent'     => 0,
				),
				array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d' )
			);
		}

		return $this->get_settings( $auction_id, $user_id );
	}

	public function disable( $auction_id, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_autobid';
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
		return $this->get_settings( $auction_id, $user_id );
	}

	public function toggle_autobid( $auction_id, $user_id, $enable, $status = 'registration', $max_bids = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_autobid';
		$enable = $enable ? 1 : 0;
		$max_bids = max( 1, (int) $max_bids );

		$current = $this->get_settings( $auction_id, $user_id );

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE auction_id = %d AND user_id = %d",
				$auction_id,
				$user_id
			)
		);

		if ( $enable ) {
			$start = null;
			$ends_at = null;
			if ( $exists ) {
				$wpdb->update(
					$table,
					array(
						'enabled'          => 1,
						'window_started_at'=> $start,
						'window_ends_at'   => $ends_at,
						'reminder_sent'    => 0,
						'max_spend'        => 0,
						'max_bids'         => $max_bids,
					),
					array(
						'auction_id' => $auction_id,
						'user_id'    => $user_id,
					),
					array( '%d', '%s', '%s', '%d', '%f', '%d' ),
					array( '%d', '%d' )
				);
			} else {
				$wpdb->insert(
					$table,
					array(
						'auction_id'       => $auction_id,
						'user_id'          => $user_id,
						'enabled'          => 1,
						'max_bids'         => 0,
						'remaining_bids'   => 0,
						'enabled_at'       => current_time( 'mysql' ),
						'window_started_at'=> $start,
						'window_ends_at'   => $ends_at,
						'reminder_sent'    => 0,
						'max_spend'        => 0,
						'max_bids'         => $max_bids,
					),
					array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%f', '%d' )
				);
			}
		} else {
			if ( $exists ) {
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
			}
		}

		return $this->get_settings( $auction_id, $user_id );
	}

	public function maybe_run_autobids( $auction_id ) {
		if ( empty( $this->settings['autobid_enabled'] ) ) {
			return;
		}

		$meta = $this->repo->get_auction_meta( $auction_id );

		if ( $meta['auction_status'] !== 'live' ) {
			return;
		}

		$this->start_windows_for_live( $auction_id );

		$live_left = $this->calculate_seconds_left( $meta['live_expires_at'], $meta['live_timer_seconds'] );
		if ( $live_left > self::TRIGGER_THRESHOLD ) {
			return;
		}

		$current_winner = $this->repo->get_current_winner( $auction_id );
		global $wpdb;
		$table = $wpdb->prefix . 'auction_autobid';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE auction_id = %d AND enabled = 1 ORDER BY max_bids DESC, user_id ASC",
				$auction_id
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return;
		}

		$candidates = array();
		foreach ( $rows as $row ) {
			$user_id = (int) $row['user_id'];
			if ( ! $this->repo->is_user_registered( $auction_id, $user_id ) ) {
				continue;
			}
			$this->maybe_send_autobid_reminder( $auction_id, $row );
			$bids = $this->repo->get_user_bids( $auction_id, $user_id );
			if ( $bids >= (int) $row['max_bids'] ) {
				continue;
			}
			$candidates[] = $row + array( 'bids' => $bids );
		}

		if ( empty( $candidates ) ) {
			return;
		}

		usort(
			$candidates,
			function ( $a, $b ) {
				if ( (int) $a['max_bids'] === (int) $b['max_bids'] ) {
					return $a['user_id'] <=> $b['user_id'];
				}
				return (int) $b['max_bids'] <=> (int) $a['max_bids'];
			}
		);

		$challenger = null;
		foreach ( $candidates as $candidate ) {
			if ( $current_winner && (int) $candidate['user_id'] === (int) $current_winner ) {
				continue;
			}
			$challenger = $candidate;
			break;
		}

		$target_user = $challenger ? (int) $challenger['user_id'] : null;
		if ( ! $target_user ) {
			return;
		}

		$this->engine->process_bid( $auction_id, $target_user, true );
	}

	private function calculate_seconds_left( $expires_at, $timer_seconds ) {
		if ( empty( $expires_at ) ) {
			return (int) $timer_seconds;
		}
		$expires = strtotime( $expires_at );
		if ( ! $expires ) {
			return (int) $timer_seconds;
		}
		return max( 0, $expires - time() );
	}

	public function get_window_seconds() {
		return isset( $this->settings['autobid_window_seconds'] ) ? (int) $this->settings['autobid_window_seconds'] : 0;
	}

	public function get_activation_cost() {
		return isset( $this->settings['autobid_activation_cost_points'] ) ? (int) $this->settings['autobid_activation_cost_points'] : 0;
	}

	public function can_user_pay_autobid( $user_id ) {
		$cost = $this->get_activation_cost();
		$balance = $this->points->get_balance( $user_id );
		return $balance >= $cost;
	}

	public function charge_user_autobid( $user_id ) {
		$cost = $this->get_activation_cost();
		if ( $cost <= 0 ) {
			return 0;
		}
		$result = $this->points->deduct_points( $user_id, $cost );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result;
	}

	public function get_remaining_seconds( $settings_row ) {
		if ( empty( $settings_row['enabled'] ) ) {
			return 0;
		}
		return 0;
	}

	private function expire_windows( $auction_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_autobid';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET enabled = 0 WHERE auction_id = %d AND enabled = 1 AND window_ends_at IS NOT NULL AND window_ends_at <= UTC_TIMESTAMP()",
				$auction_id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	private function start_windows_for_live( $auction_id ) {
		return;
	}

	public function start_window_for_user( $auction_id, $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'auction_autobid';
		$seconds = $this->get_window_seconds();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET window_started_at = UTC_TIMESTAMP(), window_ends_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL %d SECOND), reminder_sent = 0
				WHERE auction_id = %d AND user_id = %d AND enabled = 1 AND window_started_at IS NULL",
				$seconds,
				$auction_id,
				$user_id
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL
		return $this->get_settings( $auction_id, $user_id );
	}

	private function maybe_send_autobid_reminder( $auction_id, $row ) {
		if ( empty( $row['window_ends_at'] ) || ! empty( $row['reminder_sent'] ) ) {
			return;
		}
		$remaining = strtotime( $row['window_ends_at'] ) - time();
		if ( $remaining <= 60 && $remaining > 0 ) {
			$user_id = (int) $row['user_id'];
			if ( class_exists( 'OBA_Email' ) ) {
				$mailer = new OBA_Email();
				$mailer->notify_autobid_expiring(
					$user_id,
					$auction_id,
					array(
						'seconds' => $remaining,
					)
				);
			}
			global $wpdb;
			$table = $wpdb->prefix . 'auction_autobid';
			$wpdb->update(
				$table,
				array( 'reminder_sent' => 1 ),
				array(
					'auction_id' => $auction_id,
					'user_id'    => (int) $row['user_id'],
				),
				array( '%d' ),
				array( '%d', '%d' )
			);
		}
	}
}
