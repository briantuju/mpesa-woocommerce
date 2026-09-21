<?php
/**
 * Plugin Name: M-Pesa Payment Gateway for WooCommerce
 * Description: Accept payments via Safaricom M-Pesa STK Push (Lipa Na M-Pesa Online).
 * Version: 0.1.0
 * Author: Brian Tuju
 * Text Domain: wc-mpesa
 * Requires Plugins: woocommerce
 */

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'mpesa_gateway_init', 0 );

function mpesa_gateway_init(): void {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class WC_Gateway_Mpesa extends WC_Payment_Gateway {
		public function __construct() {
			$this->id = 'mpesa';
		}
	}

	add_filter(
		'woocommerce_payment_gateways',
		function ( $methods ) {
			$methods[] = 'WC_Gateway_Mpesa';

			return $methods;
		}
	);
}