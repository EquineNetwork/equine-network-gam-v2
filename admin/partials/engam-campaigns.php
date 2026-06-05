<?php if ( ! defined( 'WPINC' ) ) die;

$api          = new Equinenetwork_Gam_V2_API();
$sheet_url    = get_option( 'engam_v2_sheet_csv_url', '' );
$ms_file_url  = get_option( 'engam_v2_ms_file_url', '' );
$is_connected = $sheet_url || $ms_file_url;
$sponsors     = $api->get_sponsor_options();
$active_count = count( $sponsors );

include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-shared-styles.php';
?>
<div id="engam-v2-wrap">

<section class="eg-mast">
    <div class="eg-brand">
        <div class="eg-logo">EN</div>
        <div class="eg-brand-text">
            <small>Google Ad Manager &mdash; v2</small>
            <h1>Sponsor ID's</h1>
        </div>
    </div>
    <div class="eg-mast-actions">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=equinenetwork-gam-v2' ) ); ?>" class="eg-btn ghost">Dashboard</a>
    </div>
</section>

<div class="eg-content">

<div class="eg-card" style="margin-top:18px">
    <div class="eg-head">
        <div>
            <h2>Sponsor ID's</h2>
            <p>Pulled live from your connected spreadsheet. Only rows marked <strong>Active</strong> appear here and in the "Lock to Sponsor" dropdowns.</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
            <span class="eg-tag"><?php echo esc_html( $active_count ); ?> Active</span>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-settings' ) ); ?>" class="eg-btn dark" style="border-color:#111">Manage Sheet</a>
        </div>
    </div>

    <?php if ( ! $is_connected ) : ?>
        <div class="eg-empty">
            <strong>No spreadsheet connected.</strong>
            Go to <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-settings' ) ); ?>">Settings</a> and connect a SharePoint sheet or paste a published CSV URL to populate this list.
        </div>
    <?php elseif ( empty( $sponsors ) ) : ?>
        <div class="eg-empty">
            <strong>No active sponsors found.</strong>
            Make sure your sheet has a <em>Status</em> column with rows marked <strong>Active</strong>, then click "Test &amp; Refresh" on the Settings page.
        </div>
    <?php else : ?>
        <table class="eg-table">
            <thead>
                <tr>
                    <th>Advertiser</th>
                    <th>Sponsor ID</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $sponsors as $s ) : ?>
                <tr>
                    <td><?php echo esc_html( $s['name'] ); ?></td>
                    <td style="font-family:monospace;font-size:12px;color:#555"><?php echo esc_html( $s['id'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="eg-accentline"></div>
</div>

</div><!-- .eg-content -->
</div><!-- #engam-v2-wrap -->
