<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Email {

	private $settings;

	public function __construct() {
		$this->settings = OBA_Settings::get_settings();
	}

	public function send_raw( $email, $subject, $body ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$from_name  = $this->settings['email_from_name'];
		$from_email = $this->settings['email_from_address'];
		if ( $from_email ) {
			$headers[] = 'From: ' . ( $from_name ? $from_name : get_bloginfo( 'name' ) ) . ' <' . $from_email . '>';
		}
		$html = '<div style="font-family:Helvetica,Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
			<div style="background:#0f172a;color:#fff;padding:12px 16px;font-weight:700;">' . esc_html( get_bloginfo( 'name' ) ) . '</div>
			<div style="padding:16px;color:#0f172a;line-height:1.6;font-size:14px;">' . wp_kses_post( $body ) . '</div>
			<div style="padding:12px 16px;font-size:12px;color:#6b7280;background:#f8fafc;">' . esc_html( get_bloginfo( 'name' ) ) . '</div>
		</div>';
		return wp_mail( $email, $subject, $html, $headers );
	}

	private function resolve_template( $key, $subject, $body, $tokens = array() ) {
		$tpls = isset( $this->settings['email_templates'] ) ? $this->settings['email_templates'] : array();
		if ( isset( $tpls[ $key ]['subject'] ) && $tpls[ $key ]['subject'] ) {
			$subject = $tpls[ $key ]['subject'];
		}
		if ( isset( $tpls[ $key ]['body'] ) && $tpls[ $key ]['body'] ) {
			$body = $tpls[ $key ]['body'];
		}
		if ( $tokens ) {
			$replace = array();
			foreach ( $tokens as $tk => $val ) {
				$replace[ '{' . $tk . '}' ] = $val;
			}
			$subject = strtr( $subject, $replace );
			$body    = strtr( $body, $replace );
		}
		return array( $subject, $body );
	}

	private function send( $user_id, $subject, $body, $cta_label = '', $cta_url = '' ) {
		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! $user->user_email ) {
			return false;
		}

		$from_name  = $this->settings['email_from_name'];
		$from_email = $this->settings['email_from_address'];

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		if ( $from_email ) {
			$headers[] = 'From: ' . ( $from_name ? $from_name : get_bloginfo( 'name' ) ) . ' <' . $from_email . '>';
		}

		$button = '';
		if ( $cta_label && $cta_url ) {
			$button = '<p style="text-align:center;"><a href="' . esc_url( $cta_url ) . '" style="background:#0f172a;color:#fff;padding:10px 16px;border-radius:6px;text-decoration:none;display:inline-block;">' . esc_html( $cta_label ) . '</a></p>';
		}

		$html = '<div style="font-family:Helvetica,Arial,sans-serif;max-width:600px;margin:0 auto;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
			<div style="background:#0f172a;color:#fff;padding:12px 16px;font-weight:700;">' . esc_html( get_bloginfo( 'name' ) ) . '</div>
			<div style="padding:16px;color:#0f172a;line-height:1.6;font-size:14px;">' . wp_kses_post( $body ) . $button . '</div>
			<div style="padding:12px 16px;font-size:12px;color:#6b7280;background:#f8fafc;">' . esc_html( get_bloginfo( 'name' ) ) . '</div>
		</div>';

		return wp_mail( $user->user_email, $subject, $html, $headers );
	}

	private function product_link( $auction_id ) {
		$link = get_permalink( $auction_id );
		return $link ? $link : home_url();
	}

	private function auction_title( $auction_id ) {
		return get_the_title( $auction_id ) ?: sprintf( __( 'Auction #%d', 'one-ba-auctions' ), $auction_id );
	}

	public function notify_registration_pending( $user_id, $data ) {
		$auction_id = isset( $data['auction_id'] ) ? (int) $data['auction_id'] : 0;
		$order_id   = isset( $data['order_id'] ) ? (int) $data['order_id'] : 0;
		list( $subject, $body ) = $this->resolve_template(
			'registration_pending',
			__( '[Auction] Registration pending', 'one-ba-auctions' ),
			__( 'Hi {user_name},<br />Your registration for "<strong>{auction_title}</strong>" is pending approval/payment.<br />Order: #{order_id}.<br /><a href="{auction_link}">View auction</a>', 'one-ba-auctions' ),
			array(
				'user_name'     => $this->get_user_name( $user_id ),
				'auction_title' => $this->auction_title( $auction_id ),
				'auction_link'  => $this->product_link( $auction_id ),
				'order_id'      => $order_id,
			)
		);
		$this->send( $user_id, $subject, $body, __( 'View auction', 'one-ba-auctions' ), $this->product_link( $auction_id ) );
	}

	public function notify_registration_approved( $user_id, $data ) {
		$auction_id = isset( $data['auction_id'] ) ? (int) $data['auction_id'] : 0;
		$order_id   = isset( $data['order_id'] ) ? (int) $data['order_id'] : 0;
		list( $subject, $body ) = $this->resolve_template(
			'registration_approved',
			__( '[Auction] Registration approved', 'one-ba-auctions' ),
			__( 'Hi {user_name},<br />Your registration for "<strong>{auction_title}</strong>" is now active.<br />Order: #{order_id}.<br /><a href="{auction_link}">Open auction</a>', 'one-ba-auctions' ),
			array(
				'user_name'     => $this->get_user_name( $user_id ),
				'auction_title' => $this->auction_title( $auction_id ),
				'auction_link'  => $this->product_link( $auction_id ),
				'order_id'      => $order_id,
			)
		);
		$this->send( $user_id, $subject, $body, __( 'Open auction', 'one-ba-auctions' ), $this->product_link( $auction_id ) );
	}

	private function get_user_name( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return __( 'Customer', 'one-ba-auctions' );
		}
		if ( $user->first_name ) {
			return $user->first_name;
		}
		return $user->display_name ? $user->display_name : $user->user_login;
	}

	public function send_test_templates( $keys, $email ) {
		if ( empty( $keys ) || ! $email ) {
			return;
		}
		$tokens_base = array(
			'user_name'     => 'Admin',
			'auction_title' => 'Test Auction',
			'auction_link'  => home_url(),
			'claim_price'   => 10,
			'bid_cost'      => 1,
			'live_timer'    => 10,
			'seconds'       => 15,
			'balance'       => 99,
			'status'        => __( 'active', 'one-ba-auctions' ),
			'autobid_max_bids' => __( 'No limit', 'one-ba-auctions' ),
		);
		foreach ( $keys as $key ) {
			$subject = __( '[Auction Test]', 'one-ba-auctions' );
			$body    = __( 'This is a test email.', 'one-ba-auctions' );
			switch ( $key ) {
				case 'pre_live':
					list( $subject, $body ) = $this->resolve_template(
						'pre_live',
						__( '[Auction] Countdown to live: {auction_title}', 'one-ba-auctions' ),
						__( 'Hi {user_name},<br />The auction "<strong>{auction_title}</strong>" will go live soon.<br />Countdown: <strong>{seconds}s</strong>.<br />Get ready to bid as soon as it goes live.<br /><a href="{auction_link}">Open auction</a>', 'one-ba-auctions' ),
						$tokens_base
					);
					break;
				case 'live':
					list( $subject, $body ) = $this->resolve_template(
						'live',
						__( '[Auction] Live now: {auction_title}', 'one-ba-auctions' ),
						__( 'Hi {user_name},<br />The auction "<strong>{auction_title}</strong>" is now <strong>LIVE</strong>.<br />Bid cost: {bid_cost} credits. Claim price: {claim_price} credits.<br />Live timer: {live_timer}s (resets on each bid).<br /><a href="{auction_link}">Bid now</a>', 'one-ba-auctions' ),
						$tokens_base
					);
					break;
				case 'winner':
					list( $subject, $body ) = $this->resolve_template(
						'winner',
						__( '[Auction] You won: {auction_title}', 'one-ba-auctions' ),
						__( 'Congrats {user_name}!<br />You won "<strong>{auction_title}</strong>".<br />Claim price: <strong>{claim_price} credits</strong>.<br />Click below to claim your prize.', 'one-ba-auctions' ),
						$tokens_base
					);
					break;
				case 'loser':
					list( $subject, $body ) = $this->resolve_template(
						'loser',
						__( '[Auction] Ended: {auction_title}', 'one-ba-auctions' ),
						__( 'Hi {user_name},<br />The auction "<strong>{auction_title}</strong>" has ended.<br />Your reserved credits have been refunded.<br />Thanks for participating!', 'one-ba-auctions' ),
						$tokens_base
					);
					break;
			case 'claim':
				list( $subject, $body ) = $this->resolve_template(
					'claim',
					__( '[Auction] Claim confirmation', 'one-ba-auctions' ),
					__( 'Hi {user_name},<br />Your claim for "<strong>{auction_title}</strong>" has started.<br />Order/claim price: {claim_price} credits.<br /><a href="{auction_link}">View auction</a>', 'one-ba-auctions' ),
					$tokens_base
				);
				break;
			case 'registration_pending':
				list( $subject, $body ) = $this->resolve_template(
					'registration_pending',
					__( '[Auction] Registration pending', 'one-ba-auctions' ),
					__( 'Hi {user_name},<br />Your registration for "<strong>{auction_title}</strong>" is pending approval/payment.<br />Order: #{order_id}.<br /><a href="{auction_link}">View auction</a>', 'one-ba-auctions' ),
					$tokens_base
				);
				break;
			case 'registration_approved':
				list( $subject, $body ) = $this->resolve_template(
					'registration_approved',
					__( '[Auction] Registration approved', 'one-ba-auctions' ),
					__( 'Hi {user_name},<br />Your registration for "<strong>{auction_title}</strong>" is now active.<br />Order: #{order_id}.<br /><a href="{auction_link}">View auction</a>', 'one-ba-auctions' ),
					$tokens_base
				);
				break;
			case 'credits':
				list( $subject, $body ) = $this->resolve_template(
					'credits',
					__( '[Auction] Your credits were updated', 'one-ba-auctions' ),
					__( 'Hi {user_name},<br />Your credits changed by <strong>{delta}</strong>.<br />New balance: <strong>{balance} credits</strong>.', 'one-ba-auctions' ),
						array_merge( $tokens_base, array( 'delta' => 5 ) )
					);
					break;
			case 'participant':
				list( $subject, $body ) = $this->resolve_template(
					'participant',
					__( '[Auction] Participation updated', 'one-ba-auctions' ),
					__( 'Hi {user_name},<br />Your status for auction "<strong>{auction_title}</strong>" is now <strong>{status}</strong>.', 'one-ba-auctions' ),
					$tokens_base
				);
				break;
			case 'autobid_expiring':
				continue 2;
			case 'autobid_on':
				list( $subject, $body ) = $this->resolve_template(
					'autobid_on',
					__( '[Auction] Autobid enabled: {auction_title}', 'one-ba-auctions' ),
					__( 'Hi {user_name},<br />Your autobid is now ON for "<strong>{auction_title}</strong>".<br />Max bids: {autobid_max_bids}.<br /><a href="{auction_link}">Open auction</a>', 'one-ba-auctions' ),
					$tokens_base
				);
				break;
			case 'autobid_off':
				list( $subject, $body ) = $this->resolve_template(
					'autobid_off',
					__( '[Auction] Autobid disabled: {auction_title}', 'one-ba-auctions' ),
					__( 'Hi {user_name},<br />Your autobid is now OFF for "<strong>{auction_title}</strong>".<br /><a href="{auction_link}">Open auction</a>', 'one-ba-auctions' ),
					$tokens_base
				);
				break;
			case 'autobid_limitless_reminder':
				list( $subject, $body ) = $this->resolve_template(
					'autobid_limitless_reminder',
					__( '[Auction] Autobid is active: {auction_title}', 'one-ba-auctions' ),
					__( 'Hi {user_name},<br />Your autobid is still ON (no limit) for "<strong>{auction_title}</strong>".<br />We will keep you on top automatically.<br /><a href="{auction_link}">Open auction</a>', 'one-ba-auctions' ),
					$tokens_base
				);
				break;
			}
			$this->send_raw( $email, $subject, $body );
		}
	}

	public function notify_prelive( $auction_id, $user_ids, $seconds ) {
		list( $subject_tpl, $body_tpl ) = $this->resolve_template(
			'pre_live',
			__( '[Auction] Countdown to live: {auction_title}', 'one-ba-auctions' ),
			__( 'Hi {user_name},<br />The auction "<strong>{auction_title}</strong>" will go live soon.<br />Countdown: <strong>{seconds}s</strong>.<br />Get ready to bid as soon as it goes live.<br /><a href="{auction_link}">Open auction</a>', 'one-ba-auctions' ),
			array(
				'auction_title' => $this->auction_title( $auction_id ),
				'auction_link'  => $this->product_link( $auction_id ),
				'seconds'       => (int) $seconds,
			)
		);
		foreach ( $user_ids as $uid ) {
			$this->send( $uid, $subject_tpl, $body_tpl, __( 'Open auction', 'one-ba-auctions' ), $this->product_link( $auction_id ) );
		}
	}

	public function notify_live( $auction_id, $user_ids, $meta ) {
		list( $subject_tpl, $body_tpl ) = $this->resolve_template(
			'live',
			__( '[Auction] Live now: {auction_title}', 'one-ba-auctions' ),
			__( 'Hi {user_name},<br />The auction "<strong>{auction_title}</strong>" is now <strong>LIVE</strong>.<br />Bid cost: {bid_cost} credits. Claim price: {claim_price} credits.<br />Live timer: {live_timer}s (resets on each bid).<br /><a href="{auction_link}">Bid now</a>', 'one-ba-auctions' ),
			array(
				'auction_title' => $this->auction_title( $auction_id ),
				'bid_cost'      => (float) $meta['bid_cost_credits'],
				'claim_price'   => (float) $meta['claim_price_credits'],
				'live_timer'    => (int) $meta['live_timer_seconds'],
				'auction_link'  => $this->product_link( $auction_id ),
			)
		);
		foreach ( $user_ids as $uid ) {
			$this->send( $uid, $subject_tpl, $body_tpl, __( 'Bid now', 'one-ba-auctions' ), $this->product_link( $auction_id ) );
		}
	}

	public function notify_autobid_on( $user_id, $auction_id, $data ) {
		$max_bids = isset( $data['autobid_max_bids'] ) ? (int) $data['autobid_max_bids'] : 0;
		list( $subject_tpl, $body_tpl ) = $this->resolve_template(
			'autobid_on',
			__( '[Auction] Autobid enabled: {auction_title}', 'one-ba-auctions' ),
			__( 'Hi {user_name},<br />Your autobid is now ON for "<strong>{auction_title}</strong>".<br />Max bids: {autobid_max_bids}.<br /><a href="{auction_link}">Open auction</a>', 'one-ba-auctions' ),
			array(
				'user_name'        => $this->get_user_name( $user_id ),
				'auction_title'    => $this->auction_title( $auction_id ),
				'auction_link'     => $this->product_link( $auction_id ),
				'autobid_max_bids' => $max_bids ? $max_bids : __( 'No limit', 'one-ba-auctions' ),
			)
		);
		$this->send( $user_id, $subject_tpl, $body_tpl, __( 'Open auction', 'one-ba-auctions' ), $this->product_link( $auction_id ) );
	}

	public function notify_autobid_off( $user_id, $auction_id ) {
		list( $subject_tpl, $body_tpl ) = $this->resolve_template(
			'autobid_off',
			__( '[Auction] Autobid disabled: {auction_title}', 'one-ba-auctions' ),
			__( 'Hi {user_name},<br />Your autobid is now OFF for "<strong>{auction_title}</strong>".<br /><a href="{auction_link}">Open auction</a>', 'one-ba-auctions' ),
			array(
				'user_name'     => $this->get_user_name( $user_id ),
				'auction_title' => $this->auction_title( $auction_id ),
				'auction_link'  => $this->product_link( $auction_id ),
			)
		);
		$this->send( $user_id, $subject_tpl, $body_tpl, __( 'Open auction', 'one-ba-auctions' ), $this->product_link( $auction_id ) );
	}

	public function notify_autobid_limitless_reminder( $user_id, $auction_id, $data ) {
		list( $subject_tpl, $body_tpl ) = $this->resolve_template(
			'autobid_limitless_reminder',
			__( '[Auction] Autobid is active: {auction_title}', 'one-ba-auctions' ),
			__( 'Hi {user_name},<br />Your autobid is still ON (no limit) for "<strong>{auction_title}</strong>".<br />We will keep you on top automatically.<br /><a href="{auction_link}">Open auction</a>', 'one-ba-auctions' ),
			array(
				'user_name'     => $this->get_user_name( $user_id ),
				'auction_title' => $this->auction_title( $auction_id ),
				'auction_link'  => $this->product_link( $auction_id ),
			)
		);
		$this->send( $user_id, $subject_tpl, $body_tpl, __( 'Open auction', 'one-ba-auctions' ), $this->product_link( $auction_id ) );
	}

	public function notify_end_winner( $auction_id, $winner_id, $details ) {
		list( $subject, $body ) = $this->resolve_template(
			'winner',
			__( '[Auction] You won: {auction_title}', 'one-ba-auctions' ),
			__( 'Congrats {user_name}!<br />You won "<strong>{auction_title}</strong>".<br />Claim price: <strong>{claim_price} credits</strong>.<br />Click below to claim your prize.', 'one-ba-auctions' ),
			array(
				'auction_title' => $this->auction_title( $auction_id ),
				'claim_price'   => isset( $details['claim_price'] ) ? (float) $details['claim_price'] : 0,
				'auction_link'  => $this->product_link( $auction_id ),
			)
		);
		$this->send( $winner_id, $subject, $body, __( 'Claim now', 'one-ba-auctions' ), $this->product_link( $auction_id ) );
	}

	public function notify_end_losers( $auction_id, $loser_ids, $details ) {
		list( $subject, $body ) = $this->resolve_template(
			'loser',
			__( '[Auction] Ended: {auction_title}', 'one-ba-auctions' ),
			__( 'Hi {user_name},<br />The auction "<strong>{auction_title}</strong>" has ended.<br />Your reserved credits have been refunded.<br />Thanks for participating!', 'one-ba-auctions' ),
			array(
				'auction_title' => $this->auction_title( $auction_id ),
				'auction_link'  => $this->product_link( $auction_id ),
			)
		);
		foreach ( $loser_ids as $uid ) {
			$this->send( $uid, $subject, $body, __( 'View auction', 'one-ba-auctions' ), $this->product_link( $auction_id ) );
		}
	}

	public function notify_credits_edit( $user_id, $old, $new ) {
		$delta = $new - $old;
		list( $subject, $body ) = $this->resolve_template(
			'credits',
			__( '[Auction] Your credits were updated', 'one-ba-auctions' ),
			__( 'Hi {user_name},<br />Your credits changed by <strong>{delta}</strong>.<br />New balance: <strong>{balance} credits</strong>.', 'one-ba-auctions' ),
			array(
				'delta'   => $delta,
				'balance' => $new,
			)
		);
		$this->send( $user_id, $subject, $body );
	}

	public function notify_participant_status( $auction_id, $user_id, $status ) {
		if ( ! $user_id ) {
			return;
		}
		$status_label = $status;
		if ( 'removed' === $status ) {
			$status_label = __( 'removed', 'one-ba-auctions' );
		} elseif ( 'banned' === $status ) {
			$status_label = __( 'banned', 'one-ba-auctions' );
		} elseif ( 'active' === $status ) {
			$status_label = __( 'restored', 'one-ba-auctions' );
		}
		list( $subject, $body ) = $this->resolve_template(
			'participant',
			__( '[Auction] Participation updated', 'one-ba-auctions' ),
			__( 'Hi {user_name},<br />Your status for auction "<strong>{auction_title}</strong>" is now <strong>{status}</strong>.', 'one-ba-auctions' ),
			array(
				'auction_title' => $this->auction_title( $auction_id ),
				'status'        => $status_label,
				'auction_link'  => $this->product_link( $auction_id ),
			)
		);
		$this->send( $user_id, $subject, $body, __( 'View auction', 'one-ba-auctions' ), $this->product_link( $auction_id ) );
	}
}
