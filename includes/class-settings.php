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
			'credit_pack_links'       => array( '', '', '' ),
			'credit_pack_labels'      => array( '', '', '' ),
			'login_link'              => '',
			'status_info_html'        => '<ol><li><strong>Registration</strong><br/>Users join the auction by paying the registration fee in credits and securing their spot.</li><li><strong>Time to Live</strong><br/>Once all spots are filled, a short countdown starts before the auction goes live.</li><li><strong>Live</strong><br/>Bidding opens; each bid costs credits and resets the timer—the action continues until no new bids come in.</li><li><strong>End</strong><br/>Claim your reward with credits or other payment providers. If you lose, your credits will be refunded.</li></ol>',
			'email_from_name'         => get_bloginfo( 'name' ),
			'email_from_address'      => get_option( 'admin_email' ),
			'translations'            => array(),
		);
	}

	public static function get_settings() {
		$stored   = get_option( self::OPTION_KEY, array() );
		$defaults = self::defaults();
		return wp_parse_args( $stored, $defaults );
	}

	public static function update_settings( $data ) {
		$defaults = self::defaults();
		$new      = array(
			'default_prelive_seconds' => isset( $data['default_prelive_seconds'] ) ? max( 1, (int) $data['default_prelive_seconds'] ) : $defaults['default_prelive_seconds'],
			'default_live_seconds'    => isset( $data['default_live_seconds'] ) ? max( 1, (int) $data['default_live_seconds'] ) : $defaults['default_live_seconds'],
			'poll_interval_ms'        => isset( $data['poll_interval_ms'] ) ? max( 500, (int) $data['poll_interval_ms'] ) : $defaults['poll_interval_ms'],
			'terms_text'              => isset( $data['terms_text'] ) ? wp_kses_post( $data['terms_text'] ) : $defaults['terms_text'],
			'show_header_balance'     => ! empty( $data['show_header_balance'] ),
			'credit_pack_links'       => array(
				isset( $data['credit_pack_link_1'] ) ? esc_url_raw( $data['credit_pack_link_1'] ) : '',
				isset( $data['credit_pack_link_2'] ) ? esc_url_raw( $data['credit_pack_link_2'] ) : '',
				isset( $data['credit_pack_link_3'] ) ? esc_url_raw( $data['credit_pack_link_3'] ) : '',
			),
			'credit_pack_labels'      => array(
				isset( $data['credit_pack_label_1'] ) ? sanitize_text_field( $data['credit_pack_label_1'] ) : '',
				isset( $data['credit_pack_label_2'] ) ? sanitize_text_field( $data['credit_pack_label_2'] ) : '',
				isset( $data['credit_pack_label_3'] ) ? sanitize_text_field( $data['credit_pack_label_3'] ) : '',
			),
			'login_link'              => isset( $data['login_link'] ) ? esc_url_raw( $data['login_link'] ) : '',
			'status_info_html'        => isset( $data['status_info_html'] ) ? wp_kses_post( $data['status_info_html'] ) : $defaults['status_info_html'],
			'email_from_name'         => isset( $data['email_from_name'] ) ? sanitize_text_field( wp_unslash( $data['email_from_name'] ) ) : $defaults['email_from_name'],
			'email_from_address'      => isset( $data['email_from_address'] ) ? sanitize_email( wp_unslash( $data['email_from_address'] ) ) : $defaults['email_from_address'],
			'translations'            => isset( $data['translations'] ) && is_array( $data['translations'] ) ? array_map( 'sanitize_text_field', $data['translations'] ) : ( isset( $stored['translations'] ) ? $stored['translations'] : array() ),
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
			'buy_credits_title' => '',
		);

		$translations = array();
		foreach ( $defaults as $key => $default ) {
			$translations[ $key ] = isset( $data[ $key ] ) ? sanitize_text_field( wp_unslash( $data[ $key ] ) ) : $default;
		}

		$settings['translations'] = $translations;
		update_option( self::OPTION_KEY, $settings );

		return $settings;
	}
}
