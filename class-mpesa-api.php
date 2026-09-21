<?php

defined( 'ABSPATH' ) || exit;

class MPesa_API {

	private $base_url;

	private $consumer_key;

	private $consumer_secret;

	public function __construct( string $environment, string $consumer_key, string $consumer_secret ) {
		$this->base_url        = 'live' === $environment ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
		$this->consumer_key    = $consumer_key;
		$this->consumer_secret = $consumer_secret;
	}

	/**
	 * @return string|WP_Error
	 */
	public function get_access_token() {
		$response = wp_remote_get(
			$this->base_url . '/oauth/v1/generate?grant_type=client_credentials',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->consumer_key . ':' . $this->consumer_secret ),
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
			$message = isset( $body['error_description'] ) ? $body['error_description'] : wp_remote_retrieve_response_message( $response );

			return new WP_Error( 'mpesa_auth_failed', $message );
		}

		return $body['access_token'];
	}
}