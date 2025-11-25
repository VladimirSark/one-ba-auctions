<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Product_Auction' ) && class_exists( 'WC_Product_Simple' ) ) {
	class WC_Product_Auction extends WC_Product_Simple {
		public function get_type() {
			return 'auction';
		}
	}
}
