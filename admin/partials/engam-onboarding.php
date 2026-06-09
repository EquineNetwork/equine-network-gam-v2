<?php
if ( ! defined( 'WPINC' ) ) die;
if ( ! current_user_can( 'manage_options' ) ) return;

require_once EQUINENETWORK_GAM_V2_PATH . 'includes/engam-v2-migrate.php';

$ob_dismissed = (bool) get_option( 'engam_v2_onboarding_dismissed', 0 );
$gam_id       = get_option( 'equinenetwork_gam_v2_id', '' );
$has_creds    = ! empty( get_option( 'equinenetwork_gam_v2_credentials', '' ) )
                || ( defined( 'ENGAM_GAM_CREDENTIALS_JSON' ) && ENGAM_GAM_CREDENTIALS_JSON );
$ms_file_url  = get_option( 'engam_v2_ms_file_url', '' );
$ms_sheet     = get_option( 'engam_v2_ms_sheet_name', '' );
$setup_done   = $gam_id && $has_creds && $ms_file_url;

// Cached count — avoid a DB hit on every admin page load when setup is done.
$acf_count = 0;
if ( ! $setup_done || ! $ob_dismissed ) {
    global $wpdb;
    $acf_count = (int) $wpdb->get_var(
        "SELECT COUNT( DISTINCT m.post_id ) FROM {$wpdb->postmeta} m
           INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
          WHERE m.meta_key IN ( 'sponlineitemid', 'sponsorship_id' ) AND m.meta_value <> ''
            AND p.post_type <> 'revision'
            AND p.post_status IN ( 'publish', 'private', 'draft', 'pending', 'future' )"
    ) + (int) $wpdb->get_var(
        "SELECT COUNT( DISTINCT term_id ) FROM {$wpdb->termmeta}
          WHERE meta_key IN ( 'sponlineitemid', 'sponsorship_id' ) AND meta_value <> ''"
    );
}

// Auto-show when not dismissed AND setup is incomplete.
$initial_display = ( ! $ob_dismissed && ! $setup_done ) ? 'flex' : 'none';

$ob_nonce   = wp_create_nonce( 'engam_v2_admin' );
$ajax_nonce = wp_create_nonce( 'engam_v2_ajax' );
?>
<div id="engam-ob-overlay" style="position:fixed;inset:0;background:rgba(5,5,5,.75);z-index:999999;display:<?php echo esc_attr( $initial_display ); ?>;align-items:center;justify-content:center;padding:16px">
  <div id="engam-ob-modal" style="background:#fff;max-width:600px;width:100%;border-radius:4px;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.35);display:flex;flex-direction:column;max-height:calc(100vh - 32px)">

    <!-- Header -->
    <div style="background:#111111;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="width:34px;height:34px;background:#C8FF00;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:13px;color:#111111;letter-spacing:-.02em;flex-shrink:0">EN</div>
        <div>
          <div style="font-size:10px;color:#777;text-transform:uppercase;letter-spacing:.1em;margin-bottom:1px">Setup Wizard</div>
          <div style="font-size:16px;font-weight:700;color:#fff;line-height:1.2">Get started with EN Ads</div>
        </div>
      </div>
      <button type="button" id="engam-ob-close" aria-label="Close wizard" style="background:none;border:none;color:#666;cursor:pointer;font-size:22px;line-height:1;padding:4px 8px;border-radius:3px">✕</button>
    </div>

    <!-- Step indicator -->
    <div style="background:#f7f7f4;border-bottom:1px solid #e8e8e0;padding:14px 24px;flex-shrink:0">
      <div id="engam-ob-steps-nav" style="display:flex;align-items:center"></div>
    </div>

    <!-- Step body -->
    <div id="engam-ob-body" style="padding:24px 24px 20px;overflow-y:auto;flex:1;min-height:0">

      <!-- Step 1: GAM Settings -->
      <div class="engam-ob-step" data-step="1">
        <h3 style="margin:0 0 6px;font-size:18px;font-weight:700;color:#111111">GAM Settings</h3>
        <p style="margin:0 0 20px;font-size:13px;color:#555;line-height:1.55">Enter your Google Ad Manager network path. You can find it in GAM under <strong>Admin → Network settings</strong>.</p>
        <div style="margin-bottom:6px">
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#444;margin-bottom:6px" for="engam-ob-gam-id">GAM Network ID</label>
          <input type="text" id="engam-ob-gam-id"
            style="width:100%;padding:10px 12px;border:1.5px solid #ccc;border-radius:4px;font-size:14px;box-sizing:border-box;outline:none;font-family:monospace"
            value="<?php echo esc_attr( $gam_id ); ?>"
            placeholder="/22345131513/sitename">
          <p style="margin:7px 0 0;font-size:12px;color:#888">Format: <code style="background:#f5f5f0;padding:1px 5px;border-radius:3px">/XXXXXXXXX/sitename</code></p>
        </div>
        <div id="engam-ob-step1-msg" style="display:none;margin-top:12px;padding:10px 14px;border-radius:4px;font-size:13px;font-weight:600"></div>
      </div>

      <!-- Step 2: GAM API Credentials -->
      <div class="engam-ob-step" data-step="2" style="display:none">
        <h3 style="margin:0 0 6px;font-size:18px;font-weight:700;color:#111111">GAM API Credentials</h3>
        <?php if ( $has_creds ) : ?>
        <div style="background:#f7f7f4;border:1px solid #deded8;border-left:4px solid #111111;padding:12px 14px;border-radius:4px;display:flex;align-items:center;gap:10px;margin-bottom:16px">
          <span style="width:28px;height:28px;background:#111111;display:flex;align-items:center;justify-content:center;flex-shrink:0;border-radius:2px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#C8FF00" stroke-width="3" stroke-linecap="square"/></svg></span>
          <span style="font-size:13px;font-weight:600;color:#111111">API credentials are already configured — you can skip this step or upload a replacement key.</span>
        </div>
        <?php else : ?>
        <p style="margin:0 0 16px;font-size:13px;color:#555;line-height:1.55">Upload your Google service account JSON key file to enable live campaign sync from GAM.</p>
        <?php endif; ?>
        <div id="engam-ob-upload-area"
          style="border:2px dashed #ccc;background:#f8f8f5;padding:14px 16px;cursor:pointer;border-radius:4px;transition:border-color .15s;display:flex;align-items:center;gap:12px;margin-bottom:14px"
          onclick="document.getElementById('engam-ob-creds-file').click()"
          ondragover="event.preventDefault();this.style.borderColor='#111111'"
          ondragleave="this.style.borderColor='#ccc'"
          ondrop="engamObHandleDrop(event)">
          <input type="file" id="engam-ob-creds-file" accept=".json,application/json" style="display:none">
          <span style="flex-shrink:0;width:30px;height:30px;background:#111111;display:inline-flex;align-items:center;justify-content:center;border-radius:2px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 15V4M8 8l4-4 4 4M5 20h14" stroke="#C8FF00" stroke-width="2.4" stroke-linecap="square" stroke-linejoin="miter"/></svg>
          </span>
          <div id="engam-ob-upload-label">
            <strong style="font-size:12px;display:block">Click to upload or drag &amp; drop</strong>
            <span style="font-size:11px;color:#777">.json service account key file</span>
          </div>
        </div>
        <button type="button" id="engam-ob-test-api"
          style="background:#fff;color:#111111;border:2px solid #111;padding:9px 18px;font-size:12px;font-weight:700;cursor:pointer;border-radius:3px">Test Connection</button>
        <div id="engam-ob-step2-msg" style="display:none;margin-top:12px;padding:10px 14px;border-radius:4px;font-size:13px;font-weight:600"></div>
      </div>

      <!-- Step 3: SharePoint Spreadsheet -->
      <div class="engam-ob-step" data-step="3" style="display:none">
        <h3 style="margin:0 0 6px;font-size:18px;font-weight:700;color:#111111">Sponsor ID Spreadsheet</h3>
        <p style="margin:0 0 20px;font-size:13px;color:#555;line-height:1.55">Connect your SharePoint spreadsheet to power the &ldquo;Lock to Sponsor&rdquo; dropdowns and Carousels across the site.</p>

        <div style="margin-bottom:16px">
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#444;margin-bottom:6px">Step 1 — Paste your SharePoint share link</label>
          <div style="display:flex;gap:8px">
            <input type="url" id="engam-ob-ms-url"
              style="flex:1;padding:10px 12px;border:1.5px solid #ccc;border-radius:4px;font-size:13px;box-sizing:border-box;outline:none;min-width:0"
              value="<?php echo esc_attr( $ms_file_url ); ?>"
              placeholder="https://equinenetwork.sharepoint.com/:x:/s/Home/...">
            <button type="button" id="engam-ob-load-tabs"
              style="white-space:nowrap;padding:0 14px;font-size:12px;font-weight:700;border:2px solid #111;background:#fff;cursor:pointer;border-radius:3px;flex-shrink:0">&#8635; Load Tabs</button>
          </div>
        </div>

        <div style="margin-bottom:16px;max-width:300px">
          <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#444;margin-bottom:6px">Step 2 — Select worksheet tab</label>
          <!-- Both share the logical name; only one is enabled at a time so the correct value posts natively. -->
          <select id="engam-ob-sheet-select"
            style="display:none;width:100%;padding:10px 12px;border:1.5px solid #ccc;border-radius:4px;font-size:13px;box-sizing:border-box;background:#fff" disabled>
            <option value="<?php echo esc_attr( $ms_sheet ); ?>"><?php echo esc_html( $ms_sheet ); ?></option>
          </select>
          <input type="text" id="engam-ob-sheet-text"
            style="width:100%;padding:10px 12px;border:1.5px solid #ccc;border-radius:4px;font-size:13px;box-sizing:border-box;outline:none"
            value="<?php echo esc_attr( $ms_sheet ); ?>"
            placeholder="e.g. EQUUS">
          <p id="engam-ob-tab-hint" style="margin:6px 0 0;font-size:12px;color:#888">Click &ldquo;Load Tabs&rdquo; above to populate this dropdown.</p>
        </div>

        <button type="button" id="engam-ob-test-ms"
          style="background:#fff;color:#111111;border:2px solid #111;padding:9px 18px;font-size:12px;font-weight:700;cursor:pointer;border-radius:3px">Test Connection</button>
        <div id="engam-ob-step3-msg" style="display:none;margin-top:12px;padding:10px 14px;border-radius:4px;font-size:13px;font-weight:600"></div>
      </div>

      <!-- Step 4: ACF Migration -->
      <div class="engam-ob-step" data-step="4" style="display:none">
        <h3 style="margin:0 0 6px;font-size:18px;font-weight:700;color:#111111">Migrate Sponsor IDs from ACF</h3>
        <?php if ( $acf_count > 0 ) : ?>
        <p style="margin:0 0 16px;font-size:13px;color:#555;line-height:1.55">
          Found <strong><?php echo (int) $acf_count; ?> item(s)</strong> with legacy ACF sponsor IDs (<code style="background:#f5f5f0;padding:1px 4px;border-radius:2px">sponlineitemid</code> / <code style="background:#f5f5f0;padding:1px 4px;border-radius:2px">sponsorship_id</code>).
          Copy them into this plugin now so they keep working after you remove those ACF fields. Existing plugin assignments are never overwritten.
        </p>
        <button type="button" id="engam-ob-run-migrate"
          style="background:#111111;color:#C8FF00;border:none;padding:10px 22px;font-size:13px;font-weight:700;cursor:pointer;border-radius:3px">Run Migration Now</button>
        <?php else : ?>
        <div style="background:#f7f7f4;border:1px solid #deded8;border-left:4px solid #111111;padding:14px 16px;border-radius:4px;display:flex;align-items:center;gap:10px;margin-bottom:8px">
          <span style="width:28px;height:28px;background:#111111;display:flex;align-items:center;justify-content:center;flex-shrink:0;border-radius:2px"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="#C8FF00" stroke-width="3" stroke-linecap="square"/></svg></span>
          <span style="font-size:13px;font-weight:600;color:#111111">No ACF sponsor IDs found — nothing to migrate. You're all set!</span>
        </div>
        <?php endif; ?>
        <div id="engam-ob-step4-msg" style="display:none;margin-top:12px;padding:10px 14px;border-radius:4px;font-size:13px;font-weight:600"></div>
      </div>

    </div><!-- #engam-ob-body -->

    <!-- Footer navigation -->
    <div style="padding:14px 24px;border-top:1px solid #eee;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;background:#fafaf8">
      <button type="button" id="engam-ob-prev" style="background:#fff;border:2px solid #ccc;color:#555;padding:8px 16px;font-size:13px;font-weight:600;cursor:pointer;border-radius:3px;display:none">&#8592; Back</button>
      <span id="engam-ob-prev-spacer"></span>
      <div style="display:flex;gap:10px;align-items:center">
        <button type="button" id="engam-ob-skip" style="background:none;border:none;color:#999;padding:8px 4px;font-size:13px;cursor:pointer;text-decoration:underline;text-underline-offset:2px">Skip this step</button>
        <button type="button" id="engam-ob-next" style="background:#111111;color:#C8FF00;border:none;padding:10px 22px;font-size:13px;font-weight:700;cursor:pointer;border-radius:3px">Continue &#8594;</button>
      </div>
    </div>

  </div>
</div>

<script>
(function(){
    var OB_NONCE   = '<?php echo esc_js( $ob_nonce ); ?>';
    var AJAX_NONCE = '<?php echo esc_js( $ajax_nonce ); ?>';
    var ACF_COUNT  = <?php echo (int) $acf_count; ?>;

    var overlay  = document.getElementById('engam-ob-overlay');
    var closeBtn = document.getElementById('engam-ob-close');
    var prevBtn  = document.getElementById('engam-ob-prev');
    var nextBtn  = document.getElementById('engam-ob-next');
    var skipBtn  = document.getElementById('engam-ob-skip');
    var stepsNav = document.getElementById('engam-ob-steps-nav');

    var currentStep  = 1;
    var totalSteps   = 4;
    var pendingCreds = null; // parsed JSON from uploaded file, not yet saved

    var STEP_LABELS = ['GAM Settings', 'GAM API', 'Spreadsheet', 'Migrate IDs'];

    // ── Utilities ────────────────────────────────────────────────────────

    function ajaxPost(action, nonce, params, cb) {
        var body = 'action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(nonce);
        if (params) {
            Object.keys(params).forEach(function(k) {
                body += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
            });
        }
        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body,
        }).then(function(r){ return r.json(); }).then(cb).catch(function(){ cb(null); });
    }

    function showMsg(id, success, text) {
        var el = document.getElementById(id);
        if (!el) return;
        el.style.display    = 'block';
        el.style.background = success ? '#f7f7f4' : '#fde8e8';
        el.style.borderLeft = success ? '4px solid #111111' : '4px solid #cc0000';
        el.style.color      = success ? '#111' : '#9b1c1c';
        el.textContent      = text;
    }

    function clearMsg(id) {
        var el = document.getElementById(id);
        if (el) el.style.display = 'none';
    }

    function setBusy(btn, label) {
        btn.disabled = true; btn.textContent = label;
    }

    function setIdle(btn, label) {
        btn.disabled = false; btn.textContent = label;
    }

    // ── Step navigation ───────────────────────────────────────────────────

    function buildStepsNav() {
        stepsNav.innerHTML = '';
        STEP_LABELS.forEach(function(label, i) {
            var n = i + 1;
            var wrap = document.createElement('div');
            wrap.style.cssText = 'display:flex;align-items:center;gap:6px;flex:' + (n < totalSteps ? '1' : '0');

            var dot = document.createElement('div');
            dot.id = 'engam-ob-dot-' + n;
            dot.style.cssText = 'width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;transition:background .2s,color .2s';
            dot.textContent = n;

            var lbl = document.createElement('div');
            lbl.id = 'engam-ob-lbl-' + n;
            lbl.style.cssText = 'font-size:11px;font-weight:600;white-space:nowrap;transition:color .2s';
            lbl.textContent = label;

            wrap.appendChild(dot);
            wrap.appendChild(lbl);

            if (n < totalSteps) {
                var line = document.createElement('div');
                line.style.cssText = 'flex:1;height:1px;background:#e0e0da;margin:0 6px';
                wrap.appendChild(line);
            }

            stepsNav.appendChild(wrap);
        });
    }

    function updateNav() {
        STEP_LABELS.forEach(function(_, i) {
            var n = i + 1;
            var dot = document.getElementById('engam-ob-dot-' + n);
            var lbl = document.getElementById('engam-ob-lbl-' + n);
            if (!dot || !lbl) return;
            if (n < currentStep) {
                dot.style.background = '#111111'; dot.style.color = '#C8FF00'; dot.textContent = '✓';
                lbl.style.color = '#555';
            } else if (n === currentStep) {
                dot.style.background = '#C8FF00'; dot.style.color = '#111111'; dot.textContent = n;
                lbl.style.color = '#111111';
            } else {
                dot.style.background = '#e0e0da'; dot.style.color = '#aaa'; dot.textContent = n;
                lbl.style.color = '#aaa';
            }
        });

        prevBtn.style.display = currentStep > 1 ? 'inline-block' : 'none';
        document.getElementById('engam-ob-prev-spacer').style.display = currentStep > 1 ? 'none' : 'inline-block';

        skipBtn.style.display = 'inline-block';

        if (currentStep === totalSteps) {
            nextBtn.textContent = ACF_COUNT > 0 ? 'Finish Setup ✓' : 'Done ✓';
            skipBtn.textContent = 'Skip';
        } else {
            nextBtn.textContent = 'Continue →';
            skipBtn.textContent = 'Skip this step';
        }
    }

    function showStep(n) {
        if (n < 1 || n > totalSteps) return;
        currentStep = n;

        document.querySelectorAll('.engam-ob-step').forEach(function(el) {
            el.style.display = parseInt(el.dataset.step, 10) === n ? 'block' : 'none';
        });

        updateNav();

        // Auto-load tabs when entering step 3 and a URL is already saved.
        if (n === 3) {
            var urlEl = document.getElementById('engam-ob-ms-url');
            if (urlEl && urlEl.value.trim()) obLoadTabs(false);
        }
    }

    // ── Continue / Next logic ─────────────────────────────────────────────

    nextBtn.addEventListener('click', function() {
        if (currentStep === 1) doStep1();
        else if (currentStep === 2) doStep2();
        else if (currentStep === 3) doStep3();
        else doFinish();
    });

    skipBtn.addEventListener('click', function() {
        if (currentStep < totalSteps) showStep(currentStep + 1);
        else doFinish();
    });

    prevBtn.addEventListener('click', function() {
        showStep(currentStep - 1);
    });

    closeBtn.addEventListener('click', doDismiss);

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) doDismiss();
    });

    // Step 1 — GAM Network ID
    function doStep1() {
        var val = document.getElementById('engam-ob-gam-id').value.trim();
        if (!val) { showStep(2); return; }
        setBusy(nextBtn, 'Saving…');
        ajaxPost('engam_v2_onboarding_save_gam', OB_NONCE, {engam_gam_id: val}, function(r) {
            setIdle(nextBtn, 'Continue →');
            if (r && r.success) { clearMsg('engam-ob-step1-msg'); showStep(2); }
            else showMsg('engam-ob-step1-msg', false, (r && r.data) || 'Save failed — please try again.');
        });
    }

    // Step 2 — Credentials
    function doStep2() {
        if (!pendingCreds) { showStep(3); return; }
        setBusy(nextBtn, 'Saving…');
        ajaxPost('engam_v2_save_credentials', AJAX_NONCE, {credentials: JSON.stringify(pendingCreds)}, function(r) {
            setIdle(nextBtn, 'Continue →');
            var msg = (r && r.data && r.data.message) || (r && r.message) || '';
            if (r && r.success) { clearMsg('engam-ob-step2-msg'); showStep(3); }
            else showMsg('engam-ob-step2-msg', false, msg || 'Save failed — check the JSON file and try again.');
        });
    }

    // Step 3 — SharePoint
    function doStep3() {
        var urlEl    = document.getElementById('engam-ob-ms-url');
        var selEl    = document.getElementById('engam-ob-sheet-select');
        var txtEl    = document.getElementById('engam-ob-sheet-text');
        var url      = urlEl ? urlEl.value.trim() : '';
        var sheet    = (selEl && !selEl.disabled && selEl.value) || (txtEl && txtEl.value.trim()) || '';
        if (!url) { showStep(4); return; }
        setBusy(nextBtn, 'Saving…');
        ajaxPost('engam_v2_onboarding_save_ms', OB_NONCE, {ms_url: url, ms_sheet: sheet}, function(r) {
            setIdle(nextBtn, 'Continue →');
            if (r && r.success) { clearMsg('engam-ob-step3-msg'); showStep(4); }
            else showMsg('engam-ob-step3-msg', false, (r && r.data) || 'Save failed — please try again.');
        });
    }

    // Step 4 — Finish
    function doFinish() {
        setBusy(nextBtn, 'Closing…');
        ajaxPost('engam_v2_onboarding_dismiss', OB_NONCE, {}, function() {
            overlay.style.display = 'none';
            window.location.reload();
        });
    }

    function doDismiss() {
        overlay.style.display = 'none';
        ajaxPost('engam_v2_onboarding_dismiss', OB_NONCE, {}, function() {});
    }

    // ── Test Connection buttons ───────────────────────────────────────────

    var testApiBtn = document.getElementById('engam-ob-test-api');
    if (testApiBtn) {
        testApiBtn.addEventListener('click', function() {
            setBusy(testApiBtn, 'Testing…');
            // If a file is pending, save it first so we're testing the uploaded key.
            var doTest = function() {
                ajaxPost('engam_v2_test_connection', AJAX_NONCE, {}, function(r) {
                    setIdle(testApiBtn, 'Test Connection');
                    var msg = (r && r.message) || (r && r.data && r.data.message) || (r && r.data) || 'No response.';
                    showMsg('engam-ob-step2-msg', !!(r && r.success), msg);
                });
            };
            if (pendingCreds) {
                ajaxPost('engam_v2_save_credentials', AJAX_NONCE, {credentials: JSON.stringify(pendingCreds)}, function(sr) {
                    if (sr && sr.success) { pendingCreds = null; }
                    doTest();
                });
            } else {
                doTest();
            }
        });
    }

    var testMsBtn = document.getElementById('engam-ob-test-ms');
    if (testMsBtn) {
        testMsBtn.addEventListener('click', function() {
            setBusy(testMsBtn, 'Testing…');
            ajaxPost('engam_v2_test_ms', OB_NONCE, {}, function(r) {
                setIdle(testMsBtn, 'Test Connection');
                showMsg('engam-ob-step3-msg', !!(r && r.success), (r && r.data) || 'No response.');
                if (r && r.success) obLoadTabs(true);
            });
        });
    }

    // ── ACF Migration (Step 4) ────────────────────────────────────────────

    var runMigrateBtn = document.getElementById('engam-ob-run-migrate');
    if (runMigrateBtn) {
        runMigrateBtn.addEventListener('click', function() {
            if (!confirm('Copy ACF sponsor IDs to the plugin for every post/term that does not already have one? This is safe to re-run.')) return;
            setBusy(runMigrateBtn, 'Migrating…');
            ajaxPost('engam_v2_onboarding_migrate', OB_NONCE, {}, function(r) {
                setIdle(runMigrateBtn, 'Run Migration Now');
                if (r && r.success) {
                    showMsg('engam-ob-step4-msg', true, r.data || 'Migration complete.');
                    runMigrateBtn.style.display = 'none';
                    nextBtn.textContent = 'Done ✓';
                } else {
                    showMsg('engam-ob-step4-msg', false, (r && r.data) || 'Migration failed.');
                }
            });
        });
    }

    // ── Tab picker (Step 3) ───────────────────────────────────────────────

    var obTabSelect = document.getElementById('engam-ob-sheet-select');
    var obTabText   = document.getElementById('engam-ob-sheet-text');
    var obTabHint   = document.getElementById('engam-ob-tab-hint');
    var obLoadBtn   = document.getElementById('engam-ob-load-tabs');

    function obFillTabs(tabs, current) {
        if (!obTabSelect) return;
        obTabSelect.innerHTML = '';
        tabs.forEach(function(name) {
            var opt = document.createElement('option');
            opt.value = name; opt.textContent = name;
            if (name === current) opt.selected = true;
            obTabSelect.appendChild(opt);
        });
        if (current && tabs.indexOf(current) === -1) {
            var opt = document.createElement('option');
            opt.value = current; opt.textContent = current; opt.selected = true;
            obTabSelect.insertBefore(opt, obTabSelect.firstChild);
        }
        if (current) obTabSelect.value = current;
        obTabSelect.disabled = false;
        obTabSelect.style.display = 'block';
        if (obTabText) { obTabText.disabled = true; obTabText.style.display = 'none'; }
        if (obTabHint) obTabHint.textContent = 'Pick from your ' + tabs.length + ' tab(s).';
    }

    function obLoadTabs(force) {
        var urlEl = document.getElementById('engam-ob-ms-url');
        var urlVal = urlEl ? urlEl.value.trim() : '';
        if (!urlVal) return;
        if (obLoadBtn) { obLoadBtn.textContent = '↻ Loading…'; obLoadBtn.disabled = true; }
        var params = {url: urlVal};
        if (force) params.force = '1';
        ajaxPost('engam_v2_ms_tabs', OB_NONCE, params, function(data) {
            if (obLoadBtn) { obLoadBtn.textContent = '↻ Load Tabs'; obLoadBtn.disabled = false; }
            if (data && data.success && data.data && data.data.tabs && data.data.tabs.length) {
                var preferred = (obTabSelect && !obTabSelect.disabled && obTabSelect.value)
                             || (obTabText && obTabText.value)
                             || data.data.current || '';
                obFillTabs(data.data.tabs, preferred);
            }
        });
    }

    if (obLoadBtn) obLoadBtn.addEventListener('click', function() { obLoadTabs(true); });

    // ── File upload handling (Step 2) ─────────────────────────────────────

    window.engamObHandleDrop = function(e) {
        e.preventDefault();
        document.getElementById('engam-ob-upload-area').style.borderColor = '#ccc';
        var files = e.dataTransfer ? e.dataTransfer.files : null;
        if (files && files[0]) engamObReadFile(files[0]);
    };

    document.getElementById('engam-ob-creds-file').addEventListener('change', function() {
        if (this.files[0]) engamObReadFile(this.files[0]);
    });

    function engamObReadFile(file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            try {
                var json = JSON.parse(e.target.result);
                if (!json.client_email || !json.private_key) {
                    showMsg('engam-ob-step2-msg', false, 'Invalid JSON — missing client_email or private_key.');
                    return;
                }
                pendingCreds = json;
                var lbl = document.getElementById('engam-ob-upload-label');
                if (lbl) lbl.innerHTML = '<strong style="font-size:12px;display:block;color:#111111">' + file.name + '</strong>'
                    + '<span style="font-size:11px;color:#0a6e0a">Ready — click Continue to save</span>';
                clearMsg('engam-ob-step2-msg');
            } catch(ex) {
                showMsg('engam-ob-step2-msg', false, 'Could not parse the file as JSON. Make sure it\'s a service account key file.');
            }
        };
        reader.readAsText(file);
    }

    // ── Public API (called by "Setup Wizard" button in settings) ─────────

    window.engamOpenWizard = function(startStep) {
        overlay.style.display = 'flex';
        showStep(startStep || 1);
    };

    // ── Init ──────────────────────────────────────────────────────────────

    buildStepsNav();
    showStep(1);

})();
</script>
