<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Auction_Engine {

	private $credits;
	private $points;
	private $repo;

	public function __construct() {
		$this->credits = new OBA_Credits_Service();
		$this->points  = new OBA_Points_Service();
		$this->repo    = new OBA_Auction_Repository();
	}

	public function calculate_lobby_percent( $auction_id ) {
		$meta         = $this->repo->get_auction_meta( $auction_id );
		$total_needed = max( 1, (int) $meta['required_participants'] );
		$count        = $this->repo->get_participant_count( $auction_id );

		return min( 100, (int) floor( ( $count / $total_needed ) * 100 ) );
	}

	public function process_registration( $auction_id, $user_id ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'not_logged_in', __( 'You must be logged in to register.', 'one-ba-auctions' ) );
		}

		$meta = $this->repo->get_auction_meta( $auction_id );

		if ( $meta['auction_status'] !== 'registration' ) {
			return new WP_Error( 'invalid_state', __( 'Registration is closed.', 'one-ba-auctions' ) );
		}

		if ( $this->repo->is_user_registered( $auction_id, $user_id ) ) {
			return true;
		}

		if ( ! $this->user_has_membership( $user_id ) ) {
			return new WP_Error( 'membership_required', __( 'Membership required to register.', 'one-ba-auctions' ) );
		}

		$required_points = isset( $meta['registration_points'] ) ? (float) $meta['registration_points'] : 0;
		if ( $required_points > 0 ) {
			$deduct = $this->points->deduct_points( $user_id, $required_points );
			if ( is_wp_error( $deduct ) ) {
				return $deduct;
			}
		}

		return true;
	}

	public function maybe_start_pre_live( $auction_id ) {
		$meta = $this->repo->get_auction_meta( $auction_id );

		if ( $meta['auction_status'] !== 'registration' ) {
			return;
		}

		$count = $this->repo->get_participant_count( $auction_id );

		if ( $count >= (int) $meta['required_participants'] ) {
			update_post_meta( $auction_id, '_auction_status', 'pre_live' );
			update_post_meta( $auction_id, '_pre_live_start', gmdate( 'Y-m-d H:i:s' ) );
			if ( class_exists( 'OBA_Audit_Log' ) ) {
				OBA_Audit_Log::log(
					'stage_change',
					array(
						'auction_id' => $auction_id,
						'from'       => 'registration',
						'to'         => 'pre_live',
						'reason'     => 'required_participants_reached',
						'participants' => $count,
					),
					$auction_id
				);
			}
			$this->notify_pre_live( $auction_id, $meta );
		}
	}


	public function get_bid_fee_amount( $meta ) {
		if ( empty( $meta['bid_product_id'] ) ) {
			return 0;
		}
		$product = wc_get_product( $meta['bid_product_id'] );
		if ( $product && '' !== $product->get_price() ) {
			return (float) $product->get_price();
		}
		return 0;
	}

	public function enroll_participant( $auction_id, $user_id, $fee = 0 ) {
		global $wpdb;
		if ( $this->repo->is_user_registered( $auction_id, $user_id ) ) {
			return;
		}
		$table = $wpdb->prefix . 'auction_participants';
		$wpdb->insert(
			$table,
			array(
				'auction_id'              => $auction_id,
				'user_id'                 => $user_id,
				'registration_fee_credits'=> $fee,
				'status'                  => 'active',
			),
			array( '%d', '%d', '%f', '%s' )
		);
		if ( class_exists( 'OBA_Audit_Log' ) ) {
			OBA_Audit_Log::log(
				'user_registered',
				array(
					'auction_id' => $auction_id,
					'user_id'    => $user_id,
					'fee'        => (float) $fee,
				),
				$auction_id
			);
		}
		$this->maybe_start_pre_live( $auction_id );
	}

	public function user_has_membership( $user_id ) {
		return (bool) get_user_meta( $user_id, '_oba_has_membership', true );
	}

	public function maybe_move_to_live( $auction_id ) {
		$meta = $this->repo->get_auction_meta( $auction_id );

		if ( $meta['auction_status'] !== 'pre_live' ) {
			return;
		}

		if ( empty( $meta['pre_live_start'] ) ) {
			return;
		}

		$deadline = strtotime( $meta['pre_live_start'] ) + (int) $meta['prelive_timer_seconds'];

		if ( time() >= $deadline ) {
			update_post_meta( $auction_id, '_auction_status', 'live' );
			update_post_meta( $auction_id, '_live_expires_at', '' );
			if ( class_exists( 'OBA_Audit_Log' ) ) {
				OBA_Audit_Log::log(
					'stage_change',
					array(
						'auction_id' => $auction_id,
						'from'       => 'pre_live',
						'to'         => 'live',
						'reason'     => 'pre_live_timer_elapsed',
					),
					$auction_id
				);
			}
			$this->notify_live( $auction_id, $meta );
		}
	}

	public function process_bid( $auction_id, $user_id, $is_autobid = false ) {
		$meta = $this->repo->get_auction_meta( $auction_id );

		if ( $meta['auction_status'] !== 'live' ) {
			return new WP_Error( 'invalid_state', __( 'Bidding is closed.', 'one-ba-auctions' ) );
		}

		if ( ! $this->repo->is_user_registered( $auction_id, $user_id ) ) {
			return new WP_Error( 'not_registered', __( 'You are not registered for this auction.', 'one-ba-auctions' ) );
		}

		$current_winner = $this->repo->get_current_winner( $auction_id );
		if ( $current_winner && (int) $current_winner === (int) $user_id ) {
			return new WP_Error( 'already_leading', __( 'You are already the leading bidder.', 'one-ba-auctions' ) );
		}

		$bid_cost = $this->get_bid_fee_amount( $meta );

		global $wpdb;

		$table          = $wpdb->prefix . 'auction_bids';
		$sequence       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(sequence_number),0) FROM {$table} WHERE auction_id = %d", $auction_id ) ) + 1;
		$timer_seconds  = (int) $meta['live_timer_seconds'];
		if ( get_post_meta( $auction_id, '_oba_autobid_enabled', true ) && $timer_seconds > 0 ) {
			$timer_seconds = max( 60, $timer_seconds );
		}
		$animated_timer = time() + max( 1, $timer_seconds );

		$wpdb->insert(
			$table,
			array(
				'auction_id'       => $auction_id,
				'user_id'          => $user_id,
				'credits_reserved' => $bid_cost,
				'sequence_number'  => $sequence,
				'is_autobid'       => $is_autobid ? 1 : 0,
			),
			array( '%d', '%d', '%f', '%d', '%d' )
		);

		update_post_meta( $auction_id, '_live_expires_at', gmdate( 'Y-m-d H:i:s', $animated_timer ) );

		if ( class_exists( 'OBA_Audit_Log' ) ) {
			OBA_Audit_Log::log(
				'bid_placed',
				array(
					'auction_id'   => $auction_id,
					'user_id'      => $user_id,
					'sequence'     => $sequence,
					'is_autobid'   => $is_autobid,
					'expires_at'   => gmdate( 'Y-m-d H:i:s', $animated_timer ),
				),
				$auction_id
			);
		}

		return true;
	}

	private function reset_live_timer( $auction_id, $meta ) {
		$timer_seconds = isset( $meta['live_timer_seconds'] ) ? (int) $meta['live_timer_seconds'] : (int) get_post_meta( $auction_id, '_live_timer_seconds', true );
		$timer_seconds = max( 1, $timer_seconds );
		if ( get_post_meta( $auction_id, '_oba_autobid_enabled', true ) ) {
			$timer_seconds = max( 60, $timer_seconds );
		}
		$expires       = time() + $timer_seconds;
		update_post_meta( $auction_id, '_live_expires_at', gmdate( 'Y-m-d H:i:s', $expires ) );
		if ( class_exists( 'OBA_Audit_Log' ) ) {
			OBA_Audit_Log::log(
				'timer_extended',
				array(
					'auction_id' => $auction_id,
					'expires_at' => gmdate( 'Y-m-d H:i:s', $expires ),
					'seconds'    => $timer_seconds,
				),
				$auction_id
			);
		}
	}

	public function end_auction_if_expired( $auction_id ) {
		$lock_key = 'oba:auction:' . $auction_id;
		if ( ! OBA_Lock::acquire( $lock_key, 2 ) ) {
			if ( class_exists( 'OBA_Audit_Log' ) ) {
				OBA_Audit_Log::log( 'lock_fail', array( 'auction_id' => $auction_id, 'caller' => 'end_auction_if_expired' ), $auction_id );
			}
			return;
		}
		try {
			$meta = $this->repo->get_auction_meta( $auction_id );

			$already_ended = ( 'ended' === $meta['auction_status'] ) || get_post_meta( $auction_id, '_oba_ended_at', true );
			if ( $already_ended ) {
				return;
			}

			$expires = strtotime( $meta['live_expires_at'] );
			if ( ! $expires || $expires > time() ) {
				if ( class_exists( 'OBA_Audit_Log' ) ) {
					OBA_Audit_Log::log(
						'expiry_check_status',
						array(
							'auction_id'     => $auction_id,
							'status'         => $meta['auction_status'],
							'live_expires_at'=> $meta['live_expires_at'],
						),
						$auction_id
					);
				}
				return;
			}

			// Mark finalizing to prevent duplicate endings.
			update_post_meta( $auction_id, '_oba_finalizing', 1 );

			try {
				$this->calculate_winner_and_resolve_credits( $auction_id, 'timer' );
				update_post_meta( $auction_id, '_oba_ended_at', current_time( 'mysql', 1 ) );
				update_post_meta( $auction_id, '_auction_status', 'ended' );
				if ( class_exists( 'OBA_Audit_Log' ) ) {
					OBA_Audit_Log::log(
						'auction_end',
						array(
							'trigger'       => 'timer',
							'auction_id'    => $auction_id,
							'expires_at'    => $meta['live_expires_at'],
						),
						$auction_id
					);
				}
			} finally {
				delete_post_meta( $auction_id, '_oba_finalizing' );
			}
		} finally {
			OBA_Lock::release( $lock_key );
		}
	}

	public function calculate_winner_and_resolve_credits( $auction_id, $trigger = 'timer' ) {
		global $wpdb;

		$existing_winner = $this->repo->get_winner_row( $auction_id );
		if ( $existing_winner ) {
			update_post_meta( $auction_id, '_auction_status', 'ended' );
			return;
		}

		$finalizing_set_at = get_post_meta( $auction_id, '_oba_finalizing', true );
		if ( $finalizing_set_at ) {
			$ts = strtotime( $finalizing_set_at );
			if ( $ts && ( time() - $ts ) > 30 && ! get_post_meta( $auction_id, '_oba_ended_at', true ) ) {
				// Stale finalizing; clear and retry.
				delete_post_meta( $auction_id, '_oba_finalizing' );
				if ( class_exists( 'OBA_Audit_Log' ) ) {
					OBA_Audit_Log::log( 'finalizing_stale_reset', array( 'auction_id' => $auction_id ), $auction_id );
				}
			}
		}

		$meta  = $this->repo->get_auction_meta( $auction_id );
		$table = $wpdb->prefix . 'auction_bids';
		$last  = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT user_id, sequence_number, credits_reserved, created_at FROM {$table} WHERE auction_id = %d ORDER BY sequence_number DESC LIMIT 1",
				$auction_id
			),
			ARRAY_A
		);

		if ( ! $last ) {
			update_post_meta( $auction_id, '_auction_status', 'ended' );
			OBA_Audit_Log::log(
				'auction_end',
				array(
					'trigger'                => $trigger,
					'winner_id'              => 0,
					'total_bids'             => 0,
					'total_credits_consumed' => 0,
					'refund_total'           => 0,
					'claim_price'            => isset( $meta['claim_price_credits'] ) ? (float) $meta['claim_price_credits'] : 0,
					'last_bid_user_id'       => null,
					'last_bid_amount'        => 0,
					'last_bid_time'          => null,
				),
				$auction_id
			);
			return;
		}

		$winner_id = (int) $last['user_id'];

		$winners_table = $wpdb->prefix . 'auction_winners';

		$totals = $this->repo->get_bid_totals_by_user( $auction_id );
		$winner_totals = array(
			'total_bids'    => 0,
			'total_credits' => 0,
		);

		$refund_total = 0;

		foreach ( $totals as $row ) {
			$user_id = (int) $row['user_id'];
			if ( $user_id === $winner_id ) {
				$winner_totals['total_bids']    = (int) $row['total_bids'];
				$winner_totals['total_credits'] = (float) $row['total_credits'];
				continue;
			}

			// Refund reserved bids to non-winners.
			$this->credits->add_credits( $user_id, (float) $row['total_credits'] );
			$refund_total += (float) $row['total_credits'];
			if ( class_exists( 'OBA_Ledger' ) ) {
				OBA_Ledger::record( $user_id, (float) $row['total_credits'], $this->credits->get_balance( $user_id ), 'bid_refund', $auction_id );
			}
		}

		update_post_meta( $auction_id, '_auction_status', 'ended' );

		$wpdb->insert(
			$winners_table,
			array(
				'auction_id'             => $auction_id,
				'winner_user_id'         => $winner_id,
				'total_bids'             => $winner_totals['total_bids'],
				'total_credits_consumed' => $winner_totals['total_credits'],
				'claim_price_credits'    => (float) $meta['claim_price_credits'],
			),
			array( '%d', '%d', '%d', '%f', '%f' )
		);

		OBA_Audit_Log::log(
			'auction_end',
			array(
				'trigger'                => $trigger,
				'winner_id'              => $winner_id,
				'total_bids'             => $winner_totals['total_bids'],
				'total_credits_consumed' => $winner_totals['total_credits'],
				'refund_total'           => $refund_total,
				'claim_price'            => (float) $meta['claim_price_credits'],
				'last_bid_user_id'       => isset( $last['user_id'] ) ? (int) $last['user_id'] : null,
				'last_bid_amount'        => isset( $last['credits_reserved'] ) ? (float) $last['credits_reserved'] : 0,
				'last_bid_time'          => isset( $last['created_at'] ) ? $last['created_at'] : null,
			),
			$auction_id
		);

		$this->notify_end( $auction_id, $winner_id, $refund_total, $winner_totals, $meta );
	}

	private function notify_pre_live( $auction_id, $meta ) {
		if ( ! class_exists( 'OBA_Email' ) ) {
			return;
		}
		$mailer   = new OBA_Email();
		$users    = $this->repo->get_participant_user_ids( $auction_id, array( 'active' ) );
		$mailer->notify_prelive( $auction_id, $users, (int) $meta['prelive_timer_seconds'] );
	}

	private function notify_live( $auction_id, $meta ) {
		if ( ! class_exists( 'OBA_Email' ) ) {
			return;
		}
		$mailer = new OBA_Email();
		$users  = $this->repo->get_participant_user_ids( $auction_id, array( 'active' ) );
		$mailer->notify_live( $auction_id, $users, $meta );
	}

	private function notify_end( $auction_id, $winner_id, $refund_total, $winner_totals, $meta ) {
		if ( ! class_exists( 'OBA_Email' ) ) {
			return;
		}
		$mailer = new OBA_Email();
		$mailer->notify_end_winner(
			$auction_id,
			$winner_id,
			array(
				'claim_price' => (float) $meta['claim_price_credits'],
			)
		);

		$participants = $this->repo->get_participant_user_ids( $auction_id, array( 'active' ) );
		$losers       = array_diff( $participants, array( $winner_id ) );
		if ( $losers ) {
			$mailer->notify_end_losers(
				$auction_id,
				$losers,
				array(
					'refund_total' => $refund_total,
					'total_bids'   => $winner_totals['total_bids'],
				)
			);
		}
	}
}
