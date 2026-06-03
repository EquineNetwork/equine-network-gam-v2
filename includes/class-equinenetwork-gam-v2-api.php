<?php
if ( ! defined( 'WPINC' ) ) die;

/**
 * Handles authentication and data fetching from the Google Ad Manager API.
 * Uses JWT-based OAuth2 with a service account — no user login required.
 */
class Equinenetwork_Gam_V2_API {

	const CACHE_KEY      = 'engam_v2_line_items';
	const CACHE_DURATION = 3600; // 1 hour
	const TOKEN_CACHE    = 'engam_v2_access_token';
	const GAM_REST_BASE  = 'https://admanager.googleapis.com/v1';

	private $credentials;
	private $network_code;

	public function __construct() {
		$this->network_code = get_option( 'equinenetwork_gam_v2_id', '' );

		// wp-config.php constant takes priority over DB-stored credentials.
		if ( defined( 'ENGAM_GAM_CREDENTIALS_JSON' ) && ENGAM_GAM_CREDENTIALS_JSON ) {
			$this->credentials = json_decode( ENGAM_GAM_CREDENTIALS_JSON, true );
		} else {
			$stored = get_option( 'equinenetwork_gam_v2_credentials', '' );
			if ( ! empty( $stored ) ) {
				$this->credentials = json_decode( $stored, true );
			}
		}
	}

	public function is_configured() {
		return ! empty( $this->credentials )
			&& ! empty( $this->credentials['private_key'] )
			&& ! empty( $this->credentials['client_email'] )
			&& ! empty( $this->network_code );
	}

	/**
	 * Returns cached line items or fetches fresh ones from GAM.
	 */
	public function get_line_items( $force_refresh = false ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', 'GAM API credentials not configured.' );
		}

		if ( ! $force_refresh ) {
			$cached = get_transient( self::CACHE_KEY );
			// Auto-bust cache if it's missing required fields (old format).
			if ( $cached !== false && is_array( $cached ) && ! empty( $cached )
				&& ( ! isset( $cached[0]['gam_id'] ) || ! isset( $cached[0]['resource_name'] ) || ! isset( $cached[0]['status'] ) ) ) {
				delete_transient( self::CACHE_KEY );
				$cached = false;
			}
			if ( $cached !== false ) {
				return $cached;
			}
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		return $this->fetch_line_items( $token );
	}

	/**
	 * Gets a valid OAuth2 access token, using cache if available.
	 */
	private function get_access_token() {
		$cached = get_transient( self::TOKEN_CACHE );
		if ( $cached ) {
			return $cached;
		}

		$jwt = $this->build_jwt();
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'timeout' => 15,
			'body'    => array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			$error = isset( $body['error_description'] ) ? $body['error_description'] : wp_json_encode( $body );
			return new WP_Error( 'token_error', 'OAuth2 error: ' . $error );
		}

		$expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] - 60 : 3540;
		set_transient( self::TOKEN_CACHE, $body['access_token'], $expires_in );

		return $body['access_token'];
	}

	/**
	 * Builds a signed JWT for service account OAuth2.
	 */
	private function build_jwt() {
		$now    = time();
		$header = $this->base64url_encode( json_encode( array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		) ) );

		$claim = $this->base64url_encode( json_encode( array(
			'iss'   => $this->credentials['client_email'],
			'scope' => 'https://www.googleapis.com/auth/admanager',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'exp'   => $now + 3600,
			'iat'   => $now,
		) ) );

		$payload   = $header . '.' . $claim;
		$signature = '';

		$key = $this->credentials['private_key'];
		if ( ! openssl_sign( $payload, $signature, $key, 'SHA256' ) ) {
			return new WP_Error( 'jwt_sign_error', 'Failed to sign JWT. Check that the private key is valid.' );
		}

		return $payload . '.' . $this->base64url_encode( $signature );
	}

	private function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Fetches line items from the GAM REST API (admanager.googleapis.com/v1).
	 * Paginates through all results automatically.
	 */
	private function fetch_line_items( $token ) {
		$network_code   = preg_replace( '/[^0-9]/', '', $this->network_code );
		$base_url       = self::GAM_REST_BASE . '/networks/' . $network_code . '/lineItems';
		$items          = array();
		$filter_keyword = strtolower( get_option( 'equinenetwork_gam_v2_filter', '' ) );
		$page_token     = null;

		do {
			$params = array( 'pageSize' => 1000 );
			if ( $page_token ) {
				$params['pageToken'] = $page_token;
			}

			$response = wp_remote_get( add_query_arg( $params, $base_url ), array(
				'timeout' => 30,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			) );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code !== 200 ) {
				$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : wp_remote_retrieve_body( $response );
				return new WP_Error( 'api_error', 'GAM API error (HTTP ' . $code . '): ' . substr( $msg, 0, 300 ) );
			}

			$page_token = isset( $body['nextPageToken'] ) ? $body['nextPageToken'] : null;

		if ( ! empty( $body['lineItems'] ) ) {
			foreach ( $body['lineItems'] as $li ) {
				$id = isset( $li['externalId'] ) && $li['externalId'] !== ''
					? $li['externalId']
					: basename( $li['name'] );

				$label = isset( $li['displayName'] ) && $li['displayName'] !== ''
					? $li['displayName']
					: basename( $li['name'] );

				// Apply site filter keyword if set (fallback / additional filter).
				if ( $filter_keyword && stripos( $label, $filter_keyword ) === false && stripos( $id, $filter_keyword ) === false ) {
					continue;
				}

				$gam_id = isset( $li['name'] ) ? basename( $li['name'] ) : '';

				$items[] = array(
					'id'            => $id,
					'gam_id'        => $gam_id,
					'name'          => $label,
					'status'        => isset( $li['entityStatus'] ) ? $li['entityStatus'] : ( isset( $li['deliveryStatus'] ) ? $li['deliveryStatus'] : ( isset( $li['status'] ) ? $li['status'] : '' ) ),
					'resource_name' => $li['name'] ?? '',
					'start_time'    => isset( $li['startTime'] ) ? $li['startTime'] : '',
					'end_time'      => isset( $li['endTime'] )   ? $li['endTime']   : '',
				);
			}
		}

		} while ( $page_token );

		usort( $items, function( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );

		if ( ! empty( $items ) ) {
			set_transient( self::CACHE_KEY, $items, self::CACHE_DURATION );
		}

		return $items;
	}

	/**
	 * Returns creative sizes for all active line items targeting a given ad unit slot name.
	 * Results are cached for 1 hour. Falls back to empty array if API not configured.
	 */
	public function get_slot_sizes( $slot_name, $force_refresh = false ) {
		if ( ! $this->is_configured() ) return array();

		$cache_key = 'engam_v2_sizes_' . md5( $slot_name );
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) return $cached;
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) return array();

		$network_code = preg_replace( '/[^0-9]/', '', $this->network_code );

		// Step 1: find the numeric ad unit ID for this slot name.
		$au_resp = wp_remote_get(
			add_query_arg( array( 'pageSize' => 200 ),
				self::GAM_REST_BASE . '/networks/' . $network_code . '/adUnits' ),
			array( 'timeout' => 15, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) )
		);

		$ad_unit_id = null;
		if ( ! is_wp_error( $au_resp ) ) {
			$au_body = json_decode( wp_remote_retrieve_body( $au_resp ), true );
			if ( ! empty( $au_body['adUnits'] ) ) {
				foreach ( $au_body['adUnits'] as $au ) {
					if ( isset( $au['adUnitCode'] ) && $au['adUnitCode'] === $slot_name ) {
						$parts      = explode( '/', $au['name'] );
						$ad_unit_id = end( $parts );
						break;
					}
				}
			}
		}

		if ( ! $ad_unit_id ) {
			set_transient( $cache_key, array(), self::CACHE_DURATION );
			return array();
		}

		// Step 2: fetch line items and find those targeting this ad unit.
		$li_resp = wp_remote_get(
			add_query_arg( array( 'pageSize' => 500 ),
				self::GAM_REST_BASE . '/networks/' . $network_code . '/lineItems' ),
			array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) )
		);

		$sizes = array();
		if ( ! is_wp_error( $li_resp ) ) {
			$li_body = json_decode( wp_remote_retrieve_body( $li_resp ), true );
			if ( ! empty( $li_body['lineItems'] ) ) {
				foreach ( $li_body['lineItems'] as $li ) {
					$targets = false;
					if ( isset( $li['targeting']['inventoryTargeting']['targetedAdUnits'] ) ) {
						foreach ( $li['targeting']['inventoryTargeting']['targetedAdUnits'] as $t ) {
							if ( isset( $t['adUnitId'] ) && (string) $t['adUnitId'] === (string) $ad_unit_id ) {
								$targets = true;
								break;
							}
						}
					}
					if ( ! $targets ) continue;

					if ( isset( $li['creativePlaceholders'] ) ) {
						foreach ( $li['creativePlaceholders'] as $ph ) {
							if ( isset( $ph['size']['width'], $ph['size']['height'] ) ) {
								$w = (int) $ph['size']['width'];
								$h = (int) $ph['size']['height'];
								if ( $w > 0 && $h > 0 ) {
									$sizes[] = array( $w, $h );
								}
							}
						}
					}
				}
			}
		}

		// Deduplicate by JSON encoding each size pair.
		$sizes = array_values( array_map( 'json_decode',
			array_unique( array_map( 'json_encode', $sizes ) ) ) );

		set_transient( $cache_key, $sizes, self::CACHE_DURATION );
		return $sizes;
	}

	/**
	 * Fetches active sponsor options from the configured Google Sheet.
	 * Returns array of [ 'id' => sponsor_id, 'name' => advertiser_name ] sorted by name.
	 * Sheet structure: col A = Advertiser, col C = Sponsorship ID, col D = Status.
	 * Only rows where col D = "Active" (case-insensitive) are included.
	 */
	/**
	 * Fetches active sponsor options from the published Google Sheets CSV URL.
	 * Sheet columns: A=Advertiser, B=Date Range, C=Sponsorship ID, D=Status.
	 * Only rows where Status = "Active" are included.
	 */
	public function get_sponsor_options( $force_refresh = false ) {
		$csv_url = get_option( 'engam_v2_sheet_csv_url', '' );
		if ( empty( $csv_url ) ) return array();

		$cache_key = 'engam_v2_sponsor_options';
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) return $cached;
		}

		$response = wp_remote_get( $csv_url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) return array();

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) return array();

		$csv  = wp_remote_retrieve_body( $response );
		$rows = array_map( 'str_getcsv', explode( "\n", trim( $csv ) ) );

		if ( empty( $rows ) ) return array();

		// First row is the header — skip it.
		$headers = array_shift( $rows );
		// Detect columns by header name, defaulting to A=0, C=2, D=3.
		$col_name = $col_id = $col_status = null;
		foreach ( $headers as $i => $h ) {
			$h = strtolower( trim( $h ) );
			if ( $col_name   === null && ( strpos( $h, 'advertiser' ) !== false || strpos( $h, 'name' ) !== false ) ) $col_name   = $i;
			if ( $col_id     === null && strpos( $h, 'sponsor' ) !== false && strpos( $h, 'id' ) !== false )          $col_id     = $i;
			if ( $col_status === null && strpos( $h, 'status' ) !== false )                                            $col_status = $i;
		}
		$col_name   = $col_name   ?? 0;
		$col_id     = $col_id     ?? 2;
		$col_status = $col_status ?? 3;

		$options = array();
		foreach ( $rows as $row ) {
			if ( empty( $row ) || count( $row ) <= $col_id ) continue;
			$sponsor_id = trim( $row[ $col_id ] );
			if ( $sponsor_id === '' ) continue;
			$status = isset( $row[ $col_status ] ) ? strtolower( trim( $row[ $col_status ] ) ) : '';
			if ( $status !== 'active' ) continue;
			$advertiser = isset( $row[ $col_name ] ) ? trim( $row[ $col_name ] ) : '';
			$options[]  = array(
				'id'   => $sponsor_id,
				'name' => $advertiser !== '' ? $advertiser : $sponsor_id,
			);
		}

		usort( $options, function( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );

		set_transient( $cache_key, $options, self::CACHE_DURATION );
		return $options;
	}

	/**
	 * Gets an OAuth2 token scoped for Google Sheets (read-only).
	 */
	private function get_sheets_token() {
		$cached = get_transient( 'engam_v2_sheets_token' );
		if ( $cached ) return $cached;

		$now    = time();
		$header = $this->base64url_encode( json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claim  = $this->base64url_encode( json_encode( array(
			'iss'   => $this->credentials['client_email'],
			'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'exp'   => $now + 3600,
			'iat'   => $now,
		) ) );

		$payload   = $header . '.' . $claim;
		$signature = '';
		if ( ! openssl_sign( $payload, $signature, $this->credentials['private_key'], 'SHA256' ) ) {
			return new WP_Error( 'jwt_sign_error', 'Failed to sign Sheets JWT.' );
		}
		$jwt = $payload . '.' . $this->base64url_encode( $signature );

		$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
			'timeout' => 15,
			'body'    => array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt,
			),
		) );

		if ( is_wp_error( $response ) ) return $response;

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$err = isset( $body['error_description'] ) ? $body['error_description'] : wp_json_encode( $body );
			return new WP_Error( 'sheets_token_error', 'Sheets OAuth2 error: ' . $err );
		}

		$expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] - 60 : 3540;
		set_transient( 'engam_v2_sheets_token', $body['access_token'], $expires_in );
		return $body['access_token'];
	}

	/**
	 * Returns sheet metadata (title + list of tab names) for a given Sheet ID.
	 */
	public function get_sheet_tabs( $sheet_id ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', 'GAM credentials not configured.' );
		}
		$token = $this->get_sheets_token();
		if ( is_wp_error( $token ) ) return $token;

		$url      = 'https://sheets.googleapis.com/v4/spreadsheets/' . $sheet_id . '?fields=properties.title,sheets.properties';
		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
		) );

		if ( is_wp_error( $response ) ) return $response;

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code;
			return new WP_Error( 'sheets_api_error', $msg );
		}

		$tabs = array();
		if ( ! empty( $body['sheets'] ) ) {
			foreach ( $body['sheets'] as $s ) {
				$tabs[] = $s['properties']['title'];
			}
		}

		return array(
			'title' => isset( $body['properties']['title'] ) ? $body['properties']['title'] : '',
			'tabs'  => $tabs,
		);
	}

	/**
	 * Returns the first N rows of a sheet tab for column-mapping preview.
	 */
	public function get_sheet_preview( $sheet_id, $tab, $header_row = 1, $preview_rows = 5 ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', 'GAM credentials not configured.' );
		}
		$token = $this->get_sheets_token();
		if ( is_wp_error( $token ) ) return $token;

		$start    = $header_row;
		$end      = $header_row + $preview_rows;
		$range    = rawurlencode( $tab . '!' . $start . ':' . $end );
		$url      = 'https://sheets.googleapis.com/v4/spreadsheets/' . $sheet_id . '/values/' . $range;

		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
		) );

		if ( is_wp_error( $response ) ) return $response;

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'HTTP ' . $code;
			return new WP_Error( 'sheets_api_error', $msg );
		}

		return isset( $body['values'] ) ? $body['values'] : array();
	}

	/**
	 * Clears cached line items and token so next request fetches fresh data.
	 */
	public function clear_cache() {
		delete_transient( self::CACHE_KEY );
		delete_transient( self::TOKEN_CACHE );
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_engam_v2_sizes_%' OR option_name LIKE '_transient_timeout_engam_v2_sizes_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_engam_v2_wrap_cr_%' OR option_name LIKE '_transient_timeout_engam_v2_wrap_cr_%'" );
		delete_transient( 'engam_v2_sponsor_options' );
		delete_transient( 'engam_v2_sheets_token' );
	}

	/**
	 * Tests the connection and returns a status array.
	 */
	public function test_connection() {
		if ( ! $this->is_configured() ) {
			return array( 'success' => false, 'message' => 'Credentials not configured.' );
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return array( 'success' => false, 'message' => $token->get_error_message() );
		}

		$items = $this->fetch_line_items( $token );
		if ( is_wp_error( $items ) ) {
			return array( 'success' => false, 'message' => $items->get_error_message() );
		}

		return array(
			'success' => true,
			'message' => 'Connected! Found ' . count( $items ) . ' active line items.',
			'count'   => count( $items ),
		);
	}
}
