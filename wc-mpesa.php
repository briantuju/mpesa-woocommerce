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

function mpesa_load_env(): void {
	$file = dirname( __FILE__ ) . '/.env';

	if ( ! file_exists( $file ) ) {
		return;
	}

	$lines = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

	foreach ( $lines as $line ) {
		$line = trim( $line );

		if ( '' === $line || str_starts_with( $line, '#' ) ) {
			continue;
		}

		$parts = explode( '=', $line, 2 );
		$key   = trim( $parts[0] );
		$value = isset( $parts[1] ) ? trim( trim( $parts[1] ), '"' ) : '';

		if ( str_starts_with( $key, 'MPESA_' ) && ! defined( $key ) ) {
			define( $key, $value );
		}
	}
}

add_action( 'plugins_loaded', 'mpesa_gateway_init', 0 );

function mpesa_gateway_init(): void {
	mpesa_load_env();

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
					'default'  => defined( 'MPESA_ENVIRONMENT' ) && MPESA_ENVIRONMENT ? MPESA_ENVIRONMENT : 'sandbox',
					'desc_tip' => true,
				),
				'consumer_key'    => array(
					'title'    => __( 'Consumer Key', MPESA_TD ),
					'type'     => 'text',
					'default'  => defined( 'MPESA_CONSUMER_KEY' ) ? MPESA_CONSUMER_KEY : '',
					'desc_tip' => true,
				),
				'consumer_secret' => array(
					'title'   => __( 'Consumer Secret', MPESA_TD ),
					'type'    => 'password',
					'default' => defined( 'MPESA_CONSUMER_SECRET' ) ? MPESA_CONSUMER_SECRET : '',
				),
				'paybill'         => array(
					'title'       => __( 'PayBill / Till Number', MPESA_TD ),
					'type'        => 'text',
					'default'     => defined( 'MPESA_SHORTCODE' ) ? MPESA_SHORTCODE : '',
					'description' => __( 'Business shortcode, e.g. 174379', MPESA_TD ),
				),
				'passkey'         => array(
					'title'       => __( 'Lipa Na M-Pesa Online Pass Key', MPESA_TD ),
					'type'        => 'password',
					'default'     => defined( 'MPESA_PASSKEY' ) ? MPESA_PASSKEY : '',
					'description' => __( 'Used to build the STK push request password', MPESA_TD ),
				),
				'callback_url'    => array(
					'title'       => __( 'Callback URL', MPESA_TD ),
					'type'        => 'text',
					'default'     => defined( 'MPESA_CALLBACK_URL' ) && MPESA_CALLBACK_URL ? MPESA_CALLBACK_URL : home_url( '/wc-api/wc_gateway_mpesa' ),
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