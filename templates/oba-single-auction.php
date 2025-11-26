<?php
/**
 * Auction single product template (redesigned).
 *
 * @var WC_Product $product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$meta     = array(
	'registration_fee' => get_post_meta( $product->get_id(), '_registration_fee_credits', true ),
	'bid_cost'         => get_post_meta( $product->get_id(), '_bid_cost_credits', true ),
	'claim_price'      => get_post_meta( $product->get_id(), '_claim_price_credits', true ),
);
$settings = OBA_Settings::get_settings();
$tr       = isset( $settings['translations'] ) ? $settings['translations'] : array();
$get      = function( $key, $default ) use ( $tr ) {
	return ! empty( $tr[ $key ] ) ? $tr[ $key ] : $default;
};
?>

<div class="oba-auction-wrap">
	<div class="oba-layout">
		<div class="oba-col-right">
			<div class="oba-card oba-phase-card" data-step="registration">
				<div class="oba-phase-header">
					<div class="oba-phase-title"><span>1.</span><span class="oba-phase-label"><?php echo esc_html( $get( 'step1_label', __( 'Registration', 'one-ba-auctions' ) ) ); ?></span></div>
					<span class="oba-phase-icon icon-lock" aria-hidden="true">
						<span class="icon icon-check"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'check-circle' ) ); ?></span>
						<span class="icon icon-lock"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'lock' ) ); ?></span>
						<span class="icon icon-up"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'chevron-up' ) ); ?></span>
						<span class="icon icon-down"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'chevron-down' ) ); ?></span>
					</span>
				</div>
				<div class="oba-phase-body">
					<p>
						<?php
						/* translators: 1: label, 2: amount, 3: credits plural */
						printf(
							esc_html__( '%1$s: %2$s %3$s', 'one-ba-auctions' ),
							esc_html( $get( 'registration_fee_label', __( 'Registration fee', 'one-ba-auctions' ) ) ),
							esc_html( $meta['registration_fee'] ),
							esc_html( $get( 'credit_plural', __( 'credits', 'one-ba-auctions' ) ) )
						);
						?>
					</p>
					<div class="oba-bar oba-lobby-bar"><span style="width:0%"></span></div>
					<p class="oba-lobby-count"><?php echo esc_html( $get( 'lobby_progress', __( 'Lobby progress', 'one-ba-auctions' ) ) . ': 0%' ); ?></p>
					<div class="oba-register-note">
						<span class="oba-badge danger oba-not-registered"><?php echo esc_html( $get( 'not_registered_badge', __( 'Not registered', 'one-ba-auctions' ) ) ); ?></span>
						<span class="oba-badge success oba-registered" style="display:none;"><?php echo esc_html( $get( 'registered_badge', __( 'Registered', 'one-ba-auctions' ) ) ); ?></span>
					</div>
					<div class="oba-registered-note" style="display:none;">
						<?php echo esc_html( $get( 'register_note', __( 'You are registered, wait for Step 2. Share this auction to reach 100% faster!', 'one-ba-auctions' ) ) ); ?>
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
			</div>
			</div>

			<div class="oba-card oba-phase-card is-collapsed" data-step="pre_live">
				<div class="oba-phase-header">
					<div class="oba-phase-title"><span>2.</span><span class="oba-phase-label"><?php echo esc_html( $get( 'step2_label', __( 'Countdown to Live', 'one-ba-auctions' ) ) ); ?></span></div>
					<span class="oba-phase-icon icon-lock" aria-hidden="true">
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
					<span class="oba-phase-icon icon-lock" aria-hidden="true">
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
						$credits_label = $get( 'credit_plural', __( 'credits', 'one-ba-auctions' ) );
						printf(
							esc_html__( '%1$s: %2$s %3$s', 'one-ba-auctions' ),
							esc_html( $bid_label ),
							esc_html( $meta['bid_cost'] ),
							esc_html( $credits_label )
						);
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
					</div>
					<ul class="oba-history"></ul>
					<div class="oba-actions">
						<button class="button button-primary oba-bid"><?php esc_html_e( 'Place bid', 'one-ba-auctions' ); ?></button>
					</div>
				</div>
			</div>

			<div class="oba-card oba-phase-card is-collapsed" data-step="ended">
				<div class="oba-phase-header">
					<div class="oba-phase-title"><span>4.</span><span class="oba-phase-label"><?php echo esc_html( $get( 'step4_label', __( 'Auction Ended', 'one-ba-auctions' ) ) ); ?></span></div>
					<span class="oba-phase-icon icon-lock" aria-hidden="true">
						<span class="icon icon-check"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'check-circle' ) ); ?></span>
						<span class="icon icon-lock"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'lock' ) ); ?></span>
						<span class="icon icon-up"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'chevron-up' ) ); ?></span>
						<span class="icon icon-down"><?php echo wp_kses_post( OBA_Product_Type::lucide_svg( 'chevron-down' ) ); ?></span>
					</span>
				</div>
				<div class="oba-phase-body">
					<div class="oba-winner-claim" style="display:none;">
						<div class="oba-outcome oba-outcome--win">
							<p><?php echo esc_html( $get( 'winner_msg', __( 'You won! Claim price:', 'one-ba-auctions' ) ) ); ?> <span class="oba-claim-amount"><?php echo esc_html( $meta['claim_price'] ); ?></span></p>
							<div class="oba-claim-status" style="display:none;"></div>
							<button class="button button-primary oba-claim"><?php echo esc_html( $get( 'claim_button', __( 'Claim now', 'one-ba-auctions' ) ) ); ?></button>
						</div>
					</div>
					<div class="oba-loser" style="display:none;">
						<div class="oba-outcome oba-outcome--lose">
							<p><?php echo esc_html( $get( 'loser_msg', __( 'You did not win this auction.', 'one-ba-auctions' ) ) ); ?></p>
							<p class="oba-refund-note"><?php echo esc_html( $get( 'refund_msg', __( 'Your reserved credits have been refunded.', 'one-ba-auctions' ) ) ); ?></p>
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
<div class="oba-claim-modal">
	<h4><?php echo esc_html( $get( 'claim_modal_title', __( 'Choose how to claim', 'one-ba-auctions' ) ) ); ?></h4>
	<div class="oba-claim-options">
		<label><input type="radio" name="oba-claim-method" value="credits" checked /> <?php echo esc_html( $get( 'claim_option_credits', __( 'Pay with credits', 'one-ba-auctions' ) ) ); ?></label>
		<label><input type="radio" name="oba-claim-method" value="gateway" /> <?php echo esc_html( $get( 'claim_option_gateway', __( 'Pay via checkout', 'one-ba-auctions' ) ) ); ?></label>
	</div>
	<div class="oba-claim-error oba-alert oba-alert-error"><?php echo esc_html( $get( 'claim_error', __( 'Claim failed. Please try again.', 'one-ba-auctions' ) ) ); ?></div>
	<div class="oba-actions">
		<button class="button button-primary oba-claim-confirm"><?php echo esc_html( $get( 'claim_continue', __( 'Continue', 'one-ba-auctions' ) ) ); ?></button>
		<button class="button oba-claim-cancel" type="button"><?php echo esc_html( $get( 'claim_cancel', __( 'Cancel', 'one-ba-auctions' ) ) ); ?></button>
	</div>
</div>

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
