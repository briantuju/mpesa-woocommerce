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

require_once dirname( __FILE__ ) . '/class-mpesa-api.php';

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
			$this->id           = 'mpesa';
			$this->method_title = __( 'M-Pesa', MPESA_TD );
			$this->method_description
			                    = __( 'Let customers pay to your store using M-Pesa',
					MPESA_TD );
			$this->has_fields   = true;

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
					'description' => __( 'This controls the title which the user sees during checkout.',
						'woocommerce' ),
					'default'     => __( 'M-Pesa', MPESA_TD ),
					'desc_tip'    => true,
				),
				'description'     => array(
					'title'   => __( 'Customer Message', 'woocommerce' ),
					'type'    => 'textarea',
					'default' => __( 'Enter your M-Pesa phone number to pay',
						MPESA_TD ),
				),
				'environment'     => array(
					'title'    => __( 'Environment', MPESA_TD ),
					'type'     => 'select',
					'options'  => array(
						'sandbox' => __( 'Sandbox (test)', MPESA_TD ),
						'live'    => __( 'Live (production)', MPESA_TD ),
					),
					'default'  => defined( 'MPESA_ENVIRONMENT' )
					              && MPESA_ENVIRONMENT ? MPESA_ENVIRONMENT
						: 'sandbox',
					'desc_tip' => true,
				),
				'consumer_key'    => array(
					'title'    => __( 'Consumer Key', MPESA_TD ),
					'type'     => 'text',
					'default'  => defined( 'MPESA_CONSUMER_KEY' )
						? MPESA_CONSUMER_KEY : '',
					'desc_tip' => true,
				),
				'consumer_secret' => array(
					'title'   => __( 'Consumer Secret', MPESA_TD ),
					'type'    => 'password',
					'default' => defined( 'MPESA_CONSUMER_SECRET' )
						? MPESA_CONSUMER_SECRET : '',
				),
				'paybill'         => array(
					'title'       => __( 'PayBill / Till Number', MPESA_TD ),
					'type'        => 'text',
					'default'     => defined( 'MPESA_SHORTCODE' )
						? MPESA_SHORTCODE : '',
					'description' => __( 'Business shortcode, e.g. 174379',
						MPESA_TD ),
				),
				'passkey'         => array(
					'title'       => __( 'Lipa Na M-Pesa Online Pass Key',
						MPESA_TD ),
					'type'        => 'password',
					'default'     => defined( 'MPESA_PASSKEY' ) ? MPESA_PASSKEY
						: '',
					'description' => __( 'Used to build the STK push request password',
						MPESA_TD ),
				),
				'callback_url'    => array(
					'title'       => __( 'Callback URL', MPESA_TD ),
					'type'        => 'text',
					'default'     => defined( 'MPESA_CALLBACK_URL' )
					                 && MPESA_CALLBACK_URL ? MPESA_CALLBACK_URL
						: home_url( '/wc-api/wc_gateway_mpesa' ),
					'description' => __( 'Where Daraja sends the STK push result',
						MPESA_TD ),
				),
			);
		}

		public function payment_fields(): void {
			woocommerce_form_field(
				'mpesa_phone',
				array(
					'type'        => 'tel',
					'label'       => __( 'M-Pesa phone number', MPESA_TD ),
					'placeholder' => '07XXXXXXXX or 2547XXXXXXXX',
					'required'    => true,
				)
			);
		}

		public function validate_fields(): bool {
			$phone = isset( $_POST['mpesa_phone'] )
				? sanitize_text_field( wp_unslash( $_POST['mpesa_phone'] ) )
				: '';

			$phone = preg_replace( '/[^0-9]/', '', $phone );
			$phone = ltrim( $phone, '0' );
			$phone = '254' . $phone;

			if ( ! preg_match( '/^2547\d{8}$/', $phone ) ) {
				wc_add_notice( __( 'Please enter a valid M-Pesa number, e.g. 0712345678',
					MPESA_TD ), 'error' );

				return false;
			}

			$_POST['mpesa_phone'] = $phone;

			return true;
		}

		public function process_payment( $order_id ): array {
			$order = wc_get_order( $order_id );

			$phone = isset( $_POST['mpesa_phone'] )
				? sanitize_text_field( wp_unslash( $_POST['mpesa_phone'] ) )
				: '';

			$order->update_meta_data( '_mpesa_phone', $phone );
			$order->save();

			$api = new MPesa_API(
				$this->get_option( 'environment' ),
				$this->get_option( 'consumer_key' ),
				$this->get_option( 'consumer_secret' )
			);

			$access_token = $api->get_access_token();

			if ( is_wp_error( $access_token ) ) {
				wc_add_notice( __( 'Payment error:', MPESA_TD ) . ' '
				               . $access_token->get_error_message(), 'error' );

				return array( 'result' => 'failure' );
			}

			$result = $api->stk_push(
				$access_token,
				array(
					'shortcode'        => $this->get_option( 'paybill' ),
					'passkey'          => $this->get_option( 'passkey' ),
					'phone'            => $phone,
					'amount'           => (int) round( $order->get_total() ),
					'account_ref'      => (string) $order->get_id(),
					'transaction_desc' => sprintf( __( 'Payment for order #%s',
						MPESA_TD ), $order->get_id() ),
					'callback_url'     => $this->get_option( 'callback_url' ),
				)
			);

			if ( is_wp_error( $result ) ) {
				wc_add_notice( __( 'Payment error:', MPESA_TD ) . ' '
				               . $result->get_error_message(), 'error' );

				return array( 'result' => 'failure' );
			}

			if ( ! isset( $result['ResponseCode'] )
			     || '0' !== $result['ResponseCode']
			) {
				$message = isset( $result['ResponseDescription'] )
					? $result['ResponseDescription']
					: __( 'Unknown M-Pesa error', MPESA_TD );

				wc_add_notice( __( 'Payment error:', MPESA_TD ) . ' '
				               . $message, 'error' );

				return array( 'result' => 'failure' );
			}

			$order->update_meta_data( '_mpesa_checkout_request_id',
				$result['CheckoutRequestID'] );
			$order->update_status( 'on-hold',
				__( 'Awaiting M-Pesa STK push confirmation', MPESA_TD ) );
			$order->save();

			WC()->cart->empty_cart();

			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_order_received_url(),
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