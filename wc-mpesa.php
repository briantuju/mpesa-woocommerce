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
		const TD = 'wc-mpesa';

		public function __construct() {
			$this->id                 = 'mpesa';
			$this->method_title       = __( 'M-Pesa', self::TD );
			$this->method_description = __( 'Let customers pay to your store using M-Pesa', self::TD );
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
					'label'   => __( 'Enable M-Pesa payments', self::TD ),
					'default' => 'yes',
				),
				'title'           => array(
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'M-Pesa', self::TD ),
					'desc_tip'    => true,
				),
				'description'     => array(
					'title'   => __( 'Customer Message', 'woocommerce' ),
					'type'    => 'textarea',
					'default' => __( 'Enter your M-Pesa phone number to pay', self::TD ),
				),
				'environment'     => array(
					'title'    => __( 'Environment', self::TD ),
					'type'     => 'select',
					'options'  => array(
						'sandbox' => __( 'Sandbox (test)', self::TD ),
						'live'    => __( 'Live (production)', self::TD ),
					),
					'default'  => 'sandbox',
					'desc_tip' => true,
				),
				'consumer_key'    => array(
					'title'    => __( 'Consumer Key', self::TD ),
					'type'     => 'text',
					'desc_tip' => true,
				),
				'consumer_secret' => array(
					'title' => __( 'Consumer Secret', self::TD ),
					'type'  => 'password',
				),
				'paybill'         => array(
					'title'       => __( 'PayBill / Till Number', self::TD ),
					'type'        => 'text',
					'description' => __( 'Business shortcode, e.g. 174379', self::TD ),
				),
				'passkey'         => array(
					'title'       => __( 'Lipa Na M-Pesa Online Pass Key', self::TD ),
					'type'        => 'password',
					'description' => __( 'Used to build the STK push request password', self::TD ),
				),
				'callback_url'    => array(
					'title'       => __( 'Callback URL', self::TD ),
					'type'        => 'text',
					'default'     => home_url( '/wc-api/wc_gateway_mpesa' ),
					'description' => __( 'Where Daraja sends the STK push result', self::TD ),
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