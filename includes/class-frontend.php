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
				'i18n'         => $this->build_i18n( $settings ),
				)
			);
	}

	private function build_i18n( $settings ) {
		$t = isset( $settings['translations'] ) ? $settings['translations'] : array();
		$credit_singular = ! empty( $t['credit_singular'] ) ? $t['credit_singular'] : __( 'credit', 'one-ba-auctions' );
		$credit_plural   = ! empty( $t['credit_plural'] ) ? $t['credit_plural'] : __( 'credits', 'one-ba-auctions' );
		return array(
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
			'you_leading_custom' => ! empty( $t['you_leading'] ) ? $t['you_leading'] : __( 'You are leading', 'one-ba-auctions' ),
			'buy_credits'       => __( 'Buy credits', 'one-ba-auctions' ),
			'pack_label'        => __( 'Pack', 'one-ba-auctions' ),
			'login_required'    => __( 'Please log in to register.', 'one-ba-auctions' ),
			'register'          => __( 'Register', 'one-ba-auctions' ),
			'register_cta'      => ! empty( $t['register_cta'] ) ? $t['register_cta'] : __( 'Register & Reserve Spot', 'one-ba-auctions' ),
			'lobby_progress'    => ! empty( $t['lobby_progress'] ) ? $t['lobby_progress'] : __( 'Lobby progress', 'one-ba-auctions' ),
			'bid_button'        => ! empty( $t['bid_button'] ) ? $t['bid_button'] : __( 'Place bid', 'one-ba-auctions' ),
			'step1'             => __( 'Step 1. Registration', 'one-ba-auctions' ),
			'step2'             => __( 'Step 2. Pre-Live', 'one-ba-auctions' ),
			'step3'             => __( 'Step 3. Live', 'one-ba-auctions' ),
			'step4'             => __( 'Step 4. Ended', 'one-ba-auctions' ),
			'step1_short'       => __( '1. Registration', 'one-ba-auctions' ),
			'step2_short'       => __( '2. Time to Live', 'one-ba-auctions' ),
			'step3_short'       => __( '3. Live', 'one-ba-auctions' ),
			'step4_short'       => __( '4. End', 'one-ba-auctions' ),
			'step1_label'       => ! empty( $t['step1_label'] ) ? $t['step1_label'] : __( 'Registration', 'one-ba-auctions' ),
			'step2_label'       => ! empty( $t['step2_label'] ) ? $t['step2_label'] : __( 'Countdown to Live', 'one-ba-auctions' ),
			'step3_label'       => ! empty( $t['step3_label'] ) ? $t['step3_label'] : __( 'Live Bidding', 'one-ba-auctions' ),
			'step4_label'       => ! empty( $t['step4_label'] ) ? $t['step4_label'] : __( 'Auction Ended', 'one-ba-auctions' ),
			'step1_desc'        => ! empty( $t['step1_desc'] ) ? $t['step1_desc'] : __( 'Join the lobby with credits.', 'one-ba-auctions' ),
			'step2_desc'        => ! empty( $t['step2_desc'] ) ? $t['step2_desc'] : __( 'Short pre-live timer.', 'one-ba-auctions' ),
			'step3_desc'        => ! empty( $t['step3_desc'] ) ? $t['step3_desc'] : __( 'Bid, reset timer, compete.', 'one-ba-auctions' ),
			'step4_desc'        => ! empty( $t['step4_desc'] ) ? $t['step4_desc'] : __( 'Claim or view results.', 'one-ba-auctions' ),
			'prelive_hint'      => ! empty( $t['prelive_hint'] ) ? $t['prelive_hint'] : __( 'Auction is about to go live', 'one-ba-auctions' ),
			'winner_msg'        => ! empty( $t['winner_msg'] ) ? $t['winner_msg'] : __( 'You won! Claim price:', 'one-ba-auctions' ),
			'loser_msg'         => ! empty( $t['loser_msg'] ) ? $t['loser_msg'] : __( 'You did not win this auction.', 'one-ba-auctions' ),
			'refund_msg'        => ! empty( $t['refund_msg'] ) ? $t['refund_msg'] : __( 'Your reserved credits have been refunded.', 'one-ba-auctions' ),
			'register_note'     => ! empty( $t['register_note'] ) ? $t['register_note'] : __( 'You are registered, wait for Step 2. Share this auction to reach 100% faster!', 'one-ba-auctions' ),
			'buy_credits_title' => ! empty( $t['buy_credits_title'] ) ? $t['buy_credits_title'] : __( 'Buy credits', 'one-ba-auctions' ),
			'credit_singular'   => $credit_singular,
			'credit_plural'     => $credit_plural,
			'bid_cost_label'    => ! empty( $t['bid_cost_label'] ) ? $t['bid_cost_label'] : __( 'Bid cost', 'one-ba-auctions' ),
			'your_bids_label'   => ! empty( $t['your_bids_label'] ) ? $t['your_bids_label'] : __( 'Your bids', 'one-ba-auctions' ),
			'your_cost_label'   => ! empty( $t['your_cost_label'] ) ? $t['your_cost_label'] : __( 'Your cost', 'one-ba-auctions' ),
			'claim_button'      => ! empty( $t['claim_button'] ) ? $t['claim_button'] : __( 'Claim now', 'one-ba-auctions' ),
			'registration_fail_custom' => ! empty( $t['notify_registration_fail'] ) ? $t['notify_registration_fail'] : '',
			'registration_success_custom' => ! empty( $t['notify_registration_success'] ) ? $t['notify_registration_success'] : '',
			'bid_placed_custom' => ! empty( $t['notify_bid_placed'] ) ? $t['notify_bid_placed'] : '',
			'bid_failed_custom' => ! empty( $t['notify_bid_failed'] ) ? $t['notify_bid_failed'] : '',
			'claim_started_custom' => ! empty( $t['notify_claim_started'] ) ? $t['notify_claim_started'] : '',
			'claim_failed_custom' => ! empty( $t['notify_claim_failed'] ) ? $t['notify_claim_failed'] : '',
			'cannot_bid_custom' => ! empty( $t['notify_cannot_bid'] ) ? $t['notify_cannot_bid'] : '',
			'login_required_custom' => ! empty( $t['notify_login_required'] ) ? $t['notify_login_required'] : '',
			'claim_modal_title' => ! empty( $t['claim_modal_title'] ) ? $t['claim_modal_title'] : __( 'Choose how to claim', 'one-ba-auctions' ),
			'claim_option_credits' => ! empty( $t['claim_option_credits'] ) ? $t['claim_option_credits'] : __( 'Pay with credits', 'one-ba-auctions' ),
			'claim_option_gateway' => ! empty( $t['claim_option_gateway'] ) ? $t['claim_option_gateway'] : __( 'Pay via checkout', 'one-ba-auctions' ),
			'claim_continue' => ! empty( $t['claim_continue'] ) ? $t['claim_continue'] : __( 'Continue', 'one-ba-auctions' ),
			'claim_cancel' => ! empty( $t['claim_cancel'] ) ? $t['claim_cancel'] : __( 'Cancel', 'one-ba-auctions' ),
			'claim_error' => ! empty( $t['claim_error'] ) ? $t['claim_error'] : __( 'Claim failed. Please try again.', 'one-ba-auctions' ),
			'credits_pill_label' => ! empty( $t['credits_pill_label'] ) ? $t['credits_pill_label'] : __( 'Credits', 'one-ba-auctions' ),
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

		$translations    = isset( $settings['translations'] ) ? $settings['translations'] : array();
		$pill_label      = ! empty( $translations['credits_pill_label'] ) ? $translations['credits_pill_label'] : __( 'Credits', 'one-ba-auctions' );
		$buy_title       = ! empty( $translations['buy_credits_title'] ) ? $translations['buy_credits_title'] : __( 'Buy credits', 'one-ba-auctions' );

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
		echo '<div class="oba-credit-pill' . esc_attr( $low_class ) . '" data-balance="' . esc_attr( $balance ) . '"><span class="oba-credit-balance">' . esc_html( $pill_label ) . ': ' . esc_html( $balance ) . '</span><span class="oba-credit-links">' . $html_links . '</span></div>';

		if ( $render_modal ) {
			echo '<div class="oba-credit-overlay" style="display:none;"></div><div class="oba-credit-modal" style="display:none;"><div class="oba-credit-modal__inner"><button class="oba-credit-close" type="button" aria-label="' . esc_attr__( 'Close', 'one-ba-auctions' ) . '">&times;</button><h4>' . esc_html( $buy_title ) . '</h4><div class="oba-credit-options"></div></div></div>';
		}

		// Ensure styling is present on non-auction pages where main stylesheet may not enqueue.
		echo '<style>
		.oba-credit-pill{position:fixed;right:16px;bottom:16px;background:#0f172a;color:#fff;padding:10px 14px;border-radius:999px;font-weight:700;z-index:120005;box-shadow:0 10px 24px rgba(0,0,0,0.25);display:flex;align-items:center;gap:8px;cursor:pointer;white-space:nowrap;}
		.oba-credit-pill.low{background:#b91c1c;}
		.oba-credit-pill .oba-credit-links{display:none;}
		.oba-credit-overlay{position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.4);z-index:120000;display:none;}
		.oba-credit-modal{position:fixed;left:50%;top:120px;transform:translateX(-50%);background:#fff;border-radius:10px;box-shadow:0 20px 40px rgba(0,0,0,0.25);z-index:120001;min-width:260px;max-width:90%;max-height:calc(100vh - 200px);overflow:auto;padding:16px;display:none;}
		.oba-credit-options a{display:inline-block;padding:8px 12px;border-radius:8px;background:#0f172a;color:#fff;text-decoration:none;font-weight:600;box-shadow:0 4px 10px rgba(0,0,0,0.12);}
		@media(max-width:480px){.oba-credit-pill{right:10px;bottom:10px;padding:8px 12px;font-size:12px;}}
		</style>';

		// Minimal JS to open modal on non-auction pages.
		echo '<script>
		(function(){
			var pill=document.querySelector(".oba-credit-pill");
			var overlay=document.querySelector(".oba-credit-overlay");
			var modal=document.querySelector(".oba-credit-modal");
			var close=document.querySelector(".oba-credit-close");
			if(!pill){return;}
			var opts=document.querySelector(".oba-credit-options");
			if(opts){opts.innerHTML="";';
		foreach ( $links as $idx => $url ) {
			if ( empty( $url ) ) {
				continue;
			}
			$label = ! empty( $labels[ $idx ] ) ? $labels[ $idx ] : sprintf( __( 'Pack %d', 'one-ba-auctions' ), $idx + 1 );
			echo 'opts.insertAdjacentHTML("beforeend","<a href=\'' . esc_url( $url ) . '\' target=\'_blank\' rel=\'noopener\'>' . esc_html( $label ) . '</a>");';
		}
		echo '}
			function open(){ if(overlay) overlay.style.display="block"; if(modal) modal.style.display="block"; }
			function closeModal(){ if(overlay) overlay.style.display="none"; if(modal) modal.style.display="none"; }
			pill.addEventListener("click",function(e){ if(e.target.closest("a")) return; open();});
			if(overlay){ overlay.addEventListener("click",closeModal); }
			if(close){ close.addEventListener("click",function(e){e.preventDefault(); closeModal();}); }
		})();</script>';
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
