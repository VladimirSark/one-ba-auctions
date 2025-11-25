<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Product_Type {

	public function hooks() {
		add_filter( 'product_type_selector', array( $this, 'register_type' ) );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_fields' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_frontend_wrapper' ), 5 );
		add_action( 'woocommerce_before_single_product', array( $this, 'render_explainer_bar' ), 1 );
		add_action( 'init', array( $this, 'register_product_class' ) );
	}

	public function register_type( $types ) {
		$types['auction'] = __( 'Auction', 'one-ba-auctions' );
		return $types;
	}

	public function register_product_class() {
		if ( ! class_exists( 'WC_Product_Auction' ) ) {
			require_once OBA_PLUGIN_DIR . 'includes/class-wc-product-auction.php';
		}
	}

	public function add_product_tab( $tabs ) {
		$tabs['auction'] = array(
			'label'    => __( 'Auction', 'one-ba-auctions' ),
			'target'   => 'oba_auction_product_data',
			'class'    => array( 'show_if_auction' ),
			'priority' => 50,
		);

		return $tabs;
	}

	public function render_fields() {
		echo '<div id="oba_auction_product_data" class="panel woocommerce_options_panel">';

		woocommerce_wp_text_input(
			array(
				'id'          => '_registration_fee_credits',
				'label'       => __( 'Registration fee (credits)', 'one-ba-auctions' ),
				'type'        => 'number',
				'desc_tip'    => true,
				'description' => __( 'Credits required to register.', 'one-ba-auctions' ),
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_bid_cost_credits',
				'label'       => __( 'Bid cost (credits)', 'one-ba-auctions' ),
				'type'        => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_required_participants',
				'label'       => __( 'Required participants', 'one-ba-auctions' ),
				'type'        => 'number',
				'custom_attributes' => array(
					'step' => '1',
					'min'  => '1',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_live_timer_seconds',
				'label'       => __( 'Live timer (seconds)', 'one-ba-auctions' ),
				'type'        => 'number',
				'custom_attributes' => array(
					'step' => '1',
					'min'  => '1',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_prelive_timer_seconds',
				'label'       => __( 'Pre-live timer (seconds)', 'one-ba-auctions' ),
				'type'        => 'number',
				'custom_attributes' => array(
					'step' => '1',
					'min'  => '1',
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_claim_price_credits',
				'label'       => __( 'Claim price (credits)', 'one-ba-auctions' ),
				'type'        => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
			)
		);

		woocommerce_wp_select(
			array(
				'id'      => '_auction_status',
				'label'   => __( 'Auction status', 'one-ba-auctions' ),
				'options' => array(
					'registration' => __( 'Registration', 'one-ba-auctions' ),
					'pre_live'     => __( 'Pre-live', 'one-ba-auctions' ),
					'live'         => __( 'Live', 'one-ba-auctions' ),
					'ended'        => __( 'Ended', 'one-ba-auctions' ),
				),
			)
		);

		echo '</div>';
	}

	public function save_fields( $product_id ) {
		$fields = array(
			'_registration_fee_credits',
			'_bid_cost_credits',
			'_required_participants',
			'_live_timer_seconds',
			'_prelive_timer_seconds',
			'_claim_price_credits',
			'_auction_status',
		);

		foreach ( $fields as $field ) {
			$value = isset( $_POST[ $field ] ) ? wc_clean( wp_unslash( $_POST[ $field ] ) ) : '';

			if ( '' === $value && ( '_live_timer_seconds' === $field || '_prelive_timer_seconds' === $field ) ) {
				$settings = OBA_Settings::get_settings();
				$value    = '_live_timer_seconds' === $field ? $settings['default_live_seconds'] : $settings['default_prelive_seconds'];
			}

			if ( '' !== $value || in_array( $field, array( '_live_timer_seconds', '_prelive_timer_seconds' ), true ) ) {
				update_post_meta( $product_id, $field, $value );
			}
		}
	}

	public function render_frontend_wrapper() {
		global $product;

		if ( ! $product instanceof WC_Product || 'auction' !== $product->get_type() ) {
			return;
		}

		// Remove add-to-cart/price; keep gallery/title/description intact.
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );

		$settings             = OBA_Settings::get_settings();
		$GLOBALS['oba_terms_text'] = $settings['terms_text'];

		wc_get_template(
			'oba-single-auction.php',
			array( 'product' => $product ),
			'',
			OBA_PLUGIN_DIR . 'templates/'
		);
	}

	public function render_explainer_bar() {
		global $product;

		if ( ! $product instanceof WC_Product || 'auction' !== $product->get_type() ) {
			return;
		}
		?>
		<div class="oba-explainer-wrap">
			<div class="oba-explainer">
				<div class="oba-step-pill is-active" data-step="registration">
					<span class="number">1</span>
					<span class="label"><?php esc_html_e( 'Registration', 'one-ba-auctions' ); ?></span>
					<span class="desc"><?php esc_html_e( 'Join the lobby with credits.', 'one-ba-auctions' ); ?></span>
				</div>
				<div class="oba-step-pill" data-step="pre_live">
					<span class="number">2</span>
					<span class="label"><?php esc_html_e( 'Countdown to Live', 'one-ba-auctions' ); ?></span>
					<span class="desc"><?php esc_html_e( 'Short pre-live timer.', 'one-ba-auctions' ); ?></span>
				</div>
				<div class="oba-step-pill" data-step="live">
					<span class="number">3</span>
					<span class="label"><?php esc_html_e( 'Live Bidding', 'one-ba-auctions' ); ?></span>
					<span class="desc"><?php esc_html_e( 'Bid, reset timer, compete.', 'one-ba-auctions' ); ?></span>
				</div>
				<div class="oba-step-pill" data-step="ended">
					<span class="number">4</span>
					<span class="label"><?php esc_html_e( 'Auction Ended', 'one-ba-auctions' ); ?></span>
					<span class="desc"><?php esc_html_e( 'Claim or view results.', 'one-ba-auctions' ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}
}
