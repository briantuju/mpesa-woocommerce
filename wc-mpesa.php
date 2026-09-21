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
			$this->id                 = 'mpesa';
			$this->method_title       = __( 'M-Pesa', 'wc-mpesa' );
			$this->method_description = __( 'Let customers pay to your store using M-Pesa', 'wc-mpesa' );
			$this->has_fields         = true;

			$this->init_form_fields();
			$this->init_settings();

			$this->title       = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );

			add_action(
				'woocommerce_update_options_payment_gateways_' . $this->id,
				array( $this, 'process_admin_options' )
			);
		}

		public function init_form_fields(): void {
			$this->form_fields = array(
				'enabled'         => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable M-Pesa payments', 'wc-mpesa' ),
					'default' => 'yes',
				),
				'title'           => array(
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'M-Pesa', 'wc-mpesa' ),
					'desc_tip'    => true,
				),
				'description'     => array(
					'title'   => __( 'Customer Message', 'woocommerce' ),
					'type'    => 'textarea',
					'default' => __( 'Enter your M-Pesa phone number to pay', 'wc-mpesa' ),
				),
				'environment'     => array(
					'title'    => __( 'Environment', 'wc-mpesa' ),
					'type'     => 'select',
					'options'  => array(
						'sandbox' => __( 'Sandbox (test)', 'wc-mpesa' ),
						'live'    => __( 'Live (production)', 'wc-mpesa' ),
					),
					'default'  => 'sandbox',
					'desc_tip' => true,
				),
				'consumer_key'    => array(
					'title'    => __( 'Consumer Key', 'wc-mpesa' ),
					'type'     => 'text',
					'desc_tip' => true,
				),
				'consumer_secret' => array(
					'title' => __( 'Consumer Secret', 'wc-mpesa' ),
					'type'  => 'password',
				),
				'paybill'         => array(
					'title'       => __( 'PayBill / Till Number', 'wc-mpesa' ),
					'type'        => 'text',
					'description' => __( 'Business shortcode, e.g. 174379', 'wc-mpesa' ),
				),
				'passkey'         => array(
					'title'       => __( 'Lipa Na M-Pesa Online Pass Key', 'wc-mpesa' ),
					'type'        => 'password',
					'description' => __( 'Used to build the STK push request password', 'wc-mpesa' ),
				),
				'callback_url'    => array(
					'title'       => __( 'Callback URL', 'wc-mpesa' ),
					'type'        => 'text',
					'default'     => home_url( '/wc-api/wc_gateway_mpesa' ),
					'description' => __( 'Where Daraja sends the STK push result', 'wc-mpesa' ),
				),
			);
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