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

	public static function lucide_svg( $name ) {
		$icons = array(
			'check-circle' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>',
			'lock'         => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>',
			'chevron-up'   => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"></path></svg>',
			'chevron-down' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>',
		);
		return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
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

		$bid_products = $this->get_products_by_meta( '_is_bid_product' );
		$settings     = OBA_Settings::get_settings();
		$current_id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_pts  = $current_id ? (float) get_post_meta( $current_id, '_registration_points', true ) : 0;
		$current_cost = $current_id ? (float) get_post_meta( $current_id, '_product_cost', true ) : 0;
		$points_rate  = isset( $settings['points_value'] ) ? (float) $settings['points_value'] : 1;

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

		woocommerce_wp_checkbox(
			array(
				'id'          => '_oba_autobid_enabled',
				'label'       => __( 'Enable autobid for this auction', 'one-ba-auctions' ),
				'description' => __( 'If enabled, live timer will be forced to at least 60 seconds (cron-safe).', 'one-ba-auctions' ),
			)
		);

		woocommerce_wp_select(
			array(
				'id'          => '_bid_product_id',
				'label'       => __( 'Bid fee product', 'one-ba-auctions' ),
				'options'     => $bid_products,
				'desc_tip'    => true,
				'description' => __( 'Product representing cost per bid.', 'one-ba-auctions' ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_product_cost',
				'label'       => __( 'Cost of product (store currency)', 'one-ba-auctions' ),
				'type'        => 'number',
				'custom_attributes' => array(
					'step' => '0.01',
					'min'  => '0',
				),
				'description' => __( 'Internal cost used to estimate profit.', 'one-ba-auctions' ),
				'desc_tip'    => true,
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_registration_points',
				'label'       => __( 'Registration points required', 'one-ba-auctions' ),
				'type'        => 'number',
				'custom_attributes' => array(
					'step' => '1',
					'min'  => '0',
				),
				'description' => __( 'Points deducted from user on registration (no WC order).', 'one-ba-auctions' ),
				'desc_tip'    => true,
			)
		);
		?>
		<p>
			<strong><?php esc_html_e( 'Profit (approx.):', 'one-ba-auctions' ); ?></strong>
			<span id="oba_reg_points_value"><?php echo wp_kses_post( wc_price( ( $current_pts * $points_rate ) - $current_cost ) ); ?></span>
			<br><span class="description"><?php esc_html_e( 'Points × participants × point value minus cost.', 'one-ba-auctions' ); ?></span>
		</p>
		<script>
			jQuery(function($){
				const rate = <?php echo wp_json_encode( $points_rate ); ?>;
				const participants = parseFloat($('#_required_participants').val() || 0);
				let cost = <?php echo wp_json_encode( $current_cost ); ?>;
				function calc() {
					const pts = parseFloat($('#_registration_points').val() || 0);
					cost = parseFloat($('#_product_cost').val() || cost || 0);
					const val = (pts * rate * (participants || 1)) - cost;
					$('#oba_reg_points_value').text(obaFormatPrice(val));
				}
				function obaFormatPrice(val){
					if (typeof wcSettings !== 'undefined' && wcSettings.currency) {
						const c = wcSettings.currency;
						const formatter = new Intl.NumberFormat(c.thousands_sep ? navigator.language : undefined, {
							style: 'currency',
							currency: c.currency_code || c.code || 'USD',
							minimumFractionDigits: c.currency_minor_unit || 2,
							maximumFractionDigits: c.currency_minor_unit || 2
						});
						try { return formatter.format(val); } catch(e) {}
					}
					return val.toFixed(2);
				}
				$('#_registration_points').on('input', calc);
				$('#_required_participants').on('input', calc);
				calc();
			});
		</script>
		<?php

		echo '</div>';
	}

	public function save_fields( $product_id ) {
		$fields = array(
			'_required_participants',
			'_live_timer_seconds',
			'_prelive_timer_seconds',
			'_auction_status',
			'_oba_autobid_enabled',
			'_bid_product_id',
			'_registration_points',
			'_product_cost',
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

		// Enforce cron-safe live timer if autobid enabled for this auction.
		$autobid_enabled = get_post_meta( $product_id, '_oba_autobid_enabled', true );
		if ( $autobid_enabled ) {
			$live_timer = (int) get_post_meta( $product_id, '_live_timer_seconds', true );
			if ( $live_timer > 0 && $live_timer < 60 ) {
				update_post_meta( $product_id, '_live_timer_seconds', 60 );
			}
		}
	}

	private function get_products_by_meta( $meta_key ) {
		$options = array( '' => __( '— Select —', 'one-ba-auctions' ) );
		$q = new WP_Query(
			array(
				'post_type'      => 'product',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => $meta_key,
						'value' => 'yes',
					),
				),
			)
		);
		if ( $q->have_posts() ) {
			foreach ( $q->posts as $id ) {
				$options[ $id ] = get_the_title( $id ) . ' (#' . $id . ')';
			}
		}
		return $options;
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
					<span class="badge"><span class="badge-number">1</span><span class="badge-check"><?php echo wp_kses_post( self::lucide_svg( 'check-circle' ) ); ?></span></span>
					<span class="label"><?php esc_html_e( 'Registration', 'one-ba-auctions' ); ?></span>
					<span class="desc"><?php esc_html_e( 'Join the lobby with credits.', 'one-ba-auctions' ); ?></span>
				</div>
				<div class="oba-step-pill" data-step="pre_live">
					<span class="badge"><span class="badge-number">2</span><span class="badge-check"><?php echo wp_kses_post( self::lucide_svg( 'check-circle' ) ); ?></span></span>
					<span class="label"><?php esc_html_e( 'Countdown to Live', 'one-ba-auctions' ); ?></span>
					<span class="desc"><?php esc_html_e( 'Short pre-live timer.', 'one-ba-auctions' ); ?></span>
				</div>
				<div class="oba-step-pill" data-step="live">
					<span class="badge"><span class="badge-number">3</span><span class="badge-check"><?php echo wp_kses_post( self::lucide_svg( 'check-circle' ) ); ?></span></span>
					<span class="label"><?php esc_html_e( 'Live Bidding', 'one-ba-auctions' ); ?></span>
					<span class="desc"><?php esc_html_e( 'Bid, reset timer, compete.', 'one-ba-auctions' ); ?></span>
				</div>
				<div class="oba-step-pill" data-step="ended">
					<span class="badge"><span class="badge-number">4</span><span class="badge-check"><?php echo wp_kses_post( self::lucide_svg( 'check-circle' ) ); ?></span></span>
					<span class="label"><?php esc_html_e( 'Auction Ended', 'one-ba-auctions' ); ?></span>
					<span class="desc"><?php esc_html_e( 'Claim or view results.', 'one-ba-auctions' ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}
}
