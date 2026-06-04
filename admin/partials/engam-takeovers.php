<?php if ( ! defined( 'WPINC' ) ) die;

$takeovers = get_option( 'engam_v2_takeovers', array() );
if ( ! is_array( $takeovers ) ) $takeovers = array();

// Migrate legacy mastheads into combined takeovers list
$legacy_mastheads = get_option( 'engam_v2_mastheads', array() );
if ( ! empty( $legacy_mastheads ) ) {
    foreach ( $legacy_mastheads as $m ) {
        if ( ! isset( $m['type'] ) ) {
            $m['type'] = 'masthead';
            if ( ! isset( $m['slotname'] ) ) $m['slotname'] = 'homepagetakeover';
            if ( ! isset( $m['schedule_start'] ) ) $m['schedule_start'] = '';
            if ( ! isset( $m['schedule_end'] ) ) $m['schedule_end'] = '';
            $takeovers[] = $m;
        }
    }
    update_option( 'engam_v2_takeovers', $takeovers );
    delete_option( 'engam_v2_mastheads' );
}
// Ensure all existing entries have a type field
foreach ( $takeovers as &$t ) {
    if ( ! isset( $t['type'] ) ) $t['type'] = 'wrap';
}
unset( $t );

$notice = '';
$edit_id = isset( $_GET['edit_to'] ) ? sanitize_text_field( wp_unslash( $_GET['edit_to'] ) ) : '';

// ---- Handle POST ----
if ( isset( $_POST['engam_v2_to_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['engam_v2_to_nonce'] ) ), 'engam_v2_to_save' ) ) {
    if ( ! current_user_can( 'edit_posts' ) ) wp_die( -1 );

    $action = isset( $_POST['engam_to_action'] ) ? sanitize_text_field( wp_unslash( $_POST['engam_to_action'] ) ) : '';

    if ( 'save' === $action ) {
        $to_id   = isset( $_POST['engam_to_id'] ) ? sanitize_text_field( wp_unslash( $_POST['engam_to_id'] ) ) : '';
        $is_new  = empty( $to_id );
        if ( $is_new ) $to_id = uniqid( 'to_' );

        $to_type = isset( $_POST['engam_to_type'] ) ? sanitize_text_field( wp_unslash( $_POST['engam_to_type'] ) ) : 'wrap';
        if ( ! in_array( $to_type, array( 'masthead', 'wrap' ), true ) ) $to_type = 'wrap';

        // Active state is controlled from the list (Activate/Deactivate), not this
        // form. Preserve the existing record's state on edit; new records default
        // to active so they go live as soon as the GAM schedule is in flight.
        $prev_active = true;
        if ( ! $is_new ) {
            foreach ( $takeovers as $existing_to ) {
                if ( isset( $existing_to['id'] ) && $existing_to['id'] === $to_id ) {
                    $prev_active = ! empty( $existing_to['active'] );
                    break;
                }
            }
        }

        if ( 'masthead' === $to_type ) {
            // Pages come as checkbox array engam_to_pages[]
            $pages_raw = isset( $_POST['engam_to_pages'] ) ? (array) $_POST['engam_to_pages'] : array();
            $pages = array_values( array_filter( array_map( 'intval', $pages_raw ) ) );

            $record = array(
                'id'             => $to_id,
                'type'           => 'masthead',
                'name'           => sanitize_text_field( wp_unslash( $_POST['engam_to_name'] ?? '' ) ),
                'slotname'       => sanitize_text_field( wp_unslash( $_POST['engam_to_slotname'] ?? 'homepagetakeover' ) ),
                'bg_color'       => sanitize_hex_color( wp_unslash( $_POST['engam_to_bg_color'] ?? '' ) ) ?: '',
                'show_home'      => ! empty( $_POST['engam_to_show_home'] ),
                'pages'          => $pages,
                'gam_line_item_id' => sanitize_text_field( wp_unslash( $_POST['engam_to_gam_line_item_id'] ?? '' ) ),
                'schedule_start' => sanitize_text_field( wp_unslash( $_POST['engam_to_schedule_start'] ?? '' ) ),
                'schedule_end'   => sanitize_text_field( wp_unslash( $_POST['engam_to_schedule_end'] ?? '' ) ),
                'active'         => $prev_active,
            );
        } else {
            $record = array(
                'id'               => $to_id,
                'type'             => 'wrap',
                'name'             => sanitize_text_field( wp_unslash( $_POST['engam_to_name'] ?? '' ) ),
                'gam_line_item_id' => sanitize_text_field( wp_unslash( $_POST['engam_to_wrap_gam_line_item_id'] ?? '' ) ),
                'bg_color'         => sanitize_hex_color( wp_unslash( $_POST['engam_to_bg_color'] ?? '#000000' ) ) ?: '#000000',
                'active'           => $prev_active,
                'show_to_admins'   => ! empty( $_POST['engam_to_show_to_admins'] ),
                'wrap_cats'        => implode( ',', array_filter( array_map( 'sanitize_title', (array) ( $_POST['engam_to_wrap_cats'] ?? array() ) ) ) ),
                'wrap_pages'       => array_values( array_filter( array_map( 'intval', (array) ( $_POST['engam_to_wrap_pages'] ?? array() ) ) ) ),
                'wrap_posts'       => array_values( array_filter( array_map( 'intval', (array) ( $_POST['engam_to_wrap_posts'] ?? array() ) ) ) ),
            );
        }

        if ( $is_new ) {
            // New records default to active — deactivate all others first so
            // only one takeover is ever live at a time.
            if ( $prev_active ) {
                foreach ( $takeovers as &$t ) { $t['active'] = false; }
                unset( $t );
            }
            $takeovers[] = $record;
            $notice = 'Takeover "' . esc_html( $record['name'] ) . '" created.';
        } else {
            foreach ( $takeovers as &$t ) {
                if ( $t['id'] === $to_id ) { $t = $record; break; }
            }
            unset( $t );
            $notice = 'Takeover "' . esc_html( $record['name'] ) . '" updated.';
        }
        update_option( 'engam_v2_takeovers', $takeovers );
        $edit_id = '';
    }

    if ( 'toggle' === $action && isset( $_POST['engam_to_id'] ) ) {
        $tid        = sanitize_text_field( wp_unslash( $_POST['engam_to_id'] ) );
        $activating = false;
        foreach ( $takeovers as &$t ) {
            if ( $t['id'] === $tid ) {
                $t['active'] = empty( $t['active'] );
                $activating  = $t['active'];
                break;
            }
        }
        unset( $t );
        // Only one takeover can be live at a time — deactivate all others when activating.
        if ( $activating ) {
            foreach ( $takeovers as &$t ) {
                if ( $t['id'] !== $tid ) $t['active'] = false;
            }
            unset( $t );
        }
        update_option( 'engam_v2_takeovers', $takeovers );
        $notice = $activating ? 'Takeover activated. All others have been deactivated.' : 'Takeover deactivated.';
    }

    if ( 'delete' === $action && isset( $_POST['engam_to_id'] ) ) {
        $tid = sanitize_text_field( wp_unslash( $_POST['engam_to_id'] ) );
        $takeovers = array_values( array_filter( $takeovers, function( $t ) use ( $tid ) {
            return $t['id'] !== $tid;
        } ) );
        update_option( 'engam_v2_takeovers', $takeovers );
        $notice = 'Takeover deleted.';
    }
}

// Find the record being edited
$editing = null;
if ( $edit_id ) {
    foreach ( $takeovers as $t ) {
        if ( $t['id'] === $edit_id ) { $editing = $t; break; }
    }
}

// Build a lookup of GAM line items keyed by sponsor/campaign ID for masthead schedule display.
// Build lookup of GAM line items keyed by their numeric GAM ID.
$gam_line_item_map = array();
$cached_li = get_transient( 'engam_v2_line_items' );
if ( is_array( $cached_li ) ) {
    foreach ( $cached_li as $li ) {
        if ( ! empty( $li['gam_id'] ) ) {
            $gam_line_item_map[ (string) $li['gam_id'] ] = $li;
        }
        // Fallback: also index by the id field for backwards compatibility.
        if ( ! empty( $li['id'] ) ) {
            $gam_line_item_map[ strtolower( (string) $li['id'] ) ] = $li;
        }
    }
}

// Helper: compute status label
function engam_to_status( $t ) {
    // An entry with no GAM line item has nothing to deliver — it can never be
    // "Active" no matter what the active flag says.
    if ( empty( $t['gam_line_item_id'] ) ) return array( 'inactive', 'No Line Item' );
    if ( empty( $t['active'] ) ) return array( 'inactive', 'Inactive' );
    $now   = current_time( 'timestamp' );
    $start = ! empty( $t['schedule_start'] ) ? strtotime( $t['schedule_start'] ) : 0;
    $end   = ! empty( $t['schedule_end'] )   ? strtotime( $t['schedule_end'] )   : 0;
    if ( $start && $now < $start ) return array( 'scheduled', 'Scheduled' );
    if ( $end   && $now > $end   ) return array( 'expired',   'Expired'   );
    return array( 'active', 'Active' );
}


include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-shared-styles.php';
?>
<style>
.engam-type-badge-masthead {
    display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;
    background:#dbeafe;color:#1d4ed8;text-transform:uppercase;letter-spacing:.04em;
}
.engam-type-badge-wrap {
    display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;
    background:#1e1e2d;color:#e2e8f0;text-transform:uppercase;letter-spacing:.04em;
}
.engam-type-card {
    border:2px solid #deded8;border-radius:10px;padding:20px 18px;cursor:pointer;
    background:#fff;text-align:left;transition:border-color .15s,box-shadow .15s;width:100%;
}
.engam-type-card:hover,.engam-type-card.selected {
    border-color:#1d4ed8;box-shadow:0 0 0 3px rgba(29,78,216,.12);
}
.engam-type-card h4 { margin:0 0 6px;font-size:15px; }
.engam-type-card p  { margin:0;font-size:13px;color:#666;line-height:1.4; }
/* Desktop: badges inside the name cell are redundant — dedicated Type/Status columns show them */
#engam-v2-wrap .eg-table-card .egm-top-row .engam-type-badge-masthead,
#engam-v2-wrap .eg-table-card .egm-top-row .engam-type-badge-wrap,
#engam-v2-wrap .eg-table-card .egm-top-row .eg-badge { display: none; }
@media(max-width:900px){
    /* On mobile, show the inline badges and hide the now-redundant dedicated cells */
    #engam-v2-wrap .eg-table-card .egm-top-row .engam-type-badge-masthead,
    #engam-v2-wrap .eg-table-card .egm-top-row .engam-type-badge-wrap,
    #engam-v2-wrap .eg-table-card .egm-top-row .eg-badge { display: inline-block; }
    #engam-v2-wrap .eg-table-card td:nth-child(2),
    #engam-v2-wrap .eg-table-card td:nth-child(4) { display: none; }
    /* eg-head stacks on mobile so the Add New button doesn't crowd the title */
    #engam-v2-wrap .eg-head { flex-wrap: wrap; gap: 12px; }
    #engam-v2-wrap .eg-head #engam-to-add-btn { width: 100%; text-align: center; }
}
</style>
<div id="engam-v2-wrap">

<!-- MASTHEAD -->
<section class="eg-mast">
    <div class="eg-brand">
        <div class="eg-logo">EN</div>
        <div class="eg-brand-text">
            <small>Google Ad Manager &mdash; v2</small>
            <h1>Takeovers</h1>
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

<!-- TABLE OF EXISTING TAKEOVERS -->
<div class="eg-card" style="margin-top:18px;margin-bottom:18px">
    <div class="eg-head">
        <div>
            <h2>Takeover List</h2>
            <p>Mastheads and branded wrap takeovers in one list. Only one entry can be active at a time — activating a new one automatically deactivates the current one.</p>
        </div>
        <button class="eg-btn" id="engam-to-add-btn" onclick="engamShowTypePicker()">+ Add New</button>
    </div>

    <?php if ( empty( $takeovers ) ) : ?>
        <div class="eg-empty">
            <strong>No takeovers yet</strong>
            Click "+ Add New" above to create a Masthead or Wrap Takeover.
        </div>
    <?php else : ?>
        <table class="eg-table eg-table-card">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Schedule</th>
                    <th>Status</th>
                    <th>GAM</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $engam_list_net = preg_replace( '/[^0-9]/', '', get_option( 'equinenetwork_gam_v2_id', '' ) );
                foreach ( $takeovers as $to ) :
                    list( $badge_class, $badge_label ) = engam_to_status( $to );
                    $to_type   = isset( $to['type'] ) ? $to['type'] : 'wrap';

                    // Pull flight dates from GAM line item if available (mastheads and GAM-linked wraps).
                    $gam_li_key = ! empty( $to['gam_line_item_id'] ) ? (string) $to['gam_line_item_id'] : '';
                    if ( $gam_li_key && isset( $gam_line_item_map[ $gam_li_key ] ) ) {
                        $li        = $gam_line_item_map[ $gam_li_key ];
                        $start_fmt = ! empty( $li['start_time'] ) ? date_i18n( 'M j, Y', strtotime( $li['start_time'] ) ) : '—';
                        $end_fmt   = ! empty( $li['end_time'] )   ? date_i18n( 'M j, Y', strtotime( $li['end_time'] ) )   : 'No end';
                    } else {
                        $start_fmt = ! empty( $to['schedule_start'] ) ? date_i18n( 'M j, Y', strtotime( $to['schedule_start'] ) ) : '—';
                        $end_fmt   = ! empty( $to['schedule_end'] )   ? date_i18n( 'M j, Y', strtotime( $to['schedule_end'] ) )   : '—';
                    }
                ?>
                <tr>
                    <td>
                        <div class="egm-top-row">
                            <div class="eg-campaign-name"><?php echo esc_html( $to['name'] ); ?></div>
                            <?php if ( 'masthead' === $to_type ) : ?>
                                <span class="engam-type-badge-masthead">Masthead</span>
                            <?php else : ?>
                                <span class="engam-type-badge-wrap">Wrap</span>
                            <?php endif; ?>
                            <span class="eg-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_label ); ?></span>
                        </div>
                        <div class="eg-campaign-id" style="margin-top:2px"><?php echo esc_html( $to['id'] ); ?></div>
                    </td>
                    <td data-label="Type">
                        <?php if ( 'masthead' === $to_type ) : ?>
                            <span class="engam-type-badge-masthead">Masthead</span>
                        <?php else : ?>
                            <span class="engam-type-badge-wrap">Wrap Takeover</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Schedule" style="font-size:12px;color:#555"><?php echo esc_html( $start_fmt ); ?> &rarr; <?php echo esc_html( $end_fmt ); ?></td>
                    <td data-label="Status"><span class="eg-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_label ); ?></span></td>
                    <td data-label="GAM">
                        <?php if ( $gam_li_key && $engam_list_net ) : ?>
                        <a href="https://admanager.google.com/<?php echo esc_attr( $engam_list_net ); ?>#delivery/line_item/detail/line_item_id=<?php echo rawurlencode( $gam_li_key ); ?>"
                           target="_blank" rel="noopener" class="eg-btn sm" style="background:#d0ff00;color:#111;border-color:#d0ff00">View in GAM ↗</a>
                        <?php else : ?>
                        <span style="color:#bbb;font-size:12px">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="eg-actions-cell">
                            <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'engam-v2-takeovers', 'edit_to' => $to['id'] ), admin_url( 'admin.php' ) ) ); ?>"
                               class="eg-btn sm dark">Edit</a>
                            <form method="post" action="" style="display:inline">
                                <?php wp_nonce_field( 'engam_v2_to_save', 'engam_v2_to_nonce' ); ?>
                                <input type="hidden" name="engam_to_action" value="toggle">
                                <input type="hidden" name="engam_to_id" value="<?php echo esc_attr( $to['id'] ); ?>">
                                <button type="submit" class="eg-btn sm dark">
                                    <?php echo empty( $to['active'] ) ? 'Activate' : 'Deactivate'; ?>
                                </button>
                            </form>
                            <form method="post" action="" style="display:inline"
                                onsubmit="return confirm('Delete &laquo;<?php echo esc_js( $to['name'] ); ?>&raquo;? This cannot be undone.')">
                                <?php wp_nonce_field( 'engam_v2_to_save', 'engam_v2_to_nonce' ); ?>
                                <input type="hidden" name="engam_to_action" value="delete">
                                <input type="hidden" name="engam_to_id" value="<?php echo esc_attr( $to['id'] ); ?>">
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

<!-- TYPE PICKER (shown before the form when adding new) -->
<div id="engam-to-type-picker" style="display:none">
<div class="eg-card" style="margin-top:18px">
    <div class="eg-head">
        <div>
            <h2>What type of ad do you want to create?</h2>
            <p>Choose the format that fits your campaign.</p>
        </div>
        <button class="eg-btn dark" style="border-color:#111" onclick="engamHideTypePicker()">Cancel</button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;padding:24px">
        <button type="button" class="engam-type-card" data-type="masthead" onclick="engamSelectType('masthead')">
            <h4>Masthead</h4>
            <p>Full-width fluid ad above the header. Pulls creative sizes from GAM automatically. Targets specific pages or the homepage.</p>
        </button>
        <button type="button" class="engam-type-card" data-type="wrap" onclick="engamSelectType('wrap')">
            <h4>Wrap Takeover</h4>
            <p>Full-page branded takeover with sidebar panels, header image, and click URL. Works across all pages.</p>
        </button>
    </div>
</div>
</div>

<!-- ADD / EDIT FORM -->
<div id="engam-to-form-wrap" style="<?php echo $editing ? '' : 'display:none'; ?>">
<div class="eg-card" style="margin-top:18px">
    <div class="eg-head">
        <div>
            <h2 id="engam-form-title"><?php echo $editing ? 'Edit' : 'New'; ?> <span id="engam-form-type-label"><?php
                if ( $editing ) {
                    echo ( isset( $editing['type'] ) && 'masthead' === $editing['type'] ) ? 'Masthead' : 'Wrap Takeover';
                }
            ?></span></h2>
            <p>Configure all settings for this entry.</p>
        </div>
        <?php if ( ! $editing ) : ?>
        <button class="eg-btn dark" style="border-color:#111" onclick="engamCancelForm()">Cancel</button>
        <?php else : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-takeovers' ) ); ?>" class="eg-btn dark" style="border-color:#111">Cancel</a>
        <?php endif; ?>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field( 'engam_v2_to_save', 'engam_v2_to_nonce' ); ?>
        <input type="hidden" name="engam_to_action" value="save">
        <input type="hidden" name="engam_to_id" value="<?php echo $editing ? esc_attr( $editing['id'] ) : ''; ?>">
        <input type="hidden" name="engam_to_type" id="engam-to-type-field" value="<?php echo $editing ? esc_attr( isset( $editing['type'] ) ? $editing['type'] : 'wrap' ) : 'wrap'; ?>">

        <!-- COMMON: Ad Name -->
        <div class="eg-form-section">
            <h3>Basic Information</h3>
            <div class="eg-settings-field">
                <label for="engam-to-name">Ad Name <span style="color:#cc0000">*</span></label>
                <input class="eg-input" type="text" name="engam_to_name" id="engam-to-name"
                    value="<?php echo $editing ? esc_attr( $editing['name'] ) : ''; ?>"
                    placeholder="e.g. OutSmart Fly Spray — June 2026" required>
            </div>
        </div>

        <!-- MASTHEAD FIELDS -->
        <div id="engam-fields-masthead" style="display:<?php echo ( $editing && isset( $editing['type'] ) && 'masthead' === $editing['type'] ) ? 'block' : 'none'; ?>">

            <div class="eg-form-section">
                <h3>Masthead Settings</h3>
                <input type="hidden" name="engam_to_slotname" value="homepagetakeover">

                <!-- GAM Line Item Picker -->
                <?php
                $cached_li_all = get_transient( 'engam_v2_line_items' );
                $current_gam_id = ( $editing && isset( $editing['type'] ) && 'masthead' === $editing['type'] ) ? ( $editing['gam_line_item_id'] ?? '' ) : '';
                $engam_net_code = preg_replace( '/[^0-9]/', '', get_option( 'equinenetwork_gam_v2_id', '' ) );
                $mh_gam_link    = ( $current_gam_id && $engam_net_code )
                    ? 'https://admanager.google.com/' . $engam_net_code . '#delivery/line_item/detail/line_item_id=' . rawurlencode( $current_gam_id )
                    : '';
                ?>
                <div class="eg-settings-field" style="margin-bottom:18px">
                    <label for="engam-gam-li-search">GAM Line Item <span style="font-weight:400;color:#888">(links schedule from GAM)</span></label>
                    <input type="hidden" name="engam_to_gam_line_item_id" id="engam-gam-li-id" value="<?php echo esc_attr( $current_gam_id ); ?>">
                    <input type="text" id="engam-gam-li-search" class="eg-input" autocomplete="off"
                        placeholder="Search by name or GAM ID…"
                        value="<?php
                            if ( $current_gam_id && is_array( $cached_li_all ) ) {
                                foreach ( $cached_li_all as $cli ) {
                                    if ( isset( $cli['gam_id'] ) && (string) $cli['gam_id'] === (string) $current_gam_id ) {
                                        echo esc_attr( $cli['name'] . ' (' . $cli['gam_id'] . ')' );
                                        break;
                                    }
                                }
                            }
                        ?>">
                    <div id="engam-gam-li-results" style="display:none;border:1px solid #deded8;border-top:none;border-radius:0 0 6px 6px;background:#fff;max-height:220px;overflow-y:auto;position:relative;z-index:100"></div>
                    <p class="eg-hint">Select the GAM line item that delivers this masthead — flight dates will display automatically.
                        <a id="engam-gam-li-link" href="<?php echo esc_url( $mh_gam_link ); ?>" target="_blank" rel="noopener"
                           style="<?php echo $mh_gam_link ? '' : 'display:none;'; ?>font-weight:700;text-decoration:none;margin-left:6px">View in GAM ↗</a>
                    </p>
                    <?php if ( ! is_array( $cached_li_all ) || empty( $cached_li_all ) ) : ?>
                    <p style="color:#cc8800;font-size:12px;margin:4px 0 0">No line items cached — go to <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-settings' ) ); ?>">Settings</a> and click Refresh Cache first.</p>
                    <?php endif; ?>
                </div>
                <!-- Line items data for JS -->
                <script>
                window.engamLineItems = <?php echo wp_json_encode( is_array( $cached_li_all ) ? array_map( function( $li ) {
                    return array( 'gam_id' => $li['gam_id'] ?? '', 'name' => $li['name'] ?? '', 'start' => $li['start_time'] ?? '', 'end' => $li['end_time'] ?? '' );
                }, $cached_li_all ) : array() ); ?>;
                (function() {
                    var searchEl = document.getElementById('engam-gam-li-search');
                    var idEl     = document.getElementById('engam-gam-li-id');
                    var results  = document.getElementById('engam-gam-li-results');
                    var linkEl   = document.getElementById('engam-gam-li-link');
                    var netCode  = '<?php echo esc_js( $engam_net_code ); ?>';
                    if ( ! searchEl ) return;
                    function updateLink(id) {
                        if (!linkEl) return;
                        if (id && netCode) {
                            linkEl.href = 'https://admanager.google.com/' + netCode + '#delivery/line_item/detail/line_item_id=' + encodeURIComponent(id);
                            linkEl.style.display = '';
                        } else { linkEl.style.display = 'none'; }
                    }
                    function showResults( q ) {
                        q = q.toLowerCase();
                        if ( ! q ) { results.style.display = 'none'; return; }
                        var matches = window.engamLineItems.filter( function(li) {
                            return li.name.toLowerCase().indexOf(q) > -1 || (li.gam_id && li.gam_id.toString().indexOf(q) > -1);
                        } ).slice(0, 30);
                        if ( ! matches.length ) { results.innerHTML = '<div style="padding:10px 12px;color:#888;font-size:13px">No results</div>'; results.style.display = 'block'; return; }
                        results.innerHTML = matches.map( function(li) {
                            var s = li.start ? new Date(li.start).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—';
                            var e = li.end   ? new Date(li.end).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : 'No end';
                            return '<div class="engam-gam-li-opt" data-id="'+li.gam_id+'" data-label="'+li.name.replace(/"/g,'&quot;')+' ('+li.gam_id+')" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f0f0ea;font-size:13px">'
                                + '<strong>'+li.name+'</strong><br>'
                                + '<span style="color:#888;font-size:11px">ID: '+li.gam_id+' &nbsp;|&nbsp; '+s+' → '+e+'</span>'
                                + '</div>';
                        }).join('');
                        results.style.display = 'block';
                    }
                    searchEl.addEventListener('input', function() { showResults(this.value); });
                    searchEl.addEventListener('focus', function() { if(this.value) showResults(this.value); });
                    results.addEventListener('mousedown', function(e) {
                        var opt = e.target.closest('.engam-gam-li-opt');
                        if ( ! opt ) return;
                        idEl.value = opt.dataset.id;
                        searchEl.value = opt.dataset.label;
                        updateLink(opt.dataset.id);
                        results.style.display = 'none';
                    });
                    searchEl.addEventListener('input', function() { if (!this.value) { idEl.value = ''; updateLink(''); } });
                    document.addEventListener('click', function(e) {
                        if ( e.target !== searchEl ) results.style.display = 'none';
                    });
                })();
                </script>

            </div>

            <div class="eg-form-section">
                <h3>Targeting</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
                    <div class="eg-settings-field" style="padding-top:4px">
                        <label class="eg-toggle">
                            <input type="checkbox" name="engam_to_show_home" value="1" id="engam-to-show-home"
                                <?php checked( $editing && isset( $editing['type'] ) && 'masthead' === $editing['type'] ? ! empty( $editing['show_home'] ) : false ); ?>>
                            <span class="eg-toggle-track"><span class="eg-toggle-thumb"></span></span>
                            Show on Homepage
                        </label>
                        <p class="eg-hint">Display this masthead on the front page / blog index.</p>
                    </div>
                    <div class="eg-settings-field">
                        <label>Additional Pages</label>
                        <?php
                        $selected_pages = ( $editing && isset( $editing['type'] ) && 'masthead' === $editing['type'] && ! empty( $editing['pages'] ) )
                            ? array_map( 'intval', (array) $editing['pages'] )
                            : array();
                        $selected_pages_data = array();
                        if ( $selected_pages ) {
                            foreach ( get_posts( array( 'post__in' => $selected_pages, 'post_type' => array( 'post', 'page' ), 'posts_per_page' => -1, 'post_status' => 'publish' ) ) as $sp ) {
                                $selected_pages_data[] = array( 'id' => $sp->ID, 'title' => $sp->post_title, 'type' => $sp->post_type );
                            }
                        }
                        ?>
                        <div id="engam-to-pages-picker"></div>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            engamPostPicker({
                                wrap:     '#engam-to-pages-picker',
                                name:     'engam_to_pages[]',
                                types:    ['page'],
                                selected: <?php echo wp_json_encode( $selected_pages_data ); ?>
                            });
                        });
                        </script>
                        <p class="eg-hint">Show masthead on these pages. Leave empty to use Homepage toggle only.</p>
                    </div>
                </div>
            </div>

        </div><!-- #engam-fields-masthead -->

        <!-- WRAP TAKEOVER FIELDS -->
        <div id="engam-fields-wrap" style="display:<?php echo ( ! $editing || ! isset( $editing['type'] ) || 'wrap' === $editing['type'] ) ? 'block' : 'none'; ?>">

            <!-- GAM Ad Slots -->
            <div class="eg-form-section">
                <h3>GAM Ad Slots</h3>
                <?php
                $wrap_current_gam_id = ( $editing && isset( $editing['type'] ) && 'wrap' === $editing['type'] ) ? ( $editing['gam_line_item_id'] ?? '' ) : '';
                $wrap_cached_li_all  = get_transient( 'engam_v2_line_items' );
                $wrap_gam_link       = ( $wrap_current_gam_id && $engam_net_code )
                    ? 'https://admanager.google.com/' . $engam_net_code . '#delivery/line_item/detail/line_item_id=' . rawurlencode( $wrap_current_gam_id )
                    : '';
                ?>
                <!-- GAM Line Item Picker for Wrap -->
                <div class="eg-settings-field" style="margin-bottom:18px">
                    <label for="engam-gam-li-wrap-search">GAM Line Item <span style="font-weight:400;color:#888">(links schedule from GAM)</span></label>
                    <input type="hidden" name="engam_to_wrap_gam_line_item_id" id="engam-gam-li-wrap-id" value="<?php echo esc_attr( $wrap_current_gam_id ); ?>">
                    <input type="text" id="engam-gam-li-wrap-search" class="eg-input" autocomplete="off"
                        placeholder="Search by name or GAM ID…"
                        value="<?php
                            if ( $wrap_current_gam_id && is_array( $wrap_cached_li_all ) ) {
                                foreach ( $wrap_cached_li_all as $cli ) {
                                    if ( isset( $cli['gam_id'] ) && (string) $cli['gam_id'] === (string) $wrap_current_gam_id ) {
                                        echo esc_attr( $cli['name'] . ' (' . $cli['gam_id'] . ')' );
                                        break;
                                    }
                                }
                            }
                        ?>">
                    <div id="engam-gam-li-wrap-results" style="display:none;border:1px solid #deded8;border-top:none;border-radius:0 0 6px 6px;background:#fff;max-height:220px;overflow-y:auto;position:relative;z-index:100"></div>
                    <p class="eg-hint">Select the GAM line item that delivers this wrap — flight dates will display automatically.
                        <a id="engam-gam-li-wrap-link" href="<?php echo esc_url( $wrap_gam_link ); ?>" target="_blank" rel="noopener"
                           style="<?php echo $wrap_gam_link ? '' : 'display:none;'; ?>font-weight:700;text-decoration:none;margin-left:6px">View in GAM ↗</a>
                    </p>
                    <?php if ( ! is_array( $wrap_cached_li_all ) || empty( $wrap_cached_li_all ) ) : ?>
                    <p style="color:#cc8800;font-size:12px;margin:4px 0 0">No line items cached — go to <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-settings' ) ); ?>">Settings</a> and click Refresh Cache first.</p>
                    <?php endif; ?>
                </div>
                <script>
                (function(){
                    var searchEl = document.getElementById('engam-gam-li-wrap-search');
                    var idEl     = document.getElementById('engam-gam-li-wrap-id');
                    var results  = document.getElementById('engam-gam-li-wrap-results');
                    var linkEl   = document.getElementById('engam-gam-li-wrap-link');
                    var netCode  = '<?php echo esc_js( $engam_net_code ); ?>';
                    if (!searchEl) return;
                    function updateLink(id) {
                        if (!linkEl) return;
                        if (id && netCode) {
                            linkEl.href = 'https://admanager.google.com/' + netCode + '#delivery/line_item/detail/line_item_id=' + encodeURIComponent(id);
                            linkEl.style.display = '';
                        } else { linkEl.style.display = 'none'; }
                    }
                    function showWrapResults(q) {
                        q = q.toLowerCase();
                        if (!q) { results.style.display = 'none'; return; }
                        var items = window.engamLineItems || [];
                        var matches = items.filter(function(li) {
                            return li.name.toLowerCase().indexOf(q) > -1 || (li.gam_id && li.gam_id.toString().indexOf(q) > -1);
                        }).slice(0, 30);
                        if (!matches.length) { results.innerHTML = '<div style="padding:10px 12px;color:#888;font-size:13px">No results</div>'; results.style.display = 'block'; return; }
                        results.innerHTML = matches.map(function(li) {
                            var s = li.start ? new Date(li.start).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : '—';
                            var e = li.end   ? new Date(li.end).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : 'No end';
                            return '<div class="engam-gam-li-wrap-opt" data-id="'+li.gam_id+'" data-label="'+li.name.replace(/"/g,'&quot;')+' ('+li.gam_id+')" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f0f0ea;font-size:13px">'
                                + '<strong>'+li.name+'</strong><br>'
                                + '<span style="color:#888;font-size:11px">ID: '+li.gam_id+' &nbsp;|&nbsp; '+s+' → '+e+'</span>'
                                + '</div>';
                        }).join('');
                        results.style.display = 'block';
                    }
                    searchEl.addEventListener('input', function() { showWrapResults(this.value); });
                    searchEl.addEventListener('focus', function() { if(this.value) showWrapResults(this.value); });
                    results.addEventListener('mousedown', function(e) {
                        var opt = e.target.closest('.engam-gam-li-wrap-opt');
                        if (!opt) return;
                        idEl.value = opt.dataset.id;
                        searchEl.value = opt.dataset.label;
                        updateLink(opt.dataset.id);
                        results.style.display = 'none';
                    });
                    searchEl.addEventListener('input', function() { if (!this.value) { idEl.value = ''; updateLink(''); } });
                    document.addEventListener('click', function(e) {
                        if (e.target !== searchEl) results.style.display = 'none';
                    });
                })();
                </script>

                <p class="eg-hint">The plugin automatically detects the creative size GAM serves and scales the panels accordingly — no manual size configuration needed.</p>
            </div>

            <div class="eg-form-section">
                <h3>Appearance</h3>
                <div class="eg-settings-field">
                    <label>Background Color <span style="font-weight:400;color:#888;font-size:12px">(shown while slots load or as fallback)</span></label>
                    <div class="eg-color-row">
                        <input type="color" id="engam-to-color-picker"
                            value="<?php echo ( $editing && isset( $editing['type'] ) && 'wrap' === $editing['type'] ) ? esc_attr( $editing['bg_color'] ?? '#000000' ) : '#000000'; ?>"
                            oninput="document.getElementById('engam-to-color-hex').value=this.value">
                        <input class="eg-input" type="text" name="engam_to_bg_color" id="engam-to-color-hex"
                            value="<?php echo ( $editing && isset( $editing['type'] ) && 'wrap' === $editing['type'] ) ? esc_attr( $editing['bg_color'] ?? '#000000' ) : '#000000'; ?>"
                            placeholder="#000000" maxlength="7" style="width:120px"
                            oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value))document.getElementById('engam-to-color-picker').value=this.value">
                    </div>
                </div>
            </div>

            <!-- TARGETING -->
            <div class="eg-form-section">
                <h3>Page Targeting <span style="font-weight:400;color:#888;font-size:12px">(optional — leave blank to show on all pages)</span></h3>
                <?php
                $is_wrap_edit = ( $editing && isset( $editing['type'] ) && 'wrap' === $editing['type'] );

                // Pre-populate pages.
                $wrap_sel_pages = ( $is_wrap_edit && ! empty( $editing['wrap_pages'] ) ) ? array_map( 'intval', (array) $editing['wrap_pages'] ) : array();
                $wrap_sel_pages_data = array();
                if ( $wrap_sel_pages ) {
                    foreach ( get_posts( array( 'post__in' => $wrap_sel_pages, 'post_type' => array( 'page' ), 'posts_per_page' => -1, 'post_status' => 'publish' ) ) as $sp ) {
                        $wrap_sel_pages_data[] = array( 'id' => $sp->ID, 'title' => $sp->post_title, 'type' => $sp->post_type );
                    }
                }

                // Pre-populate posts.
                $wrap_sel_posts = ( $is_wrap_edit && ! empty( $editing['wrap_posts'] ) ) ? array_map( 'intval', (array) $editing['wrap_posts'] ) : array();
                $wrap_sel_posts_data = array();
                if ( $wrap_sel_posts ) {
                    foreach ( get_posts( array( 'post__in' => $wrap_sel_posts, 'post_type' => array( 'post' ), 'posts_per_page' => -1, 'post_status' => 'publish' ) ) as $sp ) {
                        $wrap_sel_posts_data[] = array( 'id' => $sp->ID, 'title' => $sp->post_title, 'type' => $sp->post_type );
                    }
                }

                // Pre-populate categories (stored as comma-separated slugs).
                $wrap_cats_raw  = $is_wrap_edit ? trim( (string) ( $editing['wrap_cats'] ?? '' ) ) : '';
                $wrap_cat_slugs = $wrap_cats_raw !== '' ? array_filter( array_map( 'trim', explode( ',', $wrap_cats_raw ) ) ) : array();
                $wrap_sel_cats_data = array();
                foreach ( $wrap_cat_slugs as $slug ) {
                    $term = get_term_by( 'slug', $slug, 'category' );
                    $wrap_sel_cats_data[] = array( 'id' => $slug, 'title' => $term ? $term->name : $slug, 'type' => 'category' );
                }
                ?>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px">
                    <div class="eg-settings-field">
                        <label>Show Only on Specific Pages</label>
                        <div id="engam-wrap-pages-picker"></div>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            engamPostPicker({
                                wrap:        '#engam-wrap-pages-picker',
                                name:        'engam_to_wrap_pages[]',
                                types:       ['page'],
                                placeholder: 'Search pages…',
                                selected:    <?php echo wp_json_encode( $wrap_sel_pages_data ); ?>
                            });
                        });
                        </script>
                        <p class="eg-hint">Select specific pages. Leave empty to show on all (unless other targeting is set).</p>
                    </div>
                    <div class="eg-settings-field">
                        <label>Show Only on Specific Posts</label>
                        <div id="engam-wrap-posts-picker"></div>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            engamPostPicker({
                                wrap:        '#engam-wrap-posts-picker',
                                name:        'engam_to_wrap_posts[]',
                                types:       ['post'],
                                placeholder: 'Search posts…',
                                selected:    <?php echo wp_json_encode( $wrap_sel_posts_data ); ?>
                            });
                        });
                        </script>
                        <p class="eg-hint">Select specific posts. Leave empty to show on all (unless other targeting is set).</p>
                    </div>
                    <div class="eg-settings-field">
                        <label>Show Only on Categories</label>
                        <div id="engam-wrap-cats-picker"></div>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            engamPostPicker({
                                wrap:        '#engam-wrap-cats-picker',
                                name:        'engam_to_wrap_cats[]',
                                action:      'engam_v2_search_terms',
                                taxonomy:    'category',
                                placeholder: 'Search categories…',
                                selected:    <?php echo wp_json_encode( $wrap_sel_cats_data ); ?>
                            });
                        });
                        </script>
                        <p class="eg-hint">Takeover only shows on posts in these categories.</p>
                    </div>
                </div>
            </div>

            <!-- VISIBILITY -->
            <div class="eg-form-section">
                <h3>Visibility</h3>
                <div style="display:flex;flex-direction:column;gap:14px">
                    <label class="eg-toggle">
                        <input type="checkbox" name="engam_to_show_to_admins" value="1" id="engam-to-show-admins" <?php checked( ( $editing && isset( $editing['type'] ) && 'wrap' === $editing['type'] ) ? ! empty( $editing['show_to_admins'] ) : false ); ?>>
                        <span class="eg-toggle-track"><span class="eg-toggle-thumb"></span></span>
                        Show full takeover to Admins/Editors
                    </label>
                    <p class="eg-hint" style="margin-top:0">When off, admins/editors see a lime notice bar instead of the full takeover. Activate or deactivate this takeover from the list above.</p>
                </div>
            </div>

        </div><!-- #engam-fields-wrap -->

        <!-- SAVE -->
        <div class="eg-form-section" style="border-top:1px solid #deded8">
            <button type="submit" class="eg-btn" style="padding:14px 32px;font-size:14px">
                <?php echo $editing ? 'Update' : 'Save'; ?>
            </button>
        </div>

    </form>
</div><!-- .eg-card -->
</div><!-- #engam-to-form-wrap -->

</div><!-- .eg-content -->
</div><!-- #engam-v2-wrap -->

<script>
(function(){
    // ---- Type picker / form toggling ----
    window.engamShowTypePicker = function() {
        document.getElementById('engam-to-add-btn').style.display = 'none';
        document.getElementById('engam-to-type-picker').style.display = 'block';
        document.getElementById('engam-to-form-wrap').style.display = 'none';
    };
    window.engamHideTypePicker = function() {
        document.getElementById('engam-to-type-picker').style.display = 'none';
        document.getElementById('engam-to-add-btn').style.display = 'inline-block';
    };
    window.engamSelectType = function(type) {
        document.getElementById('engam-to-type-picker').style.display = 'none';
        document.getElementById('engam-to-form-wrap').style.display = 'block';
        engamApplyType(type);
        var nameEl = document.getElementById('engam-to-name');
        if (nameEl) nameEl.focus();
    };
    window.engamCancelForm = function() {
        document.getElementById('engam-to-form-wrap').style.display = 'none';
        document.getElementById('engam-to-type-picker').style.display = 'none';
        document.getElementById('engam-to-add-btn').style.display = 'inline-block';
    };

    function engamApplyType(type) {
        document.getElementById('engam-to-type-field').value = type;
        var labelMap = { masthead: 'Masthead', wrap: 'Wrap Takeover' };
        var labelEl = document.getElementById('engam-form-type-label');
        if (labelEl) labelEl.textContent = labelMap[type] || type;
        document.getElementById('engam-fields-masthead').style.display = (type === 'masthead') ? 'block' : 'none';
        document.getElementById('engam-fields-wrap').style.display     = (type === 'wrap')     ? 'block' : 'none';
    }

    // On page load: if editing, apply current type
    <?php if ( $editing ) : ?>
    var editingType = <?php echo wp_json_encode( isset( $editing['type'] ) ? $editing['type'] : 'wrap' ); ?>;
    engamApplyType(editingType);
    document.getElementById('engam-to-form-wrap').style.display = 'block';
    var addBtn = document.getElementById('engam-to-add-btn');
    if (addBtn) addBtn.style.display = 'none';
    <?php endif; ?>

    // ---- WP Media picker ----
    document.querySelectorAll('.engam-media-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var targetId  = btn.getAttribute('data-target');
            var previewId = btn.getAttribute('data-preview');
            var title     = btn.getAttribute('data-title');
            var frame = wp.media({
                title:    title || 'Select Image',
                button:   { text: 'Use this image' },
                multiple: false,
                library:  { type: 'image' }
            });
            frame.on('select', function(){
                var attachment = frame.state().get('selection').first().toJSON();
                document.getElementById(targetId).value = attachment.url;
                var preview = document.getElementById(previewId);
                preview.src = attachment.url;
                preview.style.display = 'block';
                preview.classList.add('has-img');
                var removeBtn = document.getElementById(targetId + '-remove');
                if(removeBtn) removeBtn.style.display = 'inline-block';
            });
            frame.open();
        });
    });

    window.engamRemoveImg = function(inputId, previewId, removeBtnId){
        document.getElementById(inputId).value = '';
        var preview = document.getElementById(previewId);
        preview.src = '';
        preview.style.display = 'none';
        preview.classList.remove('has-img');
        document.getElementById(removeBtnId).style.display = 'none';
    };


})();
</script>
