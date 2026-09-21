<?php
/**
 * Plugin Name: M-Pesa Payment Gateway for WooCommerce
 * Description: Accept payments via Safaricom M-Pesa STK Push (Lipa Na M-Pesa Online).
 * Version: 0.1.0
 * Author: Brian Tuju
 * Text Domain: wc-mpesa
 * Requires Plugins: woocommerce
 */

use JetBrains\PhpStorm\NoReturn;

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

			if ( ! $this->get_option( 'consumer_key' )
			     || ! $this->get_option( 'consumer_secret' )
			     || ! $this->get_option( 'paybill' )
			     || ! $this->get_option( 'passkey' )
			) {
				wc_add_notice( __( 'M-Pesa is not fully configured. Please check the payment settings.',
					MPESA_TD ), 'error' );

				return array( 'result' => 'failure' );
			}

			$callback_url = $this->get_option( 'callback_url' );

			if ( 'https' !== wp_parse_url( $callback_url, PHP_URL_SCHEME ) ) {
				wc_add_notice( __( 'M-Pesa requires an HTTPS callback URL.',
					MPESA_TD ), 'error' );

				return array( 'result' => 'failure' );
			}

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
				'redirect' => add_query_arg(
					array(
						'mpesa_pay' => '1',
						'order_id'  => $order->get_id(),
						'order_key' => $order->get_order_key(),
					),
					home_url( '/' )
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

add_action( 'woocommerce_api_wc_gateway_mpesa', 'mpesa_handle_callback' );

#[NoReturn]
function mpesa_handle_callback(): void {
	status_header( 200 );

	$body = json_decode( file_get_contents( 'php://input' ), true );

	if ( ! is_array( $body ) || ! isset( $body['Body']['stkCallback'] ) ) {
		exit;
	}

	$stk = $body['Body']['stkCallback'];

	if ( ! isset( $stk['CheckoutRequestID'] ) ) {
		exit;
	}

	$orders = wc_get_orders(
		array(
			'limit'      => 1,
			'meta_key'   => '_mpesa_checkout_request_id',
			'meta_value' => $stk['CheckoutRequestID'],
		)
	);

	if ( empty( $orders ) ) {
		// maybe do some reporting here
		exit;
	}

	$order       = $orders[0];
	$result_code = isset( $stk['ResultCode'] ) ? (int) $stk['ResultCode'] : - 1;

	if ( 0 !== $result_code ) {
		$message = isset( $stk['ResultDesc'] ) ? $stk['ResultDesc']
			: __( 'M-Pesa payment failed', MPESA_TD );
		$order->update_status( 'failed', 'M-Pesa: ' . $message );

		exit;
	}

	if ( ! $order->is_paid() ) {
		$receipt  = '';
		$datetime = '';
		$amount   = 0;

		foreach (
			(array) ( $stk['CallbackMetadata']['Item'] ?? array() ) as $item
		) {
			if ( ! isset( $item['Name'], $item['Value'] ) ) {
				continue;
			}

			switch ( $item['Name'] ) {
				case 'Amount':
					$amount = (int) $item['Value'];
					break;

				case 'MpesaReceiptNumber':
					$receipt = sanitize_text_field( $item['Value'] );
					break;

				case 'TransactionDate':
					$datetime = sanitize_text_field( $item['Value'] );
					break;
			}
		}

		if ( ! $receipt
		     || $amount !== (int) round( $order->get_total() )
		) {
			$order->update_status( 'failed',
				'M-Pesa callback rejected: receipt or amount missing/mismatched' );

			exit;
		}

		$order->update_meta_data( '_mpesa_receipt_number', $receipt );
		$order->update_meta_data( '_mpesa_transaction_date', $datetime );
		$order->add_order_note( sprintf( 'M-Pesa payment received (Receipt: %s)',
			$receipt ) );
		$order->payment_complete( $receipt );
		$order->save();
	}

	exit;
}

add_action( 'template_redirect', 'mpesa_pending_page' );

add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'mpesa_pay';

		return $vars;
	}
);

function mpesa_pending_page(): void {
	if ( ! get_query_var( 'mpesa_pay' ) ) {
		return;
	}

	$order_id  = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0;
	$order_key = isset( $_GET['order_key'] )
		? sanitize_text_field( wp_unslash( $_GET['order_key'] ) ) : '';

	$order = $order_id ? wc_get_order( $order_id ) : null;

	if ( ! $order || ! hash_equals( $order->get_order_key(), $order_key ) ) {
		wp_safe_redirect( home_url( '/' ) );

		exit;
	}

	if ( $order->is_paid() ) {
		wp_safe_redirect( $order->get_checkout_order_received_url() );

		exit;
	}

	nocache_headers();

	if ( in_array( $order->get_status(), array( 'failed', 'cancelled' ),
		true )
	) {
		get_header();

		echo '<div class="mpesa-payment-result">';
		echo '<p>' . esc_html__( 'The M-Pesa payment was not completed.',
				MPESA_TD ) . '</p>';
		echo '<p><a href="' . esc_url( $order->get_checkout_payment_url() )
		     . '">'
		     . esc_html__( 'Retry payment', MPESA_TD ) . '</a></p>';
		echo '</div>';

		get_footer();

		exit;
	}

	wp_enqueue_script( 'mpesa-poll',
		plugins_url( 'assets/mpesa-poll.js', __FILE__ ), array(), '0.1.0',
		true );

	wp_localize_script(
		'mpesa-poll',
		'MPesaPoll',
		array(
			'statusId'      => 'mpesa-status',
			'statusUrl'     => add_query_arg(
				array(
					'order_id'  => $order->get_id(),
					'order_key' => $order->get_order_key(),
				),
				rest_url( 'mpesa/v1/status' )
			),
			'interval'      => 3000,
			'timeout'       => 90000,
			'successUrl'    => $order->get_checkout_order_received_url(),
			'retryUrl'      => $order->get_checkout_payment_url(),
			'retryLabel'    => __( 'Retry payment', MPESA_TD ),
			'failedLabel'   => __( 'Payment failed. You can try again.',
				MPESA_TD ),
			'timedOutLabel' => __( 'No payment confirmed yet. Check your phone.',
				MPESA_TD ),
		)
	);

	get_header();

	echo '<div class="mpesa-payment-pending">';
	echo '<p>' . esc_html__( 'Confirm payment of ', MPESA_TD )
	     . wc_price( $order->get_total() )
	     . esc_html__( ' by entering your M-Pesa PIN on your phone.', MPESA_TD )
	     . '</p>';
	echo '<div class="mpesa-spinner"></div>';
	echo '<div id="mpesa-status"></div>';
	echo '</div>';

	get_footer();

	exit;
}

add_action( 'rest_api_init', 'mpesa_register_rest_route' );

function mpesa_register_rest_route(): void {
	register_rest_route(
		'mpesa/v1',
		'/status',
		array(
			'methods'             => 'GET',
			'callback'            => 'mpesa_order_status_endpoint',
			'permission_callback' => '__return_true',
			'args'                => array(
				'order_id'  => array( 'required' => true, 'type' => 'integer' ),
				'order_key' => array( 'required' => true, 'type' => 'string' ),
			),
		)
	);
}

function mpesa_order_status_endpoint( WP_REST_Request $request
): WP_Error|WP_REST_Response|WP_HTTP_Response {
	$order_key = sanitize_text_field( wp_unslash( $request['order_key'] ) );
	$order     = wc_get_order( (int) $request['order_id'] );

	if ( ! $order || ! hash_equals( $order->get_order_key(), $order_key ) ) {
		return new WP_REST_Response(
			array( 'status' => 'invalid' ),
			404
		);
	}

	if ( $order->is_paid() ) {
		return rest_ensure_response(
			array(
				'status'  => 'paid',
				'receipt' => $order->get_meta( '_mpesa_receipt_number' ),
			)
		);
	}

	if ( in_array( $order->get_status(), array( 'failed', 'cancelled' ),
		true )
	) {
		return rest_ensure_response( array( 'status' => 'failed' ) );
	}

	return rest_ensure_response( array( 'status' => 'pending' ) );
}