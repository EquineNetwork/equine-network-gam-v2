<?php if ( ! defined( 'WPINC' ) ) die;

$notice = '';
if ( isset( $_POST['engam_v2_stacker_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['engam_v2_stacker_nonce'] ) ), 'engam_v2_stacker_save' ) ) {
    if ( current_user_can( 'edit_posts' ) ) {
        update_option( 'equinenetwork_gam_v2_stacker_enabled', isset( $_POST['equinenetwork_gam_v2_stacker_enabled'] ) ? '1' : '0' );
        update_option( 'equinenetwork_gam_v2_stacker_slotname', sanitize_text_field( wp_unslash( $_POST['equinenetwork_gam_v2_stacker_slotname'] ?? 'stacker' ) ) );
        update_option( 'equinenetwork_gam_v2_stacker_cats', sanitize_text_field( wp_unslash( $_POST['equinenetwork_gam_v2_stacker_cats'] ?? '' ) ) );
        update_option( 'equinenetwork_gam_v2_stacker_hide_cats', sanitize_text_field( wp_unslash( $_POST['equinenetwork_gam_v2_stacker_hide_cats'] ?? '' ) ) );
        update_option( 'equinenetwork_gam_v2_stacker_hide_ids', sanitize_text_field( wp_unslash( $_POST['equinenetwork_gam_v2_stacker_hide_ids'] ?? '' ) ) );
        update_option( 'equinenetwork_gam_v2_stacker_hide_sponsors', sanitize_text_field( wp_unslash( $_POST['equinenetwork_gam_v2_stacker_hide_sponsors'] ?? '' ) ) );
        $notice = 'Stacker settings saved.';
    }
}

$stacker_enabled  = get_option( 'equinenetwork_gam_v2_stacker_enabled', '0' ) === '1';
$stacker_slotname = get_option( 'equinenetwork_gam_v2_stacker_slotname', 'stacker' );
$stacker_cats     = get_option( 'equinenetwork_gam_v2_stacker_cats', '' );
$hide_cats        = get_option( 'equinenetwork_gam_v2_stacker_hide_cats', '' );
$hide_ids         = get_option( 'equinenetwork_gam_v2_stacker_hide_ids', '' );
$hide_sponsors    = get_option( 'equinenetwork_gam_v2_stacker_hide_sponsors', '' );

include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-shared-styles.php';
?>
<div id="engam-v2-wrap">

<!-- MASTHEAD -->
<section class="eg-mast">
    <div class="eg-brand">
        <div class="eg-logo">EN</div>
        <div class="eg-brand-text">
            <small>Google Ad Manager &mdash; v2</small>
            <h1>Stackers</h1>
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

<form method="post" action="">
<?php wp_nonce_field( 'engam_v2_stacker_save', 'engam_v2_stacker_nonce' ); ?>

<div class="eg-grid" style="grid-template-columns:1fr 1fr">

    <!-- STACKER CONFIG -->
    <div class="eg-card" style="margin-top:18px">
        <div class="eg-head">
            <div>
                <h2>Stacker Ad</h2>
                <p>GAM-targeted product stacker auto-injected at the end of posts.</p>
            </div>
            <span class="eg-tag" style="<?php echo $stacker_enabled ? '' : 'background:#111;color:#d0ff00;'; ?>"><?php echo $stacker_enabled ? 'On' : 'Off'; ?></span>
        </div>
        <div class="eg-body">
            <div class="eg-settings-field">
                <label class="eg-toggle">
                    <input type="checkbox" name="equinenetwork_gam_v2_stacker_enabled" value="1" <?php checked( $stacker_enabled ); ?>>
                    <span class="eg-toggle-track"><span class="eg-toggle-thumb"></span></span>
                    Auto-inject stacker at the end of posts
                </label>
                <p class="eg-hint">GAM decides which posts actually fill it based on content targeting — empty slots collapse automatically.</p>
            </div>
            <div class="eg-settings-field">
                <label for="engam-stacker-slot">Stacker Child Ad Unit</label>
                <input class="eg-input" type="text" name="equinenetwork_gam_v2_stacker_slotname" id="engam-stacker-slot"
                    value="<?php echo esc_attr( $stacker_slotname ); ?>" placeholder="stacker">
                <p class="eg-hint">Appended to your GAM Network ID, e.g. <code>/22345131513/sitename/<strong>stacker</strong></code>. Size is 320&times;480.</p>
            </div>
            <div class="eg-settings-field">
                <label for="engam-stacker-cats">Show Only on Categories (optional)</label>
                <input class="eg-input" type="text" name="equinenetwork_gam_v2_stacker_cats" id="engam-stacker-cats"
                    value="<?php echo esc_attr( $stacker_cats ); ?>" placeholder="e.g. horse-health, training">
                <p class="eg-hint">Comma-separated category slugs. Leave blank to show on all posts.</p>
            </div>
        </div>
    </div>

    <!-- EXCLUSION RULES -->
    <div class="eg-card" style="margin-top:18px">
        <div class="eg-head">
            <div>
                <h2>Hide Rules</h2>
                <p>Stop the stacker from showing in specific situations.</p>
            </div>
            <span class="eg-tag" style="background:#111;color:#d0ff00;">Exclude</span>
        </div>
        <div class="eg-body">
            <div class="eg-settings-field">
                <label for="engam-stacker-hide-cats">Hide on Categories</label>
                <input class="eg-input" type="text" name="equinenetwork_gam_v2_stacker_hide_cats" id="engam-stacker-hide-cats"
                    value="<?php echo esc_attr( $hide_cats ); ?>" placeholder="e.g. sponsored, partner-content">
                <p class="eg-hint">Comma-separated category slugs where the stacker should never appear.</p>
            </div>
            <div class="eg-settings-field">
                <label for="engam-stacker-hide-ids">Hide on Specific Posts / Pages</label>
                <input class="eg-input" type="text" name="equinenetwork_gam_v2_stacker_hide_ids" id="engam-stacker-hide-ids"
                    value="<?php echo esc_attr( $hide_ids ); ?>" placeholder="e.g. 12, 458, 902">
                <p class="eg-hint">Comma-separated post/page IDs to exclude.</p>
            </div>
            <div class="eg-settings-field">
                <label for="engam-stacker-hide-sponsors">Hide When Sponsor ID Is Active</label>
                <input class="eg-input" type="text" name="equinenetwork_gam_v2_stacker_hide_sponsors" id="engam-stacker-hide-sponsors"
                    value="<?php echo esc_attr( $hide_sponsors ); ?>" placeholder="e.g. CactusRopes_Horses, Equinety_Salute">
                <p class="eg-hint">If a post has one of these campaign overrides assigned (EN Campaign panel), the stacker is suppressed. Use <code>*</code> to hide the stacker on <strong>any</strong> page that has a sponsor override.</p>
            </div>
        </div>
    </div>

</div>

<div style="margin-top:18px">
    <button type="submit" class="eg-btn" style="padding:14px 32px;font-size:14px">Save Stacker Settings</button>
</div>

</form>

</div><!-- .eg-content -->
</div><!-- #engam-v2-wrap -->
