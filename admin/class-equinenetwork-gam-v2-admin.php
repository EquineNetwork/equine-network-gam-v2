<?php
class Equinenetwork_Gam_V2_Admin {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		add_action( 'wp_ajax_engam_v2_test_connection',  array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_engam_v2_refresh_cache',    array( $this, 'ajax_refresh_cache' ) );
		add_action( 'wp_ajax_engam_v2_save_credentials', array( $this, 'ajax_save_credentials' ) );
		add_action( 'wp_ajax_engam_v2_get_line_items',   array( $this, 'ajax_get_line_items' ) );
		add_action( 'wp_ajax_engam_v2_test_sheets',      array( $this, 'ajax_test_sheets' ) );
		add_action( 'wp_ajax_engam_v2_sheets_tabs',      array( $this, 'ajax_sheets_tabs' ) );
		add_action( 'wp_ajax_engam_v2_sheets_preview',   array( $this, 'ajax_sheets_preview' ) );
		add_action( 'wp_ajax_engam_v2_sheets_save',      array( $this, 'ajax_sheets_save' ) );
		add_action( 'wp_ajax_engam_v2_search_posts',      array( $this, 'ajax_search_posts' ) );
		add_action( 'wp_ajax_engam_v2_search_terms',      array( $this, 'ajax_search_terms' ) );
		add_action( 'wp_ajax_engam_v2_report_slot_mismatch', array( $this, 'ajax_report_slot_mismatch' ) );
		add_action( 'wp_ajax_engam_v2_dismiss_slot_warning', array( $this, 'ajax_dismiss_slot_warning' ) );
		add_action( 'admin_notices',                      array( $this, 'admin_notice_slot_mismatch' ) );
	}

	/**
	 * Enqueue scripts on the correct admin pages.
	 */
	public function enqueue_scripts( $hook ) {
		$settings_hooks  = array(
			'en-ads_page_engam-v2-settings',
		);
		$takeovers_hooks = array(
			'en-ads_page_engam-v2-takeovers',
		);
		$carousels_hooks = array(
			'en-ads_page_engam-v2-carousels',
		);
		$post_picker_hooks = array(
			'en-ads_page_engam-v2-takeovers',
			'en-ads_page_engam-v2-masthead',
			'en-ads_page_engam-v2-stackers',
		);

		// Settings page — enqueue credentials JS
		if ( in_array( $hook, $settings_hooks, true ) ) {
			wp_enqueue_script(
				$this->plugin_name,
				EQUINENETWORK_GAM_V2_URL . 'admin/js/equinenetwork-gam-v2-admin.js',
				array( 'jquery' ),
				$this->version,
				true
			);
			wp_localize_script( $this->plugin_name, 'engamV2', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'engam_v2_ajax' ),
			) );
		}

		// Takeovers page — enqueue WP media library + inline AJAX config
		if ( in_array( $hook, $takeovers_hooks, true ) ) {
			wp_enqueue_media();
			wp_enqueue_script(
				$this->plugin_name . '-takeovers',
				EQUINENETWORK_GAM_V2_URL . 'admin/js/equinenetwork-gam-v2-admin.js',
				array( 'jquery' ),
				$this->version,
				true
			);
			wp_localize_script( $this->plugin_name . '-takeovers', 'engamV2', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'engam_v2_ajax' ),
			) );
		}

		// Post/page picker — all pages that have page/post selector fields.
		if ( in_array( $hook, $post_picker_hooks, true ) ) {
			wp_enqueue_script(
				$this->plugin_name . '-post-picker',
				EQUINENETWORK_GAM_V2_URL . 'admin/js/engam-post-picker.js',
				array(),
				$this->version,
				true
			);
			// engamV2 may already be localized above; only add if not yet registered.
			if ( ! wp_script_is( $this->plugin_name . '-takeovers', 'enqueued' ) ) {
				wp_localize_script( $this->plugin_name . '-post-picker', 'engamV2', array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'engam_v2_ajax' ),
				) );
			}
		}

		// Carousels page — enqueue WP media library for slide images.
		if ( in_array( $hook, $carousels_hooks, true ) ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Register top-level menu + 4 submenus.
	 */
	public function add_menu() {
		// Top-level page (Dashboard)
		add_menu_page(
			'EquineNetwork GAM v2',
			'EN Ads',
			'edit_posts',
			'equinenetwork-gam-v2',
			array( $this, 'page_dashboard' ),
			'dashicons-megaphone',
			58
		);

		// Rename the auto-generated submenu entry that mirrors the top-level
		add_submenu_page(
			'equinenetwork-gam-v2',
			'EN Ads — Dashboard',
			'Dashboard',
			'edit_posts',
			'equinenetwork-gam-v2',
			array( $this, 'page_dashboard' )
		);

		// Leaderboards
		add_submenu_page(
			'equinenetwork-gam-v2',
			'EN Ads — Leaderboards',
			'Leaderboards',
			'edit_posts',
			'engam-v2-leaderboards',
			array( $this, 'page_leaderboards' )
		);

		// Takeovers
		add_submenu_page(
			'equinenetwork-gam-v2',
			'EN Ads — Takeovers',
			'Takeovers',
			'edit_posts',
			'engam-v2-takeovers',
			array( $this, 'page_takeovers' )
		);

		// Carousels
		add_submenu_page(
			'equinenetwork-gam-v2',
			'EN Ads — Carousels',
			'Carousels',
			'edit_posts',
			'engam-v2-carousels',
			array( $this, 'page_carousels' )
		);

		// Stackers
		add_submenu_page(
			'equinenetwork-gam-v2',
			'EN Ads — Stackers',
			'Stackers',
			'edit_posts',
			'engam-v2-stackers',
			array( $this, 'page_stackers' )
		);

		// Sponsor IDs
		add_submenu_page(
			'equinenetwork-gam-v2',
			"EN Ads — Sponsor ID's",
			"Sponsor ID's",
			'edit_posts',
			'engam-v2-campaigns',
			array( $this, 'page_campaigns' )
		);

		// Settings
		add_submenu_page(
			'equinenetwork-gam-v2',
			'EN Ads — Settings',
			'Settings',
			'manage_options',
			'engam-v2-settings',
			array( $this, 'page_settings' )
		);
	}

	// ---- Page callbacks ----

	public function page_dashboard() {
		include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-dashboard.php';
	}

	public function page_campaigns() {
		include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-campaigns.php';
	}

	public function page_takeovers() {
		include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-takeovers.php';
	}

	public function page_leaderboards() {
		include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-leaderboards.php';
	}

	public function page_carousels() {
		include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-carousels.php';
	}

	public function page_stackers() {
		include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-stackers.php';
	}

	public function page_settings() {
		include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-settings.php';
	}

	// ---- AJAX handlers ----

	public function ajax_test_connection() {
		check_ajax_referer( 'engam_v2_ajax', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( -1 );

		$api    = new Equinenetwork_Gam_V2_API();
		$result = $api->diagnose();
		wp_send_json( $result );
	}

	public function ajax_refresh_cache() {
		check_ajax_referer( 'engam_v2_ajax', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( -1 );

		$api = new Equinenetwork_Gam_V2_API();
		$api->clear_cache();
		$items = $api->get_line_items( true );

		if ( is_wp_error( $items ) ) {
			wp_send_json( array( 'success' => false, 'message' => $items->get_error_message() ) );
		}
		wp_send_json( array( 'success' => true, 'count' => count( $items ), 'message' => 'Cache refreshed. Found ' . count( $items ) . ' line items.' ) );
	}

	public function ajax_get_line_items() {
		check_ajax_referer( 'engam_v2_ajax', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( -1 );

		$api   = new Equinenetwork_Gam_V2_API();
		$items = $api->get_line_items();

		if ( is_wp_error( $items ) ) {
			wp_send_json( array( 'success' => false, 'message' => $items->get_error_message() ) );
		}
		wp_send_json( array( 'success' => true, 'items' => $items ) );
	}

	public function ajax_save_credentials() {
		check_ajax_referer( 'engam_v2_ajax', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

		$json = isset( $_POST['credentials'] ) ? stripslashes( $_POST['credentials'] ) : '';
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE || empty( $data['client_email'] ) || empty( $data['private_key'] ) ) {
			wp_send_json( array( 'success' => false, 'message' => 'Invalid JSON — make sure you pasted the full service account key file.' ) );
		}

		$safe = array(
			'type'         => 'service_account',
			'client_email' => sanitize_email( $data['client_email'] ),
			'private_key'  => $data['private_key'],
			'project_id'   => isset( $data['project_id'] ) ? sanitize_text_field( $data['project_id'] ) : '',
		);

		update_option( 'equinenetwork_gam_v2_credentials', wp_json_encode( $safe ) );

		delete_transient( 'engam_v2_access_token' );
		delete_transient( 'engam_v2_line_items' );

		wp_send_json( array( 'success' => true, 'message' => 'Credentials saved. Click "Test Connection" to verify.' ) );
	}

	public function ajax_test_sheets() {
		check_ajax_referer( 'engam_v2_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

		require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-api.php';
		$api     = new Equinenetwork_Gam_V2_API();
		$options = $api->get_sponsor_options( true );

		if ( empty( $options ) ) {
			wp_send_json_error( 'No active sponsors found. Check the CSV URL is correct and the sheet has been published.' );
		}

		wp_send_json_success( 'Connected! Found ' . count( $options ) . ' active sponsors. Cache refreshed.' );
	}

	public function ajax_sheets_tabs() {
		check_ajax_referer( 'engam_v2_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

		$sheet_id = sanitize_text_field( wp_unslash( $_POST['sheet_id'] ?? '' ) );
		if ( ! $sheet_id ) wp_send_json_error( 'No sheet ID provided.' );

		require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-api.php';
		$api    = new Equinenetwork_Gam_V2_API();
		$result = $api->get_sheet_tabs( $sheet_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		wp_send_json_success( $result );
	}

	public function ajax_sheets_preview() {
		check_ajax_referer( 'engam_v2_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

		$sheet_id   = sanitize_text_field( wp_unslash( $_POST['sheet_id'] ?? '' ) );
		$tab        = sanitize_text_field( wp_unslash( $_POST['tab']      ?? '' ) );
		$header_row = max( 1, intval( $_POST['header_row'] ?? 1 ) );

		if ( ! $sheet_id || ! $tab ) wp_send_json_error( 'Sheet ID and tab required.' );

		require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-api.php';
		$api    = new Equinenetwork_Gam_V2_API();
		$rows   = $api->get_sheet_preview( $sheet_id, $tab, $header_row );

		if ( is_wp_error( $rows ) ) {
			wp_send_json_error( $rows->get_error_message() );
		}
		wp_send_json_success( array( 'rows' => $rows ) );
	}

	public function ajax_sheets_save() {
		check_ajax_referer( 'engam_v2_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

		$sheet_id   = sanitize_text_field( wp_unslash( $_POST['sheet_id']    ?? '' ) );
		$tab        = sanitize_text_field( wp_unslash( $_POST['tab']         ?? '' ) );
		$col_name   = max( 0, intval( $_POST['col_name']   ?? 0 ) );
		$col_id     = max( 0, intval( $_POST['col_id']     ?? 2 ) );
		$col_status = intval( $_POST['col_status'] ?? -1 );
		$header_row = max( 1, intval( $_POST['header_row'] ?? 1 ) );

		if ( ! $sheet_id || ! $tab ) wp_send_json_error( 'Sheet ID and tab required.' );

		update_option( 'engam_v2_sheet_id',      $sheet_id );
		update_option( 'engam_v2_sheet_tab',     $tab );
		update_option( 'engam_v2_sheet_col_name',   $col_name );
		update_option( 'engam_v2_sheet_col_id',     $col_id );
		update_option( 'engam_v2_sheet_col_status', $col_status );
		update_option( 'engam_v2_sheet_header_row', $header_row );
		delete_transient( 'engam_v2_sponsor_options' );

		// Fetch fresh to confirm it works.
		require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-api.php';
		$api     = new Equinenetwork_Gam_V2_API();
		$options = $api->get_sponsor_options( true );

		wp_send_json_success( array(
			'count'   => count( $options ),
			'message' => 'Saved! Found ' . count( $options ) . ' active sponsors.',
		) );
	}

	/**
	 * AJAX: search posts and pages by title keyword.
	 * Returns [{id, title, type}] for the multi-select widget.
	 */
	public function ajax_search_posts() {
		check_ajax_referer( 'engam_v2_ajax', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized', 403 );

		$q     = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$types = isset( $_GET['types'] ) ? array_map( 'sanitize_key', (array) $_GET['types'] ) : array( 'post', 'page' );

		$results = array();

		// Numeric: fetch by ID directly.
		if ( is_numeric( trim( $q ) ) ) {
			$p = get_post( (int) $q );
			if ( $p && 'publish' === $p->post_status ) {
				$results[] = array( 'id' => $p->ID, 'title' => $p->post_title, 'type' => $p->post_type );
			}
		}

		$posts = get_posts( array(
			'post_type'      => $types,
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			's'              => $q,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'exclude'        => array_column( $results, 'id' ),
		) );

		foreach ( $posts as $p ) {
			$results[] = array( 'id' => $p->ID, 'title' => $p->post_title, 'type' => $p->post_type );
		}

		wp_send_json_success( array_slice( $results, 0, 20 ) );
	}

	/**
	 * AJAX: search categories by name. Returns slug as the value so it stays
	 * compatible with the comma-separated-slug targeting the wrap already uses.
	 */
	public function ajax_search_terms() {
		check_ajax_referer( 'engam_v2_ajax', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Unauthorized', 403 );

		$q        = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : 'category';

		$terms = get_terms( array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 20,
			'search'     => $q,
			'orderby'    => 'name',
			'order'      => 'ASC',
		) );

		$results = array();
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				// id = slug (used for matching); title shown to the user.
				$results[] = array( 'id' => $t->slug, 'title' => $t->name, 'type' => $taxonomy );
			}
		}

		wp_send_json_success( $results );
	}

	/**
	 * AJAX (nopriv allowed — fires from front-end JS for any visitor, but we
	 * only act on it when the stored configured line item is known).
	 * Stores a warning when GAM serves a different line item than configured.
	 */
	public function ajax_report_slot_mismatch() {
		check_ajax_referer( 'engam_v2_slot_check', 'nonce' );

		$configured_id = sanitize_text_field( wp_unslash( $_POST['configured_id'] ?? '' ) );
		$served_id     = sanitize_text_field( wp_unslash( $_POST['served_id']     ?? '' ) );
		$slot          = sanitize_text_field( wp_unslash( $_POST['slot']          ?? '' ) );
		$takeover_name = sanitize_text_field( wp_unslash( $_POST['takeover_name'] ?? '' ) );

		if ( ! $configured_id || ! $served_id || $configured_id === $served_id ) {
			wp_send_json_success();
		}

		$warnings = get_option( 'engam_v2_slot_warnings', array() );
		$key      = $configured_id . '_' . $served_id;

		$warnings[ $key ] = array(
			'takeover_name' => $takeover_name,
			'slot'          => $slot,
			'configured_id' => $configured_id,
			'served_id'     => $served_id,
			'time'          => current_time( 'mysql' ),
			'url'           => isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '',
		);

		update_option( 'engam_v2_slot_warnings', $warnings );
		wp_send_json_success();
	}

	/**
	 * AJAX: dismiss a stored slot mismatch warning.
	 */
	public function ajax_dismiss_slot_warning() {
		check_ajax_referer( 'engam_v2_ajax', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_die( -1 );

		$key      = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
		$warnings = get_option( 'engam_v2_slot_warnings', array() );

		if ( $key === 'all' ) {
			delete_option( 'engam_v2_slot_warnings' );
		} elseif ( isset( $warnings[ $key ] ) ) {
			unset( $warnings[ $key ] );
			update_option( 'engam_v2_slot_warnings', $warnings );
		}

		wp_send_json_success();
	}

	/**
	 * Show a persistent admin notice when GAM is serving unexpected line items
	 * to a wrap or masthead slot.
	 */
	public function admin_notice_slot_mismatch() {
		if ( ! current_user_can( 'edit_posts' ) ) return;

		$warnings = get_option( 'engam_v2_slot_warnings', array() );
		if ( empty( $warnings ) ) return;

		$nonce        = wp_create_nonce( 'engam_v2_ajax' );
		$manage_url   = admin_url( 'admin.php?page=engam-v2-takeovers' );
		$network_code = preg_replace( '/[^0-9]/', '', get_option( 'equinenetwork_gam_v2_id', '' ) );

		echo '<div class="notice notice-warning engam-slot-warning" style="border-left-color:#d0ff00;">';
		echo '<p><strong>⚠ EN Ads — GAM Slot Mismatch Detected</strong></p>';
		echo '<p>GAM is delivering a different line item than configured in one or more takeover slots. '
			. 'This usually means another active line item in GAM is competing and winning the auction — '
			. 'check GAM to pause or adjust priority for the unexpected line item.</p>';
		echo '<table style="border-collapse:collapse;width:100%;margin-top:8px;font-size:13px">';
		echo '<thead><tr style="background:#f9f9f9">'
			. '<th style="padding:6px 10px;text-align:left;border:1px solid #ddd">Takeover</th>'
			. '<th style="padding:6px 10px;text-align:left;border:1px solid #ddd">Slot</th>'
			. '<th style="padding:6px 10px;text-align:left;border:1px solid #ddd">Configured Line Item ID</th>'
			. '<th style="padding:6px 10px;text-align:left;border:1px solid #ddd">Served Line Item ID</th>'
			. '<th style="padding:6px 10px;text-align:left;border:1px solid #ddd">Detected On</th>'
			. '<th style="padding:6px 10px;text-align:left;border:1px solid #ddd"></th>'
			. '</tr></thead><tbody>';

		foreach ( $warnings as $key => $w ) {
			$page_link = ! empty( $w['url'] ) ? ' <a href="' . esc_url( $w['url'] ) . '" target="_blank" style="font-size:11px">(view page)</a>' : '';
			$gam_base  = $network_code ? 'https://admanager.google.com/' . $network_code . '#delivery/line_item/detail/line_item_id=' : '';
			$conf_link = $gam_base && ! empty( $w['configured_id'] )
				? '<a href="' . esc_url( $gam_base . $w['configured_id'] ) . '" target="_blank" style="color:#22c55e;font-weight:bold">' . esc_html( $w['configured_id'] ) . '</a>'
				: '<span style="color:#22c55e;font-weight:bold">' . esc_html( $w['configured_id'] ) . '</span>';
			$served_link = $gam_base && ! empty( $w['served_id'] )
				? '<a href="' . esc_url( $gam_base . $w['served_id'] ) . '" target="_blank" style="color:#ef4444;font-weight:bold">' . esc_html( $w['served_id'] ) . '</a>'
				: '<span style="color:#ef4444;font-weight:bold">' . esc_html( $w['served_id'] ) . '</span>';
			echo '<tr>'
				. '<td style="padding:6px 10px;border:1px solid #ddd">' . esc_html( $w['takeover_name'] ) . '</td>'
				. '<td style="padding:6px 10px;border:1px solid #ddd">' . esc_html( $w['slot'] ) . '</td>'
				. '<td style="padding:6px 10px;border:1px solid #ddd">' . $conf_link . '</td>'
				. '<td style="padding:6px 10px;border:1px solid #ddd">' . $served_link . '</td>'
				. '<td style="padding:6px 10px;border:1px solid #ddd">' . esc_html( $w['time'] ) . $page_link . '</td>'
				. '<td style="padding:6px 10px;border:1px solid #ddd">'
				. '<button type="button" class="button button-small engam-dismiss-warning" '
				. 'data-key="' . esc_attr( $key ) . '" data-nonce="' . esc_attr( $nonce ) . '">Dismiss</button>'
				. '</td>'
				. '</tr>';
		}

		echo '</tbody></table>';
		echo '<p style="margin-top:10px">';
		echo '<a href="' . esc_url( $manage_url ) . '" class="button button-primary">Manage Takeovers</a> ';
		echo '<button type="button" class="button engam-dismiss-warning" data-key="all" data-nonce="' . esc_attr( $nonce ) . '">Dismiss All</button>';
		echo '</p>';
		echo '</div>';

		// Inline JS for dismiss buttons — no extra file needed.
		echo '<script>
(function(){
    document.addEventListener("click", function(e) {
        var btn = e.target.closest(".engam-dismiss-warning");
        if (!btn) return;
        var key   = btn.dataset.key;
        var nonce = btn.dataset.nonce;
        var row   = btn.closest("tr");
        var notice = btn.closest(".engam-slot-warning");
        fetch(' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ', {
            method: "POST",
            headers: {"Content-Type":"application/x-www-form-urlencoded"},
            body: "action=engam_v2_dismiss_slot_warning&nonce=" + encodeURIComponent(nonce) + "&key=" + encodeURIComponent(key)
        }).then(function() {
            if (key === "all") { notice && notice.remove(); }
            else { row && row.remove(); }
        });
    });
})();
</script>';
	}
}
