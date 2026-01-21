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
	<style>
	.oba-guest-banner {
		display: none;
		background: #fff;
		color: #0f172a;
		padding: 14px 16px;
		border-radius: 12px;
		margin-bottom: 12px;
		box-shadow: 0 10px 30px rgba(15,23,42,0.06);
		border: 1px solid #e5e7eb;
		align-items: center;
		gap: 12px;
		justify-content: space-between;
		position: relative;
		z-index: 9500;
	}
	.oba-guest-banner h4 { margin: 0; font-size: 16px; color: #0f172a; }
	.oba-guest-banner p { margin: 0; opacity: 0.85; color: #334155; }
	.oba-guest-banner a.button {
		background: #0f172a;
		border-color: #0f172a;
		color: #fff;
		margin-top: 15px;
	}
	.oba-guest-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; min-width: 220px; }
	.oba-guest-social { width: 100%; }
	.oba-guest-blur { filter: blur(2px); opacity: 0.9; }
	.oba-autobid-card {
		display: flex;
		flex-direction: row;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		padding: 16px;
		background: #ffffff;
		border-radius: 12px;
		border: 1px solid #e2e8f0;
	}
	.oba-autobid-card > div {
		display: flex;
		flex-direction: row;
		align-items: center;
		gap: 8px;
	}
	.oba-autobid-card h4 {
		margin: 0;
		font-weight: 700;
		font-size: 15px;
	}
	.oba-autobid-toggle-col { margin-left: auto; }
	.oba-toggle {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		cursor: pointer;
		user-select: none;
	}
	.oba-toggle input {
		display: none;
	}
	.oba-toggle-slider {
		width: 46px;
		height: 24px;
		background: #cbd5e1;
		border-radius: 999px;
		position: relative;
		transition: background 0.2s ease;
	}
	.oba-toggle-slider:after {
		content: '';
		position: absolute;
		top: 3px;
		left: 3px;
		width: 18px;
		height: 18px;
		background: #ffffff;
		border-radius: 50%;
		box-shadow: 0 1px 3px rgba(0,0,0,0.2);
		transition: transform 0.2s ease;
	}
	.oba-toggle input:checked + .oba-toggle-slider {
		background: #22c55e;
	}
	.oba-toggle input:checked + .oba-toggle-slider:after {
		transform: translateX(22px);
	}
	.oba-toggle-text {
		font-weight: 600;
		color: #0f172a;
	}
	.oba-autobid-window { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:8px; }
	.oba-autobid-window button {
		border:1px solid #e2e8f0;
		background:#f8fafc;
		border-radius:8px;
		padding:6px 10px;
		cursor:pointer;
		font-weight:600;
		color:#0f172a;
	}
	.oba-autobid-window button.is-active { background:#0f172a; color:#fff; border-color:#0f172a; }
	.oba-autobid-window-remaining { font-size:13px; color:#475569; display:inline-block; }
	.oba-autobid-window-overlay { position:fixed; inset:0; background:rgba(15,23,42,0.35); display:none; z-index:12000; }
	.oba-autobid-window-modal { position:fixed; inset:0; display:none; z-index:12001; align-items:center; justify-content:center; }
	.oba-autobid-window-modal__inner { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; width:320px; box-shadow:0 15px 40px rgba(15,23,42,0.18); }
	.oba-autobid-window-modal h4 { margin:0 0 10px; }
	.oba-autobid-window-modal p { margin:0 0 12px; color:#475569; }
	.oba-autobid-window-modal .oba-autobid-window { margin-top:0; }
	.oba-autobid-window-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:14px; }
	</style>
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
		<div class="oba-guest-banner">
			<div>
				<h4><?php esc_html_e( 'Log in to participate', 'one-ba-auctions' ); ?></h4>
				<p><?php esc_html_e( 'Please log in or create an account to view details and join this auction.', 'one-ba-auctions' ); ?></p>
			</div>
			<div class="oba-guest-actions">
				<a class="button button-primary oba-guest-login" href="<?php echo esc_url( $settings['login_link'] ? $settings['login_link'] : wp_login_url( get_permalink( $product->get_id() ) ) ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'Log in / Sign up', 'one-ba-auctions' ); ?>
				</a>
				<?php if ( function_exists( 'shortcode_exists' ) && shortcode_exists( 'nextend_social_login_register_flow' ) && function_exists( 'do_shortcode' ) ) : ?>
					<div class="oba-guest-social">
						<?php echo do_shortcode( '[nextend_social_login_register_flow]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
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
					<div class="oba-autobid-setup" data-phase="registration" style="margin-top:12px;">
						<div class="oba-autobid-card">
							<div class="oba-autobid-left">
								<h4 style="margin:0;font-size:15px;font-weight:700;"><?php esc_html_e( 'Autobid', 'one-ba-auctions' ); ?></h4>
							</div>
							<div class="oba-autobid-toggle-col">
								<label class="oba-toggle">
									<input type="checkbox" class="oba-autobid-switch" />
									<span class="oba-toggle-slider"></span>
									<span class="oba-toggle-text"><?php esc_html_e( 'Off', 'one-ba-auctions' ); ?></span>
								</label>
							</div>
						</div>
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
						<div class="oba-card oba-bidder-status-card">
							<div class="oba-legend-label"><?php esc_html_e( 'Status', 'one-ba-auctions' ); ?></div>
							<div class="oba-legend-value oba-bidder-status-pill" style="font-size:16px;padding:6px 10px;border-radius:10px;"><?php esc_html_e( 'Outbid', 'one-ba-auctions' ); ?></div>
						</div>
					</div>
					<div class="oba-autobid-setup" data-phase="live" style="margin-top:12px;">
						<div class="oba-autobid-card">
							<div class="oba-autobid-left">
								<h4 style="margin:0;font-size:15px;font-weight:700;"><?php esc_html_e( 'Autobid', 'one-ba-auctions' ); ?></h4>
							</div>
							<div class="oba-autobid-toggle-col">
								<label class="oba-toggle">
									<input type="checkbox" class="oba-autobid-switch" />
									<span class="oba-toggle-slider"></span>
									<span class="oba-toggle-text"><?php esc_html_e( 'Off', 'one-ba-auctions' ); ?></span>
								</label>
							</div>
							<div class="oba-autobid-window">
								<span class="oba-autobid-window-remaining" style="font-size:13px;font-weight:600;color:#475569;"></span>
							</div>
						</div>
					</div>
					<div class="oba-actions" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
						<button class="button button-primary oba-bid"><?php esc_html_e( 'Place bid', 'one-ba-auctions' ); ?></button>
					</div>
					<div class="oba-live-terms" style="display:none;margin-top:8px;">
						<label style="display:flex;gap:8px;align-items:center;">
							<input type="checkbox" class="oba-terms-checkbox" />
							<span><a href="#" class="oba-terms-link"><?php esc_html_e( 'T&C must be accepted before participating', 'one-ba-auctions' ); ?></a></span>
						</label>
					</div>
					<div class="oba-history-head">
						<span><?php esc_html_e( 'Last bidder', 'one-ba-auctions' ); ?></span>
						<span class="oba-history-time-head"><?php esc_html_e( 'Time', 'one-ba-auctions' ); ?></span>
						<span class="oba-history-value-head"><?php esc_html_e( "Bid's value", 'one-ba-auctions' ); ?></span>
					</div>
					<ul class="oba-history"></ul>
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
					<div class="oba-claimed-summary" style="display:none;background:#ecfdf3;border:1px solid #bbf7d0;border-radius:12px;padding:14px;margin-bottom:12px;">
						<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
							<div style="font-weight:700;font-size:16px;"><?php esc_html_e( 'Prize claimed', 'one-ba-auctions' ); ?></div>
							<div class="oba-claimed-ended" style="font-size:13px;color:#0f172a;"></div>
						</div>
						<div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:8px;font-size:14px;">
							<div><strong><?php esc_html_e( 'Winner', 'one-ba-auctions' ); ?>:</strong> <span class="oba-claimed-winner">—</span></div>
							<div><strong><?php esc_html_e( 'Bids placed', 'one-ba-auctions' ); ?>:</strong> <span class="oba-claimed-bids">—</span></div>
							<div><strong><?php esc_html_e( 'Bids value', 'one-ba-auctions' ); ?>:</strong> <span class="oba-claimed-value">—</span></div>
							<div><strong><?php esc_html_e( 'Saved vs cost', 'one-ba-auctions' ); ?>:</strong> <span class="oba-claimed-saved">—</span></div>
						</div>
					</div>
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

<div class="oba-autobid-window-overlay" style="display:none;"></div>
<div class="oba-autobid-window-modal" style="display:none;">
	<div class="oba-autobid-window-modal__inner">
		<h4><?php echo esc_html( $get( 'autobid_modal_title', __( 'Enable autobid', 'one-ba-auctions' ) ) ); ?></h4>
		<p><?php echo esc_html( $get( 'autobid_modal_desc', __( 'Select how long autobid should stay active. Enabling will charge activation points.', 'one-ba-auctions' ) ) ); ?></p>
		<div class="oba-autobid-window">
			<button type="button" class="oba-autobid-window-btn" data-minutes="10"><?php echo esc_html( $get( 'autobid_window_10', __( '10m', 'one-ba-auctions' ) ) ); ?></button>
			<button type="button" class="oba-autobid-window-btn" data-minutes="30"><?php echo esc_html( $get( 'autobid_window_30', __( '30m', 'one-ba-auctions' ) ) ); ?></button>
			<button type="button" class="oba-autobid-window-btn" data-minutes="60"><?php echo esc_html( $get( 'autobid_window_60', __( '60m', 'one-ba-auctions' ) ) ); ?></button>
			<span class="oba-autobid-window-remaining"></span>
		</div>
		<div class="oba-autobid-window-actions">
			<button type="button" class="button oba-autobid-window-cancel"><?php echo esc_html( $get( 'autobid_modal_cancel', __( 'Cancel', 'one-ba-auctions' ) ) ); ?></button>
			<button type="button" class="button button-primary oba-autobid-window-confirm"><?php echo esc_html( $get( 'autobid_modal_enable', __( 'Enable', 'one-ba-auctions' ) ) ); ?></button>
		</div>
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
