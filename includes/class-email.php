<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Email {

	private $settings;

	public function __construct() {
		$this->settings = OBA_Settings::get_settings();
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
		foreach ( $user_ids as $uid ) {
			$subject = __( 'Auction pre-live countdown started', 'one-ba-auctions' );
			$body    = sprintf(
				__( 'The auction "%1$s" is moving to live soon. Countdown: %2$d seconds.', 'one-ba-auctions' ),
				esc_html( $this->auction_title( $auction_id ) ),
				(int) $seconds
			);
			$body   .= '<br />' . esc_html__( 'Get ready to bid as soon as it goes live.', 'one-ba-auctions' );
			$this->send( $uid, $subject, $body, __( 'Open auction', 'one-ba-auctions' ), $this->product_link( $auction_id ) );
		}
	}

	public function notify_live( $auction_id, $user_ids, $meta ) {
		foreach ( $user_ids as $uid ) {
			$subject = __( 'Auction is live', 'one-ba-auctions' );
			$body    = sprintf(
				__( 'The auction "%1$s" is now LIVE.', 'one-ba-auctions' ),
				esc_html( $this->auction_title( $auction_id ) )
			);
			$body   .= '<br />' . sprintf( __( 'Bid cost: %s credits. Claim price: %s credits.', 'one-ba-auctions' ), (float) $meta['bid_cost_credits'], (float) $meta['claim_price_credits'] );
			$body   .= '<br />' . sprintf( __( 'Live timer: %d seconds. Each bid resets the timer.', 'one-ba-auctions' ), (int) $meta['live_timer_seconds'] );
			$this->send( $uid, $subject, $body, __( 'Bid now', 'one-ba-auctions' ), $this->product_link( $auction_id ) );
		}
	}

	public function notify_end_winner( $auction_id, $winner_id, $details ) {
		$subject = __( 'You won the auction!', 'one-ba-auctions' );
		$body    = sprintf( __( 'Congratulations! You won "%s".', 'one-ba-auctions' ), esc_html( $this->auction_title( $auction_id ) ) );
		$body   .= '<br />' . sprintf( __( 'Claim price: %s credits.', 'one-ba-auctions' ), isset( $details['claim_price'] ) ? (float) $details['claim_price'] : 0 );
		$body   .= '<br />' . __( 'Click below to claim your prize.', 'one-ba-auctions' );
		$this->send( $winner_id, $subject, $body, __( 'Claim now', 'one-ba-auctions' ), $this->product_link( $auction_id ) );
	}

	public function notify_end_losers( $auction_id, $loser_ids, $details ) {
		foreach ( $loser_ids as $uid ) {
			$subject = __( 'Auction ended', 'one-ba-auctions' );
			$body    = sprintf( __( 'The auction "%s" has ended.', 'one-ba-auctions' ), esc_html( $this->auction_title( $auction_id ) ) );
			$body   .= '<br />' . __( 'Your reserved credits have been refunded.', 'one-ba-auctions' );
			$this->send( $uid, $subject, $body, __( 'View auction', 'one-ba-auctions' ), $this->product_link( $auction_id ) );
		}
	}

	public function notify_credits_edit( $user_id, $old, $new ) {
		$delta   = $new - $old;
		$subject = __( 'Your credits balance was updated', 'one-ba-auctions' );
		$body    = sprintf( __( 'Your credits balance was changed by %1$s. New balance: %2$s credits.', 'one-ba-auctions' ), $delta, $new );
		$this->send( $user_id, $subject, $body );
	}

	public function notify_participant_status( $auction_id, $user_id, $status ) {
		if ( ! $user_id ) {
			return;
		}
		$subject = __( 'Your auction participation was updated', 'one-ba-auctions' );
		$status_label = $status;
		if ( 'removed' === $status ) {
			$status_label = __( 'removed', 'one-ba-auctions' );
		} elseif ( 'banned' === $status ) {
			$status_label = __( 'banned', 'one-ba-auctions' );
		} elseif ( 'active' === $status ) {
			$status_label = __( 'restored', 'one-ba-auctions' );
		}
		$body = sprintf(
			__( 'Your status for auction "%1$s" has been set to %2$s.', 'one-ba-auctions' ),
			esc_html( $this->auction_title( $auction_id ) ),
			$status_label
		);
		$this->send( $user_id, $subject, $body, __( 'View auction', 'one-ba-auctions' ), $this->product_link( $auction_id ) );
	}
}
