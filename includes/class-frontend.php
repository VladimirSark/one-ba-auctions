<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Frontend {

	public function hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_points_pill' ) );
		add_shortcode( 'oba_credits_balance', array( $this, 'shortcode_balance' ) );
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'render_archive_teaser' ), 15 );
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
			'membership_links' => $settings['membership_links'],
			'membership_labels'=> $settings['membership_labels'],
			'login_url'    => $settings['login_link'] ? $settings['login_link'] : wp_login_url( get_permalink( $product->get_id() ) ),
			'i18n'         => $this->build_i18n( $settings ),
			'currency_symbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '€',
			'currency_code'   => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'EUR',
			'currency_decimals' => function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2,
			)
		);
	}

	private function build_i18n( $settings ) {
		$t = isset( $settings['translations'] ) ? $settings['translations'] : array();
		$credit_singular = ! empty( $t['credit_singular'] ) ? $t['credit_singular'] : __( 'credit', 'one-ba-auctions' );
		$credit_plural   = ! empty( $t['credit_plural'] ) ? $t['credit_plural'] : __( 'credits', 'one-ba-auctions' );
		$points_label    = ! empty( $t['points_label'] ) ? $t['points_label'] : __( 'Points', 'one-ba-auctions' );
		$points_suffix   = ! empty( $t['points_suffix'] ) ? $t['points_suffix'] : __( 'pts', 'one-ba-auctions' );
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
			'points_label'      => $points_label,
			'points_suffix'     => $points_suffix,
			'membership_required' => ! empty( $t['membership_required'] ) ? $t['membership_required'] : __( 'A membership plan is required to register.', 'one-ba-auctions' ),
			'membership_cta'      => ! empty( $t['membership_cta'] ) ? $t['membership_cta'] : __( 'Get membership', 'one-ba-auctions' ),
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
			'step1_desc'        => ! empty( $t['step1_desc'] ) ? $t['step1_desc'] : __( 'Join the lobby with points.', 'one-ba-auctions' ),
			'step2_desc'        => ! empty( $t['step2_desc'] ) ? $t['step2_desc'] : __( 'Short pre-live timer.', 'one-ba-auctions' ),
			'step3_desc'        => ! empty( $t['step3_desc'] ) ? $t['step3_desc'] : __( 'Bid, reset timer, compete.', 'one-ba-auctions' ),
			'step4_desc'        => ! empty( $t['step4_desc'] ) ? $t['step4_desc'] : __( 'Claim or view results.', 'one-ba-auctions' ),
			'prelive_hint'      => ! empty( $t['prelive_hint'] ) ? $t['prelive_hint'] : __( 'Auction is about to go live', 'one-ba-auctions' ),
			'winner_msg'        => ! empty( $t['winner_msg'] ) ? $t['winner_msg'] : __( 'You won! Claim price:', 'one-ba-auctions' ),
			'loser_msg'         => ! empty( $t['loser_msg'] ) ? $t['loser_msg'] : __( 'You did not win this auction.', 'one-ba-auctions' ),
			'refund_msg'        => ! empty( $t['refund_msg'] ) ? $t['refund_msg'] : __( 'Your reserved credits have been refunded.', 'one-ba-auctions' ),
			'register_note'     => ! empty( $t['register_note'] ) ? $t['register_note'] : __( 'You are registered, wait for Step 2. Share this auction to reach 100% faster!', 'one-ba-auctions' ),
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
			'claim_modal_title' => '',
			'claim_option_gateway' => '',
			'claim_continue' => '',
			'claim_cancel' => '',
			'claim_error' => '',
			'stage1_tip'       => ! empty( $t['stage1_tip'] ) ? $t['stage1_tip'] : '',
			'stage2_tip'       => ! empty( $t['stage2_tip'] ) ? $t['stage2_tip'] : '',
			'stage3_tip'       => ! empty( $t['stage3_tip'] ) ? $t['stage3_tip'] : '',
			'stage4_tip'       => ! empty( $t['stage4_tip'] ) ? $t['stage4_tip'] : '',
			'membership_required_title' => ! empty( $t['membership_required_title'] ) ? $t['membership_required_title'] : '',
			'points_low_title' => ! empty( $t['points_low_title'] ) ? $t['points_low_title'] : '',
			'points_label'      => ! empty( $t['points_label'] ) ? $t['points_label'] : __( 'Points', 'one-ba-auctions' ),
			'points_suffix'     => ! empty( $t['points_suffix'] ) ? $t['points_suffix'] : __( 'pts', 'one-ba-auctions' ),
			'win_save_prefix'   => ! empty( $t['win_save_prefix'] ) ? $t['win_save_prefix'] : __( 'You saved around', 'one-ba-auctions' ),
			'win_save_suffix'   => ! empty( $t['win_save_suffix'] ) ? $t['win_save_suffix'] : __( 'from regular price in other stores.', 'one-ba-auctions' ),
			'lose_save_prefix'  => ! empty( $t['lose_save_prefix'] ) ? $t['lose_save_prefix'] : __( 'If you win, you would save around', 'one-ba-auctions' ),
			'lose_save_suffix'  => ! empty( $t['lose_save_suffix'] ) ? $t['lose_save_suffix'] : __( 'from regular price in other stores.', 'one-ba-auctions' ),
		);
	}

	public function render_points_pill() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$has_membership = get_user_meta( get_current_user_id(), '_oba_has_membership', true );
		if ( ! $has_membership ) {
			return;
		}

		$settings       = OBA_Settings::get_settings();
		$points_service = new OBA_Points_Service();
		$balance        = $points_service->get_balance( get_current_user_id() );
		$tr             = isset( $settings['translations'] ) ? $settings['translations'] : array();
		$label          = ! empty( $tr['points_label'] ) ? $tr['points_label'] : __( 'Points', 'one-ba-auctions' );
		?>
		<div class="oba-credit-pill oba-credit-floating" aria-live="polite">
			<span class="oba-credit-label"><?php echo esc_html( $label ); ?></span>
			<span class="oba-credit-amount"><?php echo esc_html( $balance ); ?></span>
		</div>
		<style>
			/* Minimal styles for floating pill on non-auction pages */
			.oba-credit-pill{display:inline-flex;align-items:center;gap:8px;background:#111827;color:#fff;padding:10px 14px;border-radius:999px;font-weight:700;box-shadow:0 8px 20px rgba(15,23,42,0.18);}
			.oba-credit-label{opacity:.85;font-weight:600;}
			.oba-credit-floating{position:fixed;right:16px;bottom:16px;z-index:9999;}
			@media(max-width:640px){.oba-credit-pill{padding:8px 12px;font-size:12px;}}
		</style>
		<?php
	}

	public function shortcode_balance() {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$credits_service = new OBA_Credits_Service();
		$balance         = $credits_service->get_balance( get_current_user_id() );
		return '<span class="oba-credit-shortcode">' . esc_html( $balance ) . '</span>';
	}

	public function render_archive_teaser() {
		global $product;

		if ( ! $product instanceof WC_Product || 'auction' !== $product->get_type() ) {
			return;
		}

		$repo         = new OBA_Auction_Repository();
		$auction_id   = $product->get_id();
		$meta         = $repo->get_auction_meta( $auction_id );
		$required     = isset( $meta['required_participants'] ) ? (int) $meta['required_participants'] : 0;
		$registered   = $repo->get_participant_count( $auction_id );
		$lobby_pct    = $required > 0 ? round( ( $registered / max( 1, $required ) ) * 100 ) : 0;
		$status       = isset( $meta['auction_status'] ) ? $meta['auction_status'] : 'registration';
		$reg_points   = isset( $meta['registration_points'] ) ? (float) $meta['registration_points'] : 0;
		$status_label = ucfirst( $status );
		?>
		<div class="oba-loop-teaser" aria-label="<?php esc_attr_e( 'Auction summary', 'one-ba-auctions' ); ?>">
			<span class="oba-loop-pill"><?php esc_html_e( 'Auction', 'one-ba-auctions' ); ?></span>
			<span class="oba-loop-status"><?php echo esc_html( $status_label ); ?></span>
			<span class="oba-loop-lobby"><?php printf( esc_html__( 'Lobby: %s%%', 'one-ba-auctions' ), esc_html( $lobby_pct ) ); ?></span>
			<span class="oba-loop-reg"><?php printf( esc_html__( 'Reg: %s pts', 'one-ba-auctions' ), esc_html( $reg_points ) ); ?></span>
		</div>
		<style>
			.oba-loop-teaser{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;font-size:12px;align-items:center;}
			.oba-loop-teaser span{background:#f3f4f6;border:1px solid #e5e7eb;border-radius:999px;padding:4px 8px;color:#111827;font-weight:600;}
			.oba-loop-pill{background:#111827;color:#fff;border-color:#111827;}
		</style>
		<?php
	}
}
