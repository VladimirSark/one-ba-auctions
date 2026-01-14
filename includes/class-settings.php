<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Settings {

	const OPTION_KEY = 'oba_settings';

	public static function defaults() {
		return array(
			'default_prelive_seconds' => 60,
			'default_live_seconds'    => 10,
			'poll_interval_ms'        => 1500,
			'terms_text'              => '',
			'show_header_balance'     => false,
			'login_link'              => '',
			'status_info_html'        => '<ol><li><strong>Registration</strong><br/>Users join the auction by paying the registration fee in credits and securing their spot.</li><li><strong>Time to Live</strong><br/>Once all spots are filled, a short countdown starts before the auction goes live.</li><li><strong>Live</strong><br/>Bidding opens; each bid costs credits and resets the timer—the action continues until no new bids come in.</li><li><strong>End</strong><br/>Claim your reward with credits or other payment providers. If you lose, your credits will be refunded.</li></ol>',
			'email_from_name'         => get_bloginfo( 'name' ),
			'email_from_address'      => get_option( 'admin_email' ),
			'membership_links'        => array( '', '', '' ),
			'membership_labels'       => array( '', '', '' ),
			'points_value'            => '1.00',
			'autobid_enabled'         => true,
			'autobid_window_seconds'  => 300, // legacy/no-op, kept for compatibility
			'autobid_activation_cost_points' => 5,
			'autobid_reminder_minutes'=> 10,
			'translations'            => array(),
			'email_templates'         => array(),
		);
	}

	public static function get_settings() {
		$stored   = get_option( self::OPTION_KEY, array() );
		$defaults = self::defaults();
		return wp_parse_args( $stored, $defaults );
	}

	public static function update_settings( $data ) {
		$stored   = self::get_settings();
		$defaults = self::defaults();
		$new      = array(
			'default_prelive_seconds' => isset( $data['default_prelive_seconds'] ) ? max( 1, (int) $data['default_prelive_seconds'] ) : $defaults['default_prelive_seconds'],
			'default_live_seconds'    => isset( $data['default_live_seconds'] ) ? max( 1, (int) $data['default_live_seconds'] ) : $defaults['default_live_seconds'],
			'poll_interval_ms'        => isset( $data['poll_interval_ms'] ) ? max( 500, (int) $data['poll_interval_ms'] ) : $defaults['poll_interval_ms'],
			'terms_text'              => isset( $data['terms_text'] ) ? wp_kses_post( $data['terms_text'] ) : $defaults['terms_text'],
			'show_header_balance'     => ! empty( $data['show_header_balance'] ),
			'login_link'              => isset( $data['login_link'] ) ? esc_url_raw( $data['login_link'] ) : '',
			'status_info_html'        => isset( $data['status_info_html'] ) ? wp_kses_post( $data['status_info_html'] ) : $defaults['status_info_html'],
			'email_from_name'         => isset( $data['email_from_name'] ) ? sanitize_text_field( wp_unslash( $data['email_from_name'] ) ) : $defaults['email_from_name'],
			'email_from_address'      => isset( $data['email_from_address'] ) ? sanitize_email( wp_unslash( $data['email_from_address'] ) ) : $defaults['email_from_address'],
			'membership_links'        => isset( $data['membership_links'] ) && is_array( $data['membership_links'] ) ? array_map( 'esc_url_raw', $data['membership_links'] ) : $defaults['membership_links'],
			'membership_labels'       => isset( $data['membership_labels'] ) && is_array( $data['membership_labels'] ) ? array_map( 'sanitize_text_field', $data['membership_labels'] ) : $defaults['membership_labels'],
			'points_value'            => isset( $data['points_value'] ) ? sanitize_text_field( wp_unslash( $data['points_value'] ) ) : $defaults['points_value'],
			'autobid_enabled'         => true,
			'autobid_window_seconds'  => isset( $stored['autobid_window_seconds'] ) ? (int) $stored['autobid_window_seconds'] : $defaults['autobid_window_seconds'],
			'autobid_activation_cost_points' => isset( $data['autobid_activation_cost_points'] ) ? max( 0, (int) $data['autobid_activation_cost_points'] ) : $defaults['autobid_activation_cost_points'],
			'autobid_reminder_minutes'=> isset( $data['autobid_reminder_minutes'] ) ? max( 1, (int) $data['autobid_reminder_minutes'] ) : $defaults['autobid_reminder_minutes'],
			'translations'            => isset( $data['translations'] ) && is_array( $data['translations'] ) ? array_map( 'sanitize_text_field', $data['translations'] ) : ( isset( $stored['translations'] ) ? $stored['translations'] : array() ),
			'email_templates'         => isset( $data['email_templates'] ) && is_array( $data['email_templates'] ) ? self::sanitize_email_templates( $data['email_templates'] ) : ( isset( $stored['email_templates'] ) ? $stored['email_templates'] : array() ),
		);

		update_option( self::OPTION_KEY, $new );

		return $new;
	}

	public static function update_translations( $data ) {
		$settings = self::get_settings();
			$defaults = array(
				'step1_label'    => __( 'Registration', 'one-ba-auctions' ),
				'step2_label'    => __( 'Countdown to Live', 'one-ba-auctions' ),
				'step3_label'    => __( 'Live Bidding', 'one-ba-auctions' ),
				'step4_label'    => __( 'Auction Ended', 'one-ba-auctions' ),
				'step1_desc'     => __( 'Join the lobby with points.', 'one-ba-auctions' ),
				'step2_desc'     => __( 'Short countdown before live.', 'one-ba-auctions' ),
				'step3_desc'     => __( 'Bid, reset timer, compete.', 'one-ba-auctions' ),
				'step4_desc'     => __( 'Auction has ended.', 'one-ba-auctions' ),
				'lobby_progress' => __( 'Lobby progress', 'one-ba-auctions' ),
				'register_cta'   => __( 'Register & Reserve Spot', 'one-ba-auctions' ),
				'bid_button'     => __( 'Place bid', 'one-ba-auctions' ),
				'prelive_hint'   => __( 'Auction is about to go live', 'one-ba-auctions' ),
				'winner_msg'     => __( 'You won!', 'one-ba-auctions' ),
				'loser_msg'      => __( 'Auction ended.', 'one-ba-auctions' ),
				'refund_msg'     => __( 'Your reserved points have been refunded.', 'one-ba-auctions' ),
				'register_note'  => __( 'You are registered, wait for Step 2. Share this auction to reach 100% faster!', 'one-ba-auctions' ),
				'buy_credits_title' => __( 'Buy points', 'one-ba-auctions' ),
				'registration_fee_label' => __( 'Registration points', 'one-ba-auctions' ),
				'registered_badge' => __( 'Registered', 'one-ba-auctions' ),
				'not_registered_badge' => __( 'Not registered', 'one-ba-auctions' ),
				'credit_singular' => __( 'credit', 'one-ba-auctions' ),
				'credit_plural'   => __( 'credits', 'one-ba-auctions' ),
				'points_label'    => __( 'Points', 'one-ba-auctions' ),
				'points_suffix'   => __( 'pts', 'one-ba-auctions' ),
				'bid_cost_label'  => __( 'Bid cost', 'one-ba-auctions' ),
				'your_bids_label' => __( 'Your bids', 'one-ba-auctions' ),
				'your_cost_label' => __( 'Your cost', 'one-ba-auctions' ),
				'you_leading'     => __( 'You are leading', 'one-ba-auctions' ),
				'claim_button'    => __( 'Claim now', 'one-ba-auctions' ),
				'notify_bid_placed' => __( 'Bid placed', 'one-ba-auctions' ),
				'notify_bid_failed' => __( 'Bid failed', 'one-ba-auctions' ),
				'notify_claim_started' => __( 'Claim started', 'one-ba-auctions' ),
				'notify_claim_failed' => __( 'Claim failed', 'one-ba-auctions' ),
				'notify_registration_success' => __( 'Registered', 'one-ba-auctions' ),
				'notify_registration_fail' => __( 'Registration failed', 'one-ba-auctions' ),
				'notify_cannot_bid' => __( 'Cannot bid', 'one-ba-auctions' ),
				'notify_login_required' => __( 'Please log in to register.', 'one-ba-auctions' ),
				'claim_modal_title' => __( 'Claim prize', 'one-ba-auctions' ),
				'claim_option_gateway' => __( 'Checkout', 'one-ba-auctions' ),
				'claim_continue' => __( 'Continue', 'one-ba-auctions' ),
				'claim_cancel' => __( 'Cancel', 'one-ba-auctions' ),
				'claim_error' => __( 'Claim failed. Please try again.', 'one-ba-auctions' ),
				'stage2_tip'       => __( 'Lobby filled, short countdown', 'one-ba-auctions' ),
				'stage3_tip'       => __( 'Bid to reset timer', 'one-ba-auctions' ),
				'stage4_tip'       => __( 'See results and claim', 'one-ba-auctions' ),
				'stage1_tip'       => __( 'Register to join', 'one-ba-auctions' ),
				'login_prompt'     => __( 'Please log in or create an account to register.', 'one-ba-auctions' ),
				'login_button'     => __( 'Log in / Create account', 'one-ba-auctions' ),
				'membership_required_title' => __( 'Membership required to register.', 'one-ba-auctions' ),
				'points_low_title' => __( 'Not enough points to continue.', 'one-ba-auctions' ),
				'points_label'    => __( 'Points', 'one-ba-auctions' ),
				'points_suffix'   => __( 'pts', 'one-ba-auctions' ),
				'win_save_prefix' => __( 'You saved around', 'one-ba-auctions' ),
				'win_save_suffix' => __( 'from regular price in other stores.', 'one-ba-auctions' ),
				'lose_save_prefix' => __( 'If you win, you would save around', 'one-ba-auctions' ),
				'lose_save_suffix' => __( 'from regular price in other stores.', 'one-ba-auctions' ),
				'autobid_on_button' => __( 'Aut. statymas įjungtas', 'one-ba-auctions' ),
				'autobid_off_button' => __( 'Autobid off', 'one-ba-auctions' ),
				'autobid_on' => __( 'On', 'one-ba-auctions' ),
				'autobid_off' => __( 'Off', 'one-ba-auctions' ),
				'autobid_saved' => __( 'Autobid updated', 'one-ba-auctions' ),
				'autobid_error' => __( 'Could not update autobid', 'one-ba-auctions' ),
				'autobid_ended' => __( 'Autobid ended', 'one-ba-auctions' ),
				'autobid_confirm' => __( 'Autobid will charge {cost} points and will be enabled for {minutes} minutes in live stage. Proceed?', 'one-ba-auctions' ),
				'remaining' => __( 'Remaining', 'one-ba-auctions' ),
				'registration_closed' => __( 'Registration closed', 'one-ba-auctions' ),
				'autobid_title' => __( 'Autobid', 'one-ba-auctions' ),
				'autobid_cost_hint' => __( 'Autobid activation will deduct points.', 'one-ba-auctions' ),
				'autobid_prompt_title' => __( 'Enable autobid for:', 'one-ba-auctions' ),
				'autobid_set_title' => __( 'Autobid is set for:', 'one-ba-auctions' ),
				'autobid_set' => __( 'Set autobid', 'one-ba-auctions' ),
				'autobid_edit' => __( 'Edit autobid', 'one-ba-auctions' ),
				'autobid_on_badge' => __( 'ON', 'one-ba-auctions' ),
				'autobid_off_badge' => __( 'OFF', 'one-ba-auctions' ),
				'outbid_label' => __( 'Outbid', 'one-ba-auctions' ),
				'autobid_limitless_label' => __( 'Unlimited autobid', 'one-ba-auctions' ),
				'autobid_window_title' => __( 'Enable autobid for:', 'one-ba-auctions' ),
				'autobid_window_10' => __( '10m', 'one-ba-auctions' ),
				'autobid_window_30' => __( '30m', 'one-ba-auctions' ),
				'autobid_window_60' => __( '60m', 'one-ba-auctions' ),
				'autobid_window_select' => __( 'Select a time window to enable autobid.', 'one-ba-auctions' ),
				'live_join_cta' => __( 'Participate in auction', 'one-ba-auctions' ),
				'participate_cta' => __( 'Participate in auction', 'one-ba-auctions' ),
				'live_terms_label' => __( 'T&C must be accepted before participating', 'one-ba-auctions' ),
				'guest_banner_title' => __( 'Please log in or create an account to register.', 'one-ba-auctions' ),
				'guest_banner_button' => __( 'Log in / Create account', 'one-ba-auctions' ),
			);

		$translations = array();
		foreach ( $defaults as $key => $default ) {
			$translations[ $key ] = isset( $data[ $key ] ) ? sanitize_text_field( wp_unslash( $data[ $key ] ) ) : $default;
		}

		$settings['translations'] = $translations;
		update_option( self::OPTION_KEY, $settings );

		return $settings;
	}

	private static function sanitize_email_templates( $templates ) {
		$clean = array();
		foreach ( (array) $templates as $key => $tpl ) {
			$clean[ $key ] = array(
				'subject' => isset( $tpl['subject'] ) ? sanitize_text_field( wp_unslash( $tpl['subject'] ) ) : '',
				'body'    => isset( $tpl['body'] ) ? wp_kses_post( $tpl['body'] ) : '',
			);
		}
		return $clean;
	}
}
