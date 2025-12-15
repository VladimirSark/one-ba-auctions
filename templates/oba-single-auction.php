<?php
/**
 * Auction single product template (redesigned).
 *
 * @var WC_Product $product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reg_points     = (float) get_post_meta( $product->get_id(), '_registration_points', true );
$bid_product_id = (int) get_post_meta( $product->get_id(), '_bid_product_id', true );
$bid_price      = $bid_product_id ? wc_get_product( $bid_product_id ) : null;
$bid_price_text = ( $bid_price && $bid_price->get_price() !== '' ) ? wc_price( $bid_price->get_price() ) : '';
$settings = OBA_Settings::get_settings();
$tr       = isset( $settings['translations'] ) ? $settings['translations'] : array();
$get      = function( $key, $default ) use ( $tr ) {
	return ! empty( $tr[ $key ] ) ? $tr[ $key ] : $default;
};
$points_suffix = $get( 'points_suffix', __( 'pts', 'one-ba-auctions' ) );
$product_cost  = (float) get_post_meta( $product->get_id(), '_product_cost', true );
$meta     = array(
	'registration_fee' => $reg_points ? $reg_points . ' ' . $points_suffix : '',
	'bid_cost'         => $bid_price_text,
	'claim_price'      => '',
);
$stage_tips = array(
	'registration' => isset( $tr['stage1_tip'] ) ? $tr['stage1_tip'] : '',
	'pre_live' => isset( $tr['stage2_tip'] ) ? $tr['stage2_tip'] : '',
	'live'     => isset( $tr['stage3_tip'] ) ? $tr['stage3_tip'] : '',
	'ended'    => isset( $tr['stage4_tip'] ) ? $tr['stage4_tip'] : '',
);
?>

<div class="oba-auction-wrap" data-product-cost="<?php echo esc_attr( $product_cost ); ?>">
	<div class="oba-membership-overlay" style="display:none;">
		<div class="oba-lock-overlay__inner">
			<div class="oba-lock-title"><?php echo esc_html( $get( 'membership_required_title', __( 'Membership required to view auction details.', 'one-ba-auctions' ) ) ); ?></div>
			<div class="oba-membership-links"></div>
		</div>
	</div>
	<div class="oba-points-overlay" style="display:none;">
		<div class="oba-lock-overlay__inner">
			<div class="oba-lock-title"><?php echo esc_html( $get( 'points_low_title', __( 'Not enough points to continue. Update membership to get more points.', 'one-ba-auctions' ) ) ); ?></div>
			<div class="oba-membership-links"></div>
		</div>
	</div>
	<div class="oba-layout">
		<div class="oba-col-right">
			<div class="oba-card oba-phase-card" data-step="registration">
				<div class="oba-phase-header">
					<div class="oba-phase-title"><span>1.</span><span class="oba-phase-label"><?php echo esc_html( $get( 'step1_label', __( 'Registration', 'one-ba-auctions' ) ) ); ?></span></div>
					<span class="oba-phase-icon icon-lock" aria-hidden="true" data-tip="<?php echo esc_attr( $stage_tips['registration'] ); ?>">
						<span class="icon icon-check"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'check-circle' ) ); ?></span>
						<span class="icon icon-lock"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'lock' ) ); ?></span>
						<span class="icon icon-up"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'chevron-up' ) ); ?></span>
						<span class="icon icon-down"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'chevron-down' ) ); ?></span>
					</span>
				</div>
				<div class="oba-phase-body">
					<div class="oba-pending-banner" style="display:none;"><?php esc_html_e( 'Registration pending admin approval.', 'one-ba-auctions' ); ?></div>
					<p>
						<?php
						$label = $get( 'registration_fee_label', __( 'Registration fee', 'one-ba-auctions' ) );
						echo wp_kses_post( $label . ( $meta['registration_fee'] ? ': ' . $meta['registration_fee'] : '' ) );
						?>
					</p>
					<div class="oba-bar oba-lobby-bar"><span style="width:0%"></span></div>
					<p class="oba-lobby-count"><?php echo esc_html( $get( 'lobby_progress', __( 'Lobby progress', 'one-ba-auctions' ) ) . ': 0%' ); ?></p>
					<div class="oba-register-note">
						<span class="oba-badge danger oba-not-registered"><?php echo esc_html( $get( 'not_registered_badge', __( 'Not registered', 'one-ba-auctions' ) ) ); ?></span>
						<span class="oba-badge success oba-registered" style="display:none;"><?php echo esc_html( $get( 'registered_badge', __( 'Registered', 'one-ba-auctions' ) ) ); ?></span>
						<span class="oba-badge oba-autobid-pill" style="display:none;"></span>
					</div>
					<?php if ( ! is_user_logged_in() ) : ?>
						<p class="oba-login-hint" style="display:none;" data-login-url="<?php echo esc_url( wp_login_url( get_permalink( $product->get_id() ) ) ); ?>">
							<?php
							printf(
								wp_kses_post( __( 'Please <a href="%s">log in</a> or create an account to register.', 'one-ba-auctions' ) ),
								esc_url( wp_login_url( get_permalink( $product->get_id() ) ) )
							);
							?>
						</p>
						<div class="oba-login-cta" style="display:none;" data-login-url="<?php echo esc_url( $settings['login_link'] ? $settings['login_link'] : wp_login_url( get_permalink( $product->get_id() ) ) ); ?>">
							<div class="oba-login-cta__text"><?php echo esc_html( $get( 'login_prompt', __( 'Please log in or create an account to register.', 'one-ba-auctions' ) ) ); ?></div>
							<a class="button button-primary" href="<?php echo esc_url( $settings['login_link'] ? $settings['login_link'] : wp_login_url( get_permalink( $product->get_id() ) ) ); ?>">
								<?php echo esc_html( $get( 'login_button', __( 'Log in / Create account', 'one-ba-auctions' ) ) ); ?>
							</a>
						</div>
					<?php endif; ?>
					<?php if ( ! empty( $GLOBALS['oba_terms_text'] ) ) : ?>
						<div class="oba-terms">
							<label>
								<input type="checkbox" class="oba-terms-checkbox" />
								<span><a href="#" class="oba-terms-link"><?php esc_html_e( 'T&C must be accepted before registering to auction', 'one-ba-auctions' ); ?></a></span>
							</label>
						</div>
						<div class="oba-terms-overlay" style="display:none;"></div>
						<div class="oba-terms-modal" style="display:none;">
							<div class="oba-terms-modal__inner">
								<button class="oba-terms-close" type="button">&times;</button>
								<div class="oba-terms-content">
									<?php echo wp_kses_post( $GLOBALS['oba_terms_text'] ); ?>
								</div>
							</div>
						</div>
					<?php endif; ?>
					<div class="oba-actions">
						<button class="button button-primary oba-register"><?php echo esc_html( $get( 'register_cta', __( 'Register & Reserve Spot', 'one-ba-auctions' ) ) ); ?></button>
					</div>
					<div class="oba-registered-note" style="display:none;margin-top:8px;">
						<?php echo esc_html( $get( 'register_note', __( 'You are registered, wait for Step 2. Share this auction to reach 100% faster!', 'one-ba-auctions' ) ) ); ?>
						<div class="oba-share-buttons">
							<a class="oba-share-btn oba-share-fb" href="#" data-network="facebook"><?php esc_html_e( 'Share on Facebook', 'one-ba-auctions' ); ?></a>
							<a class="oba-share-btn oba-share-instagram" href="#" data-network="instagram"><?php esc_html_e( 'Share on Instagram', 'one-ba-auctions' ); ?></a>
							<a class="oba-share-btn oba-share-x" href="#" data-network="x"><?php esc_html_e( 'Share on X', 'one-ba-auctions' ); ?></a>
							<a class="oba-share-btn oba-share-copy" href="#" data-network="copy"><?php esc_html_e( 'Copy link', 'one-ba-auctions' ); ?></a>
						</div>
					</div>
					<div class="oba-autobid oba-autobid-registration registration-only" style="display:none;margin-top:12px;padding:10px;border:1px solid #e5e7eb;border-radius:10px;">
						<div class="oba-autobid-heading" style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
							<h4 class="oba-autobid-title" style="margin:0;"><?php esc_html_e( 'Would you like to set autobid?', 'one-ba-auctions' ); ?></h4>
						</div>
						<p class="oba-autobid-help" style="margin:6px 0;font-size:13px;color:#475569;">
							<?php esc_html_e( 'Autobid will place bids automatically for you. It does not guarantee a win if others set autobid later or set higher values.', 'one-ba-auctions' ); ?>
						</p>
						<div class="oba-autobid-actions" style="display:flex;gap:8px;align-items:center;">
							<button type="button" class="button button-secondary oba-autobid-config"><?php esc_html_e( 'Set autobid', 'one-ba-auctions' ); ?></button>
						</div>
						<p class="oba-autobid-status" style="display:none;"></p>
						<p class="oba-autobid-total" style="display:none;"></p>
						<input type="checkbox" id="oba-autobid-enabled" style="display:none;" />
					</div>
				</div>
			</div>

			<div class="oba-card oba-phase-card is-collapsed" data-step="pre_live">
				<div class="oba-phase-header">
					<div class="oba-phase-title"><span>2.</span><span class="oba-phase-label"><?php echo esc_html( $get( 'step2_label', __( 'Countdown to Live', 'one-ba-auctions' ) ) ); ?></span></div>
					<span class="oba-phase-icon icon-lock" aria-hidden="true" data-tip="<?php echo esc_attr( $stage_tips['pre_live'] ); ?>">
						<span class="icon icon-check"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'check-circle' ) ); ?></span>
						<span class="icon icon-lock"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'lock' ) ); ?></span>
						<span class="icon icon-up"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'chevron-up' ) ); ?></span>
						<span class="icon icon-down"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'chevron-down' ) ); ?></span>
					</span>
				</div>
				<div class="oba-phase-body">
					<p class="oba-badge info"><?php echo esc_html( $get( 'prelive_hint', __( 'Auction is about to go live', 'one-ba-auctions' ) ) ); ?></p>
					<div class="oba-timer-large oba-prelive-seconds">0s</div>
					<div class="oba-bar oba-prelive-bar"><span style="width:0%"></span></div>
				</div>
			</div>

			<div class="oba-card oba-phase-card is-collapsed" data-step="live">
				<div class="oba-phase-header">
					<div class="oba-phase-title"><span>3.</span><span class="oba-phase-label"><?php echo esc_html( $get( 'step3_label', __( 'Live Bidding', 'one-ba-auctions' ) ) ); ?></span></div>
					<span class="oba-phase-icon icon-lock" aria-hidden="true" data-tip="<?php echo esc_attr( $stage_tips['live'] ); ?>">
						<span class="icon icon-check"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'check-circle' ) ); ?></span>
						<span class="icon icon-lock"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'lock' ) ); ?></span>
						<span class="icon icon-up"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'chevron-up' ) ); ?></span>
						<span class="icon icon-down"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'chevron-down' ) ); ?></span>
					</span>
				</div>
				<div class="oba-phase-body">
					<p>
						<?php
						$bid_label = $get( 'bid_cost_label', __( 'Bid cost', 'one-ba-auctions' ) );
						echo wp_kses_post( $bid_label . ( $meta['bid_cost'] ? ': ' . $meta['bid_cost'] : '' ) );
						?>
					</p>
					<div class="oba-timer-large"><span class="oba-live-seconds">0</span></div>
					<div class="oba-bar oba-live-bar"><span style="width:0%"></span></div>
					<div class="oba-legend">
						<div class="oba-card">
							<div class="oba-legend-label"><?php echo esc_html( $get( 'your_bids_label', __( 'Your bids', 'one-ba-auctions' ) ) ); ?></div>
							<div class="oba-legend-value oba-user-bids">0</div>
						</div>
						<div class="oba-card">
							<div class="oba-legend-label"><?php echo esc_html( $get( 'your_cost_label', __( 'Your cost', 'one-ba-auctions' ) ) ); ?></div>
							<div class="oba-legend-value oba-user-cost">0</div>
						</div>
						<div class="oba-card oba-autobid-card">
							<div class="oba-legend-label"><?php esc_html_e( 'Autobid', 'one-ba-auctions' ); ?></div>
							<div class="oba-legend-value oba-autobid-value"><?php esc_html_e( 'Off', 'one-ba-auctions' ); ?></div>
							<div class="oba-toggle-wrap" style="margin-top:6px;">
								<label class="oba-switch">
									<input type="checkbox" class="oba-autobid-switch" />
									<span class="oba-slider round"></span>
								</label>
							</div>
						</div>
						<div class="oba-card oba-bidder-status-card">
							<div class="oba-legend-label"><?php esc_html_e( 'Status', 'one-ba-auctions' ); ?></div>
							<div class="oba-legend-value oba-bidder-status-pill" style="font-size:16px;padding:6px 10px;border-radius:10px;"><?php esc_html_e( 'Outbid', 'one-ba-auctions' ); ?></div>
						</div>
					</div>
					<div class="oba-history-head">
						<span><?php esc_html_e( 'Last bidder', 'one-ba-auctions' ); ?></span>
						<span class="oba-history-time-head"><?php esc_html_e( 'Time', 'one-ba-auctions' ); ?></span>
						<span class="oba-history-value-head"><?php esc_html_e( "Bid's value", 'one-ba-auctions' ); ?></span>
					</div>
					<ul class="oba-history"></ul>
					<div class="oba-actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
						<button class="button button-primary oba-bid"><?php esc_html_e( 'Place bid', 'one-ba-auctions' ); ?></button>
						<button type="button" class="button button-secondary oba-autobid-config"><?php esc_html_e( 'Set autobid', 'one-ba-auctions' ); ?></button>
					</div>
					<div class="oba-autobid live-only" style="margin-top:12px;padding:10px;border:1px solid #e5e7eb;border-radius:10px;">
						<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
							<h4 style="margin:0;"><?php echo esc_html( $get( 'autobid_title', __( 'Autobid', 'one-ba-auctions' ) ) ); ?></h4>
						</div>
						<p class="oba-autobid-status" style="display:none;"></p>
						<p class="oba-autobid-total" style="display:none;"></p>
						<input type="checkbox" id="oba-autobid-enabled" style="display:none;" />
					</div>
				</div>
			</div>

			<div class="oba-card oba-phase-card is-collapsed" data-step="ended">
				<div class="oba-phase-header">
					<div class="oba-phase-title"><span>4.</span><span class="oba-phase-label"><?php echo esc_html( $get( 'step4_label', __( 'Auction Ended', 'one-ba-auctions' ) ) ); ?></span></div>
					<span class="oba-phase-icon icon-lock" aria-hidden="true" data-tip="<?php echo esc_attr( $stage_tips['ended'] ); ?>">
						<span class="icon icon-check"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'check-circle' ) ); ?></span>
						<span class="icon icon-lock"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'lock' ) ); ?></span>
						<span class="icon icon-up"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'chevron-up' ) ); ?></span>
						<span class="icon icon-down"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'chevron-down' ) ); ?></span>
					</span>
				</div>
				<div class="oba-phase-body">
					<div class="oba-winner-claim" style="display:none;">
						<div class="oba-outcome oba-outcome--win">
							<h4 class="oba-win-title"><?php echo esc_html( $get( 'winner_msg', __( 'You won!', 'one-ba-auctions' ) ) ); ?></h4>
							<p class="oba-win-stat oba-win-bids"><?php esc_html_e( 'Bids placed:', 'one-ba-auctions' ); ?> <span class="oba-win-bids-count">0</span></p>
							<p class="oba-win-stat oba-win-value"><?php esc_html_e( 'Bids value:', 'one-ba-auctions' ); ?> <span class="oba-win-bids-value">0</span></p>
							<p class="oba-win-save" style="display:none;">
								<span class="oba-save-prefix"><?php esc_html_e( 'You saved around', 'one-ba-auctions' ); ?></span>
								<span class="oba-save-highlight oba-win-save-value">0</span>
								<span class="oba-save-suffix"><?php esc_html_e( 'from regular price in other stores.', 'one-ba-auctions' ); ?></span>
							</p>
							<div class="oba-claim-status" style="display:none;"></div>
							<button class="button button-primary oba-claim"><?php echo esc_html( $get( 'claim_button', __( 'Claim now', 'one-ba-auctions' ) ) ); ?> <span class="oba-claim-amount"></span></button>
						</div>
					</div>
					<div class="oba-loser" style="display:none;">
						<div class="oba-outcome oba-outcome--lose">
							<h4 class="oba-lose-title"><?php echo esc_html( $get( 'loser_msg', __( 'You did not win this auction.', 'one-ba-auctions' ) ) ); ?></h4>
							<p class="oba-lose-stat oba-lose-bids"><?php esc_html_e( 'Bids placed:', 'one-ba-auctions' ); ?> <span class="oba-lose-bids-count">0</span></p>
							<p class="oba-lose-stat oba-lose-value"><?php esc_html_e( 'Bids value:', 'one-ba-auctions' ); ?> <span class="oba-lose-bids-value">0</span></p>
							<p class="oba-lose-save" style="display:none;">
								<span class="oba-save-prefix"><?php esc_html_e( 'If you win, you would save around', 'one-ba-auctions' ); ?></span>
								<span class="oba-save-highlight oba-lose-save-value">0</span>
								<span class="oba-save-suffix"><?php esc_html_e( 'from regular price in other stores.', 'one-ba-auctions' ); ?></span>
							</p>
							<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Return to home page', 'one-ba-auctions' ); ?></a>
						</div>
					</div>
				</div>
			</div>

			<div class="oba-alert oba-alert-error" style="display:none;"></div>
			<div class="oba-alert oba-alert-info oba-success-banner" style="display:none;"></div>
			<div class="oba-toast" role="alert"></div>
			<div class="oba-last-refreshed" style="display:none;"></div>
		</div>
	</div>
</div>

<div class="oba-modal-overlay" style="display:none;"></div>

<div class="oba-credit-overlay" style="display:none;"></div>
<div class="oba-credit-modal" style="display:none;">
	<div class="oba-credit-modal__inner">
		<button class="oba-credit-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'one-ba-auctions' ); ?>">&times;</button>
		<h4><?php echo esc_html( $get( 'buy_credits_title', __( 'Buy credits', 'one-ba-auctions' ) ) ); ?></h4>
		<div class="oba-credit-options"></div>
	</div>
</div>

<div class="oba-info-overlay" style="display:none;"></div>
<div class="oba-info-modal" style="display:none;">
	<div class="oba-info-modal__inner">
		<button class="oba-info-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'one-ba-auctions' ); ?>">&times;</button>
		<h4><?php esc_html_e( 'Auction steps', 'one-ba-auctions' ); ?></h4>
		<div class="oba-info-content">
			<?php echo wp_kses_post( $settings['status_info_html'] ); ?>
		</div>
	</div>
</div>

<div class="oba-autobid-overlay" style="display:none;"></div>
<div class="oba-autobid-modal" style="display:none;">
	<div class="oba-autobid-modal__inner">
		<button class="oba-autobid-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'one-ba-auctions' ); ?>">&times;</button>
		<h4><?php echo esc_html( $get( 'autobid_title', __( 'Autobid', 'one-ba-auctions' ) ) ); ?></h4>
		<label style="display:flex;flex-direction:column;gap:4px;margin:8px 0;">
			<span><?php esc_html_e( 'Max autobid bids', 'one-ba-auctions' ); ?></span>
			<input type="number" min="1" id="oba-autobid-max-amount" placeholder="5" />
		</label>
		<label style="display:flex;align-items:center;gap:8px;margin:6px 0;">
			<input type="checkbox" id="oba-autobid-limitless" />
			<span><?php esc_html_e( 'Stay on top (no limit)', 'one-ba-auctions' ); ?></span>
		</label>
		<p class="oba-autobid-total oba-autobid-total-modal" style="margin:4px 0;font-size:12px;color:#0f172a;font-weight:700;"></p>
		<p class="oba-autobid-cost" style="margin:6px 0 0;font-size:12px;color:#475569;">
			<?php echo esc_html( $get( 'autobid_cost_hint', __( 'Enabling autobid will charge points and stay active for a limited time.', 'one-ba-auctions' ) ) ); ?>
		</p>
		<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px;">
			<button type="button" class="button oba-autobid-close"><?php esc_html_e( 'Cancel', 'one-ba-auctions' ); ?></button>
			<button type="button" class="button button-primary oba-autobid-toggle" aria-pressed="false"><?php esc_html_e( 'Enable autobid', 'one-ba-auctions' ); ?></button>
		</div>
	</div>
</div>
