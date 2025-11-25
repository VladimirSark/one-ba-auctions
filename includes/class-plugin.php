<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once OBA_PLUGIN_DIR . 'includes/class-settings.php';
require_once OBA_PLUGIN_DIR . 'includes/class-credits-service.php';
require_once OBA_PLUGIN_DIR . 'includes/class-auction-repository.php';
require_once OBA_PLUGIN_DIR . 'includes/class-auction-engine.php';
require_once OBA_PLUGIN_DIR . 'includes/class-product-type.php';
require_once OBA_PLUGIN_DIR . 'includes/class-ajax-controller.php';
require_once OBA_PLUGIN_DIR . 'includes/class-frontend.php';
require_once OBA_PLUGIN_DIR . 'includes/class-credits-order-integration.php';
require_once OBA_PLUGIN_DIR . 'includes/class-audit.php';
require_once OBA_PLUGIN_DIR . 'includes/class-ledger.php';
require_once OBA_PLUGIN_DIR . 'includes/class-email.php';
require_once OBA_PLUGIN_DIR . 'includes/class-admin.php';

class OBA_Plugin {

	public function init() {
		OBA_Activator::maybe_upgrade();
		$this->register_hooks();
	}

	private function register_hooks() {
		$product_type = new OBA_Product_Type();
		$product_type->hooks();

		$credits_integration = new OBA_Credits_Order_Integration();
		$credits_integration->hooks();

		$ajax = new OBA_Ajax_Controller();
		$ajax->hooks();

		$frontend = new OBA_Frontend();
		$frontend->hooks();

		if ( is_admin() ) {
			$admin = new OBA_Admin();
			$admin->hooks();
		}

		add_action( 'oba_run_expiry_check', array( $this, 'check_expired_auctions' ) );
		add_action( 'wp', array( $this, 'maybe_schedule_cron' ) );
	}

	public function check_expired_auctions() {
		$engine = new OBA_Auction_Engine();
		$repo   = new OBA_Auction_Repository();

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => 20,
			'meta_query'     => array(
				array(
					'key'   => '_product_type',
					'value' => 'auction',
				),
				array(
					'key'   => '_auction_status',
					'value' => 'live',
				),
			),
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		$q = new WP_Query( $args );
		if ( $q->have_posts() ) {
			foreach ( $q->posts as $auction_id ) {
				$engine->end_auction_if_expired( $auction_id );
			}
		}
	}

	public function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( 'oba_run_expiry_check' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'minute', 'oba_run_expiry_check' );
		}
	}
}
