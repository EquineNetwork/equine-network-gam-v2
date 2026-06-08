<?php
/**
 * EquineNetwork GAM v2 — uninstall cleanup.
 *
 * Runs automatically when the plugin is deleted from the WordPress admin.
 * Removes ALL plugin data: options, transients, and post meta it created.
 * (Elementor's own meta keys are intentionally left untouched.)
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Delete everything this plugin stores for a single site.
 */
function engam_v2_uninstall_cleanup() {
    global $wpdb;

    // ── Options ───────────────────────────────────────────────
    $options = array(
        // Core / API
        'equinenetwork_gam_v2_id',
        'equinenetwork_gam_v2_credentials',
        'equinenetwork_gam_v2_campaigns',
        'equinenetwork_gam_v2_filter',
        'equinenetwork_gam_v2_site_au_id', // legacy
        // Feature data
        'engam_v2_takeovers',
        'engam_v2_mastheads',
        'engam_v2_carousels',
        'engam_v2_leaderboards_list',
        'engam_v2_stacker_settings',
        'engam_v2_stackers_list',          // legacy
        'engam_v2_stacker_name_filter',    // legacy
        // Sponsor spreadsheet — Google Sheets CSV (legacy)
        'engam_v2_sheet_csv_url',
        'engam_v2_sheet_id',
        'engam_v2_sheet_tab',
        'engam_v2_sheet_header_row',
        'engam_v2_sheet_col_id',
        'engam_v2_sheet_col_name',
        'engam_v2_sheet_col_status',
        // Sponsor spreadsheet — Microsoft Graph / OneDrive
        'engam_v2_ms_tenant_id',
        'engam_v2_ms_client_id',
        'engam_v2_ms_client_secret',
        'engam_v2_ms_file_url',
        'engam_v2_ms_sheet_name',
        'engam_v2_li_manual',              // line items wired up by direct GAM-ID lookup
    );
    foreach ( $options as $opt ) {
        delete_option( $opt );
    }

    // ── Named transients ──────────────────────────────────────
    $transients = array(
        'engam_v2_line_items',
        'engam_v2_access_token',
        'engam_v2_adslot_counts',
        'engam_v2_sheets_token',
        'engam_v2_graph_token',
        'engam_v2_ms_worksheets',
        'engam_v2_sponsor_options',
        'engam_v2_slot_warnings',
        'engam_v2_debug_targeting', // legacy
    );
    foreach ( $transients as $t ) {
        delete_transient( $t );
    }

    // ── Wildcard transients (per-slot size + wrap creative caches) ──
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
          WHERE option_name LIKE '\_transient\_engam_v2\_sizes\_%'
             OR option_name LIKE '\_transient\_timeout\_engam_v2\_sizes\_%'
             OR option_name LIKE '\_transient\_engam_v2\_wrap_cr\_%'
             OR option_name LIKE '\_transient\_timeout\_engam_v2\_wrap_cr\_%'"
    );

    // ── Post meta created by the plugin ───────────────────────
    $meta_keys = array(
        '_engam_v2_sponsor_id',
        '_engam_v2_ai_category',
        '_engam_v2_ai_sub_category',
    );
    foreach ( $meta_keys as $mk ) {
        delete_post_meta_by_key( $mk );
    }
}

// Only wipe data when the admin explicitly opted in via
// Settings → "Delete all data on uninstall". Otherwise leave everything intact.
if ( is_multisite() ) {
    $site_ids = get_sites( array( 'fields' => 'ids' ) );
    foreach ( $site_ids as $site_id ) {
        switch_to_blog( $site_id );
        if ( (int) get_option( 'engam_v2_delete_data_on_uninstall', 0 ) === 1 ) {
            engam_v2_uninstall_cleanup();
            delete_option( 'engam_v2_delete_data_on_uninstall' );
        }
        restore_current_blog();
    }
} elseif ( (int) get_option( 'engam_v2_delete_data_on_uninstall', 0 ) === 1 ) {
    engam_v2_uninstall_cleanup();
    delete_option( 'engam_v2_delete_data_on_uninstall' );
}
