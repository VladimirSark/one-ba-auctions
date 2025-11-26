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
						'register_cta'      => __( 'Register & Reserve Spot', 'one-ba-auctions' ),
						'lobby_progress'    => __( 'Lobby progress', 'one-ba-auctions' ),
						'bid_button'        => __( 'Place bid', 'one-ba-auctions' ),
						'step1'             => __( 'Step 1. Registration', 'one-ba-auctions' ),
						'step2'             => __( 'Step 2. Pre-Live', 'one-ba-auctions' ),
						'step3'             => __( 'Step 3. Live', 'one-ba-auctions' ),
						'step4'             => __( 'Step 4. Ended', 'one-ba-auctions' ),
					'step1_short'       => __( '1. Registration', 'one-ba-auctions' ),
					'step2_short'       => __( '2. Time to Live', 'one-ba-auctions' ),
					'step3_short'       => __( '3. Live', 'one-ba-auctions' ),
					'step4_short'       => __( '4. End', 'one-ba-auctions' ),
					'step1_label'       => __( 'Registration', 'one-ba-auctions' ),
					'step2_label'       => __( 'Countdown to Live', 'one-ba-auctions' ),
					'step3_label'       => __( 'Live Bidding', 'one-ba-auctions' ),
					'step4_label'       => __( 'Auction Ended', 'one-ba-auctions' ),
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

		$render_modal = true;
		if ( is_product() ) {
			global $product;
			if ( $product instanceof WC_Product && 'auction' === $product->get_type() ) {
				$render_modal = false; // Template includes its own credit modal.
			}
		}

		$credits_service = new OBA_Credits_Service();
		$balance         = $credits_service->get_balance( get_current_user_id() );
		$is_low          = $balance < 10;

		$links  = $settings['credit_pack_links'];
		$labels = $settings['credit_pack_labels'];
		$html_links = '';
		foreach ( $links as $idx => $url ) {
			if ( empty( $url ) ) {
				continue;
			}
			$label = ! empty( $labels[ $idx ] ) ? $labels[ $idx ] : sprintf( __( 'Pack %d', 'one-ba-auctions' ), $idx + 1 );
			$html_links .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
		}

		$low_class = $is_low ? ' low' : '';
		echo '<div class="oba-credit-pill' . esc_attr( $low_class ) . '" data-balance="' . esc_attr( $balance ) . '"><span class="oba-credit-balance">Credits: ' . esc_html( $balance ) . '</span><span class="oba-credit-links">' . $html_links . '</span></div>';

		if ( $render_modal ) {
			echo '<div class="oba-credit-overlay" style="display:none;"></div><div class="oba-credit-modal" style="display:none;"><div class="oba-credit-modal__inner"><button class="oba-credit-close" type="button" aria-label="' . esc_attr__( 'Close', 'one-ba-auctions' ) . '">&times;</button><h4>' . esc_html__( 'Buy credits', 'one-ba-auctions' ) . '</h4><div class="oba-credit-options"></div></div></div>';
		}

		// Ensure styling is present on non-auction pages where main stylesheet may not enqueue.
		echo '<style>
		.oba-credit-pill{position:fixed;right:16px;bottom:16px;background:#0f172a;color:#fff;padding:10px 14px;border-radius:999px;font-weight:700;z-index:120005;box-shadow:0 10px 24px rgba(0,0,0,0.25);display:flex;align-items:center;gap:8px;cursor:pointer;white-space:nowrap;}
		.oba-credit-pill.low{background:#b91c1c;}
		.oba-credit-pill .oba-credit-links{display:none;}
		@media(max-width:480px){.oba-credit-pill{right:10px;bottom:10px;padding:8px 12px;font-size:12px;}}
		</style>';
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
