<?php
if ( ! defined( 'WPINC' ) ) die;

/**
 * Handles authentication and data fetching from the Google Ad Manager API.
 * Uses JWT-based OAuth2 with a service account — no user login required.
 */
class Equinenetwork_Gam_V2_API {

	const CACHE_KEY          = 'engam_v2_line_items';
	const CACHE_DURATION     = 3600; // 1 hour
	const TOKEN_CACHE        = 'engam_v2_access_token';
	const GAM_REST_BASE      = 'https://admanager.googleapis.com/v1';
	const CACHE_SITE_UNITS   = 'engam_v2_site_unit_res'; // stores ad unit resource names
	const REPORT_NAME        = 'EN Plugin — Line Items by Ad Unit (auto)';
	const REPORT_RANGE       = 'LAST_90_DAYS'; // window used to detect line items running on the site
	const CACHE_AI_CATS      = 'engam_v2_ai_category_values'; // read-only ai_category targeting taxonomy from GAM
	const CACHE_GRAPH_TOKEN  = 'engam_v2_graph_token';       // Microsoft Graph OAuth2 token
	const CACHE_MS_SHEETS    = 'engam_v2_ms_worksheets';     // cached list of worksheet names

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
	 * Returns the read-only list of "ai_category" custom-targeting values defined in GAM — the
	 * taxonomy the stacker (and other) line items target against. GAM's v1 API does not expose a
	 * line item's own targeting, so this is the category universe, not a per-line-item list.
	 * Cached for 12 hours. Returns an array of category labels, or a WP_Error on failure.
	 */
	public function get_ai_category_values( $force_refresh = false ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', 'GAM API credentials not configured.' );
		}

		if ( ! $force_refresh ) {
			$cached = get_transient( self::CACHE_AI_CATS );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$network_code = preg_replace( '/[^0-9]/', '', $this->network_code );
		$net_base     = self::GAM_REST_BASE . '/networks/' . $network_code;

		// 1) Find the "ai_category" key by scanning all custom targeting keys (avoids filter-syntax pitfalls).
		$key_name   = '';
		$page_token = null;
		do {
			$url = $net_base . '/customTargetingKeys?pageSize=1000';
			if ( $page_token ) {
				$url .= '&pageToken=' . rawurlencode( $page_token );
			}
			$kbody = $this->gam_json_request( 'GET', $url, $token );
			if ( is_wp_error( $kbody ) ) {
				return $kbody;
			}
			if ( ! empty( $kbody['customTargetingKeys'] ) ) {
				foreach ( $kbody['customTargetingKeys'] as $k ) {
					if ( isset( $k['adTagName'], $k['name'] ) && strcasecmp( $k['adTagName'], 'ai_category' ) === 0 ) {
						$key_name = $k['name'];
						break 2;
					}
				}
			}
			$page_token = isset( $kbody['nextPageToken'] ) ? $kbody['nextPageToken'] : null;
		} while ( $page_token );

		if ( $key_name === '' ) {
			// No such key — cache an empty result briefly so we don't re-scan on every page load.
			set_transient( self::CACHE_AI_CATS, array(), HOUR_IN_SECONDS );
			return array();
		}

		// 2) List that key's values (nested collection, paginated).
		$values     = array();
		$page_token = null;
		do {
			$url = self::GAM_REST_BASE . '/' . $key_name . '/customTargetingValues?pageSize=1000';
			if ( $page_token ) {
				$url .= '&pageToken=' . rawurlencode( $page_token );
			}
			$vbody = $this->gam_json_request( 'GET', $url, $token );
			if ( is_wp_error( $vbody ) ) {
				return $vbody;
			}
			if ( ! empty( $vbody['customTargetingValues'] ) ) {
				foreach ( $vbody['customTargetingValues'] as $v ) {
					if ( isset( $v['displayName'] ) && $v['displayName'] !== '' ) {
						$values[] = $v['displayName'];
					} elseif ( isset( $v['adTagName'] ) && $v['adTagName'] !== '' ) {
						$values[] = $v['adTagName'];
					}
				}
			}
			$page_token = isset( $vbody['nextPageToken'] ) ? $vbody['nextPageToken'] : null;
		} while ( $page_token );

		$values = array_values( array_unique( $values ) );
		natcasesort( $values );
		$values = array_values( $values );

		set_transient( self::CACHE_AI_CATS, $values, 12 * HOUR_IN_SECONDS );
		return $values;
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
	 * Resolves the site's root ad unit resource name (and one level of children) from the GAM network path.
	 * The network code "/22345131513/nationalteamroping" implies the root ad unit code is "nationalteamroping".
	 * Returns an array of full resource names, e.g. "networks/22345131513/adUnits/23297243907".
	 * Results are cached for 1 hour alongside line items.
	 */
	private function get_site_ad_unit_resources( $token ) {
		$cached = get_transient( self::CACHE_SITE_UNITS );
		if ( is_array( $cached ) ) return $cached;

		// Extract site code — last non-empty segment of the network path.
		$segments  = array_values( array_filter( explode( '/', $this->network_code ) ) );
		$site_code = end( $segments );
		// Need at least two segments: numeric publisher ID + site code.
		if ( ! $site_code || count( $segments ) < 2 ) return array();

		$network_code = preg_replace( '/[^0-9]/', '', $this->network_code );
		$all_units    = array();
		$page_token   = null;

		do {
			$params = array( 'pageSize' => 500 );
			if ( $page_token ) $params['pageToken'] = $page_token;
			$resp = wp_remote_get(
				add_query_arg( $params, self::GAM_REST_BASE . '/networks/' . $network_code . '/adUnits' ),
				array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) )
			);
			if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) !== 200 ) break;
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );
			if ( ! empty( $body['adUnits'] ) ) $all_units = array_merge( $all_units, $body['adUnits'] );
			$page_token = $body['nextPageToken'] ?? null;
		} while ( $page_token );

		// Find the root ad unit by code, then collect its direct children (one level deep).
		$root_resource = null;
		$resources     = array();
		foreach ( $all_units as $au ) {
			if ( isset( $au['adUnitCode'], $au['name'] ) && strcasecmp( $au['adUnitCode'], $site_code ) === 0 ) {
				$root_resource = $au['name'];
				$resources[]   = $au['name'];
				break;
			}
		}

		if ( $root_resource ) {
			foreach ( $all_units as $au ) {
				if ( isset( $au['name'] ) && ( $au['parentAdUnit'] ?? '' ) === $root_resource ) {
					$resources[] = $au['name'];
				}
			}
		}

		set_transient( self::CACHE_SITE_UNITS, $resources, self::CACHE_DURATION );
		return $resources;
	}

	/**
	 * Fetches the line items for THIS site and caches them.
	 *
	 * GAM does not allow filtering the line items list by ad unit, and the list response omits the
	 * targeting object — so the only way to get a per-site list is to run a GAM report scoped to the
	 * site's ad unit(s). If the report path fails for any reason, we fall back to the full unfiltered
	 * list so the dashboard stays populated rather than erroring or showing zero.
	 */
	private function fetch_line_items( $token ) {
		$report_items = $this->run_ad_unit_report( $token );
		if ( ! is_wp_error( $report_items ) ) {
			usort( $report_items, function( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );
			set_transient( self::CACHE_KEY, $report_items, self::CACHE_DURATION );
			return $report_items;
		}

		// Fallback: full unfiltered list.
		$network_code = preg_replace( '/[^0-9]/', '', $this->network_code );
		$base_url     = self::GAM_REST_BASE . '/networks/' . $network_code . '/lineItems';
		$raw          = $this->list_line_items_raw( $token, $base_url, '' );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$items = $this->map_raw_line_items( $raw );
		usort( $items, function( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );

		if ( ! empty( $items ) ) {
			set_transient( self::CACHE_KEY, $items, self::CACHE_DURATION );
		}

		return $items;
	}

	/**
	 * Maps raw GAM lineItems.list objects into the shape the rest of the plugin expects.
	 */
	private function map_raw_line_items( $raw ) {
		$filter_keyword = strtolower( get_option( 'equinenetwork_gam_v2_filter', '' ) );
		$items          = array();
		foreach ( $raw as $li ) {
			$name  = isset( $li['name'] ) ? $li['name'] : '';
			$id    = isset( $li['externalId'] ) && $li['externalId'] !== '' ? $li['externalId'] : basename( $name );
			$label = isset( $li['displayName'] ) && $li['displayName'] !== '' ? $li['displayName'] : basename( $name );

			if ( $filter_keyword && stripos( $label, $filter_keyword ) === false && stripos( $id, $filter_keyword ) === false ) {
				continue;
			}

			$items[] = array(
				'id'            => $id,
				'gam_id'        => $name !== '' ? basename( $name ) : '',
				'name'          => $label,
				'status'        => isset( $li['entityStatus'] ) ? $li['entityStatus'] : ( isset( $li['deliveryStatus'] ) ? $li['deliveryStatus'] : ( isset( $li['status'] ) ? $li['status'] : '' ) ),
				'resource_name' => $name,
				'start_time'    => isset( $li['startTime'] ) ? $li['startTime'] : '',
				'end_time'      => isset( $li['endTime'] )   ? $li['endTime']   : '',
			);
		}
		return $items;
	}

	/**
	 * Runs a GAM report scoped to the site's ad unit(s) and returns the line items that delivered
	 * to that inventory — the per-site list. This is the same data behind the GAM UI's "Line items
	 * against this inventory" tab. Creates a temporary report, runs it (async), reads the rows, and
	 * deletes the report afterward. Rows are filtered by computed status to keep only currently-live
	 * line items (delivering / ready / paused) and drop completed, archived, inactive, etc.
	 *
	 * @param string     $token OAuth2 access token.
	 * @param array|null $log   Optional — receives human-readable diagnostic lines (used by diagnose()).
	 * @return array|WP_Error   Mapped line item rows, or WP_Error on any failure.
	 */
	private function run_ad_unit_report( $token, &$log = null ) {
		$network_code = preg_replace( '/[^0-9]/', '', $this->network_code );
		$net_base     = self::GAM_REST_BASE . '/networks/' . $network_code;

		// Resolve numeric ad unit IDs (root + direct children) from the network path.
		$resources   = $this->get_site_ad_unit_resources( $token );
		$ad_unit_ids = array();
		foreach ( $resources as $r ) {
			$ad_unit_ids[] = (string) basename( $r );
		}
		if ( $log !== null ) {
			$log[] = 'Ad unit IDs for report filter: ' . ( $ad_unit_ids ? implode( ', ', $ad_unit_ids ) : '(none)' );
		}
		if ( empty( $ad_unit_ids ) ) {
			return new WP_Error( 'no_ad_units', 'Could not resolve the site ad unit for the report filter.' );
		}

		// 1) Create a report scoped to those ad units.
		$report_body = array(
			'displayName'      => self::REPORT_NAME,
			'reportDefinition' => array(
				'reportType' => 'HISTORICAL',
				'dateRange'  => array( 'relative' => self::REPORT_RANGE ),
				'dimensions' => array( 'LINE_ITEM_ID', 'LINE_ITEM_NAME', 'LINE_ITEM_COMPUTED_STATUS_NAME' ),
				'metrics'    => array( 'AD_SERVER_IMPRESSIONS' ),
				'filters'    => array(
					array(
						'fieldFilter' => array(
							'field'     => array( 'dimension' => 'AD_UNIT_ID' ),
							'operation' => 'IN',
							'values'    => array_map( function( $id ) { return array( 'intValue' => $id ); }, $ad_unit_ids ),
						),
					),
				),
			),
		);

		$created = $this->gam_json_request( 'POST', $net_base . '/reports', $token, $report_body );
		if ( is_wp_error( $created ) ) {
			if ( $log !== null ) $log[] = 'Create report → ' . $created->get_error_message();
			return $created;
		}
		$report_name = isset( $created['name'] ) ? $created['name'] : '';
		if ( $log !== null ) $log[] = 'Created report: ' . $report_name;
		if ( $report_name === '' ) {
			return new WP_Error( 'report_no_name', 'Report created but the API returned no resource name.' );
		}

		// 2) Run the report (async long-running operation).
		$op = $this->gam_json_request( 'POST', self::GAM_REST_BASE . '/' . $report_name . ':run', $token, new stdClass() );
		if ( is_wp_error( $op ) ) {
			if ( $log !== null ) $log[] = 'Run report → ' . $op->get_error_message();
			$this->delete_report( $token, $report_name );
			return $op;
		}
		$op_name = isset( $op['name'] ) ? $op['name'] : '';
		if ( $log !== null ) $log[] = 'Run operation: ' . ( $op_name ? $op_name : '(returned inline)' );

		// 3) Poll the operation until done (bounded so we stay within PHP execution limits).
		$result_name = '';
		if ( ! empty( $op['done'] ) && isset( $op['response']['reportResult'] ) ) {
			$result_name = $op['response']['reportResult'];
		} elseif ( $op_name ) {
			$deadline = time() + 22;
			$delay    = 2;
			while ( time() < $deadline ) {
				sleep( $delay );
				$pop = $this->gam_json_request( 'GET', self::GAM_REST_BASE . '/' . $op_name, $token );
				if ( is_wp_error( $pop ) ) {
					if ( $log !== null ) $log[] = 'Poll → ' . $pop->get_error_message();
					$this->delete_report( $token, $report_name );
					return $pop;
				}
				if ( ! empty( $pop['done'] ) ) {
					if ( isset( $pop['error'] ) ) {
						$emsg = isset( $pop['error']['message'] ) ? $pop['error']['message'] : 'report run failed';
						if ( $log !== null ) $log[] = 'Operation finished with error: ' . $emsg;
						$this->delete_report( $token, $report_name );
						return new WP_Error( 'report_failed', 'Report run failed: ' . $emsg );
					}
					$result_name = isset( $pop['response']['reportResult'] ) ? $pop['response']['reportResult'] : '';
					break;
				}
				$delay = min( $delay + 1, 5 );
			}
		}
		if ( $result_name === '' ) {
			if ( $log !== null ) $log[] = 'No report result name (operation did not finish in time).';
			$this->delete_report( $token, $report_name );
			return new WP_Error( 'report_timeout', 'Report did not finish in the allotted time.' );
		}
		if ( $log !== null ) $log[] = 'Report result: ' . $result_name;

		// 4) Fetch the result rows (paginated).
		$items        = array();
		$total_rows   = 0;
		$status_tally = array();
		$page_token   = null;
		do {
			$url = self::GAM_REST_BASE . '/' . $result_name . ':fetchRows?pageSize=1000';
			if ( $page_token ) {
				$url .= '&pageToken=' . rawurlencode( $page_token );
			}
			$fbody = $this->gam_json_request( 'GET', $url, $token );
			if ( is_wp_error( $fbody ) ) {
				if ( $log !== null ) $log[] = 'Fetch rows → ' . $fbody->get_error_message();
				$this->delete_report( $token, $report_name );
				return $fbody;
			}
			if ( ! empty( $fbody['rows'] ) ) {
				foreach ( $fbody['rows'] as $row ) {
					$dv      = isset( $row['dimensionValues'] ) ? $row['dimensionValues'] : array();
					$li_id   = isset( $dv[0] ) ? $this->report_value_string( $dv[0] ) : '';
					$li_name = isset( $dv[1] ) ? $this->report_value_string( $dv[1] ) : '';
					$status  = isset( $dv[2] ) ? $this->report_value_string( $dv[2] ) : '';
					if ( $li_id === '' && $li_name === '' ) {
						continue;
					}
					$total_rows++;
					$tally_key                  = $status !== '' ? $status : '(none)';
					$status_tally[ $tally_key ] = ( isset( $status_tally[ $tally_key ] ) ? $status_tally[ $tally_key ] : 0 ) + 1;

					// Keep only line items that are currently live: delivering, ready, or paused.
					// Drops completed, archived, inactive, pending approval, draft, canceled, disapproved.
					if ( ! $this->is_active_status( $status ) ) {
						continue;
					}
					$items[] = array(
						'id'            => $li_id,
						'gam_id'        => $li_id,
						'name'          => $li_name !== '' ? $li_name : $li_id,
						'status'        => $status,
						'resource_name' => $li_id !== '' ? ( 'networks/' . $network_code . '/lineItems/' . $li_id ) : '',
						'start_time'    => '',
						'end_time'      => '',
					);
				}
			}
			$page_token = isset( $fbody['nextPageToken'] ) ? $fbody['nextPageToken'] : null;
		} while ( $page_token );

		if ( $log !== null ) {
			$log[] = 'Line items on this ad unit (last 90 days): ' . $total_rows . ' total.';
			if ( $status_tally ) {
				$parts = array();
				foreach ( $status_tally as $st => $n ) {
					$parts[] = $st . ' × ' . $n;
				}
				$log[] = '   Status breakdown: ' . implode( ', ', $parts );
			}
			$log[] = 'Kept after status filter (delivering / ready / paused): ' . count( $items );
		}

		// 5) Best-effort cleanup so we don't clutter the GAM Reports UI.
		$this->delete_report( $token, $report_name );

		return $items;
	}

	/**
	 * Extracts a scalar string out of a report ReportValue object.
	 */
	private function report_value_string( $val ) {
		if ( ! is_array( $val ) ) {
			return (string) $val;
		}
		if ( isset( $val['stringValue'] ) ) return (string) $val['stringValue'];
		if ( isset( $val['intValue'] ) )    return (string) $val['intValue'];
		if ( isset( $val['doubleValue'] ) ) return (string) $val['doubleValue'];
		return '';
	}

	/**
	 * Decides whether a line item's computed status counts as "live" for this site's dashboard.
	 * Includes delivering, delivery-extended, ready, and paused (incl. paused/inventory-released).
	 * Excludes completed, archived, inactive, pending approval, draft, canceled, disapproved.
	 * Matches on the status name case-insensitively so it survives enum spelling / locale variations.
	 * An empty status is kept rather than hidden, so a missing field never produces a false zero.
	 */
	private function is_active_status( $status ) {
		$s = strtolower( trim( (string) $status ) );
		if ( $s === '' ) {
			return true;
		}
		foreach ( array( 'deliver', 'ready', 'paus' ) as $needle ) {
			if ( strpos( $s, $needle ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Best-effort deletion of a temporary report.
	 */
	private function delete_report( $token, $report_name ) {
		if ( ! $report_name ) {
			return;
		}
		wp_remote_request( self::GAM_REST_BASE . '/' . $report_name, array(
			'method'  => 'DELETE',
			'timeout' => 15,
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
		) );
	}

	/**
	 * Performs a JSON GAM API request and returns the decoded body, or a WP_Error carrying the
	 * exact HTTP status and error message from GAM.
	 *
	 * @param string $method GET or POST.
	 * @param string $url    Full request URL.
	 * @param string $token  OAuth2 access token.
	 * @param mixed  $body   Optional body to JSON-encode for POST.
	 */
	private function gam_json_request( $method, $url, $token, $body = null ) {
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
		);
		if ( 'POST' === $method ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( null === $body ? new stdClass() : $body );
		}

		$resp = wp_remote_request( $url, $args );
		if ( is_wp_error( $resp ) ) {
			return new WP_Error( 'transport', 'transport error: ' . $resp->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $resp );
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : substr( wp_remote_retrieve_body( $resp ), 0, 400 );
			return new WP_Error( 'http_' . $code, 'HTTP ' . $code . ': ' . $msg );
		}

		return is_array( $body ) ? $body : array();
	}

	/**
	 * Low-level paginating fetch of raw line item objects from the GAM list endpoint.
	 * Returns an array of raw line item arrays, or a WP_Error on transport / HTTP failure.
	 *
	 * @param string $token    OAuth2 access token.
	 * @param string $base_url Fully-qualified lineItems list URL.
	 * @param string $filter   Optional AIP-160 filter expression (empty = no filter).
	 */
	private function list_line_items_raw( $token, $base_url, $filter = '' ) {
		$raw        = array();
		$page_token = null;

		do {
			// Build the query string with RFC-3986 encoding (spaces -> %20, quotes -> %22) so the
			// filter expression survives transport intact. add_query_arg() encodes spaces as "+",
			// which the filter parser does not accept.
			$query = array( 'pageSize=1000' );
			if ( $filter !== '' ) {
				$query[] = 'filter=' . rawurlencode( $filter );
			}
			if ( $page_token ) {
				$query[] = 'pageToken=' . rawurlencode( $page_token );
			}
			$url = $base_url . '?' . implode( '&', $query );

			$response = wp_remote_get( $url, array(
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

			if ( ! empty( $body['lineItems'] ) ) {
				$raw = array_merge( $raw, $body['lineItems'] );
			}

			$page_token = isset( $body['nextPageToken'] ) ? $body['nextPageToken'] : null;
		} while ( $page_token );

		return $raw;
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
							// v1 REST uses the resource name "adUnit" (…/adUnits/ID); legacy uses numeric "adUnitId".
							$tid = '';
							if ( isset( $t['adUnit'] ) && $t['adUnit'] !== '' ) {
								$tid = (string) basename( $t['adUnit'] );
							} elseif ( isset( $t['adUnitId'] ) ) {
								$tid = (string) $t['adUnitId'];
							}
							if ( $tid !== '' && $tid === (string) $ad_unit_id ) {
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
		// Microsoft Graph (OneDrive/SharePoint) takes priority when configured.
		if ( $this->is_ms_configured() ) {
			return $this->get_ms_sponsor_options( $force_refresh );
		}

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

	// ── Microsoft Graph / OneDrive ────────────────────────────────────────────

	public function is_ms_configured() {
		return get_option( 'engam_v2_ms_tenant_id', '' )
			&& get_option( 'engam_v2_ms_client_id', '' )
			&& get_option( 'engam_v2_ms_client_secret', '' )
			&& get_option( 'engam_v2_ms_file_url', '' );
	}

	/**
	 * Gets an OAuth2 token via Microsoft client-credentials flow.
	 */
	public function get_graph_token() {
		$cached = get_transient( self::CACHE_GRAPH_TOKEN );
		if ( $cached ) return $cached;

		$tenant = get_option( 'engam_v2_ms_tenant_id', '' );
		$client = get_option( 'engam_v2_ms_client_id', '' );
		$secret = get_option( 'engam_v2_ms_client_secret', '' );

		if ( ! $tenant || ! $client || ! $secret ) {
			return new WP_Error( 'ms_not_configured', 'Microsoft credentials not configured.' );
		}

		$response = wp_remote_post(
			'https://login.microsoftonline.com/' . rawurlencode( $tenant ) . '/oauth2/v2.0/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type'    => 'client_credentials',
					'client_id'     => $client,
					'client_secret' => $secret,
					'scope'         => 'https://graph.microsoft.com/.default',
				),
			)
		);

		if ( is_wp_error( $response ) ) return $response;

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$err = $body['error_description'] ?? ( $body['error'] ?? wp_json_encode( $body ) );
			return new WP_Error( 'graph_token_error', 'Microsoft OAuth2 error: ' . $err );
		}

		$ttl = isset( $body['expires_in'] ) ? (int) $body['expires_in'] - 60 : 3540;
		set_transient( self::CACHE_GRAPH_TOKEN, $body['access_token'], $ttl );
		return $body['access_token'];
	}

	/**
	 * Converts a SharePoint share URL into the Graph API driveItem base URL,
	 * resolving drive+item IDs for maximum compatibility.
	 */
	private function graph_file_base( $file_url, $token ) {
		$share_id = 'u!' . rtrim( strtr( base64_encode( $file_url ), '+/', '-_' ), '=' );
		$share_base = 'https://graph.microsoft.com/v1.0/shares/' . rawurlencode( $share_id ) . '/driveItem';

		$response = wp_remote_get( $share_base . '?$select=id,parentReference', array(
			'timeout' => 15,
			'headers' => array( 'Authorization' => 'Bearer ' . $token ),
		) );

		if ( is_wp_error( $response ) ) return $response;

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$msg = $body['error']['message'] ?? ( 'HTTP ' . $code );
			return new WP_Error( 'graph_resolve_error', $msg );
		}

		$item_id  = $body['id']                           ?? '';
		$drive_id = $body['parentReference']['driveId']   ?? '';

		if ( $drive_id && $item_id ) {
			return "https://graph.microsoft.com/v1.0/drives/{$drive_id}/items/{$item_id}";
		}

		return $share_base;
	}

	/**
	 * Returns the worksheet names for the configured OneDrive/SharePoint file.
	 */
	public function get_ms_worksheet_names( $force_refresh = false ) {
		if ( ! $this->is_ms_configured() ) {
			return new WP_Error( 'ms_not_configured', 'Microsoft credentials not configured.' );
		}

		if ( ! $force_refresh ) {
			$cached = get_transient( self::CACHE_MS_SHEETS );
			if ( $cached !== false ) return $cached;
		}

		$token = $this->get_graph_token();
		if ( is_wp_error( $token ) ) return $token;

		$base = $this->graph_file_base( get_option( 'engam_v2_ms_file_url', '' ), $token );
		if ( is_wp_error( $base ) ) return $base;

		$response = wp_remote_get( $base . '/workbook/worksheets?$select=name,position', array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
		) );

		if ( is_wp_error( $response ) ) return $response;

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$msg = $body['error']['message'] ?? ( 'HTTP ' . $code );
			return new WP_Error( 'graph_worksheets_error', $msg );
		}

		$names = array();
		if ( ! empty( $body['value'] ) ) {
			usort( $body['value'], function ( $a, $b ) { return (int) $a['position'] - (int) $b['position']; } );
			foreach ( $body['value'] as $ws ) {
				if ( isset( $ws['name'] ) ) $names[] = $ws['name'];
			}
		}

		set_transient( self::CACHE_MS_SHEETS, $names, 12 * HOUR_IN_SECONDS );
		return $names;
	}

	/**
	 * Reads active sponsors from the configured OneDrive/SharePoint Excel file.
	 * Auto-detects the header row by looking for a row containing "Advertiser".
	 */
	public function get_ms_sponsor_options( $force_refresh = false ) {
		$cache_key = 'engam_v2_sponsor_options';
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) return $cached;
		}

		$token = $this->get_graph_token();
		if ( is_wp_error( $token ) ) return array();

		$sheet = get_option( 'engam_v2_ms_sheet_name', 'HR' );
		$base  = $this->graph_file_base( get_option( 'engam_v2_ms_file_url', '' ), $token );
		if ( is_wp_error( $base ) ) return array();

		$response = wp_remote_get(
			$base . '/workbook/worksheets/' . rawurlencode( $sheet ) . '/usedRange(valuesOnly=true)',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) return array();

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $body['values'] ) ) return array();

		$all_rows = $body['values'];

		// Find the first row that looks like a header (contains "Advertiser" or "Sponsorship ID").
		$header_idx = null;
		foreach ( $all_rows as $i => $row ) {
			foreach ( $row as $cell ) {
				$lc = strtolower( trim( (string) $cell ) );
				if ( strpos( $lc, 'advertiser' ) !== false
					|| ( strpos( $lc, 'sponsor' ) !== false && strpos( $lc, 'id' ) !== false ) ) {
					$header_idx = $i;
					break 2;
				}
			}
		}
		if ( $header_idx === null ) return array();

		$headers   = $all_rows[ $header_idx ];
		$data_rows = array_slice( $all_rows, $header_idx + 1 );

		$col_name = $col_id = $col_status = null;
		foreach ( $headers as $i => $h ) {
			$h = strtolower( trim( (string) $h ) );
			if ( $col_name   === null && ( strpos( $h, 'advertiser' ) !== false || strpos( $h, 'name' ) !== false ) ) $col_name   = $i;
			if ( $col_id     === null && strpos( $h, 'sponsor' ) !== false && strpos( $h, 'id' ) !== false )          $col_id     = $i;
			if ( $col_status === null && strpos( $h, 'status' ) !== false )                                            $col_status = $i;
		}
		$col_name   = $col_name   ?? 0;
		$col_id     = $col_id     ?? 2;
		$col_status = $col_status ?? 3;

		$options = array();
		foreach ( $data_rows as $row ) {
			if ( empty( $row ) ) continue;
			$sponsor_id = trim( (string) ( $row[ $col_id ] ?? '' ) );
			if ( $sponsor_id === '' ) continue;
			$status = strtolower( trim( (string) ( $row[ $col_status ] ?? '' ) ) );
			if ( $status !== 'active' ) continue;
			$advertiser = trim( (string) ( $row[ $col_name ] ?? '' ) );
			$options[]  = array(
				'id'   => $sponsor_id,
				'name' => $advertiser !== '' ? $advertiser : $sponsor_id,
			);
		}

		usort( $options, function ( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );

		set_transient( $cache_key, $options, self::CACHE_DURATION );
		return $options;
	}

	/**
	 * Clears cached line items and token so next request fetches fresh data.
	 */
	public function clear_cache() {
		delete_transient( self::CACHE_KEY );
		delete_transient( self::TOKEN_CACHE );
		delete_transient( self::CACHE_SITE_UNITS );
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_engam_v2_sizes_%' OR option_name LIKE '_transient_timeout_engam_v2_sizes_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_engam_v2_wrap_cr_%' OR option_name LIKE '_transient_timeout_engam_v2_wrap_cr_%'" );
		delete_transient( 'engam_v2_sponsor_options' );
		delete_transient( 'engam_v2_sheets_token' );
		delete_transient( self::CACHE_GRAPH_TOKEN );
		delete_transient( self::CACHE_MS_SHEETS );
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

	/**
	 * Diagnostic probe for the per-site report flow. Runs the full ad-unit report and reports each
	 * step (ad unit resolution, create, run, poll, fetch) with the exact GAM response, so any failure
	 * is visible. Surfaced via the "Test Connection" button.
	 */
	public function diagnose() {
		if ( ! $this->is_configured() ) {
			return array( 'success' => false, 'message' => 'Credentials not configured.' );
		}

		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return array( 'success' => false, 'message' => 'OAuth token error: ' . $token->get_error_message() );
		}

		// Force a fresh ad unit lookup so the diagnostic reflects current state.
		delete_transient( self::CACHE_SITE_UNITS );

		$log     = array();
		$log[]   = 'Network path: ' . $this->network_code;
		$log[]   = 'Date window: ' . self::REPORT_RANGE;
		$log[]   = '';
		$start   = microtime( true );
		$items   = $this->run_ad_unit_report( $token, $log );
		$elapsed = round( microtime( true ) - $start, 1 );

		$log[] = '';
		if ( is_wp_error( $items ) ) {
			$log[] = 'RESULT: report path failed — ' . $items->get_error_message();
			$log[] = '(The dashboard falls back to the full network list when this happens.)';
			return array( 'success' => false, 'message' => implode( "\n", $log ) );
		}

		$log[] = 'RESULT: ' . count( $items ) . ' active line items for this site (in ' . $elapsed . 's).';
		foreach ( array_slice( $items, 0, 20 ) as $it ) {
			$status = $it['status'] !== '' ? ' [' . $it['status'] . ']' : '';
			$log[] = '   • ' . $it['name'] . ' (' . $it['gam_id'] . ')' . $status;
		}
		return array( 'success' => true, 'message' => implode( "\n", $log ) );
	}
}
