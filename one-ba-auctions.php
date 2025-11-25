<?php
/**
 * Plugin Name: One BA Auctions
 * Description: Credits-based auction system with AJAX-driven 4-step flow.
 * Version: 0.1.0
 * Author: Codex
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OBA_VERSION', '0.1.0' );
define( 'OBA_PLUGIN_FILE', __FILE__ );
define( 'OBA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OBA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once OBA_PLUGIN_DIR . 'includes/class-activator.php';
require_once OBA_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'OBA_Activator', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		if ( did_action( 'oba_loaded' ) ) {
			return;
		}

		do_action( 'oba_loaded' );

		$plugin = new OBA_Plugin();
		$plugin->init();
	}
);
