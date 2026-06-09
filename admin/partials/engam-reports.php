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
        <?php if ( $api_configured ) : ?>
        <button type="button" class="eg-btn ghost engam-reports-refresh">Refresh</button>
        <?php endif; ?>
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
            The report runs alongside the GAM line-item sync, and refreshes automatically about every 45 minutes. Pull it now:
            <div style="margin-top:16px">
                <button type="button" class="eg-btn engam-reports-refresh">Refresh Cache</button>
                <div class="engam-reports-refresh-msg eg-hint" style="margin-top:12px;min-height:1em"></div>
            </div>
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
            <table id="engam-imp-table" class="eg-table eg-table-card">
                <thead>
                    <tr>
                        <th>Line Item</th>
                        <th class="engam-sort" data-key="status">Status <span class="engam-sort-ind" aria-hidden="true"></span></th>
                        <th class="engam-sort" data-key="imp" data-num="1" data-dir="desc" style="text-align:right">Impressions <span class="engam-sort-ind" aria-hidden="true">&darr;</span></th>
                        <th>GAM</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $r ) :
                        list( $badge_class, $badge_label ) = engam_imp_badge( $r['status'] ?? '' );
                        $gid = (string) ( $r['gam_id'] ?? '' );
                    ?>
                    <tr data-status="<?php echo esc_attr( $badge_label ); ?>" data-imp="<?php echo (int) ( $r['impressions'] ?? 0 ); ?>">
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

<?php if ( $api_configured ) : ?>
<script>
(function(){
    var NONCE = '<?php echo esc_js( wp_create_nonce( 'engam_v2_ajax' ) ); ?>';
    var btns  = document.querySelectorAll('.engam-reports-refresh');
    var msg   = document.querySelector('.engam-reports-refresh-msg');
    if ( ! btns.length ) return;

    function setMsg( color, text ) { if ( msg ) { msg.style.color = color; msg.textContent = text; } }

    btns.forEach(function(btn){
        var orig = btn.textContent;
        btn.addEventListener('click', function(){
            btns.forEach(function(b){ b.disabled = true; });
            btn.textContent = 'Refreshing… (~20s)';
            setMsg('#888', 'Pulling line items and impressions from GAM…');
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=engam_v2_refresh_cache&nonce=' + encodeURIComponent(NONCE)
            })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if ( res && res.success ) {
                    setMsg('#3c5200', (res.message || 'Cache refreshed.') + ' Reloading…');
                    location.reload();
                } else {
                    btns.forEach(function(b){ b.disabled = false; });
                    btn.textContent = orig;
                    setMsg('#b02020', (res && res.message) ? res.message : 'Refresh failed — try again.');
                }
            })
            .catch(function(){
                btns.forEach(function(b){ b.disabled = false; });
                btn.textContent = orig;
                setMsg('#b02020', 'Network error — try again.');
            });
        });
    });
})();
</script>
<style>
#engam-imp-table th.engam-sort{cursor:pointer;user-select:none}
#engam-imp-table th.engam-sort:hover{color:#111}
#engam-imp-table th.engam-sort .engam-sort-ind{font-size:11px;color:#111;margin-left:2px}
</style>
<script>
(function(){
    var table = document.getElementById('engam-imp-table');
    if ( ! table ) return;
    var tbody = table.querySelector('tbody');
    var ths   = table.querySelectorAll('th.engam-sort');
    function clearInd(){ ths.forEach(function(t){ var s = t.querySelector('.engam-sort-ind'); if ( s ) s.textContent = ''; }); }
    ths.forEach(function(th){
        th.addEventListener('click', function(){
            var key = th.getAttribute('data-key');
            var num = th.getAttribute('data-num') === '1';
            var dir = th.getAttribute('data-dir') === 'asc' ? 'desc' : 'asc';
            th.setAttribute('data-dir', dir);
            var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
            rows.sort(function(a, b){
                var av = a.getAttribute('data-' + key) || '';
                var bv = b.getAttribute('data-' + key) || '';
                if ( num ) { av = parseFloat(av) || 0; bv = parseFloat(bv) || 0; return dir === 'asc' ? av - bv : bv - av; }
                return dir === 'asc' ? String(av).localeCompare(String(bv)) : String(bv).localeCompare(String(av));
            });
            rows.forEach(function(r){ tbody.appendChild(r); });
            clearInd();
            var ind = th.querySelector('.engam-sort-ind'); if ( ind ) ind.textContent = ( dir === 'asc' ? '↑' : '↓' );
        });
    });
})();
</script>
<?php endif; ?>
