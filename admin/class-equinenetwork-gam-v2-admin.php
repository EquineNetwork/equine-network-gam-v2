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
		$result = $api->test_connection();
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
}
