<?php if ( ! defined( 'WPINC' ) ) die;

// Handle add / delete manual sponsor entries.
if ( isset( $_POST['engam_v2_manual_nonce'] ) && wp_verify_nonce( $_POST['engam_v2_manual_nonce'], 'engam_v2_manual_sponsors' ) && current_user_can( 'manage_options' ) ) {

	$manual = get_option( 'engam_v2_manual_sponsors', array() );

	if ( isset( $_POST['engam_manual_add'] ) ) {
		$new_name = sanitize_text_field( $_POST['engam_manual_name'] ?? '' );
		$new_id   = sanitize_text_field( $_POST['engam_manual_id'] ?? '' );
		if ( $new_id !== '' ) {
			$existing_ids = array_column( $manual, 'id' );
			if ( ! in_array( $new_id, $existing_ids, true ) ) {
				$manual[] = array( 'name' => $new_name !== '' ? $new_name : $new_id, 'id' => $new_id );
				update_option( 'engam_v2_manual_sponsors', $manual );
			}
		}
	}

	if ( isset( $_POST['engam_manual_delete'] ) ) {
		$del_id = sanitize_text_field( $_POST['engam_manual_delete'] );
		$manual = array_values( array_filter( $manual, fn( $m ) => $m['id'] !== $del_id ) );
		update_option( 'engam_v2_manual_sponsors', $manual );
	}

	wp_redirect( admin_url( 'admin.php?page=engam-v2-campaigns' ) );
	exit;
}

$api          = new Equinenetwork_Gam_V2_API();
$sheet_url    = get_option( 'engam_v2_sheet_csv_url', '' );
$ms_file_url  = get_option( 'engam_v2_ms_file_url', '' );
$is_connected = $sheet_url || $ms_file_url;
$sponsors     = $api->get_sponsor_options();
$manual       = get_option( 'engam_v2_manual_sponsors', array() );
$manual_ids   = array_column( (array) $manual, 'id' );
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

    <?php if ( ! $is_connected && empty( $manual ) ) : ?>
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
                    <th style="width:60px"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $sponsors as $s ) :
                    $is_manual = in_array( $s['id'], $manual_ids, true );
                ?>
                <tr>
                    <td>
                        <?php echo esc_html( $s['name'] ); ?>
                        <?php if ( $is_manual ) : ?>
                            <span class="eg-manual-badge">manual</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="engam-spid"><?php echo esc_html( $s['id'] ); ?></span>
                        <button type="button" class="engam-copy-id" data-copy="<?php echo esc_attr( $s['id'] ); ?>" title="Copy Sponsor ID" aria-label="Copy Sponsor ID">
                            <svg class="engam-copy-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2" stroke="currentColor" stroke-width="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                            <span class="engam-copy-done" aria-hidden="true">Copied!</span>
                        </button>
                    </td>
                    <td>
                        <?php if ( $is_manual ) : ?>
                            <form method="post" style="margin:0">
                                <?php wp_nonce_field( 'engam_v2_manual_sponsors', 'engam_v2_manual_nonce' ); ?>
                                <input type="hidden" name="engam_manual_delete" value="<?php echo esc_attr( $s['id'] ); ?>">
                                <button type="submit" class="eg-delete-btn" title="Remove manual entry">&times;</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="eg-manual-add">
        <h3>Add Manual Entry</h3>
        <form method="post" class="eg-manual-form">
            <?php wp_nonce_field( 'engam_v2_manual_sponsors', 'engam_v2_manual_nonce' ); ?>
            <input type="text" name="engam_manual_name" placeholder="Advertiser name" class="eg-manual-input">
            <input type="text" name="engam_manual_id" placeholder="Sponsor ID (required)" class="eg-manual-input" required>
            <button type="submit" name="engam_manual_add" value="1" class="eg-btn primary">Add</button>
        </form>
    </div>

    <div class="eg-accentline"></div>
</div>

</div><!-- .eg-content -->
</div><!-- #engam-v2-wrap -->

<style>
.eg-manual-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .06em;
    background: #C8FF00;
    color: #111;
    padding: 1px 6px;
    border-radius: 4px;
    margin-left: 6px;
    vertical-align: middle;
}
.eg-delete-btn {
    background: none;
    border: none;
    color: #c0392b;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
    padding: 0 4px;
}
.eg-delete-btn:hover { color: #911; }
.eg-manual-add {
    padding: 18px 20px 6px;
    border-top: 1px solid #eee;
    margin-top: 4px;
}
.eg-manual-add h3 {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    margin: 0 0 10px;
    color: #444;
}
.eg-manual-form {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}
.eg-manual-input {
    padding: 7px 10px;
    font-size: 13px;
    border: 1px solid #bbb;
    border-radius: 6px;
    background: #fff;
    min-width: 200px;
}
.eg-manual-input:focus { border-color: #111; outline: none; }
.engam-spid { font-family: monospace; font-size: 12px; color: #555; }
.engam-copy-id {
    background: none; border: none; cursor: pointer; padding: 3px 6px; margin-left: 6px;
    color: #999; vertical-align: middle; border-radius: 4px; line-height: 0; transition: background .12s, color .12s;
}
.engam-copy-id:hover { color: #111; background: #F0F0F0; }
.engam-copy-id .engam-copy-done {
    display: none; font-family: 'IBM Plex Sans', Arial, sans-serif; font-size: 10px; font-weight: 600;
    color: #4a6600; text-transform: uppercase; letter-spacing: .04em; line-height: 1; vertical-align: middle;
}
.engam-copy-id.copied { background: #E8F5C8; }
.engam-copy-id.copied .engam-copy-icon { display: none; }
.engam-copy-id.copied .engam-copy-done { display: inline; }
</style>
<script>
(function(){
    document.querySelectorAll('.engam-copy-id').forEach(function(btn){
        btn.addEventListener('click', function(){
            var text = btn.getAttribute('data-copy') || '';
            function done(){
                btn.classList.add('copied');
                setTimeout(function(){ btn.classList.remove('copied'); }, 1200);
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done, done);
            } else {
                var ta = document.createElement('textarea');
                ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
                document.body.appendChild(ta); ta.focus(); ta.select();
                try { document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(ta); done();
            }
        });
    });
})();
</script>
