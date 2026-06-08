<?php if ( ! defined( 'WPINC' ) ) die;

/**
 * One-time migration: copy legacy ACF sponsor IDs into the plugin's native
 * `_engam_v2_sponsor_id` meta so assignments survive deletion of the ACF
 * fields. Covers posts/pages (post meta) AND categories/tags/other taxonomies
 * (term meta) — both are read by the front-end. Reads meta directly (not
 * get_field) so it still works after the ACF field definitions are removed.
 * Never overwrites an existing plugin value, and is safe to run repeatedly.
 *
 * @param bool $write When false, performs a dry run (counts only, no changes).
 * @return array{candidates:int,migrated:int,skipped_existing:int,posts:int,terms:int,samples:array}
 */
if ( ! function_exists( 'engam_v2_migrate_acf_sponsors' ) ) {
    function engam_v2_migrate_acf_sponsors( $write = false ) {
        global $wpdb;
        if ( $write ) { @set_time_limit( 0 ); }  // large sites: avoid timing out mid-write

        $result = array(
            'candidates' => 0, 'migrated' => 0, 'skipped_existing' => 0,
            'posts' => 0, 'terms' => 0, 'samples' => array(),
        );

        // Group raw meta rows by object id, keeping both ACF keys so we can apply
        // the front-end priority: sponlineitemid wins over sponsorship_id.
        $group = function( $rows ) {
            $by = array();
            if ( is_array( $rows ) ) {
                foreach ( $rows as $r ) {
                    $by[ (int) $r->obj_id ][ $r->meta_key ] = $r->meta_value;
                }
            }
            return $by;
        };

        // --- Posts / pages ---
        // Join wp_posts to skip revisions, auto-drafts, and trash — ACF copies
        // field values onto revisions, which would otherwise inflate the count
        // massively and waste writes on non-served content.
        $post_cands = $group( $wpdb->get_results(
            "SELECT m.post_id AS obj_id, m.meta_key, m.meta_value
               FROM {$wpdb->postmeta} m
               INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
              WHERE m.meta_key IN ( 'sponlineitemid', 'sponsorship_id' ) AND m.meta_value <> ''
                AND p.post_type <> 'revision'
                AND p.post_status IN ( 'publish', 'private', 'draft', 'pending', 'future' )"
        ) );
        // Bulk lookup of posts that already have a plugin value, so we don't run a
        // per-row get_post_meta() across thousands of posts (which can time out).
        $post_has = array();
        if ( $post_cands ) {
            $ids = implode( ',', array_map( 'intval', array_keys( $post_cands ) ) );
            foreach ( (array) $wpdb->get_col(
                "SELECT post_id FROM {$wpdb->postmeta}
                  WHERE meta_key = '_engam_v2_sponsor_id' AND meta_value <> '' AND post_id IN ( {$ids} )"
            ) as $had ) {
                $post_has[ (int) $had ] = true;
            }
        }
        foreach ( $post_cands as $pid => $vals ) {
            $acf_value = sanitize_text_field( $vals['sponlineitemid'] ?? $vals['sponsorship_id'] ?? '' );
            if ( $acf_value === '' ) continue;
            $result['candidates']++;
            if ( isset( $post_has[ (int) $pid ] ) ) { $result['skipped_existing']++; continue; }

            if ( $write ) update_post_meta( $pid, '_engam_v2_sponsor_id', $acf_value );
            $result['migrated']++;
            $result['posts']++;

            if ( count( $result['samples'] ) < 25 ) {
                $p = get_post( $pid );
                $result['samples'][] = array(
                    'kind'  => 'Post',
                    'title' => $p ? $p->post_title : '(unknown #' . $pid . ')',
                    'value' => $acf_value,
                    'meta'  => $p ? $p->post_type : '',
                    'edit'  => get_edit_post_link( $pid ),
                );
            }
        }

        // --- Categories / tags / other taxonomies (no revisions to worry about) ---
        $term_cands = $group( $wpdb->get_results(
            "SELECT term_id AS obj_id, meta_key, meta_value
               FROM {$wpdb->termmeta}
              WHERE meta_key IN ( 'sponlineitemid', 'sponsorship_id' ) AND meta_value <> ''"
        ) );
        $term_has = array();
        if ( $term_cands ) {
            $ids = implode( ',', array_map( 'intval', array_keys( $term_cands ) ) );
            foreach ( (array) $wpdb->get_col(
                "SELECT term_id FROM {$wpdb->termmeta}
                  WHERE meta_key = '_engam_v2_sponsor_id' AND meta_value <> '' AND term_id IN ( {$ids} )"
            ) as $had ) {
                $term_has[ (int) $had ] = true;
            }
        }
        foreach ( $term_cands as $tid => $vals ) {
            $acf_value = sanitize_text_field( $vals['sponlineitemid'] ?? $vals['sponsorship_id'] ?? '' );
            if ( $acf_value === '' ) continue;
            $result['candidates']++;
            if ( isset( $term_has[ (int) $tid ] ) ) { $result['skipped_existing']++; continue; }

            if ( $write ) update_term_meta( $tid, '_engam_v2_sponsor_id', $acf_value );
            $result['migrated']++;
            $result['terms']++;

            if ( count( $result['samples'] ) < 25 ) {
                $t = get_term( $tid );
                $valid = ( $t && ! is_wp_error( $t ) );
                $tax_obj = $valid ? get_taxonomy( $t->taxonomy ) : null;
                $result['samples'][] = array(
                    'kind'  => 'Term',
                    'title' => $valid ? $t->name : '(unknown term #' . $tid . ')',
                    'value' => $acf_value,
                    'meta'  => $tax_obj ? $tax_obj->labels->singular_name : ( $valid ? $t->taxonomy : '' ),
                    'edit'  => $valid ? get_edit_term_link( $tid, $t->taxonomy ) : '',
                );
            }
        }

        return $result;
    }
}

$migration_result = null;
$migration_ran    = false;

$gam_id               = get_option( 'equinenetwork_gam_v2_id', '' );
$id_active            = ! empty( $gam_id );
$has_credentials      = ! empty( get_option( 'equinenetwork_gam_v2_credentials', '' ) );
$credentials_in_const = defined( 'ENGAM_GAM_CREDENTIALS_JSON' ) && ENGAM_GAM_CREDENTIALS_JSON;
$api                  = new Equinenetwork_Gam_V2_API();
$api_configured       = $api->is_configured();

$notice = '';
if ( isset( $_POST['engam_v2_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['engam_v2_settings_nonce'] ) ), 'engam_v2_settings_save' ) ) {
    $form = sanitize_text_field( wp_unslash( $_POST['engam_form'] ?? '' ) );
    if ( current_user_can( 'manage_options' ) && 'core' === $form ) {
        update_option( 'equinenetwork_gam_v2_id', sanitize_text_field( wp_unslash( $_POST['equinenetwork_gam_v2_id'] ?? '' ) ) );
        update_option( 'engam_v2_delete_data_on_uninstall', isset( $_POST['engam_v2_delete_data_on_uninstall'] ) ? 1 : 0 );
        delete_transient( 'engam_v2_line_items' );
        $gam_id    = get_option( 'equinenetwork_gam_v2_id', '' );
        $id_active = ! empty( $gam_id );
        $notice    = 'Settings saved.';
    }
    if ( current_user_can( 'manage_options' ) && 'sheets' === $form ) {
        update_option( 'engam_v2_sheet_csv_url', esc_url_raw( wp_unslash( $_POST['engam_v2_sheet_csv_url'] ?? '' ) ) );
        delete_transient( 'engam_v2_sponsor_options' );
        $notice = 'Sponsor Sheet saved.';
    }
    if ( current_user_can( 'manage_options' ) && 'ms_sponsor' === $form ) {
        // Only overwrite Azure fields when they are present in the POST (the UI may have removed them).
        if ( isset( $_POST['engam_v2_ms_tenant_id'] ) ) {
            update_option( 'engam_v2_ms_tenant_id', sanitize_text_field( wp_unslash( $_POST['engam_v2_ms_tenant_id'] ) ) );
        }
        if ( isset( $_POST['engam_v2_ms_client_id'] ) ) {
            update_option( 'engam_v2_ms_client_id', sanitize_text_field( wp_unslash( $_POST['engam_v2_ms_client_id'] ) ) );
        }
        update_option( 'engam_v2_ms_file_url',   esc_url_raw( wp_unslash( $_POST['engam_v2_ms_file_url']           ?? '' ) ) );
        update_option( 'engam_v2_ms_sheet_name', sanitize_text_field( wp_unslash( $_POST['engam_v2_ms_sheet_name'] ?? '' ) ) );
        $new_secret = sanitize_text_field( wp_unslash( $_POST['engam_v2_ms_client_secret'] ?? '' ) );
        if ( $new_secret !== '' ) {
            update_option( 'engam_v2_ms_client_secret', $new_secret );
        }
        delete_transient( 'engam_v2_sponsor_options' );
        delete_transient( 'engam_v2_graph_token' );
        delete_transient( 'engam_v2_ms_worksheets' );
        $notice = 'SharePoint connection saved.';
    }
    if ( current_user_can( 'manage_options' ) && 'acf_migrate' === $form ) {
        $migration_ran    = isset( $_POST['acf_migrate_confirm'] );
        $migration_result = engam_v2_migrate_acf_sponsors( $migration_ran );
        if ( $migration_ran ) {
            $notice = sprintf(
                '%d post(s) migrated from ACF. %d already had a plugin sponsor ID and were left unchanged.',
                (int) $migration_result['migrated'],
                (int) $migration_result['skipped_existing']
            );
        }
    }

}

$sheet_csv_url     = get_option( 'engam_v2_sheet_csv_url', '' );
$sheets_configured = ! empty( $sheet_csv_url );

$ms_tenant     = get_option( 'engam_v2_ms_tenant_id', '' );
$ms_client     = get_option( 'engam_v2_ms_client_id', '' );
$ms_secret_set = ! empty( get_option( 'engam_v2_ms_client_secret', '' ) );
$ms_file_url   = get_option( 'engam_v2_ms_file_url', '' );
$ms_sheet      = get_option( 'engam_v2_ms_sheet_name', '' );  // no site-specific default — the 'HR' placeholder hints the format
$ms_configured = $ms_tenant && $ms_client && $ms_secret_set && $ms_file_url;
$ms_link_only  = ! $ms_configured && $ms_file_url;     // share-link path (no Azure)
$ms_active     = $ms_configured || $ms_link_only;
// Default source: show MS if already configured, else CSV if CSV url exists, else MS (new setup).
$sponsor_source = $ms_active ? 'ms' : ( $sheets_configured ? 'csv' : 'ms' );

// Count posts AND terms still carrying legacy ACF sponsor IDs (for the migration card).
// Posts join wp_posts to skip revisions/auto-drafts/trash so the count matches
// what the migration actually touches.
global $wpdb;
$acf_candidate_count = (int) $wpdb->get_var(
    "SELECT COUNT( DISTINCT m.post_id ) FROM {$wpdb->postmeta} m
       INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
      WHERE m.meta_key IN ( 'sponlineitemid', 'sponsorship_id' ) AND m.meta_value <> ''
        AND p.post_type <> 'revision'
        AND p.post_status IN ( 'publish', 'private', 'draft', 'pending', 'future' )"
) + (int) $wpdb->get_var(
    "SELECT COUNT( DISTINCT term_id ) FROM {$wpdb->termmeta}
      WHERE meta_key IN ( 'sponlineitemid', 'sponsorship_id' ) AND meta_value <> ''"
);

include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-shared-styles.php';
?>
<div id="engam-v2-wrap">

<!-- MASTHEAD -->
<section class="eg-mast">
    <div class="eg-brand">
        <div class="eg-logo">EN</div>
        <div class="eg-brand-text">
            <small>Google Ad Manager &mdash; v2</small>
            <h1>Settings</h1>
        </div>
    </div>
    <div class="eg-mast-actions">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=equinenetwork-gam-v2' ) ); ?>" class="eg-btn ghost">Dashboard</a>
    </div>
</section>

<div class="eg-content">

<?php if ( $notice ) : ?>
<div class="eg-notice"><?php echo esc_html( $notice ); ?></div>
<?php endif; ?>

<div class="eg-grid" style="grid-template-columns:1fr 1fr">

    <!-- GAM SETTINGS -->
    <div class="eg-card" style="margin-top:18px">
        <div class="eg-head">
            <div>
                <h2>1. GAM Settings</h2>
                <p>Core configuration for this site.</p>
            </div>
            <span class="eg-tag"><?php echo $id_active ? 'Configured' : 'Setup'; ?></span>
        </div>
        <div class="eg-body">
            <form method="post" action="">
                <?php wp_nonce_field( 'engam_v2_settings_save', 'engam_v2_settings_nonce' ); ?>
                <input type="hidden" name="engam_form" value="core">
                <div class="eg-settings-field">
                    <label for="engam-gam-id">GAM Network ID</label>
                    <input class="eg-input" type="text" name="equinenetwork_gam_v2_id" id="engam-gam-id"
                        value="<?php echo esc_attr( $gam_id ); ?>"
                        placeholder="/22345131513/sitename">
                    <p class="eg-hint">Your full Google Ad Manager network path.</p>
                </div>
                <div class="eg-settings-field" style="margin-top:16px;padding-top:16px;border-top:1px solid #eee">
                    <label class="eg-toggle">
                        <input type="checkbox" name="engam_v2_delete_data_on_uninstall" value="1" <?php checked( (int) get_option( 'engam_v2_delete_data_on_uninstall', 0 ), 1 ); ?>>
                        <span class="eg-toggle-track"><span class="eg-toggle-thumb"></span></span>
                        Delete all data on uninstall
                    </label>
                    <p class="eg-hint">When on, deleting the plugin removes all its settings, caches, and saved ads (carousels, takeovers, leaderboards, stacker rules, credentials). When off, your data is preserved if the plugin is removed. Does not affect updates or deactivation.</p>
                </div>
                <button type="submit" class="eg-btn" style="width:100%;justify-content:center;display:flex">Save Settings</button>
            </form>
        </div>
    </div>

    <!-- GAM API CREDENTIALS -->
    <div class="eg-card" style="margin-top:18px">
        <div class="eg-head">
            <div>
                <h2>2. GAM API</h2>
                <p><?php echo $api_configured ? 'Credentials active. API is live.' : 'Paste your service account JSON key to enable live campaign sync.'; ?></p>
            </div>
            <span class="eg-tag" style="<?php echo $api_configured ? '' : 'background:#111;color:#d0ff00;'; ?>"><?php echo $api_configured ? 'Active' : 'Setup'; ?></span>
        </div>
        <div class="eg-body">

            <?php if ( $credentials_in_const ) : ?>
            <!-- Constant-based credentials — no upload needed -->
            <div style="background:#f7f7f4;border:1px solid #deded8;border-left:4px solid #050505;padding:14px 16px;margin-bottom:14px;display:flex;align-items:flex-start;gap:12px">
                <span style="flex-shrink:0;width:34px;height:34px;background:#050505;display:inline-flex;align-items:center;justify-content:center"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke="#d0ff00" stroke-width="3" stroke-linecap="square"/></svg></span>
                <div>
                    <strong style="font-size:13px;display:block;margin-bottom:4px">Credentials loaded from <code>wp-config.php</code></strong>
                    <span style="font-size:12px;color:#555">The <code>ENGAM_GAM_CREDENTIALS_JSON</code> constant is defined on this server — no file upload needed. To update credentials, edit that constant in <code>wp-config.php</code>.</span>
                </div>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button class="eg-btn dark" style="border-color:#111" id="engam-test-connection">Test Connection</button>
                <button class="eg-btn dark" style="border-color:#111" id="engam-refresh-cache">Refresh Cache</button>
            </div>

            <?php else : ?>
            <!-- Normal upload UI -->
            <?php if ( $api_configured ) :
                $stored_creds = json_decode( get_option( 'equinenetwork_gam_v2_credentials', '' ), true );
                $acct_email   = $stored_creds['client_email'] ?? '';
                $project_id   = $stored_creds['project_id']   ?? '';
            ?>
                <div style="background:#f7f7f4;border:1px solid #deded8;border-left:4px solid #050505;padding:12px 14px;margin-bottom:14px;display:flex;align-items:flex-start;gap:10px">
                    <span style="flex-shrink:0;width:30px;height:30px;background:#050505;display:inline-flex;align-items:center;justify-content:center"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 13l4 4L19 7" stroke="#d0ff00" stroke-width="3" stroke-linecap="square"/></svg></span>
                    <div style="min-width:0">
                        <strong style="font-size:13px;display:block;margin-bottom:2px">Connected</strong>
                        <?php if ( $acct_email ) : ?>
                        <span style="font-size:12px;color:#555;word-break:break-all"><?php echo esc_html( $acct_email ); ?></span><br>
                        <?php endif; ?>
                        <?php if ( $project_id ) : ?>
                        <span style="font-size:11px;color:#888">Project: <?php echo esc_html( $project_id ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
                    <button class="eg-btn dark" id="engam-test-connection" style="border-color:#111">Test Connection</button>
                    <button class="eg-btn dark" style="border-color:#111" id="engam-refresh-cache">Refresh Cache</button>
                </div>
            <?php else : ?>
                <p style="font-size:13px;color:#555;margin:0 0 14px">Upload your service account JSON key file to enable live campaign sync from GAM.</p>
            <?php endif; ?>

            <div class="eg-settings-field">
                <label>Service Account JSON Key <?php echo $api_configured ? '<span style="color:#777;font-weight:400;text-transform:none;letter-spacing:0">(upload to replace)</span>' : ''; ?></label>
                <div id="engam-upload-area" style="border:2px dashed #bbb;background:#f8f8f5;padding:10px 12px;cursor:pointer;transition:border-color .2s;display:flex;align-items:center;gap:10px"
                    onclick="document.getElementById('engam-credentials-file').click()"
                    ondragover="event.preventDefault();this.style.borderColor='#050505'"
                    ondragleave="this.style.borderColor='#bbb'"
                    ondrop="engamHandleDrop(event)">
                    <input type="file" id="engam-credentials-file" accept=".json,application/json" style="display:none" onchange="engamHandleFile(this.files[0])">
                    <span style="flex-shrink:0;width:30px;height:30px;background:#050505;display:inline-flex;align-items:center;justify-content:center"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 15V4M8 8l4-4 4 4M5 20h14" stroke="#d0ff00" stroke-width="2.4" stroke-linecap="square" stroke-linejoin="miter"/></svg></span>
                    <div id="engam-upload-label" style="min-width:0">
                        <strong style="font-size:12px;display:block">Click to upload or drag &amp; drop</strong>
                        <span style="font-size:11px;color:#777">.json service account key file</span>
                    </div>
                </div>
                <p class="eg-hint">The key is stored securely in the WordPress database — never in a file or repository.</p>
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button class="eg-btn" id="engam-save-credentials" style="pointer-events:none;opacity:.4" disabled>Save Credentials</button>
                <?php if ( ! $api_configured ) : ?>
                <button class="eg-btn dark" id="engam-test-connection" style="pointer-events:none;opacity:.4;border-color:#bbb">Test Connection</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div id="engam-api-status" style="display:none;margin-top:12px;padding:10px 14px;font-size:13px;font-weight:700"></div>
        </div>
    </div>

    <!-- SPONSOR SPREADSHEET (SharePoint / CSV) -->
    <div class="eg-card">
        <div class="eg-head">
            <div>
                <h2>3. Sponsor ID Spreadsheet</h2>
                <p>Connect your sponsorship ID sheet to populate the "Lock to Sponsor" dropdowns and the Carousels list.</p>
            </div>
            <span class="eg-tag" style="<?php echo $ms_active ? '' : 'background:#111;color:#d0ff00;'; ?>">
                <?php echo $ms_active ? 'SharePoint' : 'Setup'; ?>
            </span>
        </div>
        <div class="eg-body">

            <!-- MICROSOFT 365 (SharePoint) -->
            <div id="engam-src-ms-body">
                <form method="post" action="" id="engam-ms-form">
                    <?php wp_nonce_field( 'engam_v2_settings_save', 'engam_v2_settings_nonce' ); ?>
                    <input type="hidden" name="engam_form" value="ms_sponsor">

                    <?php if ( $ms_active ) : ?>
                    <div style="background:#f7f7f4;border:1px solid #deded8;border-left:4px solid #050505;padding:12px 14px;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px">
                        <span style="flex-shrink:0;width:30px;height:30px;background:#050505;display:inline-flex;align-items:center;justify-content:center"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#d0ff00" stroke-width="3" stroke-linecap="square"/></svg></span>
                        <div>
                            <strong style="font-size:13px;display:block;margin-bottom:2px"><?php echo $ms_configured ? 'Connected via Microsoft Graph' : 'Connected via share link'; ?></strong>
                            <span style="font-size:12px;color:#555;word-break:break-all"><?php echo esc_html( $ms_file_url ); ?></span><br>
                            <span style="font-size:11px;color:#888">Tab: <?php echo esc_html( $ms_sheet ); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Step 1 -->
                    <div style="margin-bottom:18px">
                        <div style="font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">Step 1 &mdash; Paste your SharePoint share link</div>
                        <div style="display:flex;gap:8px;align-items:stretch">
                            <input class="eg-input" type="url" name="engam_v2_ms_file_url" id="engam-ms-file-url"
                                value="<?php echo esc_attr( $ms_file_url ); ?>"
                                placeholder="https://equinenetwork.sharepoint.com/:x:/s/Home/..."
                                style="flex:1;margin:0">
                            <button type="button" id="engam-ms-tabs-refresh"
                                style="white-space:nowrap;padding:0 14px;font-size:12px;font-weight:700;border:2px solid #111;background:#fff;cursor:pointer;border-radius:4px;flex-shrink:0;line-height:1">&#8635; Load Tabs</button>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div style="margin-bottom:18px;max-width:320px">
                        <div style="font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">Step 2 &mdash; Select worksheet tab</div>
                        <!-- Both controls share the field name so the visible one submits its value
                             natively — no JavaScript sync needed. The hidden one is disabled so it
                             never posts. Select is used once tabs load; text input is the fallback. -->
                        <select class="eg-input" name="engam_v2_ms_sheet_name" id="engam-ms-sheet-select" style="display:none" disabled>
                            <option value="<?php echo esc_attr( $ms_sheet ); ?>"><?php echo esc_html( $ms_sheet ); ?></option>
                        </select>
                        <input class="eg-input" type="text" name="engam_v2_ms_sheet_name" id="engam-ms-sheet" autocomplete="off"
                            value="<?php echo esc_attr( $ms_sheet ); ?>"
                            placeholder="e.g. EQUUS">
                        <p class="eg-hint" id="engam-ms-sheet-hint" style="margin-top:6px">Click &ldquo;Load Tabs&rdquo; above to populate this dropdown.</p>
                    </div>

                    <!-- Step 3 -->
                    <div style="font-size:11px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Step 3 &mdash; Test &amp; save</div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap">
                        <button type="submit" class="eg-btn" style="flex:1;justify-content:center;display:flex">Save</button>
                        <button type="button" class="eg-btn dark" style="border-color:#111" id="engam-ms-test-btn">Test Connection</button>
                    </div>
                    <div id="engam-ms-status" style="display:none;margin-top:12px;padding:10px 14px;font-size:13px;font-weight:700;border-radius:6px"></div>
                </form>
            </div>

        </div>
    </div>

    <!-- ACF SPONSOR ID MIGRATION -->
    <div class="eg-card">
    <div class="eg-head">
        <div>
            <h2>4. Migrate Sponsor IDs from ACF</h2>
            <p>One-time merge: copy sponsor IDs assigned with the legacy ACF fields (<code>sponlineitemid</code> / <code>sponsorship_id</code>) into this plugin — across posts, pages, categories, and tags — so they keep working after you delete those ACF fields.</p>
        </div>
        <span class="eg-tag"<?php echo $acf_candidate_count > 0 ? '' : ' style="background:#eee;color:#999"'; ?>><?php echo (int) $acf_candidate_count; ?> found</span>
    </div>
    <div class="eg-body">
        <?php if ( $acf_candidate_count === 0 && ! $migration_result ) : ?>
            <p style="font-size:13px;color:#555;margin:0">No posts, categories, or tags with legacy ACF sponsor IDs were found — nothing to migrate.</p>
        <?php else : ?>
            <p class="eg-hint" style="margin:0 0 14px">
                Existing assignments are never overwritten — only items without an EN Sponsor ID already set are updated. Revisions, auto-drafts, and trashed posts are skipped. Run <strong>Preview</strong> first to see exactly what will change; it's safe to run more than once.
            </p>
            <form method="post" action="">
                <?php wp_nonce_field( 'engam_v2_settings_save', 'engam_v2_settings_nonce' ); ?>
                <input type="hidden" name="engam_form" value="acf_migrate">
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <button type="submit" class="eg-btn dark" style="border-color:#111">Preview Changes</button>
                    <button type="submit" name="acf_migrate_confirm" value="1" class="eg-btn"
                        onclick="return confirm('Copy ACF sponsor IDs into the plugin for every post that does not already have one? Safe to re-run.')">Run Migration Now</button>
                </div>
            </form>

            <?php if ( $migration_result ) : ?>
            <div style="margin-top:16px;border:1px solid #deded8;border-radius:6px;padding:14px;background:#fafaf8">
                <strong style="font-size:13px;display:block;margin-bottom:8px">
                    <?php echo $migration_ran ? 'Migration complete' : 'Preview — no changes made yet'; ?>
                </strong>
                <div style="font-size:13px;color:#333;margin-bottom:10px">
                    <?php echo (int) $migration_result['candidates']; ?> item(s) with ACF sponsor IDs &middot;
                    <strong><?php echo (int) $migration_result['migrated']; ?></strong> <?php echo $migration_ran ? 'migrated' : 'will be migrated'; ?>
                    (<?php echo (int) $migration_result['posts']; ?> post/page, <?php echo (int) $migration_result['terms']; ?> category/tag) &middot;
                    <?php echo (int) $migration_result['skipped_existing']; ?> already had a plugin value (left unchanged)
                </div>
                <?php if ( ! empty( $migration_result['samples'] ) ) : ?>
                <table class="eg-table" style="margin-top:6px">
                    <thead><tr><th>Type</th><th>Item</th><th>Sponsor ID</th></tr></thead>
                    <tbody>
                        <?php foreach ( $migration_result['samples'] as $s ) : ?>
                        <tr>
                            <td style="font-size:12px;color:#555"><?php echo esc_html( $s['kind'] ); ?><?php echo $s['meta'] ? ' &middot; ' . esc_html( $s['meta'] ) : ''; ?></td>
                            <td>
                                <?php if ( ! empty( $s['edit'] ) ) : ?>
                                    <a href="<?php echo esc_url( $s['edit'] ); ?>" style="font-weight:700;text-decoration:none;color:#050505"><?php echo esc_html( $s['title'] ); ?></a>
                                <?php else : ?>
                                    <strong><?php echo esc_html( $s['title'] ); ?></strong>
                                <?php endif; ?>
                            </td>
                            <td><code style="font-size:12px"><?php echo esc_html( $s['value'] ); ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ( (int) $migration_result['migrated'] > count( $migration_result['samples'] ) ) : ?>
                <p class="eg-hint" style="margin:8px 0 0">Showing first <?php echo count( $migration_result['samples'] ); ?> of <?php echo (int) $migration_result['migrated']; ?>.</p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    </div>

</div><!-- .eg-grid -->

</div><!-- .eg-content -->
</div><!-- #engam-v2-wrap -->

<script>
(function(){
    var NONCE = '<?php echo esc_js( wp_create_nonce( 'engam_v2_admin' ) ); ?>';

    function ajaxPost(action, extra, cb) {
        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=' + action + '&nonce=' + encodeURIComponent(NONCE) + (extra || '')
        }).then(function(r){ return r.json(); }).then(cb).catch(function(){
            cb({ success: false, data: 'Request failed.' });
        });
    }

    function showStatus(elId, data) {
        var el = document.getElementById(elId);
        if (!el) return;
        el.style.display    = 'block';
        el.style.background = data.success ? '#f7f7f4' : '#fde8e8';
        el.style.borderLeft = data.success ? '4px solid #050505' : '4px solid #cc0000';
        el.style.color      = data.success ? '#111' : '#9b1c1c';
        el.textContent      = data.data || (data.success ? 'OK' : 'Error');
    }

    // ── Worksheet/tab picker ──────────────────────────────────────────
    // The <select> and the text input share name="engam_v2_ms_sheet_name".
    // Whichever is active (enabled + visible) submits its value natively — no
    // JS sync into a hidden field, so a missed change event can't blank it out.
    var tabSelect  = document.getElementById('engam-ms-sheet-select');  // shown when tabs load
    var tabText    = document.getElementById('engam-ms-sheet');         // text fallback
    var tabRefresh = document.getElementById('engam-ms-tabs-refresh');
    var tabHint    = document.getElementById('engam-ms-sheet-hint');
    var msFileUrl  = document.getElementById('engam-ms-file-url');

    function fillTabs(tabs, current) {
        if (!tabSelect) return;
        tabSelect.innerHTML = '';
        tabs.forEach(function(name){
            var opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            if (name === current) opt.selected = true;
            tabSelect.appendChild(opt);
        });
        // If the saved tab isn't in the list, add it so it isn't silently lost.
        if (current && !tabs.includes(current)) {
            var opt = document.createElement('option');
            opt.value = current; opt.textContent = current; opt.selected = true;
            tabSelect.insertBefore(opt, tabSelect.firstChild);
        }
        // Activate the select (submits its own value); deactivate the text input
        // so only one field with the shared name is posted.
        if (current) tabSelect.value = current;
        tabSelect.disabled = false;
        tabSelect.style.display = '';
        if (tabText) { tabText.disabled = true; tabText.style.display = 'none'; }
        if (tabHint) tabHint.textContent = 'Pick from your ' + tabs.length + ' tabs, or type a name not in the list.';
    }

    function loadTabs(force) {
        if (!tabSelect) return;
        var urlVal = msFileUrl ? msFileUrl.value.trim() : '';
        if (!urlVal) return;
        if (tabRefresh) { tabRefresh.textContent = '↻ Loading…'; tabRefresh.disabled = true; }
        var extra = (force ? '&force=1' : '') + '&url=' + encodeURIComponent(urlVal);
        ajaxPost('engam_v2_ms_tabs', extra, function(data){
            if (tabRefresh) { tabRefresh.textContent = '↻ Load Tabs'; tabRefresh.disabled = false; }
            if (data && data.success && data.data && data.data.tabs && data.data.tabs.length) {
                // Prefer the value already showing (select or text) over the DB default
                // so Load Tabs / auto-load never resets a tab the user just picked.
                var preferred = (tabSelect && !tabSelect.disabled && tabSelect.value)
                             || (tabText && tabText.value)
                             || data.data.current || '';
                fillTabs(data.data.tabs, preferred);
            }
            // On failure, leave the text input visible — Test Connection shows why.
        });
    }

    if (tabRefresh) tabRefresh.addEventListener('click', function(){ loadTabs(true); });

    // ── MS Graph test button ───────────────────────────────────────────
    var msTestBtn = document.getElementById('engam-ms-test-btn');
    if (msTestBtn) {
        msTestBtn.addEventListener('click', function(){
            document.getElementById('engam-ms-status').style.display = 'none';
            msTestBtn.disabled = true; msTestBtn.textContent = 'Testing…';
            ajaxPost('engam_v2_test_ms', '', function(data){
                showStatus('engam-ms-status', data);
                msTestBtn.disabled = false; msTestBtn.textContent = 'Test Connection';
                // Test Connection re-reads the file, so refresh the tab list too.
                if (data && data.success) loadTabs(true);
            });
        });
    }

    // Populate the tab list on load when a share link is already saved.
    <?php if ( $ms_active ) : ?>
    loadTabs(false);
    <?php endif; ?>
})();
</script>
