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

define( 'MPESA_TD', 'wc-mpesa' );

add_action( 'plugins_loaded', 'mpesa_gateway_init', 0 );

function mpesa_gateway_init(): void {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class WC_Gateway_Mpesa extends WC_Payment_Gateway {
		public function __construct() {
			$this->id                 = 'mpesa';
			$this->method_title       = __( 'M-Pesa', MPESA_TD );
			$this->method_description = __( 'Let customers pay to your store using M-Pesa', MPESA_TD );
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
					'label'   => __( 'Enable M-Pesa payments', MPESA_TD ),
					'default' => 'yes',
				),
				'title'           => array(
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'M-Pesa', MPESA_TD ),
					'desc_tip'    => true,
				),
				'description'     => array(
					'title'   => __( 'Customer Message', 'woocommerce' ),
					'type'    => 'textarea',
					'default' => __( 'Enter your M-Pesa phone number to pay', MPESA_TD ),
				),
				'environment'     => array(
					'title'    => __( 'Environment', MPESA_TD ),
					'type'     => 'select',
					'options'  => array(
						'sandbox' => __( 'Sandbox (test)', MPESA_TD ),
						'live'    => __( 'Live (production)', MPESA_TD ),
					),
					'default'  => 'sandbox',
					'desc_tip' => true,
				),
				'consumer_key'    => array(
					'title'    => __( 'Consumer Key', MPESA_TD ),
					'type'     => 'text',
					'desc_tip' => true,
				),
				'consumer_secret' => array(
					'title' => __( 'Consumer Secret', MPESA_TD ),
					'type'  => 'password',
				),
				'paybill'         => array(
					'title'       => __( 'PayBill / Till Number', MPESA_TD ),
					'type'        => 'text',
					'description' => __( 'Business shortcode, e.g. 174379', MPESA_TD ),
				),
				'passkey'         => array(
					'title'       => __( 'Lipa Na M-Pesa Online Pass Key', MPESA_TD ),
					'type'        => 'password',
					'description' => __( 'Used to build the STK push request password', MPESA_TD ),
				),
				'callback_url'    => array(
					'title'       => __( 'Callback URL', MPESA_TD ),
					'type'        => 'text',
					'default'     => home_url( '/wc-api/wc_gateway_mpesa' ),
					'description' => __( 'Where Daraja sends the STK push result', MPESA_TD ),
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