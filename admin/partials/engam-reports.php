<?php if ( ! defined( 'WPINC' ) ) die;

require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-api.php';
$api            = new Equinenetwork_Gam_V2_API();
$api_configured = $api->is_configured();
$report         = $api_configured ? $api->get_impressions_report() : null;

$net_code = preg_replace( '/[^0-9]/', '', get_option( 'equinenetwork_gam_v2_id', '' ) );

// Map a GAM computed-status string onto one of the shared badge styles.
function engam_imp_badge( $status ) {
    $s = strtoupper( (string) $status );
    if ( strpos( $s, 'DELIVER' ) !== false || strpos( $s, 'READY' ) !== false ) return array( 'active', $status ?: 'Active' );
    if ( strpos( $s, 'PAUSED' ) !== false )  return array( 'scheduled', $status ?: 'Paused' );
    if ( strpos( $s, 'COMPLETE' ) !== false || strpos( $s, 'ARCHIV' ) !== false || strpos( $s, 'INACTIVE' ) !== false ) return array( 'expired', $status ?: 'Completed' );
    return array( 'inactive', $status !== '' ? $status : '—' );
}

$rows  = $report ? $report['rows'] : array();
$total = $report ? (int) $report['total'] : 0;

include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-shared-styles.php';
?>
<div id="engam-v2-wrap">

<section class="eg-mast">
    <div class="eg-brand">
        <div class="eg-logo">EN</div>
        <div class="eg-brand-text">
            <small>Google Ad Manager &mdash; v2</small>
            <h1>Reports</h1>
        </div>
    </div>
    <div class="eg-mast-actions">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=equinenetwork-gam-v2' ) ); ?>" class="eg-btn ghost">Dashboard</a>
    </div>
</section>

<div class="eg-content">

<?php if ( ! $api_configured ) : ?>

    <div class="eg-card" style="margin-top:18px">
        <div class="eg-body eg-empty">
            <strong>GAM API not connected</strong>
            Connect your service account in <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-settings' ) ); ?>">Settings</a> to pull impressions from Google Ad Manager.
        </div>
    </div>

<?php elseif ( ! $report ) : ?>

    <div class="eg-card" style="margin-top:18px">
        <div class="eg-body eg-empty">
            <strong>No impressions data yet</strong>
            The report runs alongside the GAM line-item sync. Open <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-settings' ) ); ?>">Settings</a> and click <strong>Refresh Cache</strong> to pull it now.
        </div>
    </div>

<?php else : ?>

    <!-- TOTAL -->
    <section class="eg-stats" style="margin-top:18px">
        <div class="eg-stat">
            <small>Total Impressions (Last 90 Days)</small>
            <strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong>
            <span class="eg-ok">Live from GAM API</span>
        </div>
        <div class="eg-stat">
            <small>Line Items Delivered</small>
            <strong><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?></strong>
        </div>
        <div class="eg-stat">
            <small>Last Updated</small>
            <strong style="font-size:18px;letter-spacing:0"><?php echo esc_html( date_i18n( 'M j, Y', (int) $report['updated'] ) ); ?></strong>
            <span class="eg-na"><?php echo esc_html( date_i18n( 'g:i a', (int) $report['updated'] ) ); ?></span>
        </div>
    </section>

    <!-- LIST -->
    <div class="eg-card" style="margin-bottom:18px">
        <div class="eg-head">
            <div>
                <h2>Impressions by Line Item</h2>
                <p>Ad-server impressions served on this site&rsquo;s ad units over the last 90 days, highest first.</p>
            </div>
        </div>

        <?php if ( empty( $rows ) ) : ?>
            <div class="eg-empty">
                <strong>No delivery in this window</strong>
                No line items recorded impressions on this site&rsquo;s ad units in the last 90 days.
            </div>
        <?php else : ?>
            <table class="eg-table eg-table-card">
                <thead>
                    <tr>
                        <th>Line Item</th>
                        <th>Status</th>
                        <th style="text-align:right">Impressions</th>
                        <th>GAM</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $r ) :
                        list( $badge_class, $badge_label ) = engam_imp_badge( $r['status'] ?? '' );
                        $gid = (string) ( $r['gam_id'] ?? '' );
                    ?>
                    <tr>
                        <td data-label="Line Item">
                            <div class="eg-campaign-name"><?php echo esc_html( $r['name'] ?? '(unnamed)' ); ?></div>
                            <div class="eg-campaign-id"><?php echo esc_html( $gid ); ?></div>
                        </td>
                        <td data-label="Status"><span class="eg-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_label ); ?></span></td>
                        <td data-label="Impressions" style="text-align:right;font-family:'IBM Plex Mono',Consolas,monospace;font-weight:600;font-size:14px"><?php echo esc_html( number_format_i18n( (int) ( $r['impressions'] ?? 0 ) ) ); ?></td>
                        <td data-label="GAM">
                            <?php if ( $gid && $net_code ) : ?>
                            <a href="https://admanager.google.com/<?php echo esc_attr( $net_code ); ?>#delivery/line_item/detail/line_item_id=<?php echo rawurlencode( $gid ); ?>"
                               target="_blank" rel="noopener" class="eg-btn sm">View in GAM &uarr;</a>
                            <?php else : ?>
                            <span style="color:#bbb;font-size:12px">&mdash;</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <div class="eg-accentline"></div>
    </div>

<?php endif; ?>

</div><!-- .eg-content -->
</div><!-- #engam-v2-wrap -->
