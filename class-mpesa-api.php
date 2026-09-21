<?php

defined( 'ABSPATH' ) || exit;

class MPesa_API {

	private string $base_url;

	private string $consumer_key;

	private string $consumer_secret;

	public function __construct(
		string $environment, string $consumer_key, string $consumer_secret
	) {
		$this->base_url        = 'live' === $environment
			? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
		$this->consumer_key    = $consumer_key;
		$this->consumer_secret = $consumer_secret;
	}

	/**
	 * @return string|WP_Error
	 */
	public function get_access_token(): WP_Error|string {
		$response = wp_remote_get(
			$this->base_url
			. '/oauth/v1/generate?grant_type=client_credentials',
			array(
				'headers' => array(
					'Authorization' => 'Basic '
					                   . base64_encode( $this->consumer_key
					                                    . ':'
					                                    . $this->consumer_secret ),
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			$message = isset( $body['error_description'] )
				? $body['error_description']
				: wp_remote_retrieve_response_message( $response );

			return new WP_Error( 'mpesa_auth_failed', $message );
		}

		return $body['access_token'];
	}

	/**
	 * @param string $access_token
	 * @param array  $params
	 *
	 * @return array|WP_Error
	 */
	public function stk_push( string $access_token, array $params
	): array|WP_Error {
		$timestamp = gmdate( 'YmdHis', time() + 3 * HOUR_IN_SECONDS );
		$password  = base64_encode( $params['shortcode'] . $params['passkey']
		                            . $timestamp );

		$body = array(
			'BusinessShortCode' => $params['shortcode'],
			'Password'          => $password,
			'Timestamp'         => $timestamp,
			'TransactionType'   => 'CustomerPayBillOnline',
			'Amount'            => $params['amount'],
			'PartyA'            => $params['phone'],
			'PartyB'            => $params['shortcode'],
			'PhoneNumber'       => $params['phone'],
			'CallBackURL'       => $params['callback_url'],
			'AccountReference'  => $params['account_ref'],
			'TransactionDesc'   => $params['transaction_desc'],
		);

		$response = wp_remote_post(
			$this->base_url . '/mpesa/stkpush/v1/processrequest',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code   = (int) wp_remote_retrieve_response_code( $response );
		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$message = isset( $result['errorMessage'] )
				? $result['errorMessage']
				: wp_remote_retrieve_response_message( $response );

			return new WP_Error( 'mpesa_stk_push_failed', $message );
		}

		return $result;
	}
}