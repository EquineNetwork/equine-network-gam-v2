<?php
if ( ! defined( 'WPINC' ) ) die;

/**
 * Thin wrapper around the Bitly v4 API for creating short links.
 * Uses a Generic Access Token stored in the WordPress options table.
 */
class Equinenetwork_Gam_V2_Bitly {

	const TOKEN_OPTION = 'engam_v2_bitly_token';
	const API_BASE     = 'https://api-ssl.bitly.com/v4';

	/**
	 * Whether a Bitly token is configured.
	 */
	public static function is_configured() {
		$token = get_option( self::TOKEN_OPTION, '' );
		return ! empty( $token );
	}

	/**
	 * Shorten a long URL. Returns the bit.ly link string or a WP_Error.
	 */
	public static function shorten( $long_url ) {
		$token = trim( (string) get_option( self::TOKEN_OPTION, '' ) );
		if ( empty( $token ) ) {
			return new WP_Error( 'no_token', 'No Bitly access token is configured. Add one on the Settings page.' );
		}

		$long_url = esc_url_raw( trim( (string) $long_url ) );
		if ( empty( $long_url ) ) {
			return new WP_Error( 'no_url', 'Enter a Click URL before shortening.' );
		}

		$response = wp_remote_post( self::API_BASE . '/shorten', array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array( 'long_url' => $long_url ) ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// 200 = existing link returned, 201 = newly created. Both are success.
		if ( $code !== 200 && $code !== 201 ) {
			$msg = isset( $body['description'] ) ? $body['description']
				: ( isset( $body['message'] ) ? $body['message'] : wp_remote_retrieve_body( $response ) );
			return new WP_Error( 'bitly_error', 'Bitly API error (HTTP ' . $code . '): ' . substr( $msg, 0, 200 ) );
		}

		if ( empty( $body['link'] ) ) {
			return new WP_Error( 'bitly_no_link', 'Bitly did not return a short link.' );
		}

		return esc_url_raw( $body['link'] );
	}
}
