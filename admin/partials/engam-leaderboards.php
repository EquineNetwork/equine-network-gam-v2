<?php if ( ! defined( 'WPINC' ) ) die;

$leaderboards = get_option( 'engam_v2_leaderboards_list', array() );
if ( ! is_array( $leaderboards ) ) $leaderboards = array();

$notice  = '';
$edit_id = isset( $_GET['edit_lb'] ) ? sanitize_text_field( wp_unslash( $_GET['edit_lb'] ) ) : '';

// ---- Handle POST ----
if ( isset( $_POST['engam_v2_lb_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['engam_v2_lb_nonce'] ) ), 'engam_v2_lb_save' ) ) {
    if ( ! current_user_can( 'edit_posts' ) ) wp_die( -1 );

    $action = isset( $_POST['engam_lb_action'] ) ? sanitize_text_field( wp_unslash( $_POST['engam_lb_action'] ) ) : '';

    if ( 'save' === $action ) {
        $lb_id  = isset( $_POST['engam_lb_id'] ) ? sanitize_text_field( wp_unslash( $_POST['engam_lb_id'] ) ) : '';
        $is_new = empty( $lb_id );
        if ( $is_new ) $lb_id = uniqid( 'lb_' );

        $record = array(
            'id'       => $lb_id,
            'name'     => sanitize_text_field( wp_unslash( $_POST['engam_lb_name'] ?? '' ) ),
            'position' => in_array( $_POST['engam_lb_position'] ?? '', array( 'header', 'footer', 'midpoint' ), true )
                              ? sanitize_text_field( wp_unslash( $_POST['engam_lb_position'] ) ) : 'header',
            'target_pages'    => sanitize_text_field( wp_unslash( $_POST['engam_lb_target_pages'] ?? '' ) ),
            'target_selector' => sanitize_text_field( wp_unslash( $_POST['engam_lb_target_selector'] ?? '' ) ),
            'slotname' => 'leaderboard',
            'bg_color' => sanitize_hex_color( wp_unslash( $_POST['engam_lb_bg_color'] ?? '' ) ) ?: '',
            'padding_top'    => max( 0, intval( $_POST['engam_lb_padding_top']    ?? 0 ) ),
            'padding_right'  => max( 0, intval( $_POST['engam_lb_padding_right']  ?? 0 ) ),
            'padding_bottom' => max( 0, intval( $_POST['engam_lb_padding_bottom'] ?? 0 ) ),
            'padding_left'   => max( 0, intval( $_POST['engam_lb_padding_left']   ?? 0 ) ),
            'active'   => $is_new ? true : ( ! empty( $leaderboards[ array_search( $lb_id, array_column( $leaderboards, 'id' ) ) ]['active'] ) ),
        );

        if ( $is_new ) {
            $leaderboards[] = $record;
            $notice = 'Leaderboard "' . esc_html( $record['name'] ) . '" created.';
        } else {
            foreach ( $leaderboards as &$lb ) {
                if ( $lb['id'] === $lb_id ) { $lb = $record; break; }
            }
            unset( $lb );
            $notice = 'Leaderboard "' . esc_html( $record['name'] ) . '" updated.';
        }
        update_option( 'engam_v2_leaderboards_list', $leaderboards );
        $edit_id = '';
    }

    if ( 'toggle' === $action && isset( $_POST['engam_lb_id'] ) ) {
        $tid = sanitize_text_field( wp_unslash( $_POST['engam_lb_id'] ) );
        foreach ( $leaderboards as &$lb ) {
            if ( $lb['id'] === $tid ) { $lb['active'] = empty( $lb['active'] ); break; }
        }
        unset( $lb );
        update_option( 'engam_v2_leaderboards_list', $leaderboards );
        $notice = 'Leaderboard updated.';
    }

    if ( 'delete' === $action && isset( $_POST['engam_lb_id'] ) ) {
        $tid = sanitize_text_field( wp_unslash( $_POST['engam_lb_id'] ) );
        $leaderboards = array_values( array_filter( $leaderboards, function( $lb ) use ( $tid ) {
            return $lb['id'] !== $tid;
        } ) );
        update_option( 'engam_v2_leaderboards_list', $leaderboards );
        $notice = 'Leaderboard deleted.';
        $edit_id = '';
    }
}

// Find record being edited
$editing = null;
if ( $edit_id && $edit_id !== 'new' ) {
    foreach ( $leaderboards as $lb ) {
        if ( $lb['id'] === $edit_id ) { $editing = $lb; break; }
    }
}

// Pull Elementor global colors from the active kit.
$elementor_colors = array();
$kit_id = get_option( 'elementor_active_kit' );
if ( $kit_id ) {
    $kit_settings = get_post_meta( (int) $kit_id, '_elementor_page_settings', true );
    if ( is_array( $kit_settings ) ) {
        foreach ( array( 'system_colors', 'custom_colors' ) as $group ) {
            if ( ! empty( $kit_settings[ $group ] ) && is_array( $kit_settings[ $group ] ) ) {
                foreach ( $kit_settings[ $group ] as $c ) {
                    if ( ! empty( $c['color'] ) && ! empty( $c['title'] ) ) {
                        $elementor_colors[] = array( 'title' => $c['title'], 'color' => $c['color'] );
                    }
                }
            }
        }
    }
}

$takeover_conflict = false; // Leaderboards always display regardless of active takeovers.

include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-shared-styles.php';
?>
<div id="engam-v2-wrap">

<section class="eg-mast">
    <div class="eg-brand">
        <div class="eg-logo">EN</div>
        <div class="eg-brand-text">
            <small>Google Ad Manager &mdash; v2</small>
            <h1>Leaderboards</h1>
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
            <h2>Leaderboard List</h2>
            <p>Auto-injected with no Elementor container needed — below the site header, at the top of the footer, or halfway down a specific page.</p>
        </div>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-leaderboards&edit_lb=new' ) ); ?>" class="eg-btn">+ Add New Leaderboard</a>
    </div>

    <?php if ( empty( $leaderboards ) ) : ?>
        <div class="eg-empty">
            <strong>No leaderboards yet</strong>
            Click "Add New Leaderboard" to create your first one.
        </div>
    <?php else : ?>
        <table class="eg-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $leaderboards as $lb ) :
                    $is_active = ! empty( $lb['active'] );
                    $pos_val   = $lb['position'] ?? 'header';
                    if ( $pos_val === 'footer' ) {
                        $pos_label = 'Footer';
                    } elseif ( $pos_val === 'midpoint' ) {
                        $pos_label = 'Half Page';
                        $tp = trim( (string) ( $lb['target_pages'] ?? '' ) );
                        if ( $tp !== '' ) {
                            $tp_post = is_numeric( $tp ) ? get_post( (int) $tp ) : get_page_by_path( $tp );
                            $pos_label .= ' (' . esc_html( $tp_post ? $tp_post->post_title : $tp ) . ')';
                        }
                    } else {
                        $pos_label = 'Header';
                    }
                ?>
                <tr>
                    <td>
                        <div style="font-weight:700;font-size:14px"><?php echo esc_html( $lb['name'] ?: '(untitled)' ); ?></div>
                        <div style="font-family:Consolas,monospace;font-size:12px;color:#555;margin-top:2px"><?php echo esc_html( $lb['id'] ); ?></div>
                    </td>
                    <td><?php echo esc_html( $pos_label ); ?></td>
                    <td><span class="eg-badge <?php echo $is_active ? 'active' : 'inactive'; ?>"><?php echo $is_active ? 'Active' : 'Off'; ?></span></td>
                    <td>
                        <div class="eg-actions-cell">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-leaderboards&edit_lb=' . $lb['id'] ) ); ?>" class="eg-btn sm dark">Edit</a>
                            <form method="post" action="" style="display:inline">
                                <?php wp_nonce_field( 'engam_v2_lb_save', 'engam_v2_lb_nonce' ); ?>
                                <input type="hidden" name="engam_lb_action" value="toggle">
                                <input type="hidden" name="engam_lb_id" value="<?php echo esc_attr( $lb['id'] ); ?>">
                                <button type="submit" class="eg-btn sm dark"><?php echo $is_active ? 'Deactivate' : 'Activate'; ?></button>
                            </form>
                            <form method="post" action="" style="display:inline" onsubmit="return confirm('Delete <?php echo esc_js( $lb['name'] ); ?>?')">
                                <?php wp_nonce_field( 'engam_v2_lb_save', 'engam_v2_lb_nonce' ); ?>
                                <input type="hidden" name="engam_lb_action" value="delete">
                                <input type="hidden" name="engam_lb_id" value="<?php echo esc_attr( $lb['id'] ); ?>">
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
        'id' => '', 'name' => '', 'position' => 'header', 'slotname' => 'leaderboard',
        'target_pages' => '', 'target_selector' => '',
        'bg_color' => '',
        'padding_top' => 10, 'padding_right' => 10, 'padding_bottom' => 10, 'padding_left' => 10,
        'active' => false,
    ) : $editing;
?>
<div class="eg-card" style="margin-top:18px">
    <div class="eg-head">
        <div>
            <h2><?php echo $is_new ? 'New Leaderboard' : 'Edit Leaderboard'; ?></h2>
            <p>Inject a leaderboard slot into the header or footer site-wide, or halfway down a specific page.</p>
        </div>
    </div>
    <form method="post" action="">
        <?php wp_nonce_field( 'engam_v2_lb_save', 'engam_v2_lb_nonce' ); ?>
        <input type="hidden" name="engam_lb_action" value="save">
        <input type="hidden" name="engam_lb_id" value="<?php echo esc_attr( $f['id'] ); ?>">

        <!-- NAME + POSITION -->
        <div class="eg-form-section">
            <h3>Name &amp; Position</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
                <div class="eg-settings-field">
                    <label for="engam-lb-name">Leaderboard Name</label>
                    <input class="eg-input" type="text" name="engam_lb_name" id="engam-lb-name"
                        value="<?php echo esc_attr( $f['name'] ); ?>" placeholder="e.g. Site-wide Header Leaderboard" required>
                </div>
                <div class="eg-settings-field">
                    <label for="engam-lb-position">Position</label>
                    <select class="eg-input" name="engam_lb_position" id="engam-lb-position">
                        <option value="header" <?php selected( $f['position'] ?? 'header', 'header' ); ?>>Header — below the site nav</option>
                        <option value="footer" <?php selected( $f['position'] ?? 'header', 'footer' ); ?>>Footer — top of the site footer</option>
                        <option value="midpoint" <?php selected( $f['position'] ?? 'header', 'midpoint' ); ?>>Half Page — halfway down a specific page</option>
                    </select>
                    <p class="eg-hint">Header/Footer show on every page. Half Page shows only on the page(s) you choose below.</p>
                </div>
            </div>

            <!-- HALF PAGE TARGETING (only relevant for the Half Page position) -->
            <div id="engam-lb-midpoint-fields" style="margin-top:18px;<?php echo ( ( $f['position'] ?? 'header' ) === 'midpoint' ) ? '' : 'display:none'; ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
                    <div class="eg-settings-field">
                        <label for="engam-lb-target-pages">Target Page</label>
                        <?php
                        $current_target = trim( (string) ( $f['target_pages'] ?? '' ) );
                        $all_pages      = get_pages( array( 'sort_column' => 'post_title', 'sort_order' => 'ASC' ) );
                        ?>
                        <select class="eg-input" name="engam_lb_target_pages" id="engam-lb-target-pages">
                            <option value="">— Select a page —</option>
                            <?php foreach ( (array) $all_pages as $p ) :
                                // Pre-select whether the saved value is a page ID (current/new) or a slug (legacy).
                                $is_sel = ( (string) $p->ID === $current_target ) || ( strcasecmp( $p->post_name, $current_target ) === 0 );
                            ?>
                                <option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $is_sel, true ); ?>>
                                    <?php echo esc_html( ( $p->post_title !== '' ? $p->post_title : '(no title)' ) . ' — #' . $p->ID ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="eg-hint">The leaderboard renders only on this page.</p>
                    </div>
                    <div class="eg-settings-field">
                        <label for="engam-lb-target-selector">Insert Before <span style="color:#777;font-weight:400;text-transform:none;letter-spacing:0">(CSS selector, optional)</span></label>
                        <input class="eg-input" type="text" name="engam_lb_target_selector" id="engam-lb-target-selector"
                            value="<?php echo esc_attr( $f['target_selector'] ?? '' ); ?>" placeholder="e.g. .tribe-events-calendar-list__event">
                        <p class="eg-hint">If the page repeats rows (calendar events, list items), enter their CSS class — the ad lands just before the middle row. Leave blank to drop it at the visual midpoint of the page content.</p>
                    </div>
                </div>
            </div>
        </div>
<script>
(function(){
    var sel = document.getElementById('engam-lb-position');
    var box = document.getElementById('engam-lb-midpoint-fields');
    if(!sel||!box) return;
    function sync(){ box.style.display = (sel.value === 'midpoint') ? '' : 'none'; }
    sel.addEventListener('change', sync);
    sync();
})();
</script>

        <!-- APPEARANCE -->
        <div class="eg-form-section">
            <h3>Appearance</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
                <div class="eg-settings-field">
                    <label>Background Color <span style="color:#777;font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
                    <div class="eg-color-row" style="flex-wrap:wrap;gap:8px">
                        <input type="color" id="engam-lb-bg-picker" value="<?php echo esc_attr( $f['bg_color'] ?: '#ffffff' ); ?>"
                            oninput="document.getElementById('engam-lb-bg-hex').value=this.value">
                        <input class="eg-input" type="text" name="engam_lb_bg_color" id="engam-lb-bg-hex"
                            value="<?php echo esc_attr( $f['bg_color'] ); ?>" placeholder="(none)" maxlength="7" style="width:110px"
                            oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value))document.getElementById('engam-lb-bg-picker').value=this.value">
                        <?php if ( ! empty( $elementor_colors ) ) : ?>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
                                <?php foreach ( $elementor_colors as $ec ) : ?>
                                <button type="button" title="<?php echo esc_attr( $ec['title'] ); ?>"
                                    onclick="document.getElementById('engam-lb-bg-hex').value='<?php echo esc_js( $ec['color'] ); ?>';document.getElementById('engam-lb-bg-picker').value='<?php echo esc_js( $ec['color'] ); ?>';"
                                    style="width:28px;height:28px;border:2px solid #ddd;border-radius:4px;cursor:pointer;background:<?php echo esc_attr( $ec['color'] ); ?>;padding:0;flex-shrink:0"></button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p class="eg-hint">Full-width band color behind the ad. Leave blank for transparent.</p>
                </div>
            </div>
            <!-- PADDING with link toggle -->
            <div class="eg-settings-field" style="margin-top:4px">
                <label>Padding (px)</label>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
                        <span style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#777">Top</span>
                        <input class="eg-input engam-lb-pad" id="engam-lb-pt" type="number" min="0" name="engam_lb_padding_top"
                            value="<?php echo esc_attr( $f['padding_top'] ?? 0 ); ?>" style="width:72px;text-align:center">
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
                        <span style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#777">Right</span>
                        <input class="eg-input engam-lb-pad" id="engam-lb-pr" type="number" min="0" name="engam_lb_padding_right"
                            value="<?php echo esc_attr( $f['padding_right'] ?? 0 ); ?>" style="width:72px;text-align:center">
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
                        <span style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#777">Bottom</span>
                        <input class="eg-input engam-lb-pad" id="engam-lb-pb" type="number" min="0" name="engam_lb_padding_bottom"
                            value="<?php echo esc_attr( $f['padding_bottom'] ?? 0 ); ?>" style="width:72px;text-align:center">
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:4px">
                        <span style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#777">Left</span>
                        <input class="eg-input engam-lb-pad" id="engam-lb-pl" type="number" min="0" name="engam_lb_padding_left"
                            value="<?php echo esc_attr( $f['padding_left'] ?? 0 ); ?>" style="width:72px;text-align:center">
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:center;gap:4px;margin-left:4px">
                        <span style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#777">Link</span>
                        <button type="button" id="engam-lb-pad-link" title="Padding linked — click to unlink"
                            style="width:38px;height:46px;border:2px solid #111;background:#111;cursor:pointer;border-radius:4px;display:flex;align-items:center;justify-content:center;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="5" y="11" width="14" height="10" rx="2" fill="white"/>
                                <path d="M8 11V7a4 4 0 0 1 8 0v4" stroke="white" stroke-width="2.2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <p class="eg-hint" id="engam-lb-pad-hint">Padding is linked — changing one side updates all.</p>
            </div>
        </div>
<script>
(function(){
    var linked = true;
    var btn = document.getElementById('engam-lb-pad-link');
    var inputs = document.querySelectorAll('.engam-lb-pad');
    var hint = document.getElementById('engam-lb-pad-hint');
    if(!btn||!inputs.length) return;
    var svgLocked = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="5" y="11" width="14" height="10" rx="2" fill="white"/><path d="M8 11V7a4 4 0 0 1 8 0v4" stroke="white" stroke-width="2.2" stroke-linecap="round"/></svg>';
    var svgUnlocked = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="5" y="11" width="14" height="10" rx="2" fill="white"/><path d="M8 11V7a4 4 0 0 1 8 0v4" stroke="#aaa" stroke-width="2.2" stroke-linecap="round" stroke-dasharray="4 3"/></svg>';
    function setLinkState(on){
        linked = on;
        btn.style.background = on ? '#111' : '#fff';
        btn.style.borderColor = on ? '#111' : '#bbb';
        btn.innerHTML = on ? svgLocked : svgUnlocked;
        if(hint) hint.textContent = on ? 'Padding is linked — changing one side updates all.' : 'Click the lock to link all sides.';
    }
    setLinkState(true);
    btn.addEventListener('click', function(){ setLinkState(!linked); });
    inputs.forEach(function(inp){
        inp.addEventListener('input', function(){
            if(!linked) return;
            var v = this.value;
            inputs.forEach(function(o){ o.value = v; });
        });
    });
})();
</script>

        <div class="eg-form-section" style="border-top:1px solid #deded8;display:flex;gap:10px;align-items:center">
            <button type="submit" class="eg-btn" style="padding:14px 32px;font-size:14px"><?php echo $is_new ? 'Create Leaderboard' : 'Save Leaderboard'; ?></button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-leaderboards' ) ); ?>" class="eg-btn dark" style="padding:14px 32px;font-size:14px">Cancel</a>
        </div>
    </form>
    <div class="eg-accentline"></div>
</div>
<?php endif; ?>

</div><!-- .eg-content -->
</div><!-- #engam-v2-wrap -->
