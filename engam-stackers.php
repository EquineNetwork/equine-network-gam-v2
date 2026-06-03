<?php if ( ! defined( 'WPINC' ) ) die;

$stackers = get_option( 'engam_v2_stackers_list', array() );
if ( ! is_array( $stackers ) ) $stackers = array();

$notice  = '';
$edit_id = isset( $_GET['edit_stacker'] ) ? sanitize_text_field( wp_unslash( $_GET['edit_stacker'] ) ) : '';

// ---- Handle POST ----
if ( isset( $_POST['engam_v2_stacker_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['engam_v2_stacker_nonce'] ) ), 'engam_v2_stacker_save' ) ) {
    if ( ! current_user_can( 'edit_posts' ) ) wp_die( -1 );

    $action = isset( $_POST['engam_stacker_action'] ) ? sanitize_text_field( wp_unslash( $_POST['engam_stacker_action'] ) ) : '';

    if ( 'save' === $action ) {
        $s_id   = isset( $_POST['engam_stacker_id'] ) ? sanitize_text_field( wp_unslash( $_POST['engam_stacker_id'] ) ) : '';
        $is_new = empty( $s_id );
        if ( $is_new ) $s_id = uniqid( 'stk_' );

        $record = array(
            'id'              => $s_id,
            'name'            => sanitize_text_field( wp_unslash( $_POST['engam_stacker_name'] ?? '' ) ),
            'slotname'        => sanitize_text_field( wp_unslash( $_POST['engam_stacker_slotname'] ?? 'stacker' ) ) ?: 'stacker',
            'after_paragraph' => max( 1, absint( $_POST['engam_stacker_after_paragraph'] ?? 5 ) ),
            'cats'            => sanitize_text_field( wp_unslash( $_POST['engam_stacker_cats'] ?? '' ) ),
            'hide_cats'       => sanitize_text_field( wp_unslash( $_POST['engam_stacker_hide_cats'] ?? '' ) ),
            'hide_ids'        => sanitize_text_field( wp_unslash( $_POST['engam_stacker_hide_ids'] ?? '' ) ),
            'hide_sponsors'   => sanitize_text_field( wp_unslash( $_POST['engam_stacker_hide_sponsors'] ?? '' ) ),
            'active'          => isset( $_POST['engam_stacker_active'] ),
        );

        if ( $is_new ) {
            $stackers[] = $record;
            $notice = 'Stacker "' . esc_html( $record['name'] ) . '" created.';
        } else {
            foreach ( $stackers as &$s ) {
                if ( $s['id'] === $s_id ) { $s = $record; break; }
            }
            unset( $s );
            $notice = 'Stacker "' . esc_html( $record['name'] ) . '" updated.';
        }
        update_option( 'engam_v2_stackers_list', $stackers );
        $edit_id = '';
    }

    if ( 'toggle' === $action && isset( $_POST['engam_stacker_id'] ) ) {
        $tid = sanitize_text_field( wp_unslash( $_POST['engam_stacker_id'] ) );
        foreach ( $stackers as &$s ) {
            if ( $s['id'] === $tid ) { $s['active'] = empty( $s['active'] ); break; }
        }
        unset( $s );
        update_option( 'engam_v2_stackers_list', $stackers );
        $notice = 'Stacker updated.';
    }

    if ( 'delete' === $action && isset( $_POST['engam_stacker_id'] ) ) {
        $tid      = sanitize_text_field( wp_unslash( $_POST['engam_stacker_id'] ) );
        $stackers = array_values( array_filter( $stackers, function( $s ) use ( $tid ) {
            return $s['id'] !== $tid;
        } ) );
        update_option( 'engam_v2_stackers_list', $stackers );
        $notice  = 'Stacker deleted.';
        $edit_id = '';
    }
}

// Find record being edited
$editing = null;
if ( $edit_id && $edit_id !== 'new' ) {
    foreach ( $stackers as $s ) {
        if ( $s['id'] === $edit_id ) { $editing = $s; break; }
    }
}

include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-shared-styles.php';
?>
<div id="engam-v2-wrap">

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

<!-- LIST -->
<div class="eg-card" style="margin-top:18px;margin-bottom:18px">
    <div class="eg-head">
        <div>
            <h2>Stacker List</h2>
            <p>GAM-targeted 320&times;480 native ad slots auto-injected into post content.</p>
        </div>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-stackers&edit_stacker=new' ) ); ?>" class="eg-btn">+ Add New Stacker</a>
    </div>

    <?php if ( empty( $stackers ) ) : ?>
        <div class="eg-empty">
            <strong>No stackers yet</strong>
            Click "Add New Stacker" to create your first one.
        </div>
    <?php else : ?>
        <table class="eg-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slot</th>
                    <th>After ¶</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $stackers as $s ) :
                    $is_active = ! empty( $s['active'] );
                ?>
                <tr>
                    <td>
                        <div style="font-weight:700;font-size:14px"><?php echo esc_html( $s['name'] ?: '(untitled)' ); ?></div>
                        <div style="font-family:Consolas,monospace;font-size:12px;color:#555;margin-top:2px"><?php echo esc_html( $s['id'] ); ?></div>
                    </td>
                    <td><code><?php echo esc_html( $s['slotname'] ?? 'stacker' ); ?></code></td>
                    <td><?php echo esc_html( $s['after_paragraph'] ?? 5 ); ?></td>
                    <td><span class="eg-badge <?php echo $is_active ? 'active' : 'inactive'; ?>"><?php echo $is_active ? 'Active' : 'Off'; ?></span></td>
                    <td>
                        <div class="eg-actions-cell">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-stackers&edit_stacker=' . $s['id'] ) ); ?>" class="eg-btn sm dark">Edit</a>
                            <form method="post" action="" style="display:inline">
                                <?php wp_nonce_field( 'engam_v2_stacker_save', 'engam_v2_stacker_nonce' ); ?>
                                <input type="hidden" name="engam_stacker_action" value="toggle">
                                <input type="hidden" name="engam_stacker_id" value="<?php echo esc_attr( $s['id'] ); ?>">
                                <button type="submit" class="eg-btn sm dark"><?php echo $is_active ? 'Deactivate' : 'Activate'; ?></button>
                            </form>
                            <form method="post" action="" style="display:inline" onsubmit="return confirm('Delete <?php echo esc_js( $s['name'] ?? '' ); ?>?')">
                                <?php wp_nonce_field( 'engam_v2_stacker_save', 'engam_v2_stacker_nonce' ); ?>
                                <input type="hidden" name="engam_stacker_action" value="delete">
                                <input type="hidden" name="engam_stacker_id" value="<?php echo esc_attr( $s['id'] ); ?>">
                                <button type="submit" class="eg-btn sm danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <div class="eg-accentline"></div>
</div>

<?php
// ---- EDIT / ADD FORM ----
if ( $edit_id ) :
    $is_new = ( $edit_id === 'new' || $editing === null );
    $f = $is_new ? array(
        'id' => '', 'name' => '', 'slotname' => 'stacker', 'after_paragraph' => 5,
        'cats' => '', 'hide_cats' => '', 'hide_ids' => '', 'hide_sponsors' => '',
        'active' => false,
    ) : $editing;
?>
<div class="eg-card" style="margin-top:18px">
    <div class="eg-head">
        <div>
            <h2><?php echo $is_new ? 'New Stacker' : 'Edit Stacker'; ?></h2>
            <p>Auto-inject a GAM 320&times;480 native ad slot into post content.</p>
        </div>
    </div>
    <form method="post" action="">
        <?php wp_nonce_field( 'engam_v2_stacker_save', 'engam_v2_stacker_nonce' ); ?>
        <input type="hidden" name="engam_stacker_action" value="save">
        <input type="hidden" name="engam_stacker_id" value="<?php echo esc_attr( $f['id'] ); ?>">

        <!-- NAME + SLOT -->
        <div class="eg-form-section">
            <h3>Identity</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
                <div class="eg-settings-field">
                    <label for="engam-stacker-name">Stacker Name</label>
                    <input class="eg-input" type="text" name="engam_stacker_name" id="engam-stacker-name"
                        value="<?php echo esc_attr( $f['name'] ); ?>" placeholder="e.g. Chewy Helpful Products" required>
                </div>
                <div class="eg-settings-field">
                    <label for="engam-stacker-slotname">Child Ad Unit</label>
                    <input class="eg-input" type="text" name="engam_stacker_slotname" id="engam-stacker-slotname"
                        value="<?php echo esc_attr( $f['slotname'] ); ?>" placeholder="stacker">
                    <p class="eg-hint">Appended to your GAM Network ID, e.g. <code>/22345131513/sitename/<strong>stacker</strong></code>. Size is 320&times;480.</p>
                </div>
            </div>
        </div>

        <!-- PLACEMENT -->
        <div class="eg-form-section">
            <h3>Placement</h3>
            <div class="eg-settings-field" style="max-width:200px">
                <label for="engam-stacker-paragraph">Inject After Paragraph</label>
                <input class="eg-input" type="number" min="1" max="20" name="engam_stacker_after_paragraph" id="engam-stacker-paragraph"
                    value="<?php echo esc_attr( $f['after_paragraph'] ?? 5 ); ?>" style="max-width:100px">
                <p class="eg-hint">Appears after this paragraph number. If the post is shorter, it falls back to the end.</p>
            </div>
        </div>

        <!-- SHOW RULES -->
        <div class="eg-form-section">
            <h3>Show Rules <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:13px;color:#777">(optional)</span></h3>
            <div class="eg-settings-field">
                <label for="engam-stacker-cats">Show Only on Categories</label>
                <input class="eg-input" type="text" name="engam_stacker_cats" id="engam-stacker-cats"
                    value="<?php echo esc_attr( $f['cats'] ?? '' ); ?>" placeholder="e.g. horse-health, training">
                <p class="eg-hint">Comma-separated category slugs. Leave blank to show on all posts.</p>
            </div>
        </div>

        <!-- HIDE RULES -->
        <div class="eg-form-section">
            <h3>Hide Rules</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
                <div class="eg-settings-field">
                    <label for="engam-stacker-hide-cats">Hide on Categories</label>
                    <input class="eg-input" type="text" name="engam_stacker_hide_cats" id="engam-stacker-hide-cats"
                        value="<?php echo esc_attr( $f['hide_cats'] ?? '' ); ?>" placeholder="e.g. sponsored, partner-content">
                    <p class="eg-hint">Comma-separated category slugs where the stacker should never appear.</p>
                </div>
                <div class="eg-settings-field">
                    <label for="engam-stacker-hide-ids">Hide on Specific Posts / Pages</label>
                    <input class="eg-input" type="text" name="engam_stacker_hide_ids" id="engam-stacker-hide-ids"
                        value="<?php echo esc_attr( $f['hide_ids'] ?? '' ); ?>" placeholder="e.g. 12, 458, 902">
                    <p class="eg-hint">Comma-separated post/page IDs to exclude.</p>
                </div>
                <div class="eg-settings-field">
                    <label for="engam-stacker-hide-sponsors">Hide When Sponsor ID Is Active</label>
                    <input class="eg-input" type="text" name="engam_stacker_hide_sponsors" id="engam-stacker-hide-sponsors"
                        value="<?php echo esc_attr( $f['hide_sponsors'] ?? '' ); ?>" placeholder="e.g. CactusRopes_Horses, Equinety_Salute">
                    <p class="eg-hint">Suppress when a post has one of these sponsor overrides. Use <code>*</code> to hide on <strong>any</strong> sponsored post.</p>
                </div>
            </div>
        </div>

        <!-- ACTIVE + SAVE -->
        <div class="eg-form-section">
            <div class="eg-settings-field">
                <label class="eg-toggle">
                    <input type="checkbox" name="engam_stacker_active" value="1" <?php checked( ! empty( $f['active'] ) ); ?>>
                    <span class="eg-toggle-track"><span class="eg-toggle-thumb"></span></span>
                    Stacker active
                </label>
                <p class="eg-hint">When off, this stacker is not injected on any post.</p>
            </div>
        </div>

        <div class="eg-form-section" style="border-top:1px solid #deded8;display:flex;gap:10px;align-items:center">
            <button type="submit" class="eg-btn" style="padding:14px 32px;font-size:14px"><?php echo $is_new ? 'Create Stacker' : 'Save Stacker'; ?></button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-stackers' ) ); ?>" class="eg-btn dark" style="padding:14px 32px;font-size:14px">Cancel</a>
        </div>
    </form>
    <div class="eg-accentline"></div>
</div>
<?php endif; ?>

</div><!-- .eg-content -->
</div><!-- #engam-v2-wrap -->
