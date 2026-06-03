<?php if ( ! defined( 'WPINC' ) ) die;

$gam_id         = get_option( 'equinenetwork_gam_v2_id', '' );
$id_active      = ! empty( $gam_id );
$campaigns      = get_option( 'equinenetwork_gam_v2_campaigns', array() );
if ( ! is_array( $campaigns ) ) $campaigns = array();
$takeovers      = get_option( 'engam_v2_takeovers', array() );
if ( ! is_array( $takeovers ) ) $takeovers = array();
$carousels      = get_option( 'engam_v2_carousels', array() );
if ( ! is_array( $carousels ) ) $carousels = array();

// Resolve carousel placements for the overview.
$carousel_rows   = array();
$placed_carousels = 0;
foreach ( $carousels as $car ) {
    $usage = class_exists( 'Equinenetwork_Gam_V2_Carousel_Render' )
        ? Equinenetwork_Gam_V2_Carousel_Render::usage( $car['id'] ?? '' )
        : array();
    if ( ! empty( $usage ) ) $placed_carousels++;
    $carousel_rows[] = array(
        'name'  => $car['name'] ?? '(untitled)',
        'id'    => $car['id'] ?? '',
        'usage' => $usage,
    );
}

$all_stackers   = get_option( 'engam_v2_stackers_list', array() );
if ( ! is_array( $all_stackers ) ) $all_stackers = array();
$stacker_active = count( array_filter( $all_stackers, function( $s ) { return ! empty( $s['active'] ); } ) );

$all_lbs        = Equinenetwork_Gam_V2_Leaderboard::get_all();
$lb_active      = count( array_filter( $all_lbs, function( $lb ) { return ! empty( $lb['active'] ); } ) );
$lb_header_on   = count( array_filter( $all_lbs, function( $lb ) { return ! empty( $lb['active'] ) && ( ! isset( $lb['position'] ) || $lb['position'] === 'header' ); } ) ) > 0;
$lb_footer_on   = count( array_filter( $all_lbs, function( $lb ) { return ! empty( $lb['active'] ) && isset( $lb['position'] ) && $lb['position'] === 'footer'; } ) ) > 0;

$all_takeovers   = get_option( 'engam_v2_takeovers', array() );
$masthead_on     = count( array_filter( $all_takeovers, function( $t ) { return ! empty( $t['active'] ) && ( ( $t['type'] ?? 'wrap' ) === 'masthead' ); } ) ) > 0;
$wrap_on         = count( array_filter( $all_takeovers, function( $t ) { return ! empty( $t['active'] ) && ( ( $t['type'] ?? 'wrap' ) === 'wrap' ); } ) ) > 0;

$api            = new Equinenetwork_Gam_V2_API();
$api_configured = $api->is_configured();
$cached_items   = $api_configured ? get_transient( 'engam_v2_line_items' ) : false;
$api_item_count = is_array( $cached_items ) ? count( $cached_items ) : 0;
$active_count   = $api_configured && $api_item_count > 0
    ? $api_item_count
    : count( array_filter( $campaigns, function( $c ) { return ! empty( $c['active'] ); } ) );
$sponsor_options  = $api->get_sponsor_options();
$sponsor_count    = count( $sponsor_options );
$active_takeovers = count( array_filter( $takeovers, function( $t ) { return ! empty( $t['active'] ); } ) );

$now              = current_time( 'timestamp' );
$active_to_rows   = array();
foreach ( $takeovers as $to ) {
    if ( empty( $to['active'] ) ) continue;
    $start = ! empty( $to['schedule_start'] ) ? strtotime( $to['schedule_start'] ) : 0;
    $end   = ! empty( $to['schedule_end'] )   ? strtotime( $to['schedule_end'] )   : 0;
    if ( $start && $now < $start )      $state = 'Scheduled';
    elseif ( $end && $now > $end )      $state = 'Expired';
    else                                $state = 'Live now';
    $active_to_rows[] = array(
        'name'  => $to['name'] ?? '(untitled)',
        'type'  => ( $to['type'] ?? 'wrap' ) === 'masthead' ? 'Masthead' : 'Wrap Takeover',
        'state' => $state,
        'start' => $start ? date_i18n( 'M j, Y g:i a', $start ) : 'Now',
        'end'   => $end ? date_i18n( 'M j, Y g:i a', $end ) : 'No end date',
    );
}

if ( ! function_exists( 'engam_v2_walk_ad_slots' ) ) {
    function engam_v2_walk_ad_slots( $elements, &$counts ) {
        if ( ! is_array( $elements ) ) return;
        foreach ( $elements as $el ) {
            if ( isset( $el['widgetType'] ) && $el['widgetType'] === 'engam_v2_ad_slot' ) {
                $preset = isset( $el['settings']['ad_preset'] ) ? $el['settings']['ad_preset'] : 'leaderboard';
                if ( ! isset( $counts[ $preset ] ) ) $counts[ $preset ] = 0;
                $counts[ $preset ]++;
            }
            if ( ! empty( $el['elements'] ) ) {
                engam_v2_walk_ad_slots( $el['elements'], $counts );
            }
        }
    }
}

global $wpdb;
delete_transient( 'engam_v2_adslot_counts' );
$ad_slot_counts = false;
if ( false === $ad_slot_counts ) {
    $ad_slot_counts = array(
        'leaderboard' => 0, 'medium_rect' => 0, 'half_page' => 0,
        'med_half'    => 0, 'takeover'    => 0, 'custom'    => 0,
    );
    try {
        $rows = $wpdb->get_col(
            "SELECT m.meta_value
               FROM {$wpdb->postmeta} m
               INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
              WHERE m.meta_key = '_elementor_data'
                AND m.meta_value LIKE '%engam_v2_ad_slot%'
                AND p.post_status IN ( 'publish', 'private' )
                AND p.post_type NOT IN ( 'revision' )"
        );
        if ( is_array( $rows ) ) {
            foreach ( $rows as $json ) {
                $data = json_decode( $json, true );
                if ( is_array( $data ) ) {
                    engam_v2_walk_ad_slots( $data, $ad_slot_counts );
                }
            }
        }
    } catch ( \Throwable $e ) {
        // Non-fatal — counts stay at zero if the query fails.
    }
    set_transient( 'engam_v2_adslot_counts', $ad_slot_counts, 5 * MINUTE_IN_SECONDS );
}
$ad_slot_total = array_sum( $ad_slot_counts );

include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-shared-styles.php';
?>
<div id="engam-v2-wrap">

<!-- HEADER — full bleed -->
<section class="eg-mast">
    <div class="eg-brand">
        <div class="eg-logo">EN</div>
        <div class="eg-brand-text">
            <small>Google Ad Manager &mdash; v2</small>
            <h1>EN Ads</h1>
        </div>
    </div>
    <div class="eg-mast-actions">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-campaigns' ) ); ?>" class="eg-btn dark">+ Add Campaign</a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-takeovers' ) ); ?>" class="eg-btn ghost">Takeovers</a>
    </div>
</section>

<!-- INNER CONTENT -->
<div class="eg-content">

<!-- GAM NETWORK ID FULL-WIDTH CARD -->
<div class="eg-card" style="margin-top:18px;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap">
    <div>
        <div style="font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:900;color:#555;margin-bottom:6px">GAM Network ID</div>
        <div style="font-size:<?php echo $id_active ? '20px' : '28px'; ?>;font-weight:900;letter-spacing:<?php echo $id_active ? '-.5px' : '-1px'; ?>;line-height:1;color:#050505">
            <?php echo $id_active ? esc_html( $gam_id ) : '—'; ?>
        </div>
    </div>
    <div>
        <?php if ( $id_active ) : ?>
            <span class="eg-ok">Configured</span>
        <?php else : ?>
            <span class="eg-na">Not set — <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-settings' ) ); ?>">see Settings</a></span>
        <?php endif; ?>
    </div>
</div>

<!-- STATS -->
<section class="eg-stats" style="margin-top:14px">
    <div class="eg-stat">
        <small>Total Sponsor IDs</small>
        <strong><?php echo $sponsor_count; ?></strong>
    </div>
    <div class="eg-stat">
        <small>GAM Line Items</small>
        <strong><?php echo $active_count; ?></strong>
        <?php if ( $api_configured ) : ?>
            <span class="eg-ok">Live from GAM API</span>
        <?php endif; ?>
    </div>
    <div class="eg-stat">
        <small>GAM API</small>
        <strong><?php echo $api_configured ? '✓' : '—'; ?></strong>
        <?php if ( $api_configured ) : ?>
            <span class="eg-ok">Connected</span>
        <?php else : ?>
            <span class="eg-na">Not configured</span>
        <?php endif; ?>
    </div>
</section>

<!-- AUTO-INJECTED SLOTS -->
<div style="margin:0 0 8px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:900;color:#555">Auto-Injected Slots</div>
<div class="eg-mini-grid">
    <?php
    $auto_slots = array(
        array( 'label' => 'Masthead',             'count' => (int) $masthead_on,    'link' => 'engam-v2-takeovers' ),
        array( 'label' => 'Wrap Takeover',        'count' => (int) $wrap_on,        'link' => 'engam-v2-takeovers' ),
        array( 'label' => 'Header Leaderboard',   'count' => (int) $lb_header_on,   'link' => 'engam-v2-leaderboards' ),
        array( 'label' => 'Footer Leaderboard',   'count' => (int) $lb_footer_on,   'link' => 'engam-v2-leaderboards' ),
        array( 'label' => 'Stacker',              'count' => (int) $stacker_active, 'link' => 'engam-v2-stackers' ),
    );
    foreach ( $auto_slots as $slot ) :
    ?>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slot['link'] ) ); ?>" style="text-decoration:none">
    <div style="background:#fff;border:1px solid #deded8;padding:12px 14px;display:flex;align-items:baseline;justify-content:space-between;gap:10px">
        <small style="font-size:11px;letter-spacing:.06em;text-transform:uppercase;font-weight:900;color:#555;line-height:1.2"><?php echo esc_html( $slot['label'] ); ?></small>
        <strong style="font-size:24px;letter-spacing:-1px;line-height:1;color:<?php echo $slot['count'] > 0 ? '#129b6f' : '#bbb'; ?>"><?php echo $slot['count'] > 0 ? 'On' : 'Off'; ?></strong>
    </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- AD PLACEMENTS -->
<div style="margin:0 0 8px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:900;color:#555">Ad Placements &mdash; Elementor (<?php echo (int) $ad_slot_total; ?> total)</div>
<div class="eg-mini-grid">
    <?php
    $med_half = isset( $ad_slot_counts['med_half'] ) ? (int) $ad_slot_counts['med_half'] : 0;
    $by_size  = array(
        'Leaderboard'       => (int) ( $ad_slot_counts['leaderboard'] ?? 0 ) + $lb_active,
        'Medium Rectangle'  => (int) ( $ad_slot_counts['medium_rect'] ?? 0 ) + $med_half,
        'Half Page'         => (int) ( $ad_slot_counts['half_page'] ?? 0 ) + $med_half,
        'Takeover'          => $active_takeovers,
    );
    if ( ! empty( $ad_slot_counts['custom'] ) ) {
        $by_size['Custom Size'] = (int) $ad_slot_counts['custom'];
    }
    foreach ( $by_size as $label => $count ) :
    ?>
    <div style="background:#fff;border:1px solid #deded8;padding:12px 14px;display:flex;align-items:baseline;justify-content:space-between;gap:10px">
        <small style="font-size:11px;letter-spacing:.06em;text-transform:uppercase;font-weight:900;color:#555;line-height:1.2"><?php echo esc_html( $label ); ?></small>
        <strong style="font-size:24px;letter-spacing:-1px;line-height:1;color:<?php echo $count > 0 ? '#050505' : '#bbb'; ?>"><?php echo $count; ?></strong>
    </div>
    <?php endforeach; ?>
</div>

<!-- CARDS -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:18px;margin-bottom:0">

    <div class="eg-card">
        <div class="eg-head">
            <h2>Takeovers</h2>
            <span class="eg-tag"><?php echo $active_takeovers; ?> Active</span>
        </div>
        <div class="eg-body" style="font-size:13px;color:#555">
            <?php if ( empty( $active_to_rows ) ) : ?>
                <p style="margin:0 0 12px">No takeovers are currently active. Build one on the Takeovers page.</p>
            <?php else : ?>
                <table class="eg-table" style="margin-bottom:14px">
                    <thead>
                        <tr><th>Name</th><th>Type</th><th>Window</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $active_to_rows as $r ) :
                            $state_color = $r['state'] === 'Live now' ? '#050505' : '#999';
                            $type_color  = $r['type'] === 'Masthead' ? '#1d4ed8' : '#374151';
                        ?>
                        <tr>
                            <td style="vertical-align:top">
                                <div style="font-weight:700"><?php echo esc_html( $r['name'] ); ?></div>
                                <div style="font-size:11px;color:<?php echo esc_attr( $state_color ); ?>;font-weight:700;text-transform:uppercase;letter-spacing:.04em"><?php echo esc_html( $r['state'] ); ?></div>
                            </td>
                            <td style="vertical-align:top">
                                <span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:<?php echo esc_attr( $type_color ); ?>;color:#fff;white-space:nowrap"><?php echo esc_html( $r['type'] ); ?></span>
                            </td>
                            <td style="vertical-align:top;font-size:12px"><?php echo esc_html( $r['start'] ); ?><br>&rarr; <?php echo esc_html( $r['end'] ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-takeovers' ) ); ?>" class="eg-btn sm">Manage Takeovers</a>
        </div>
    </div>

    <div class="eg-card">
        <div class="eg-head">
            <h2>Carousels</h2>
            <span class="eg-tag"><?php echo (int) $placed_carousels; ?> Placed</span>
        </div>
        <div class="eg-body" style="font-size:13px;color:#555">
            <?php if ( empty( $carousel_rows ) ) : ?>
                <p style="margin:0 0 12px">No carousels built yet.</p>
            <?php else : ?>
                <table class="eg-table" style="margin-bottom:14px">
                    <thead>
                        <tr><th>Carousel</th><th>Placed On</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $carousel_rows as $row ) : ?>
                        <tr>
                            <td style="font-weight:700;vertical-align:top"><?php echo esc_html( $row['name'] ); ?></td>
                            <td>
                                <?php if ( empty( $row['usage'] ) ) : ?>
                                    <span style="color:#999">Not placed</span>
                                <?php else : ?>
                                    <?php foreach ( $row['usage'] as $u ) :
                                        $badge = $u['status'] !== 'publish' ? ' <em style="color:#999;font-style:normal">(' . esc_html( $u['status'] ) . ')</em>' : '';
                                    ?>
                                        <div style="margin-bottom:2px">
                                            <a href="<?php echo esc_url( $u['edit'] ); ?>" style="text-decoration:none;color:#050505;font-weight:700"><?php echo esc_html( $u['title'] ); ?></a><?php echo $badge; // phpcs:ignore ?>
                                            <?php if ( $u['view'] ) : ?><a href="<?php echo esc_url( $u['view'] ); ?>" target="_blank" rel="noopener" title="View" style="text-decoration:none;margin-left:4px">↗</a><?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-carousels' ) ); ?>" class="eg-btn sm">Manage Carousels</a>
        </div>
    </div>

    <div class="eg-card">
        <div class="eg-head">
            <h2>Leaderboards</h2>
            <span class="eg-tag"><?php echo $lb_active; ?> Active</span>
        </div>
        <div class="eg-body" style="font-size:13px;color:#555">
            <?php if ( empty( $all_lbs ) ) : ?>
                <p style="margin:0 0 12px">No leaderboards configured yet.</p>
            <?php else : ?>
                <table class="eg-table" style="margin-bottom:14px">
                    <thead><tr><th>Name</th><th>Position</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ( $all_lbs as $lb ) :
                            $pos = isset( $lb['position'] ) && $lb['position'] === 'footer' ? 'Footer' : 'Header';
                            $on  = ! empty( $lb['active'] );
                        ?>
                        <tr>
                            <td style="font-weight:700"><?php echo esc_html( $lb['name'] ?: '(untitled)' ); ?></td>
                            <td><?php echo esc_html( $pos ); ?></td>
                            <td><span class="eg-badge <?php echo $on ? 'active' : 'inactive'; ?>"><?php echo $on ? 'Active' : 'Off'; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-leaderboards' ) ); ?>" class="eg-btn sm">Manage Leaderboards</a>
        </div>
    </div>

    <div class="eg-card">
        <div class="eg-head">
            <h2>Stackers</h2>
            <span class="eg-tag"><?php echo $stacker_active; ?> Active</span>
        </div>
        <div class="eg-body" style="font-size:13px;color:#555">
            <?php if ( empty( $all_stackers ) ) : ?>
                <p style="margin:0 0 12px">No stackers configured yet.</p>
            <?php else : ?>
                <table class="eg-table" style="margin-bottom:14px">
                    <thead><tr><th>Name</th><th>Slot</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ( $all_stackers as $sk ) :
                            $on = ! empty( $sk['active'] );
                        ?>
                        <tr>
                            <td style="font-weight:700"><?php echo esc_html( $sk['name'] ?: '(untitled)' ); ?></td>
                            <td><code><?php echo esc_html( $sk['slotname'] ?? 'stacker' ); ?></code></td>
                            <td><span class="eg-badge <?php echo $on ? 'active' : 'inactive'; ?>"><?php echo $on ? 'Active' : 'Off'; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-stackers' ) ); ?>" class="eg-btn sm">Manage Stackers</a>
        </div>
    </div>

</div><!-- cards grid -->

</div><!-- .eg-content -->

<!-- GUIDE — full bleed -->
<div class="eg-card black eg-full-bleed" style="margin-top:18px">
    <div class="eg-head" style="border-color:#2c2c2c">
        <span class="eg-tag">Guide</span>
    </div>
    <div class="eg-body" style="font-size:13px;line-height:1.6;color:#d8d8d2;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:0 48px">
        <p style="margin:0 0 12px"><strong style="color:#d0ff00">1. GAM API</strong> syncs active line items automatically — no manual list to maintain.</p>
        <p style="margin:0 0 12px"><strong style="color:#d0ff00">2. On any post or page</strong>, use the <strong style="color:#fff">EN Campaign</strong> sidebar panel to assign a sponsor ID that overrides all ads on that page.</p>
        <p style="margin:0 0 12px"><strong style="color:#d0ff00">3. In Elementor</strong>, drop the <strong style="color:#fff">EN Ad Slot</strong> widget and pick a preset — the sponsor dropdown pulls from GAM live.</p>
        <p style="margin:0 0 12px"><strong style="color:#d0ff00">4. Takeovers</strong> wrap the entire page — set a date range and upload brand images to run a full-site takeover.</p>
        <p style="margin:0 0 12px"><strong style="color:#d0ff00">5. GAM handles</strong> creative scheduling, fallbacks, and targeting automatically.</p>
    </div>
    <div class="eg-accentline"></div>
</div>

</div><!-- #engam-v2-wrap -->
