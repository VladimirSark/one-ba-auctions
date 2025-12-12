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
		add_shortcode( 'oba_ended_auctions', array( $this, 'shortcode_ended_auctions' ) );
		add_shortcode( 'oba_upcoming_auctions', array( $this, 'shortcode_upcoming_auctions' ) );
		add_shortcode( 'oba_live_auctions', array( $this, 'shortcode_live_auctions' ) );
		add_shortcode( 'oba_recent_ended_auctions', array( $this, 'shortcode_recent_ended_auctions' ) );
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
			'autobid_window_seconds' => isset( $settings['autobid_window_seconds'] ) ? (int) $settings['autobid_window_seconds'] : 300,
			'autobid_cost_points'    => isset( $settings['autobid_activation_cost_points'] ) ? (int) $settings['autobid_activation_cost_points'] : 0,
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
			'registration_closed'=> __( 'Registration closed', 'one-ba-auctions' ),
			'points_label'      => $points_label,
			'points_suffix'     => $points_suffix,
			'membership_required' => ! empty( $t['membership_required'] ) ? $t['membership_required'] : __( 'A membership plan is required to register.', 'one-ba-auctions' ),
			'membership_cta'      => ! empty( $t['membership_cta'] ) ? $t['membership_cta'] : __( 'Get membership', 'one-ba-auctions' ),
			'lobby_progress'    => ! empty( $t['lobby_progress'] ) ? $t['lobby_progress'] : __( 'Lobby progress', 'one-ba-auctions' ),
			'bid_button'        => ! empty( $t['bid_button'] ) ? $t['bid_button'] : __( 'Place bid', 'one-ba-auctions' ),
			'autobid_on_button' => ! empty( $t['autobid_on_button'] ) ? $t['autobid_on_button'] : __( 'Autobid ON', 'one-ba-auctions' ),
			'autobid_off_button'=> ! empty( $t['autobid_off_button'] ) ? $t['autobid_off_button'] : __( 'Autobid OFF', 'one-ba-auctions' ),
			'autobid_on'        => ! empty( $t['autobid_on'] ) ? $t['autobid_on'] : __( 'Autobid enabled', 'one-ba-auctions' ),
			'autobid_off'       => ! empty( $t['autobid_off'] ) ? $t['autobid_off'] : __( 'Autobid disabled.', 'one-ba-auctions' ),
			'autobid_saved'     => ! empty( $t['autobid_saved'] ) ? $t['autobid_saved'] : __( 'Autobid updated', 'one-ba-auctions' ),
			'autobid_error'     => ! empty( $t['autobid_error'] ) ? $t['autobid_error'] : __( 'Could not update autobid', 'one-ba-auctions' ),
			'autobid_ended'     => ! empty( $t['autobid_ended'] ) ? $t['autobid_ended'] : __( 'Autobid is unavailable after the auction ends.', 'one-ba-auctions' ),
			'remaining'         => ! empty( $t['remaining'] ) ? $t['remaining'] : __( 'Remaining', 'one-ba-auctions' ),
			'autobid_confirm'   => sprintf(
				/* translators: 1: window minutes, 2: points cost */
				! empty( $t['autobid_confirm'] ) ? $t['autobid_confirm'] : __( 'Autobid will charge %2$s points and will be enabled for %1$s minutes in live stage. Proceed?', 'one-ba-auctions' ),
				isset( $settings['autobid_window_seconds'] ) ? ceil( $settings['autobid_window_seconds'] / 60 ) : 5,
				isset( $settings['autobid_activation_cost_points'] ) ? (int) $settings['autobid_activation_cost_points'] : 0
			),
			'registration_closed'=> ! empty( $t['registration_closed'] ) ? $t['registration_closed'] : __( 'Registration closed', 'one-ba-auctions' ),
			'autobid_title'      => ! empty( $t['autobid_title'] ) ? $t['autobid_title'] : __( 'Autobid', 'one-ba-auctions' ),
			'autobid_cost_hint'  => ! empty( $t['autobid_cost_hint'] ) ? $t['autobid_cost_hint'] : __( 'Enabling autobid will charge points and stay active for a limited time.', 'one-ba-auctions' ),
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

	public function shortcode_ended_auctions( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit' => 20,
			),
			$atts
		);
		$limit = max( 1, absint( $atts['limit'] ) );

		$q = new WP_Query(
			array(
				'post_type'      => 'product',
				'posts_per_page' => $limit,
				'post_status'    => array( 'publish' ),
				'meta_query'     => array(
					array(
						'key'   => '_auction_status',
						'value' => 'ended',
					),
				),
			)
		);

		if ( ! $q->have_posts() ) {
			return '<p>' . esc_html__( 'No ended auctions found.', 'one-ba-auctions' ) . '</p>';
		}

		ob_start();
		?>
		<div class="oba-ended-table">
			<div class="oba-ended-row oba-ended-head">
				<span><?php esc_html_e( 'Auction', 'one-ba-auctions' ); ?></span>
				<span><?php esc_html_e( 'Regular price', 'one-ba-auctions' ); ?></span>
				<span><?php esc_html_e( 'Winner bids', 'one-ba-auctions' ); ?></span>
				<span><?php esc_html_e( 'Total paid', 'one-ba-auctions' ); ?></span>
				<span><?php esc_html_e( 'Saved', 'one-ba-auctions' ); ?></span>
				<span><?php esc_html_e( 'Ended at', 'one-ba-auctions' ); ?></span>
			</div>
			<?php
			$repo = new OBA_Auction_Repository();
			while ( $q->have_posts() ) :
				$q->the_post();
				$pid        = get_the_ID();
				$meta       = $repo->get_auction_meta( $pid );
				$product    = wc_get_product( $pid );
				$cost       = (float) get_post_meta( $pid, '_product_cost', true );
				$winner_row = $repo->get_winner_row( $pid );
				$bid_product_id = isset( $meta['bid_product_id'] ) ? (int) $meta['bid_product_id'] : 0;
				$bid_price      = $bid_product_id ? wc_get_product( $bid_product_id ) : null;
				$bid_fee        = ( $bid_price && $bid_price->get_price() !== '' ) ? (float) $bid_price->get_price() : 0;
				$winner_bids    = 0;
				$total_paid     = 0;
				if ( $winner_row ) {
					$totals = $repo->get_bid_totals_by_user( $pid );
					foreach ( $totals as $row ) {
						if ( (int) $row['user_id'] === (int) $winner_row['winner_user_id'] ) {
							$winner_bids = (int) $row['total_bids'];
							$total_paid  = $winner_bids * $bid_fee;
							break;
						}
					}
				}
				$saved    = $cost > 0 ? max( 0, $cost - $total_paid ) : 0;
				$ended_at = get_post_meta( $pid, '_live_expires_at', true );
				?>
				<div class="oba-ended-row">
					<span><a href="<?php echo esc_url( get_permalink( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ); ?></a></span>
					<span><?php echo $cost ? wp_kses_post( wc_price( $cost ) ) : '—'; ?></span>
					<span><?php echo esc_html( $winner_bids ); ?></span>
					<span><?php echo $total_paid ? wp_kses_post( wc_price( $total_paid ) ) : '—'; ?></span>
					<span><?php echo $saved ? wp_kses_post( wc_price( $saved ) ) : '—'; ?></span>
					<span><?php echo $ended_at ? esc_html( $ended_at ) : '—'; ?></span>
				</div>
				<?php
			endwhile;
			wp_reset_postdata();
			?>
		</div>
		<style>
			.oba-ended-table{display:flex;flex-direction:column;gap:8px;margin:12px 0;}
			.oba-ended-row{display:grid;grid-template-columns:2fr repeat(5,1fr);gap:8px;padding:8px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;}
			.oba-ended-head{font-weight:700;background:#f3f4f6;}
			.oba-ended-row span{font-size:13px;line-height:1.3;}
		</style>
		<?php
		return ob_get_clean();
	}

	private function render_auction_cards( $status_list, $limit = 5, $show_timer = false ) {
		$q = new WP_Query(
			array(
				'post_type'      => 'product',
				'posts_per_page' => $limit,
				'post_status'    => array( 'publish' ),
				'meta_query'     => array(
					array(
						'key'   => '_auction_status',
						'value' => (array) $status_list,
						'compare' => 'IN',
					),
				),
				'fields' => 'ids',
			)
		);

		if ( ! $q->have_posts() ) {
			return '<p>' . esc_html__( 'No auctions found.', 'one-ba-auctions' ) . '</p>';
		}

		$repo = new OBA_Auction_Repository();
		ob_start();
		?>
		<div class="oba-shortcode-grid">
			<?php foreach ( $q->posts as $pid ) :
				$meta      = $repo->get_auction_meta( $pid );
				$product   = wc_get_product( $pid );
				if ( ! $product || 'auction' !== $product->get_type() ) {
					continue;
				}
				$required  = isset( $meta['required_participants'] ) ? (int) $meta['required_participants'] : 0;
				$registered= $repo->get_participant_count( $pid );
				$lobby_pct = $required > 0 ? min( 100, (int) floor( ( $registered / max( 1, $required ) ) * 100 ) ) : 0;
				$status    = isset( $meta['auction_status'] ) ? $meta['auction_status'] : '';
				$link      = get_permalink( $pid );
				$live_left = $show_timer ? max( 0, $this->calculate_seconds_left( $meta['live_expires_at'], $meta['live_timer_seconds'] ) ) : 0;
				?>
				<div class="oba-shortcard">
					<h4 class="oba-shortcard__title"><?php echo esc_html( $product->get_name() ); ?></h4>
					<p class="oba-shortcard__status"><?php echo esc_html( ucfirst( $status ) ); ?></p>
					<?php if ( in_array( $status, array( 'registration', 'pre_live' ), true ) ) : ?>
						<div class="oba-shortcard__bar"><span style="width:<?php echo esc_attr( $lobby_pct ); ?>%"></span></div>
						<p class="oba-shortcard__meta"><?php printf( esc_html__( 'Progress: %s%%', 'one-ba-auctions' ), esc_html( $lobby_pct ) ); ?></p>
					<?php endif; ?>
					<?php if ( $show_timer && $status === 'live' ) : ?>
						<p class="oba-shortcard__timer"><?php printf( esc_html__( 'Live ends in %ss', 'one-ba-auctions' ), esc_html( $live_left ) ); ?></p>
					<?php endif; ?>
					<a class="oba-shortcard__btn" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'View auction', 'one-ba-auctions' ); ?></a>
				</div>
			<?php endforeach; ?>
		</div>
		<style>
			.oba-shortcode-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;}
			.oba-shortcard{border:1px solid #e5e7eb;border-radius:10px;padding:12px;background:#fff;box-shadow:0 4px 12px rgba(15,23,42,0.06);}
			.oba-shortcard__title{margin:0 0 6px;font-size:16px;font-weight:700;color:#0f172a;}
			.oba-shortcard__status{margin:0 0 8px;font-size:12px;color:#475569;}
			.oba-shortcard__bar{background:#f1f5f9;border-radius:8px;height:8px;overflow:hidden;margin:6px 0;}
			.oba-shortcard__bar span{display:block;height:100%;background:#0ea5e9;width:0%;}
			.oba-shortcard__meta{margin:0 0 8px;font-size:12px;color:#334155;}
			.oba-shortcard__timer{margin:0 0 8px;font-size:13px;font-weight:700;color:#0f172a;}
			.oba-shortcard__btn{display:inline-block;padding:8px 12px;border-radius:8px;background:#0f172a;color:#fff;text-decoration:none;font-weight:700;}
		</style>
		<?php
		return ob_get_clean();
	}

	public function shortcode_upcoming_auctions( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit' => 6,
			),
			$atts
		);
		return $this->render_auction_cards( array( 'registration', 'pre_live' ), (int) $atts['limit'], false );
	}

	public function shortcode_live_auctions( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit' => 6,
			),
			$atts
		);
		return $this->render_auction_cards( array( 'live' ), (int) $atts['limit'], true );
	}

	private function calculate_seconds_left( $start_time, $duration ) {
		if ( ! $start_time ) {
			return (int) $duration;
		}
		$start = strtotime( $start_time );
		return max( 0, $start - time() );
	}

	public function shortcode_recent_ended_auctions( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit' => 6,
			),
			$atts
		);

		$limit = max( 1, (int) $atts['limit'] );
		$q     = new WP_Query(
			array(
				'post_type'      => 'product',
				'posts_per_page' => $limit,
				'post_status'    => array( 'publish' ),
				'meta_query'     => array(
					array(
						'key'   => '_auction_status',
						'value' => 'ended',
					),
				),
				'orderby' => 'date',
				'order'   => 'DESC',
				'fields'  => 'ids',
			)
		);

		if ( ! $q->have_posts() ) {
			return '<p>' . esc_html__( 'No ended auctions found.', 'one-ba-auctions' ) . '</p>';
		}

		$repo = new OBA_Auction_Repository();
		ob_start();
		?>
		<div class="oba-shortcode-grid">
			<?php foreach ( $q->posts as $pid ) :
				$product = wc_get_product( $pid );
				if ( ! $product || 'auction' !== $product->get_type() ) {
					continue;
				}
				$meta   = $repo->get_auction_meta( $pid );
				$winner = $repo->get_winner_row( $pid );
				$winner_id = $winner ? (int) $winner['winner_user_id'] : 0;
				$winner_bids = $winner ? (int) $winner['total_bids'] : 0;
				$winner_value = $winner ? (float) $winner['total_credits_consumed'] : 0;
				$claim_price = $winner ? (float) $winner['claim_price_credits'] : 0;
				$product_cost = (float) get_post_meta( $pid, '_product_cost', true );
				$saved = $product_cost > 0 ? max( 0, $product_cost - $winner_value ) : 0;
				$ended_at = isset( $winner['created_at'] ) ? $winner['created_at'] : $product->get_date_modified();
				$mask = function( $uid ) {
					$user = get_user_by( 'id', $uid );
					if ( ! $user ) {
						return __( 'Anon', 'one-ba-auctions' );
					}
					$name = $user->display_name ? $user->display_name : $user->user_login;
					return substr( $name, 0, 3 ) . '***' . substr( $uid, -1 );
				};
				?>
				<div class="oba-shortcard">
					<h4 class="oba-shortcard__title"><?php echo esc_html( $product->get_name() ); ?></h4>
					<p class="oba-shortcard__status"><?php esc_html_e( 'Ended', 'one-ba-auctions' ); ?></p>
					<p class="oba-shortcard__meta"><?php printf( esc_html__( 'Winner: %s', 'one-ba-auctions' ), esc_html( $winner_id ? $mask( $winner_id ) : __( 'N/A', 'one-ba-auctions' ) ) ); ?></p>
					<p class="oba-shortcard__meta"><?php printf( esc_html__( 'Bids placed: %s (value: %s)', 'one-ba-auctions' ), esc_html( $winner_bids ), esc_html( wp_strip_all_tags( wc_price( $winner_value ) ) ) ); ?></p>
					<p class="oba-shortcard__meta"><?php printf( esc_html__( 'Saved vs. cost: %s', 'one-ba-auctions' ), esc_html( $product_cost ? wp_strip_all_tags( wc_price( $saved ) ) : __( 'N/A', 'one-ba-auctions' ) ) ); ?></p>
					<p class="oba-shortcard__meta"><?php printf( esc_html__( 'Ended: %s', 'one-ba-auctions' ), esc_html( $ended_at ? ( is_string( $ended_at ) ? $ended_at : $ended_at->date_i18n( 'Y-m-d H:i' ) ) : '' ) ); ?></p>
					<a class="oba-shortcard__btn" href="<?php echo esc_url( get_permalink( $pid ) ); ?>"><?php esc_html_e( 'View auction', 'one-ba-auctions' ); ?></a>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
