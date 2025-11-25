<?php
/**
 * Auction single product template.
 *
 * @var WC_Product $product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$meta      = array(
	'registration_fee' => get_post_meta( $product->get_id(), '_registration_fee_credits', true ),
	'bid_cost'         => get_post_meta( $product->get_id(), '_bid_cost_credits', true ),
	'claim_price'      => get_post_meta( $product->get_id(), '_claim_price_credits', true ),
);
$settings  = OBA_Settings::get_settings();
$show_pill = ! empty( $settings['show_header_balance'] ) && is_user_logged_in();
?>

<div class="oba-auction-steps">
	<div class="oba-status">
		<span class="oba-pill oba-pill-status" role="button" tabindex="0">
			<span class="oba-pill-label"><?php esc_html_e( '1. Registration', 'one-ba-auctions' ); ?></span>
			<span class="oba-pill-info-icon" aria-hidden="true">i</span>
		</span>
		<?php if ( $show_pill ) : ?>
			<?php
			$credits_service = new OBA_Credits_Service();
			$balance         = $credits_service->get_balance( get_current_user_id() );
			$links           = $settings['credit_pack_links'];
			$labels          = $settings['credit_pack_labels'];
			?>
			<div class="oba-credit-pill oba-credit-pill--inline <?php echo $balance < 10 ? 'low' : ''; ?>" data-balance="<?php echo esc_attr( $balance ); ?>">
				<span class="oba-credit-balance"><?php esc_html_e( 'Credits:', 'one-ba-auctions' ); ?> <?php echo esc_html( $balance ); ?></span>
				<span class="oba-credit-links">
					<?php foreach ( $links as $idx => $url ) : ?>
						<?php if ( empty( $url ) ) { continue; } ?>
						<?php
						$label = ! empty( $labels[ $idx ] ) ? $labels[ $idx ] : sprintf( __( 'Pack %d', 'one-ba-auctions' ), $idx + 1 );
						?>
						<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</span>
			</div>
		<?php endif; ?>
	</div>

	<div class="oba-alert oba-alert-error" style="display:none;"></div>
	<div class="oba-alert oba-alert-info oba-success-banner" style="display:none;"></div>
	<div class="oba-toast" role="alert"></div>
	<div style="margin-bottom:8px;">
		<button class="button button-secondary oba-admin-end-now" style="display:none;"><?php esc_html_e( 'End now (admin)', 'one-ba-auctions' ); ?></button>
	</div>
	<div class="oba-last-refreshed" style="font-size:12px;color:#6b7280;margin-bottom:8px;"></div>

	<div class="oba-step-card is-active" data-step="registration">
		<p><?php echo esc_html( sprintf( __( 'Registration fee: %s credits', 'one-ba-auctions' ), $meta['registration_fee'] ) ); ?></p>
		<div class="oba-lobby-bar"><span style="width:0%"></span></div>
		<p class="oba-lobby-count">0%</p>
		<div class="oba-registered-note" style="display:none;">
			<?php esc_html_e( 'You are registered, wait for Step 2. Share this auction to reach 100% faster!', 'one-ba-auctions' ); ?>
		</div>
		<?php if ( ! is_user_logged_in() ) : ?>
			<p class="oba-login-hint" style="display:none;" data-login-url="<?php echo esc_url( wp_login_url( get_permalink( $product->get_id() ) ) ); ?>">
				<?php
				printf(
					/* translators: %s: login url */
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
			<button class="button oba-register"><?php esc_html_e( 'Register', 'one-ba-auctions' ); ?></button>
		</div>
	</div>

	<div class="oba-step-card" data-step="pre_live">
		<div class="oba-timer-card" style="max-width:220px;">
			<span class="oba-timer-label"><?php esc_html_e( 'Pre-live timer', 'one-ba-auctions' ); ?></span>
			<span class="oba-timer-value"><span class="oba-prelive-seconds">0</span>s</span>
			<div class="oba-timer-bar oba-prelive-bar"><span style="width:0%"></span></div>
		</div>
	</div>

	<div class="oba-step-card" data-step="live">
		<p><?php echo esc_html( sprintf( __( 'Bid cost: %s credits', 'one-ba-auctions' ), $meta['bid_cost'] ) ); ?></p>
		<div class="oba-timers">
			<div class="oba-timer-card">
				<span class="oba-timer-label"><?php esc_html_e( 'Live timer', 'one-ba-auctions' ); ?></span>
				<span class="oba-timer-value"><span class="oba-live-seconds">0</span>s</span>
				<div class="oba-timer-bar oba-live-bar"><span style="width:0%"></span></div>
			</div>
			<div class="oba-timer-card">
				<span class="oba-timer-label"><?php esc_html_e( 'Your bids', 'one-ba-auctions' ); ?></span>
				<span class="oba-timer-value oba-user-bids">0</span>
			</div>
			<div class="oba-timer-card">
				<span class="oba-timer-label"><?php esc_html_e( 'Your cost', 'one-ba-auctions' ); ?></span>
				<span class="oba-timer-value oba-user-cost">0</span>
			</div>
		</div>
		<ul class="oba-history"></ul>
		<div class="oba-buy-credits" style="display:none;">
			<h4><?php esc_html_e( 'Out of credits? Buy more:', 'one-ba-auctions' ); ?></h4>
			<div class="oba-buy-links"></div>
		</div>
		<div class="oba-actions">
			<button class="button button-primary oba-bid"><?php esc_html_e( 'Place bid', 'one-ba-auctions' ); ?></button>
		</div>
	</div>

		<div class="oba-step-card" data-step="ended">
		<div class="oba-winner-claim" style="display:none;">
			<div class="oba-outcome oba-outcome--win">
				<p><?php esc_html_e( 'You won! Claim price:', 'one-ba-auctions' ); ?> <span class="oba-claim-amount"><?php echo esc_html( $meta['claim_price'] ); ?></span></p>
				<div class="oba-claim-status" style="display:none;"></div>
				<button class="button button-primary oba-claim"><?php esc_html_e( 'Claim now', 'one-ba-auctions' ); ?></button>
			</div>
		</div>
		<div class="oba-loser" style="display:none;">
			<div class="oba-outcome oba-outcome--lose">
				<p><?php esc_html_e( 'Auction ended. Better luck next time.', 'one-ba-auctions' ); ?></p>
				<p class="oba-refund-note"><?php esc_html_e( 'Your reserved credits have been refunded.', 'one-ba-auctions' ); ?></p>
			</div>
		</div>
	</div>
</div>

<div class="oba-modal-overlay" style="display:none;"></div>
<div class="oba-claim-modal">
	<h4><?php esc_html_e( 'Choose how to claim', 'one-ba-auctions' ); ?></h4>
	<div class="oba-claim-options">
		<label><input type="radio" name="oba-claim-method" value="credits" checked /> <?php esc_html_e( 'Pay with credits', 'one-ba-auctions' ); ?></label>
		<label><input type="radio" name="oba-claim-method" value="gateway" /> <?php esc_html_e( 'Pay via checkout', 'one-ba-auctions' ); ?></label>
	</div>
	<div class="oba-claim-error oba-alert oba-alert-error"></div>
	<div class="oba-actions">
		<button class="button button-primary oba-claim-confirm"><?php esc_html_e( 'Continue', 'one-ba-auctions' ); ?></button>
		<button class="button oba-claim-cancel" type="button"><?php esc_html_e( 'Cancel', 'one-ba-auctions' ); ?></button>
	</div>
</div>

<div class="oba-credit-overlay" style="display:none;"></div>
<div class="oba-credit-modal" style="display:none;">
	<div class="oba-credit-modal__inner">
		<button class="oba-credit-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'one-ba-auctions' ); ?>">&times;</button>
		<h4><?php esc_html_e( 'Buy credits', 'one-ba-auctions' ); ?></h4>
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
