<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Frontend {

	public function hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_body_open', array( $this, 'render_header_balance' ) );
		add_shortcode( 'oba_credits_balance', array( $this, 'shortcode_balance' ) );
	}

	public function enqueue_assets() {
		if ( ! is_product() ) {
			return;
		}

		global $product;

		if ( ! $product instanceof WC_Product || 'auction' !== $product->get_type() ) {
			return;
		}

		$settings = OBA_Settings::get_settings();

		wp_enqueue_style(
			'oba-auction',
			OBA_PLUGIN_URL . 'assets/css/auction.css',
			array(),
			OBA_VERSION
		);

		wp_enqueue_script(
			'oba-auction',
			OBA_PLUGIN_URL . 'assets/js/auction.js',
			array( 'jquery' ),
			OBA_VERSION,
			true
		);

		wp_localize_script(
			'oba-auction',
			'obaAuction',
			array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'oba_auction' ),
				'auction_id'   => $product->get_id(),
				'poll_interval'=> (int) $settings['poll_interval_ms'],
				'terms_text'   => wp_kses_post( $settings['terms_text'] ),
				'pack_links'   => $settings['credit_pack_links'],
				'pack_labels'  => $settings['credit_pack_labels'],
				'login_url'    => $settings['login_link'] ? $settings['login_link'] : wp_login_url( get_permalink( $product->get_id() ) ),
				'i18n'         => array(
					'registered'        => __( 'Registered', 'one-ba-auctions' ),
					'registration_fail' => __( 'Registration failed. Please try again.', 'one-ba-auctions' ),
					'bid_placed'        => __( 'Bid placed', 'one-ba-auctions' ),
					'bid_failed'        => __( 'Bid failed. Check connection and try again.', 'one-ba-auctions' ),
					'claim_started'     => __( 'Claim started', 'one-ba-auctions' ),
					'claim_failed'      => __( 'Claim failed. Please try again.', 'one-ba-auctions' ),
					'last_refreshed'    => __( 'Last refreshed', 'one-ba-auctions' ),
					'system_time'       => __( 'System time', 'one-ba-auctions' ),
					'cannot_bid'        => __( 'Cannot bid', 'one-ba-auctions' ),
					'you_leading'       => __( 'You are leading', 'one-ba-auctions' ),
						'buy_credits'       => __( 'Buy credits', 'one-ba-auctions' ),
						'pack_label'        => __( 'Pack', 'one-ba-auctions' ),
						'login_required'    => __( 'Please log in to register.', 'one-ba-auctions' ),
						'register'          => __( 'Register', 'one-ba-auctions' ),
						'step1'             => __( 'Step 1. Registration', 'one-ba-auctions' ),
						'step2'             => __( 'Step 2. Pre-Live', 'one-ba-auctions' ),
						'step3'             => __( 'Step 3. Live', 'one-ba-auctions' ),
						'step4'             => __( 'Step 4. Ended', 'one-ba-auctions' ),
						'step1_short'       => __( '1. Registration', 'one-ba-auctions' ),
						'step2_short'       => __( '2. Time to Live', 'one-ba-auctions' ),
						'step3_short'       => __( '3. Live', 'one-ba-auctions' ),
						'step4_short'       => __( '4. End', 'one-ba-auctions' ),
					),
				)
			);
	}

	public function render_header_balance() {
		$settings = OBA_Settings::get_settings();

		if ( ! $settings['show_header_balance'] ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		// Inline pill and modal are rendered inside the auction template; suppress floating pill everywhere.
		return;
	}

	public function shortcode_balance() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$credits_service = new OBA_Credits_Service();
		$balance         = $credits_service->get_balance( get_current_user_id() );
		return '<span class="oba-credit-shortcode">' . esc_html( $balance ) . '</span>';
	}
}
