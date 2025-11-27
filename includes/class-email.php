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
