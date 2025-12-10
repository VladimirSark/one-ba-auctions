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
			'translations'            => isset( $data['translations'] ) && is_array( $data['translations'] ) ? array_map( 'sanitize_text_field', $data['translations'] ) : ( isset( $stored['translations'] ) ? $stored['translations'] : array() ),
			'email_templates'         => isset( $data['email_templates'] ) && is_array( $data['email_templates'] ) ? self::sanitize_email_templates( $data['email_templates'] ) : ( isset( $stored['email_templates'] ) ? $stored['email_templates'] : array() ),
		);

		update_option( self::OPTION_KEY, $new );

		return $new;
	}

	public static function update_translations( $data ) {
		$settings = self::get_settings();
		$defaults = array(
			'step1_label'    => '',
			'step2_label'    => '',
			'step3_label'    => '',
			'step4_label'    => '',
			'step1_desc'     => '',
			'step2_desc'     => '',
			'step3_desc'     => '',
			'step4_desc'     => '',
			'lobby_progress' => '',
			'register_cta'   => '',
			'bid_button'     => '',
			'prelive_hint'   => '',
			'winner_msg'     => '',
			'loser_msg'      => '',
			'refund_msg'     => '',
			'register_note'  => '',
			'registration_fee_label' => '',
			'registered_badge' => '',
			'not_registered_badge' => '',
			'bid_cost_label'  => '',
			'your_bids_label' => '',
			'your_cost_label' => '',
			'you_leading'     => '',
			'claim_button'    => '',
			'notify_bid_placed' => '',
			'notify_bid_failed' => '',
			'notify_claim_started' => '',
			'notify_claim_failed' => '',
			'notify_registration_success' => '',
			'notify_registration_fail' => '',
			'notify_cannot_bid' => '',
			'notify_login_required' => '',
			'claim_modal_title' => '',
			'claim_option_gateway' => '',
			'claim_continue' => '',
			'claim_cancel' => '',
			'claim_error' => '',
			'stage2_tip'       => '',
			'stage3_tip'       => '',
			'stage4_tip'       => '',
			'stage1_tip'       => '',
			'login_prompt'     => '',
			'login_button'     => '',
			'membership_required_title' => '',
			'points_low_title' => '',
			'points_label'    => '',
			'points_suffix'   => '',
			'win_save_prefix' => '',
			'win_save_suffix' => '',
			'lose_save_prefix' => '',
			'lose_save_suffix' => '',
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
