<?php if ( ! defined( 'WPINC' ) ) die;

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
        update_option( 'engam_v2_ms_tenant_id',     sanitize_text_field( wp_unslash( $_POST['engam_v2_ms_tenant_id']     ?? '' ) ) );
        update_option( 'engam_v2_ms_client_id',     sanitize_text_field( wp_unslash( $_POST['engam_v2_ms_client_id']     ?? '' ) ) );
        update_option( 'engam_v2_ms_file_url',      esc_url_raw( wp_unslash( $_POST['engam_v2_ms_file_url']              ?? '' ) ) );
        update_option( 'engam_v2_ms_sheet_name',    sanitize_text_field( wp_unslash( $_POST['engam_v2_ms_sheet_name']    ?? 'HR' ) ) );
        // Only overwrite the secret if a new value was submitted (preserve existing on blank).
        $new_secret = sanitize_text_field( wp_unslash( $_POST['engam_v2_ms_client_secret'] ?? '' ) );
        if ( $new_secret !== '' ) {
            update_option( 'engam_v2_ms_client_secret', $new_secret );
        }
        delete_transient( 'engam_v2_sponsor_options' );
        delete_transient( 'engam_v2_graph_token' );
        delete_transient( 'engam_v2_ms_worksheets' );
        $notice = 'SharePoint connection saved.';
    }

}

$sheet_csv_url     = get_option( 'engam_v2_sheet_csv_url', '' );
$sheets_configured = ! empty( $sheet_csv_url );

$ms_tenant     = get_option( 'engam_v2_ms_tenant_id', '' );
$ms_client     = get_option( 'engam_v2_ms_client_id', '' );
$ms_secret_set = ! empty( get_option( 'engam_v2_ms_client_secret', '' ) );
$ms_file_url   = get_option( 'engam_v2_ms_file_url', '' );
$ms_sheet      = get_option( 'engam_v2_ms_sheet_name', 'HR' );
$ms_configured = $ms_tenant && $ms_client && $ms_secret_set && $ms_file_url;
$ms_link_only  = ! $ms_configured && $ms_file_url;     // share-link path (no Azure)
$ms_active     = $ms_configured || $ms_link_only;
// Default source: show MS if already configured, else CSV if CSV url exists, else MS (new setup).
$sponsor_source = $ms_active ? 'ms' : ( $sheets_configured ? 'csv' : 'ms' );

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
                <h2>GAM Settings</h2>
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
                <h2>GAM API</h2>
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
                <h2>Sponsor Spreadsheet</h2>
                <p>Connect your sponsorship ID sheet to populate the "Lock to Sponsor" dropdowns and the Carousels list.</p>
            </div>
            <span class="eg-tag" style="<?php echo ( $ms_active || $sheets_configured ) ? '' : 'background:#111;color:#d0ff00;'; ?>">
                <?php echo $ms_active ? 'SharePoint' : ( $sheets_configured ? 'CSV' : 'Setup' ); ?>
            </span>
        </div>
        <div class="eg-body">

            <!-- Source toggle -->
            <div style="display:flex;gap:0;margin-bottom:20px;border:1px solid #deded8;border-radius:6px;overflow:hidden">
                <button type="button" id="engam-src-ms"
                    onclick="engamSetSource('ms')"
                    style="flex:1;padding:9px 0;font-size:13px;font-weight:700;border:none;cursor:pointer;background:<?php echo $sponsor_source === 'ms' ? '#050505' : '#fff'; ?>;color:<?php echo $sponsor_source === 'ms' ? '#d0ff00' : '#555'; ?>;transition:background .15s,color .15s">
                    Microsoft 365 (SharePoint)
                </button>
                <button type="button" id="engam-src-csv"
                    onclick="engamSetSource('csv')"
                    style="flex:1;padding:9px 0;font-size:13px;font-weight:700;border:none;border-left:1px solid #deded8;cursor:pointer;background:<?php echo $sponsor_source === 'csv' ? '#050505' : '#fff'; ?>;color:<?php echo $sponsor_source === 'csv' ? '#d0ff00' : '#555'; ?>;transition:background .15s,color .15s">
                    CSV URL (legacy)
                </button>
            </div>

            <!-- MICROSOFT 365 SECTION -->
            <div id="engam-src-ms-body" style="<?php echo $sponsor_source !== 'ms' ? 'display:none' : ''; ?>">
                <form method="post" action="">
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

                    <p class="eg-hint" style="margin:0 0 14px">
                        If your sheet is shared with <strong>&ldquo;Anyone with the link&rdquo;</strong>, just paste that link and the tab name below &mdash; no Azure setup needed.
                    </p>

                    <div class="eg-settings-field" style="margin-bottom:14px">
                        <label for="engam-ms-file-url">SharePoint Share Link</label>
                        <input class="eg-input" type="url" name="engam_v2_ms_file_url" id="engam-ms-file-url"
                            value="<?php echo esc_attr( $ms_file_url ); ?>"
                            placeholder="https://equinenetwork.sharepoint.com/:x:/s/Home/...">
                        <p class="eg-hint">In Excel/SharePoint click <strong>Share &rarr; Copy link</strong>. Make sure it reads <strong>&ldquo;Anyone with the link&rdquo;</strong>, then paste it here. (View-only is fine &mdash; the plugin only reads.)</p>
                    </div>

                    <div class="eg-settings-field" style="margin-bottom:16px;max-width:260px">
                        <label for="engam-ms-sheet">Worksheet / Tab Name</label>
                        <input class="eg-input" type="text" name="engam_v2_ms_sheet_name" id="engam-ms-sheet"
                            value="<?php echo esc_attr( $ms_sheet ); ?>"
                            placeholder="HR">
                        <p class="eg-hint">The tab name exactly as it appears in Excel (e.g. <code>HR</code>). Case-sensitive.</p>
                    </div>

                    <!-- Advanced: Azure (only needed when the file is NOT shared via "Anyone with the link") -->
                    <details style="margin:0 0 16px;border:1px solid #deded8;border-radius:6px;overflow:hidden" <?php echo $ms_configured ? 'open' : ''; ?>>
                        <summary style="padding:10px 14px;cursor:pointer;font-weight:700;font-size:13px;background:#f7f7f4;list-style:none;display:flex;justify-content:space-between;align-items:center">
                            Private file? Advanced Microsoft (Azure) setup
                            <span style="font-size:11px;font-weight:400;color:#888">requires a Microsoft admin</span>
                        </summary>
                        <div style="padding:14px">
                            <p class="eg-hint" style="margin:0 0 12px">Only needed if the sheet <strong>cannot</strong> be shared with &ldquo;Anyone with the link.&rdquo; A Microsoft 365 / Azure admin must complete these steps:</p>
                            <ol style="margin:0 0 14px 18px;padding:0;font-size:13px;line-height:1.6;color:#333">
                                <li>Go to <a href="https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade" target="_blank" rel="noopener" style="font-weight:700">portal.azure.com &rarr; Entra ID &rarr; App registrations</a> &rarr; <strong>New registration</strong>. Leave the redirect URI blank. Click <strong>Register</strong>.</li>
                                <li>From the Overview page, copy the <strong>Directory (tenant) ID</strong> and <strong>Application (client) ID</strong>.</li>
                                <li><strong>API permissions &rarr; Add a permission &rarr; Microsoft Graph &rarr; Application permissions</strong>. Add <code>Files.Read.All</code>, then click <strong>Grant admin consent</strong>.</li>
                                <li><strong>Certificates &amp; secrets &rarr; New client secret</strong>. Copy the <strong>Value</strong> (not the ID) — it shows only once.</li>
                            </ol>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                                <div class="eg-settings-field">
                                    <label for="engam-ms-tenant">Directory (Tenant) ID</label>
                                    <input class="eg-input" type="text" name="engam_v2_ms_tenant_id" id="engam-ms-tenant"
                                        value="<?php echo esc_attr( $ms_tenant ); ?>"
                                        placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                                </div>
                                <div class="eg-settings-field">
                                    <label for="engam-ms-client">Application (Client) ID</label>
                                    <input class="eg-input" type="text" name="engam_v2_ms_client_id" id="engam-ms-client"
                                        value="<?php echo esc_attr( $ms_client ); ?>"
                                        placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                                </div>
                                <div class="eg-settings-field" style="grid-column:1 / -1">
                                    <label for="engam-ms-secret">Client Secret Value</label>
                                    <input class="eg-input" type="password" name="engam_v2_ms_client_secret" id="engam-ms-secret"
                                        value=""
                                        placeholder="<?php echo $ms_secret_set ? '(saved — leave blank to keep)' : 'Paste secret value here'; ?>"
                                        autocomplete="new-password">
                                    <?php if ( $ms_secret_set ) : ?>
                                    <p class="eg-hint" style="margin-top:4px">A secret is saved. Leave blank to keep it, or paste a new value to replace it.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </details>

                    <div style="display:flex;gap:10px;flex-wrap:wrap">
                        <button type="submit" class="eg-btn" style="flex:1;justify-content:center;display:flex">Save</button>
                        <button type="button" class="eg-btn dark" style="border-color:#111" id="engam-ms-test-btn">Test Connection</button>
                    </div>
                    <div id="engam-ms-status" style="display:none;margin-top:12px;padding:10px 14px;font-size:13px;font-weight:700;border-radius:6px"></div>
                </form>
            </div>

            <!-- CSV SECTION (legacy) -->
            <div id="engam-src-csv-body" style="<?php echo $sponsor_source !== 'csv' ? 'display:none' : ''; ?>">
                <form method="post" action="">
                    <?php wp_nonce_field( 'engam_v2_settings_save', 'engam_v2_settings_nonce' ); ?>
                    <input type="hidden" name="engam_form" value="sheets">
                    <div class="eg-settings-field">
                        <label for="engam-sheet-csv-url">Published CSV URL</label>
                        <input class="eg-input" type="url" name="engam_v2_sheet_csv_url" id="engam-sheet-csv-url"
                            value="<?php echo esc_attr( $sheet_csv_url ); ?>"
                            placeholder="https://docs.google.com/spreadsheets/d/e/…/pub?…&output=csv">
                        <p class="eg-hint">In Google Sheets: <strong>File &rarr; Share &rarr; Publish to web &rarr; CSV &rarr; Publish</strong>. Paste that link here.</p>
                    </div>
                    <div style="display:flex;gap:10px;flex-wrap:wrap">
                        <button type="submit" class="eg-btn" style="flex:1;justify-content:center;display:flex">Save</button>
                        <?php if ( $sheets_configured ) : ?>
                        <button type="button" class="eg-btn dark" style="border-color:#111" id="engam-sheets-test-btn">Test &amp; Refresh</button>
                        <?php endif; ?>
                    </div>
                    <div id="engam-sheets-status" style="display:none;margin-top:12px;padding:10px 14px;font-size:13px;font-weight:700;border-radius:6px"></div>
                </form>
            </div>

        </div>
    </div>


</div>

</div><!-- .eg-content -->
</div><!-- #engam-v2-wrap -->

<script>
(function(){
    var NONCE = '<?php echo esc_js( wp_create_nonce( 'engam_v2_admin' ) ); ?>';

    // ── Source toggle ──────────────────────────────────────────────────
    window.engamSetSource = function(src) {
        var ms  = document.getElementById('engam-src-ms');
        var csv = document.getElementById('engam-src-csv');
        var msB  = document.getElementById('engam-src-ms-body');
        var csvB = document.getElementById('engam-src-csv-body');
        if (!ms || !csv || !msB || !csvB) return;
        var on = '#050505', off = '#fff', ont = '#d0ff00', offt = '#555';
        ms.style.background  = src === 'ms'  ? on  : off;
        ms.style.color       = src === 'ms'  ? ont : offt;
        csv.style.background = src === 'csv' ? on  : off;
        csv.style.color      = src === 'csv' ? ont : offt;
        msB.style.display    = src === 'ms'  ? '' : 'none';
        csvB.style.display   = src === 'csv' ? '' : 'none';
    };

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

    // ── MS Graph test button ───────────────────────────────────────────
    var msTestBtn = document.getElementById('engam-ms-test-btn');
    if (msTestBtn) {
        msTestBtn.addEventListener('click', function(){
            document.getElementById('engam-ms-status').style.display = 'none';
            msTestBtn.disabled = true; msTestBtn.textContent = 'Testing…';
            ajaxPost('engam_v2_test_ms', '', function(data){
                showStatus('engam-ms-status', data);
                msTestBtn.disabled = false; msTestBtn.textContent = 'Test Connection';
            });
        });
    }

    // ── CSV test button ────────────────────────────────────────────────
    var testBtn = document.getElementById('engam-sheets-test-btn');
    if (testBtn) {
        testBtn.addEventListener('click', function(){
            document.getElementById('engam-sheets-status').style.display = 'none';
            testBtn.disabled = true; testBtn.textContent = 'Testing…';
            ajaxPost('engam_v2_test_sheets', '', function(data){
                showStatus('engam-sheets-status', data);
                testBtn.disabled = false; testBtn.textContent = 'Test & Refresh';
            });
        });
    }
})();
</script>
