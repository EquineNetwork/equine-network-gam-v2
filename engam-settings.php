<?php if ( ! defined( 'WPINC' ) ) die;

$gam_id          = get_option( 'equinenetwork_gam_v2_id', '' );
$id_active       = ! empty( $gam_id );
$has_credentials = ! empty( get_option( 'equinenetwork_gam_v2_credentials', '' ) );
$api             = new Equinenetwork_Gam_V2_API();
$api_configured  = $api->is_configured();

$notice = '';
if ( isset( $_POST['engam_v2_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['engam_v2_settings_nonce'] ) ), 'engam_v2_settings_save' ) ) {
    $form = sanitize_text_field( wp_unslash( $_POST['engam_form'] ?? '' ) );
    if ( current_user_can( 'manage_options' ) && 'core' === $form ) {
        update_option( 'equinenetwork_gam_v2_id', sanitize_text_field( wp_unslash( $_POST['equinenetwork_gam_v2_id'] ?? '' ) ) );
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

}

$sheet_csv_url     = get_option( 'engam_v2_sheet_csv_url', '' );
$sheets_configured = ! empty( $sheet_csv_url );

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
                <button type="submit" class="eg-btn" style="width:100%;justify-content:center;display:flex">Save Settings</button>
            </form>
        </div>
    </div>

    <!-- GAM API CREDENTIALS -->
    <div class="eg-card" style="margin-top:18px">
        <div class="eg-head">
            <div>
                <h2>GAM API</h2>
                <p><?php echo $api_configured ? 'Credentials saved. API is active.' : 'Paste your service account JSON key to enable live campaign sync.'; ?></p>
            </div>
            <span class="eg-tag" style="<?php echo $api_configured ? '' : 'background:#111;color:#d0ff00;'; ?>"><?php echo $api_configured ? 'Active' : 'Setup'; ?></span>
        </div>
        <div class="eg-body">
            <?php if ( $api_configured ) : ?>
                <p style="font-size:13px;color:#555;margin:0 0 14px">Credentials are stored. The Elementor widget sponsor dropdown pulls live from GAM and caches for 1 hour.</p>
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
                    <span style="font-size:18px;flex-shrink:0">&#128193;</span>
                    <div id="engam-upload-label" style="min-width:0">
                        <strong style="font-size:12px;display:block">Click to upload or drag &amp; drop</strong>
                        <span style="font-size:11px;color:#777">.json service account key file</span>
                    </div>
                </div>
                <p class="eg-hint">The key is stored securely in the WordPress database — never in a file or repository.</p>
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button class="eg-btn" id="engam-save-credentials" style="pointer-events:none;opacity:.4" disabled>Save Credentials</button>
                <button class="eg-btn dark" id="engam-test-connection"
                    <?php echo ! $api_configured ? 'style="pointer-events:none;opacity:.4;border-color:#bbb"' : 'style="border-color:#111"'; ?>>
                    Test Connection
                </button>
                <?php if ( $api_configured ) : ?>
                <button class="eg-btn dark" style="border-color:#111" id="engam-refresh-cache">Refresh Cache</button>
                <?php endif; ?>
            </div>
            <div id="engam-api-status" style="display:none;margin-top:12px;padding:10px 14px;font-size:13px;font-weight:700"></div>
        </div>
    </div>

    <!-- SPONSOR SHEET -->
    <div class="eg-card">
        <div class="eg-head">
            <div>
                <h2>Connect to Google Sheets</h2>
                <p>Paste the published CSV link from your sponsor ID spreadsheet.</p>
            </div>
            <span class="eg-tag" style="<?php echo $sheets_configured ? '' : 'background:#111;color:#d0ff00;'; ?>"><?php echo $sheets_configured ? 'Connected' : 'Setup'; ?></span>
        </div>
        <div class="eg-body">
            <form method="post" action="">
                <?php wp_nonce_field( 'engam_v2_settings_save', 'engam_v2_settings_nonce' ); ?>
                <input type="hidden" name="engam_form" value="sheets">
                <div class="eg-settings-field">
                    <label for="engam-sheet-csv-url">Published CSV URL</label>
                    <input class="eg-input" type="url" name="engam_v2_sheet_csv_url" id="engam-sheet-csv-url"
                        value="<?php echo esc_attr( $sheet_csv_url ); ?>"
                        placeholder="https://docs.google.com/spreadsheets/d/e/…/pub?gid=…&single=true&output=csv">
                    <p class="eg-hint">In Google Sheets: <strong>File &rarr; Share &rarr; Publish to web</strong> &rarr; select your tab &rarr; <strong>Comma-separated values</strong> &rarr; Publish &rarr; copy the link. Enable "Automatically republish when changes are made" so updates sync automatically.</p>
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

</div><!-- .eg-content -->
</div><!-- #engam-v2-wrap -->

<script>
(function(){
    var NONCE = '<?php echo esc_js( wp_create_nonce( 'engam_v2_admin' ) ); ?>';

    // Test & Refresh sponsor list
    var testBtn = document.getElementById('engam-sheets-test-btn');
    if (testBtn) {
        testBtn.addEventListener('click', function(){
            var statusEl = document.getElementById('engam-sheets-status');
            testBtn.disabled = true; testBtn.textContent = 'Testing…';
            statusEl.style.display = 'none';
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=engam_v2_test_sheets&nonce=' + encodeURIComponent(NONCE)
            }).then(function(r){ return r.json(); }).then(function(data){
                statusEl.style.display    = 'block';
                statusEl.style.background = data.success ? '#d0f0e0' : '#fde8e8';
                statusEl.style.color      = data.success ? '#1a6640' : '#9b1c1c';
                statusEl.textContent      = data.data || (data.success ? 'Connected!' : 'Error');
            }).catch(function(){
                statusEl.style.display = 'block';
                statusEl.style.background = '#fde8e8';
                statusEl.style.color = '#9b1c1c';
                statusEl.textContent = 'Request failed.';
            }).finally(function(){
                testBtn.disabled = false; testBtn.textContent = 'Test & Refresh';
            });
        });
    }

    // Debug wrap creatives
    var debugBtn = document.getElementById('engam-debug-creatives-btn');
    var debugOut  = document.getElementById('engam-debug-creatives-out');
    if (debugBtn && debugOut) {
        debugBtn.addEventListener('click', function(){
            var lid = (document.getElementById('engam-debug-li-id') || {}).value || '';
            if (!lid.trim()) { alert('Enter a Line Item ID first.'); return; }
            debugBtn.disabled = true; debugBtn.textContent = 'Testing…';
            debugOut.style.display = 'none';
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=engam_v2_debug_wrap_creatives&nonce=' + encodeURIComponent(NONCE) + '&line_item_id=' + encodeURIComponent(lid)
            }).then(function(r){ return r.json(); }).then(function(data){
                debugOut.style.display = 'block';
                debugOut.textContent = JSON.stringify(data.data || data, null, 2);
            }).catch(function(e){
                debugOut.style.display = 'block';
                debugOut.textContent = 'Request failed: ' + e;
            }).finally(function(){
                debugBtn.disabled = false; debugBtn.textContent = 'Test Creatives';
            });
        });
    }
})();
</script>
