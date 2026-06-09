<?php if ( ! defined( 'WPINC' ) ) die;

include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-shared-styles.php';
?>
<div id="engam-v2-wrap">

<section class="eg-mast">
    <div class="eg-brand">
        <div class="eg-logo">EN</div>
        <div class="eg-brand-text">
            <small>Google Ad Manager &mdash; v2</small>
            <h1>Support</h1>
        </div>
    </div>
    <div class="eg-mast-actions">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=equinenetwork-gam-v2' ) ); ?>" class="eg-btn ghost">Dashboard</a>
    </div>
</section>

<div class="eg-content">

<!-- QUICK START GUIDE -->
<div class="eg-card black eg-full-bleed" style="margin-top:18px">
    <div class="eg-head" style="border-color:#2a2a2a">
        <div>
            <h2 style="color:#fff">Quick Start Guide</h2>
            <p>How the plugin fits together, start to finish.</p>
        </div>
        <span class="eg-tag">Guide</span>
    </div>
    <div class="eg-body" style="font-size:13px;line-height:1.6;color:#d8d8d2;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:0 48px">
        <p style="margin:0 0 12px"><strong style="color:#C8FF00">1. GAM API</strong> syncs active line items automatically — no manual list to maintain.</p>
        <p style="margin:0 0 12px"><strong style="color:#C8FF00">2. On any post or page</strong>, use the <strong style="color:#fff">EN Campaign</strong> sidebar panel to assign a sponsor ID that overrides all ads on that page.</p>
        <p style="margin:0 0 12px"><strong style="color:#C8FF00">3. In Elementor</strong>, drop the <strong style="color:#fff">EN Ad Slot</strong> widget and pick a preset — the sponsor dropdown pulls from GAM live.</p>
        <p style="margin:0 0 12px"><strong style="color:#C8FF00">4. Takeovers</strong> wrap the entire page — set a date range and upload brand images to run a full-site takeover.</p>
        <p style="margin:0 0 12px"><strong style="color:#C8FF00">5. GAM handles</strong> creative scheduling, fallbacks, and targeting automatically.</p>
    </div>
    <div class="eg-accentline"></div>
</div>

<!-- WHERE TO GO -->
<div class="eg-card" style="margin-top:18px">
    <div class="eg-head">
        <div>
            <h2>Where to go</h2>
            <p>Jump straight to the area you need.</p>
        </div>
    </div>
    <div class="eg-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-settings' ) ); ?>" style="text-decoration:none;color:inherit">
            <div class="eg-stat" style="height:100%"><small>Connect GAM &amp; Sheets</small><strong style="font-size:18px;margin-top:6px">Settings &rarr;</strong></div>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-campaigns' ) ); ?>" style="text-decoration:none;color:inherit">
            <div class="eg-stat" style="height:100%"><small>Manage advertisers</small><strong style="font-size:18px;margin-top:6px">Sponsor ID's &rarr;</strong></div>
        </a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-takeovers' ) ); ?>" style="text-decoration:none;color:inherit">
            <div class="eg-stat" style="height:100%"><small>Full-site brand wraps</small><strong style="font-size:18px;margin-top:6px">Takeovers &rarr;</strong></div>
        </a>
    </div>
</div>

<!-- NEED MORE HELP -->
<div class="eg-card" style="margin-top:18px">
    <div class="eg-head">
        <div>
            <h2>Need more help?</h2>
            <p>Reach the Equine Network ad-ops team.</p>
        </div>
    </div>
    <div class="eg-body" style="font-size:13px;color:#444;line-height:1.6">
        <p style="margin:0 0 10px">For setup questions, GAM line-item issues, or feature requests, contact the ad-ops team at
            <a href="mailto:adops@equinenetwork.com" style="color:#5a7a00;font-weight:600">adops@equinenetwork.com</a>.</p>
        <p style="margin:0;color:#888">Plugin version <?php echo esc_html( defined( 'EQUINENETWORK_GAM_V2_VERSION' ) ? EQUINENETWORK_GAM_V2_VERSION : '' ); ?></p>
    </div>
</div>

</div><!-- .eg-content -->
</div><!-- #engam-v2-wrap -->
