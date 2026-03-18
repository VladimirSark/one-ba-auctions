<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OBA_Product_Type {

	private $repo;

	public function hooks() {
		$this->repo = new OBA_Auction_Repository();
		add_filter( 'product_type_selector', array( $this, 'register_type' ) );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'ensure_core_tabs_visible' ), 20 );
		add_filter( 'woocommerce_product_type_options', array( $this, 'enable_virtual_downloadable' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_fields' ) );
		// Render auction UI below the product summary but above tabs.
		add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_frontend_wrapper' ), 5 );
		add_action( 'woocommerce_before_single_product', array( $this, 'render_explainer_bar' ), 1 );
		add_action( 'init', array( $this, 'register_product_class' ) );
		add_action( 'admin_footer', array( $this, 'admin_footer_scripts' ) );
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

	/**
	 * Make core tabs (inventory, shipping) visible for auction products.
	 */
	public function ensure_core_tabs_visible( $tabs ) {
		foreach ( array( 'inventory', 'shipping' ) as $key ) {
			if ( isset( $tabs[ $key ]['class'] ) && is_array( $tabs[ $key ]['class'] ) ) {
				$tabs[ $key ]['class'][] = 'show_if_auction';
			}
		}

		return $tabs;
	}

	/**
	 * Allow virtual/downloadable toggles for auction product type.
	 */
	public function enable_virtual_downloadable( $options ) {
		foreach ( array( 'virtual', 'downloadable' ) as $field ) {
			if ( isset( $options[ $field ]['wrapper_class'] ) ) {
				$options[ $field ]['wrapper_class'] .= ' show_if_auction';
			}
		}
		return $options;
	}

	public function render_fields() {
		echo '<div id="oba_auction_product_data" class="panel woocommerce_options_panel">';

		$bid_products = $this->get_products_by_meta( '_is_bid_product' );
		$settings     = OBA_Settings::get_settings();
		$current_id   = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_pts  = $current_id ? (float) get_post_meta( $current_id, '_registration_points', true ) : 0;
		$current_cost = 0;
		if ( $current_id ) {
			$current_cost = (float) get_post_meta( $current_id, '_wc_cog_cost', true );
			if ( ! $current_cost ) {
				$current_cost = (float) get_post_meta( $current_id, '_product_cost', true );
			}
		}
		$points_rate  = isset( $settings['points_value'] ) ? (float) $settings['points_value'] : 1;
		$product_obj  = $current_id ? wc_get_product( $current_id ) : null;

		$tax_statuses = function_exists( 'wc_get_product_tax_statuses' )
			? wc_get_product_tax_statuses()
			: array(
				'taxable'  => __( 'Taxable', 'woocommerce' ),
				'shipping' => __( 'Shipping only', 'woocommerce' ),
				'none'     => __( 'None', 'woocommerce' ),
			);

		$tax_class_options = function_exists( 'wc_get_product_tax_class_options' )
			? wc_get_product_tax_class_options()
			: array( '' => __( 'Standard', 'woocommerce' ) );

		$backorder_options = function_exists( 'wc_get_product_backorder_options' )
			? wc_get_product_backorder_options()
			: array(
				'no'     => __( 'Do not allow', 'woocommerce' ),
				'notify' => __( 'Allow, but notify customer', 'woocommerce' ),
				'yes'    => __( 'Allow', 'woocommerce' ),
			);

		$shipping_class_options = function_exists( 'wc_get_product_shipping_class_options' )
			? wc_get_product_shipping_class_options()
			: ( function() {
				$options = array( '' => __( 'No shipping class', 'woocommerce' ) );
				$terms = get_terms(
					array(
						'taxonomy'   => 'product_shipping_class',
						'hide_empty' => false,
					)
				);
				if ( ! is_wp_error( $terms ) && $terms ) {
					foreach ( $terms as $term ) {
						$options[ $term->term_id ] = $term->name;
					}
				}
				return $options;
			} )();

		// Sub-tab navigation.
		?>
		<div class="oba-auction-subtabs">
			<ul class="oba-auction-subtab-nav">
				<li class="active" data-panel="pricing"><?php esc_html_e( 'Pricing', 'one-ba-auctions' ); ?></li>
				<li data-panel="inventory"><?php esc_html_e( 'Inventory', 'one-ba-auctions' ); ?></li>
				<li data-panel="shipping"><?php esc_html_e( 'Shipping', 'one-ba-auctions' ); ?></li>
				<li data-panel="settings"><?php esc_html_e( 'Auction settings', 'one-ba-auctions' ); ?></li>
				<li data-panel="other"><?php esc_html_e( 'Other settings', 'one-ba-auctions' ); ?></li>
				<li data-panel="status"><?php esc_html_e( 'Status', 'one-ba-auctions' ); ?></li>
				<li data-panel="stats"><?php esc_html_e( 'Statistics', 'one-ba-auctions' ); ?></li>
			</ul>

			<div class="oba-auction-subtab-panel is-active" data-panel="pricing">
				<div class="options_group oba-auction-pricing">
					<?php
					woocommerce_wp_text_input(
						array(
							'id'            => '_regular_price',
							'label'         => __( 'Regular price', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
							'data_type'     => 'price',
							'wrapper_class' => 'show_if_auction',
							'value'         => $product_obj ? $product_obj->get_regular_price( 'edit' ) : '',
						)
					);
					woocommerce_wp_text_input(
						array(
							'id'            => '_sale_price',
							'label'         => __( 'Sale price', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
							'data_type'     => 'price',
							'wrapper_class' => 'show_if_auction',
							'value'         => $product_obj ? $product_obj->get_sale_price( 'edit' ) : '',
						)
					);
					woocommerce_wp_text_input(
						array(
							'id'                => '_wc_cog_cost',
							'label'             => __( 'Cost of goods', 'one-ba-auctions' ) . ' (' . get_woocommerce_currency_symbol() . ')',
							'type'              => 'number',
							'custom_attributes' => array(
								'step' => '0.01',
								'min'  => '0',
							),
							'wrapper_class'     => 'show_if_auction',
							'value'             => $current_cost,
							'description'       => __( 'Used for profit estimate. Syncs with Cost of Goods value.', 'one-ba-auctions' ),
							'desc_tip'          => true,
						)
					);
					woocommerce_wp_select(
						array(
							'id'            => '_tax_status',
							'label'         => __( 'Tax status', 'woocommerce' ),
							'options'       => $tax_statuses,
							'value'         => $product_obj ? $product_obj->get_tax_status( 'edit' ) : '',
							'wrapper_class' => 'show_if_auction',
						)
					);
					woocommerce_wp_select(
						array(
							'id'            => '_tax_class',
							'label'         => __( 'Tax class', 'woocommerce' ),
							'options'       => $tax_class_options,
							'value'         => $product_obj ? $product_obj->get_tax_class( 'edit' ) : '',
							'wrapper_class' => 'show_if_auction',
						)
					);
					?>
				</div>
			</div>

			<div class="oba-auction-subtab-panel" data-panel="inventory">
				<div class="options_group">
					<?php
					woocommerce_wp_text_input(
						array(
							'id'          => '_sku',
							'label'       => __( 'SKU', 'woocommerce' ),
							'desc_tip'    => true,
							'description' => __( 'Unique identifier for stock control.', 'woocommerce' ),
						)
					);
					woocommerce_wp_checkbox(
						array(
							'id'          => '_manage_stock',
							'label'       => __( 'Manage stock?', 'woocommerce' ),
							'description' => __( 'Enable stock management at product level', 'woocommerce' ),
						)
					);
					woocommerce_wp_text_input(
						array(
							'id'                => '_stock',
							'label'             => __( 'Stock quantity', 'woocommerce' ),
							'type'              => 'number',
							'custom_attributes' => array(
								'step' => '1',
								'min'  => '0',
							),
						)
					);
					woocommerce_wp_select(
						array(
							'id'      => '_backorders',
							'label'   => __( 'Allow backorders?', 'woocommerce' ),
							'options' => $backorder_options,
						)
					);
					woocommerce_wp_checkbox(
						array(
							'id'          => '_sold_individually',
							'label'       => __( 'Sold individually', 'woocommerce' ),
							'description' => __( 'Limit purchases to 1 item per order', 'woocommerce' ),
						)
					);
					woocommerce_wp_text_input(
						array(
							'id'                => '_low_stock_amount',
							'label'             => __( 'Low stock threshold', 'woocommerce' ),
							'type'              => 'number',
							'custom_attributes' => array(
								'step' => '1',
								'min'  => '0',
							),
						)
					);
					woocommerce_wp_select(
						array(
							'id'      => '_stock_status',
							'label'   => __( 'Stock status', 'woocommerce' ),
							'options' => wc_get_product_stock_status_options(),
						)
					);
					?>
				</div>
			</div>

			<div class="oba-auction-subtab-panel" data-panel="shipping">
				<div class="options_group">
					<?php
					woocommerce_wp_text_input(
						array(
							'id'                => '_weight',
							'label'             => __( 'Weight (kg)', 'woocommerce' ),
							'type'              => 'text',
							'data_type'         => 'decimal',
						)
					);
					woocommerce_wp_text_input(
						array(
							'id'                => '_length',
							'label'             => __( 'Length (cm)', 'woocommerce' ),
							'type'              => 'text',
							'data_type'         => 'decimal',
						)
					);
					woocommerce_wp_text_input(
						array(
							'id'                => '_width',
							'label'             => __( 'Width (cm)', 'woocommerce' ),
							'type'              => 'text',
							'data_type'         => 'decimal',
						)
					);
					woocommerce_wp_text_input(
						array(
							'id'                => '_height',
							'label'             => __( 'Height (cm)', 'woocommerce' ),
							'type'              => 'text',
							'data_type'         => 'decimal',
						)
					);
					woocommerce_wp_select(
						array(
							'id'      => 'product_shipping_class',
							'label'   => __( 'Shipping class', 'woocommerce' ),
							'options' => $shipping_class_options,
						)
					);
					?>
				</div>
			</div>

			<div class="oba-auction-subtab-panel" data-panel="settings">
				<div class="options_group">
					<?php
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
							'id'          => '_bid_product_id',
							'label'       => __( 'Bid fee product', 'one-ba-auctions' ),
							'options'     => $bid_products,
							'desc_tip'    => true,
							'description' => __( 'Product representing cost per bid.', 'one-ba-auctions' ),
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
				</div>
				<p>
					<strong><?php esc_html_e( 'Profit (approx.):', 'one-ba-auctions' ); ?></strong>
					<span id="oba_reg_points_value"><?php echo wp_kses_post( wc_price( ( $current_pts * $points_rate ) - $current_cost ) ); ?></span>
					<br><span class="description"><?php esc_html_e( 'Points × participants × point value minus cost of goods.', 'one-ba-auctions' ); ?></span>
				</p>
			</div>

			<div class="oba-auction-subtab-panel" data-panel="other">
				<div class="options_group">
					<?php
					woocommerce_wp_checkbox(
						array(
							'id'          => '_oba_autobid_enabled',
							'label'       => __( 'Enable autobid for this auction', 'one-ba-auctions' ),
							'description' => __( 'Allow users to enable autobid for this auction.', 'one-ba-auctions' ),
						)
					);
					woocommerce_wp_checkbox(
						array(
							'id'          => '_allow_live_join',
							'label'       => __( 'Allow live joining', 'one-ba-auctions' ),
							'description' => __( 'Let users join during live stage for extra points.', 'one-ba-auctions' ),
						)
					);
					woocommerce_wp_text_input(
						array(
							'id'          => '_live_join_points',
							'label'       => __( 'Live join points required', 'one-ba-auctions' ),
							'type'        => 'number',
							'custom_attributes' => array(
								'step' => '1',
								'min'  => '0',
							),
							'description' => __( 'Points charged when a user joins during live stage.', 'one-ba-auctions' ),
							'desc_tip'    => true,
						)
					);
					woocommerce_wp_checkbox(
						array(
							'id'          => '_oba_buy_now_enabled',
							'label'       => __( 'Enable Buy It Now', 'one-ba-auctions' ),
							'description' => __( 'Show Buy It Now tab and allow direct purchase alongside auction.', 'one-ba-auctions' ),
						)
					);
					woocommerce_wp_text_input(
						array(
							'id'          => '_oba_buy_now_points',
							'label'       => __( 'Points granted on Buy It Now', 'one-ba-auctions' ),
							'type'        => 'number',
							'custom_attributes' => array(
								'step' => '1',
								'min'  => '0',
							),
							'description' => __( 'Points awarded to the buyer when purchasing via Buy It Now.', 'one-ba-auctions' ),
							'desc_tip'    => true,
						)
					);
					?>
				</div>
			</div>

			<div class="oba-auction-subtab-panel" data-panel="status">
				<div class="options_group">
					<?php
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
					?>
				</div>
			</div>

			<div class="oba-auction-subtab-panel" data-panel="stats">
				<div class="options_group">
					<?php
					$published_at = $current_id ? get_post_time( 'U', true, $current_id ) : 0;
					$days_since   = $published_at ? max( 0, floor( ( time() - $published_at ) / DAY_IN_SECONDS ) ) : 0;
					$required     = (int) get_post_meta( $current_id, '_required_participants', true );
					$registered   = ( $current_id && $this->repo ) ? $this->repo->get_participant_count( $current_id ) : 0;
					$reg_points   = (float) get_post_meta( $current_id, '_registration_points', true );
					$points_rate  = isset( $settings['points_value'] ) ? (float) $settings['points_value'] : 1;
					$est_profit   = ( $reg_points * $registered * $points_rate ) - $current_cost;
					?>
					<p><strong><?php esc_html_e( 'Days since publish:', 'one-ba-auctions' ); ?></strong> <?php echo esc_html( $days_since ); ?></p>
					<p><strong><?php esc_html_e( 'Participants:', 'one-ba-auctions' ); ?></strong> <?php echo esc_html( $registered ); ?> / <?php echo esc_html( $required ?: '—' ); ?></p>
					<p><strong><?php esc_html_e( 'Estimated profit:', 'one-ba-auctions' ); ?></strong> <?php echo wp_kses_post( wc_price( $est_profit ) ); ?></p>
					<p class="description"><?php esc_html_e( 'Profit = registration points × registered × point value minus cost of goods.', 'one-ba-auctions' ); ?></p>
				</div>
			</div>
		</div>
		<style>
			.oba-auction-subtab-nav{display:flex;gap:6px;margin:0 0 12px;padding:0;list-style:none;}
			.oba-auction-subtab-nav li{padding:6px 10px;border:1px solid #dcdcde;border-radius:4px;cursor:pointer;background:#f6f7f7;}
			.oba-auction-subtab-nav li.active{background:#2271b1;color:#fff;border-color:#2271b1;}
			.oba-auction-subtab-panel{display:none;}
			.oba-auction-subtab-panel.is-active{display:block;}
		</style>
		<script>
			jQuery(function($){
				const rate = <?php echo wp_json_encode( $points_rate ); ?>;
				const participants = parseFloat($('#_required_participants').val() || 0);
				let cost = <?php echo wp_json_encode( $current_cost ); ?>;
				function calc() {
					const pts = parseFloat($('#_registration_points').val() || 0);
					cost = parseFloat($('#_wc_cog_cost').val() || cost || 0);
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
			'_allow_live_join',
			'_live_join_points',
			'_oba_buy_now_enabled',
			'_oba_buy_now_points',
			'_wc_cog_cost',
			'_regular_price',
			'_sale_price',
			'_tax_status',
			'_tax_class',
			'_sku',
			'_manage_stock',
			'_stock',
			'_backorders',
			'_low_stock_amount',
			'_stock_status',
			'_sold_individually',
			'_weight',
			'_length',
			'_width',
			'_height',
			'product_shipping_class',
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

		// Keep legacy cost field in sync for calculations.
		if ( isset( $_POST['_wc_cog_cost'] ) ) {
			$mirror = wc_clean( wp_unslash( $_POST['_wc_cog_cost'] ) );
			update_post_meta( $product_id, '_product_cost', $mirror );
		}

		// Sync inventory/shipping fields via product object to keep WC internals happy.
		$product = wc_get_product( $product_id );
		if ( $product ) {
			if ( isset( $_POST['_sku'] ) ) {
				$product->set_sku( wc_clean( wp_unslash( $_POST['_sku'] ) ) );
			}
			$product->set_manage_stock( isset( $_POST['_manage_stock'] ) ? 'yes' : 'no' );
			if ( isset( $_POST['_stock'] ) && '' !== $_POST['_stock'] ) {
				$product->set_stock_quantity( wc_clean( wp_unslash( $_POST['_stock'] ) ) );
			}
			if ( isset( $_POST['_backorders'] ) ) {
				$product->set_backorders( wc_clean( wp_unslash( $_POST['_backorders'] ) ) );
			}
			if ( isset( $_POST['_low_stock_amount'] ) && $_POST['_low_stock_amount'] !== '' ) {
				$product->set_low_stock_amount( wc_clean( wp_unslash( $_POST['_low_stock_amount'] ) ) );
			}
			if ( isset( $_POST['_stock_status'] ) ) {
				$product->set_stock_status( wc_clean( wp_unslash( $_POST['_stock_status'] ) ) );
			}
			if ( isset( $_POST['_sold_individually'] ) ) {
				$product->set_sold_individually( 'yes' );
			} else {
				$product->set_sold_individually( 'no' );
			}
			if ( isset( $_POST['_weight'] ) ) {
				$product->set_weight( wc_clean( wp_unslash( $_POST['_weight'] ) ) );
			}
			foreach ( array( '_length' => 'length', '_width' => 'width', '_height' => 'height' ) as $key => $prop ) {
				if ( isset( $_POST[ $key ] ) ) {
					$setter = 'set_' . $prop;
					$product->{$setter}( wc_clean( wp_unslash( $_POST[ $key ] ) ) );
				}
			}
			if ( isset( $_POST['product_shipping_class'] ) ) {
				$product->set_shipping_class_id( absint( $_POST['product_shipping_class'] ) );
			}
			$product->save();
		}

		// Enforce cron-safe live timer if autobid enabled for this auction.
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
		if ( ! defined( 'OBA_EMBED_AUCTION_ONLY' ) ) {
			define( 'OBA_EMBED_AUCTION_ONLY', true );
		}
		wc_get_template(
			'oba-single-auction.php',
			array( 'product' => $product ),
			'',
			OBA_PLUGIN_DIR . 'templates/'
		);
	}

	public function render_explainer_bar() {}

	/**
	 * Admin inline script to show core simple-product fields for auctions.
	 */
	public function admin_footer_scripts() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}
		?>
		<script>
			jQuery(function($){
				function obaShowAuctionFields() {
					$('.show_if_simple, .show_if_grouped, .show_if_external, .show_if_variable').addClass('show_if_auction');
				}

				function obaMaybeOpenAuctionTab() {
					const type = $('#product-type').val();
					const $tabs = $('.product_data_tabs');
					const $auctionTabLink = $tabs.find('a[href="#oba_auction_product_data"]');
					const $generalTabLink = $tabs.find('a[href="#general_product_data"]');
					const $linkedTabLink = $tabs.find('a[href="#linked_product_data"]');
					const $variationsTabLink = $tabs.find('a[href="#variable_product_options"]');
					const $inventoryTabLink = $tabs.find('a[href="#inventory_product_data"]');
					const $shippingTabLink = $tabs.find('a[href="#shipping_product_data"]');
					const $generalPanel = $('#general_product_data');
					const $linkedPanel = $('#linked_product_data');
					const $variationsPanel = $('#variable_product_options');
					const $inventoryPanel = $('#inventory_product_data');
					const $shippingPanel = $('#shipping_product_data');

					if (!type || !$tabs.length) { return; }

					if (type === 'auction') {
						// Switch to Auction tab automatically so the merchant sees the fields immediately.
						if ($auctionTabLink.length) {
							$auctionTabLink.trigger('click');
						}
						// Hide tabs we don't want for auction products.
						$generalTabLink.closest('li').hide();
						$linkedTabLink.closest('li').hide();
						$variationsTabLink.closest('li').hide();
						$inventoryTabLink.closest('li').hide();
						$shippingTabLink.closest('li').hide();
						$generalPanel.hide();
						$linkedPanel.hide();
						$variationsPanel.hide();
						$inventoryPanel.hide();
						$shippingPanel.hide();
					} else {
						// Return to General tab for non-auction types.
						if ($generalTabLink.length) {
							$generalTabLink.trigger('click');
						}
						$generalTabLink.closest('li').show();
						$linkedTabLink.closest('li').show();
						$variationsTabLink.closest('li').show();
						$inventoryTabLink.closest('li').show();
						$shippingTabLink.closest('li').show();
						$generalPanel.show();
						$linkedPanel.show();
						$variationsPanel.show();
						$inventoryPanel.show();
						$shippingPanel.show();
					}
				}

				function obaInitSubTabs() {
					const $nav = $('.oba-auction-subtab-nav');
					if (!$nav.length) { return; }
					$nav.off('click', 'li').on('click', 'li', function(e){
						e.preventDefault();
						const target = $(this).data('panel');
						$(this).addClass('active').siblings().removeClass('active');
						$('.oba-auction-subtab-panel').removeClass('is-active').hide();
						$('.oba-auction-subtab-panel[data-panel="'+target+'"]').addClass('is-active').show();
					});
					// Ensure only active panel is visible on load.
					$('.oba-auction-subtab-panel').hide();
					$('.oba-auction-subtab-panel.is-active').show();
				}

				obaShowAuctionFields();
				obaMaybeOpenAuctionTab();
				obaInitSubTabs();

				$(document.body).on('woocommerce-product-type-change', function() {
					obaShowAuctionFields();
					obaMaybeOpenAuctionTab();
					obaInitSubTabs();
				});
			});
		</script>
		<?php
	}
}
