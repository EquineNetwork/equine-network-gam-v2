<?php if ( ! defined( 'WPINC' ) ) die;

$mastheads = get_option( 'engam_v2_mastheads', array() );
if ( ! is_array( $mastheads ) ) $mastheads = array();

$notice  = '';
$edit_id = isset( $_GET['edit_mh'] ) ? sanitize_text_field( wp_unslash( $_GET['edit_mh'] ) ) : '';

// ---- Handle POST ----
if ( isset( $_POST['engam_v2_mh_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['engam_v2_mh_nonce'] ) ), 'engam_v2_mh_save' ) ) {
    if ( ! current_user_can( 'edit_others_posts' ) ) wp_die( -1 );

    $action = isset( $_POST['engam_mh_action'] ) ? sanitize_text_field( wp_unslash( $_POST['engam_mh_action'] ) ) : '';

    if ( 'save' === $action ) {
        $mh_id  = isset( $_POST['engam_mh_id'] ) ? sanitize_text_field( wp_unslash( $_POST['engam_mh_id'] ) ) : '';
        $is_new = empty( $mh_id );
        if ( $is_new ) $mh_id = uniqid( 'mh_' );

        $pages = array();
        if ( isset( $_POST['engam_mh_pages'] ) && is_array( $_POST['engam_mh_pages'] ) ) {
            $pages = array_values( array_filter( array_map( 'intval', wp_unslash( $_POST['engam_mh_pages'] ) ) ) );
        }

        $record = array(
            'id'        => $mh_id,
            'name'      => sanitize_text_field( wp_unslash( $_POST['engam_mh_name'] ?? '' ) ),
            'slotname'  => 'homepagetakeover',
            'show_home' => isset( $_POST['engam_mh_show_home'] ),
            'pages'     => $pages,
            'bg_color'  => sanitize_hex_color( wp_unslash( $_POST['engam_mh_bg_color'] ?? '' ) ) ?: '',
            'active'    => isset( $_POST['engam_mh_active'] ),
        );

        if ( $is_new ) {
            $mastheads[] = $record;
            $notice = 'Masthead "' . esc_html( $record['name'] ) . '" created.';
        } else {
            foreach ( $mastheads as &$m ) {
                if ( $m['id'] === $mh_id ) { $m = $record; break; }
            }
            unset( $m );
            $notice = 'Masthead "' . esc_html( $record['name'] ) . '" updated.';
        }
        update_option( 'engam_v2_mastheads', $mastheads );
        $edit_id = '';
    }

    if ( 'toggle' === $action && isset( $_POST['engam_mh_id'] ) ) {
        $tid = sanitize_text_field( wp_unslash( $_POST['engam_mh_id'] ) );
        foreach ( $mastheads as &$m ) {
            if ( $m['id'] === $tid ) { $m['active'] = empty( $m['active'] ); break; }
        }
        unset( $m );
        update_option( 'engam_v2_mastheads', $mastheads );
        $notice = 'Masthead updated.';
    }

    if ( 'delete' === $action && isset( $_POST['engam_mh_id'] ) ) {
        $tid = sanitize_text_field( wp_unslash( $_POST['engam_mh_id'] ) );
        $mastheads = array_values( array_filter( $mastheads, function( $m ) use ( $tid ) {
            return $m['id'] !== $tid;
        } ) );
        update_option( 'engam_v2_mastheads', $mastheads );
        $notice = 'Masthead deleted.';
        $edit_id = '';
    }
}

// Find record being edited
$editing = null;
if ( $edit_id ) {
    foreach ( $mastheads as $m ) {
        if ( $m['id'] === $edit_id ) { $editing = $m; break; }
    }
}

$all_pages = get_pages( array( 'sort_column' => 'post_title', 'number' => 300 ) );

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

$takeover_conflict = Equinenetwork_Gam_V2_Takeover::has_active() &&
    count( array_filter( $mastheads, function( $m ) { return ! empty( $m['active'] ); } ) ) > 0;

include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-shared-styles.php';
?>
<div id="engam-v2-wrap">

<section class="eg-mast">
    <div class="eg-brand">
        <div class="eg-logo">EN</div>
        <div class="eg-brand-text">
            <small>Google Ad Manager &mdash; v2</small>
            <h1>Mastheads</h1>
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

<?php if ( $takeover_conflict ) : ?>
<div class="eg-notice" style="background:#fff3cd;color:#7a5c00;border-left-color:#f0ad4e;">
    <strong>Conflict:</strong> A Takeover is currently active. Mastheads will not display until the Takeover is deactivated or expires.
</div>
<?php endif; ?>

<!-- LIST -->
<div class="eg-card" style="margin-top:18px;margin-bottom:18px">
    <div class="eg-head">
        <div>
            <h2>Masthead List</h2>
            <p>Full-width GAM-served banners injected above the site header. Only active mastheads render — GAM controls the creative and scheduling.</p>
        </div>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-masthead&edit_mh=new' ) ); ?>" class="eg-btn">+ Add New Masthead</a>
    </div>

    <?php if ( empty( $mastheads ) ) : ?>
        <div class="eg-empty">
            <strong>No mastheads yet</strong>
            Click "Add New Masthead" to create your first one.
        </div>
    <?php else : ?>
        <table class="eg-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Pages</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $mastheads as $mh ) :
                    $is_active   = ! empty( $mh['active'] );
                    $pages_label = ! empty( $mh['show_home'] ) ? 'Homepage' : '';
                    if ( ! empty( $mh['pages'] ) ) {
                        $extra = count( $mh['pages'] );
                        $pages_label .= ( $pages_label ? ' +' : '' ) . $extra . ' page' . ( $extra !== 1 ? 's' : '' );
                    }
                    if ( ! $pages_label ) $pages_label = '—';
                ?>
                <tr>
                    <td>
                        <div style="font-weight:700;font-size:14px"><?php echo esc_html( $mh['name'] ?: '(untitled)' ); ?></div>
                        <div style="font-family:Consolas,monospace;font-size:12px;color:#555;margin-top:2px"><?php echo esc_html( $mh['id'] ); ?></div>
                    </td>
                    <td style="font-size:12px;color:#555"><?php echo esc_html( $pages_label ); ?></td>
                    <td><span class="eg-badge <?php echo $is_active ? 'active' : 'inactive'; ?>"><?php echo $is_active ? 'Active' : 'Inactive'; ?></span></td>
                    <td>
                        <div class="eg-actions-cell">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-masthead&edit_mh=' . $mh['id'] ) ); ?>" class="eg-btn sm dark">Edit</a>
                            <form method="post" action="" style="display:inline">
                                <?php wp_nonce_field( 'engam_v2_mh_save', 'engam_v2_mh_nonce' ); ?>
                                <input type="hidden" name="engam_mh_action" value="toggle">
                                <input type="hidden" name="engam_mh_id" value="<?php echo esc_attr( $mh['id'] ); ?>">
                                <button type="submit" class="eg-btn sm dark"><?php echo ! empty( $mh['active'] ) ? 'Deactivate' : 'Activate'; ?></button>
                            </form>
                            <form method="post" action="" style="display:inline" onsubmit="return confirm('Delete <?php echo esc_js( $mh['name'] ); ?>?')">
                                <?php wp_nonce_field( 'engam_v2_mh_save', 'engam_v2_mh_nonce' ); ?>
                                <input type="hidden" name="engam_mh_action" value="delete">
                                <input type="hidden" name="engam_mh_id" value="<?php echo esc_attr( $mh['id'] ); ?>">
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
        'id' => '', 'name' => '', 'slotname' => 'homepagetakeover',
        'show_home' => true, 'pages' => array(),
        'bg_color' => '', 'active' => false,
    ) : $editing;
?>
<div class="eg-card" style="margin-top:18px">
    <div class="eg-head">
        <div>
            <h2><?php echo $is_new ? 'New Masthead' : 'Edit Masthead'; ?></h2>
            <p>Configure slot, targeting and appearance. GAM handles all creative scheduling.</p>
        </div>
    </div>
    <form method="post" action="">
        <?php wp_nonce_field( 'engam_v2_mh_save', 'engam_v2_mh_nonce' ); ?>
        <input type="hidden" name="engam_mh_action" value="save">
        <input type="hidden" name="engam_mh_id" value="<?php echo esc_attr( $f['id'] ); ?>">

        <!-- NAME -->
        <div class="eg-form-section">
            <h3>Name</h3>
            <div class="eg-settings-field">
                <label for="engam-mh-name">Masthead Name</label>
                <input class="eg-input" type="text" name="engam_mh_name" id="engam-mh-name"
                    value="<?php echo esc_attr( $f['name'] ); ?>" placeholder="e.g. Horse&amp;Rider Homepage Masthead" required>
            </div>
        </div>

        <!-- APPEARANCE -->
        <div class="eg-form-section">
            <h3>Appearance</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
                <div class="eg-settings-field">
                    <label>Background Color <span style="color:#777;font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
                    <div class="eg-color-row" style="flex-wrap:wrap;gap:8px">
                        <input type="color" id="engam-mh-bg-picker" value="<?php echo esc_attr( $f['bg_color'] ?: '#ffffff' ); ?>"
                            oninput="document.getElementById('engam-mh-bg-hex').value=this.value">
                        <input class="eg-input" type="text" name="engam_mh_bg_color" id="engam-mh-bg-hex"
                            value="<?php echo esc_attr( $f['bg_color'] ); ?>" placeholder="(none)" maxlength="7" style="width:110px"
                            oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value))document.getElementById('engam-mh-bg-picker').value=this.value">
                        <?php if ( ! empty( $elementor_colors ) ) : ?>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
                            <?php foreach ( $elementor_colors as $ec ) : ?>
                            <button type="button" title="<?php echo esc_attr( $ec['title'] ); ?>"
                                onclick="document.getElementById('engam-mh-bg-hex').value='<?php echo esc_js( $ec['color'] ); ?>';document.getElementById('engam-mh-bg-picker').value='<?php echo esc_js( $ec['color'] ); ?>';"
                                style="width:28px;height:28px;border:2px solid #ddd;border-radius:4px;cursor:pointer;background:<?php echo esc_attr( $ec['color'] ); ?>;padding:0;flex-shrink:0"></button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <p class="eg-hint">Fills the band behind the banner. Leave blank for transparent.</p>
                </div>
            </div>
        </div>

        <!-- WHERE IT SHOWS -->
        <div class="eg-form-section">
            <h3>Where it shows</h3>
            <div class="eg-settings-field">
                <label class="eg-toggle">
                    <input type="checkbox" name="engam_mh_show_home" value="1" <?php checked( ! empty( $f['show_home'] ) ); ?>>
                    <span class="eg-toggle-track"><span class="eg-toggle-thumb"></span></span>
                    Show on the homepage
                </label>
            </div>
            <div class="eg-settings-field">
                <label>Also show on these pages <span style="color:#777;font-weight:400;text-transform:none;letter-spacing:0">(optional)</span></label>
                <?php
                $mh_selected_data = array();
                if ( ! empty( $f['pages'] ) ) {
                    foreach ( get_posts( array( 'post__in' => array_map( 'intval', (array) $f['pages'] ), 'post_type' => 'page', 'posts_per_page' => -1, 'post_status' => 'publish' ) ) as $sp ) {
                        $mh_selected_data[] = array( 'id' => $sp->ID, 'title' => $sp->post_title, 'type' => 'page' );
                    }
                }
                ?>
                <div id="engam-mh-pages-picker"></div>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    engamPostPicker({
                        wrap:     '#engam-mh-pages-picker',
                        name:     'engam_mh_pages[]',
                        types:    ['page'],
                        selected: <?php echo wp_json_encode( $mh_selected_data ); ?>
                    });
                });
                </script>
                <p class="eg-hint">Search and select pages to show this masthead on.</p>
            </div>
        </div>

        <!-- ACTIVE + SAVE -->
        <div class="eg-form-section">
            <div class="eg-settings-field">
                <label class="eg-toggle">
                    <input type="checkbox" name="engam_mh_active" value="1" <?php checked( ! empty( $f['active'] ) ); ?>>
                    <span class="eg-toggle-track"><span class="eg-toggle-thumb"></span></span>
                    Masthead active
                </label>
                <p class="eg-hint">When off, this masthead is not rendered on any page.</p>
            </div>
        </div>

        <div class="eg-form-section" style="border-top:1px solid #deded8;display:flex;gap:10px;align-items:center">
            <button type="submit" class="eg-btn" style="padding:14px 32px;font-size:14px"><?php echo $is_new ? 'Create Masthead' : 'Save Masthead'; ?></button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-masthead' ) ); ?>" class="eg-btn dark" style="padding:14px 32px;font-size:14px">Cancel</a>
        </div>
    </form>
    <div class="eg-accentline"></div>
</div>
<?php endif; ?>

</div><!-- .eg-content -->
</div><!-- #engam-v2-wrap -->
