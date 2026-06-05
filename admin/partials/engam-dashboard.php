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

// Build placement detail rows shown inside the Leaderboards dashboard card.
$lb_detail_rows = array();
foreach ( $all_lbs as $lb ) {
    if ( empty( $lb['active'] ) ) continue;
    $pos_val = $lb['position'] ?? 'header';
    if ( preg_match( '/^(header|footer)_tmpl_(\d+)$/', $pos_val, $lpm ) ) {
        $lpm_post  = get_post( (int) $lpm[2] );
        $type_label = $lpm[1] === 'header' ? 'Header' : 'Footer';
        $pos_label  = $type_label . ': ' . ( $lpm_post ? $lpm_post->post_title : '#' . $lpm[2] );
    } elseif ( $pos_val === 'footer' ) {
        $pos_label = 'Footer';
    } elseif ( $pos_val === 'midpoint' ) {
        $tp = trim( (string) ( $lb['target_pages'] ?? '' ) );
        $pos_label = 'Half Page';
        if ( $tp !== '' ) {
            $tp_post = is_numeric( $tp ) ? get_post( (int) $tp ) : get_page_by_path( $tp );
            $pos_label .= ': ' . ( $tp_post ? $tp_post->post_title : $tp );
        }
    } else {
        $pos_label = 'Header';
    }
    $lb_detail_rows[] = array(
        'name' => $lb['name'] ?? '(untitled)',
        'pos'  => $pos_label,
    );
}

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
$ad_slot_counts     = false;
$ad_slot_placements = array(); // preset → [ ['title'=>..., 'post_id'=>..., 'type'=>...], ... ]
if ( false === $ad_slot_counts ) {
    $ad_slot_counts = array(
        'leaderboard' => 0, 'medium_rect' => 0, 'half_page' => 0,
        'med_half'    => 0, 'takeover'    => 0, 'custom'    => 0,
    );
    try {
        $el_rows = $wpdb->get_results(
            "SELECT m.meta_value, p.ID AS post_id, p.post_title, p.post_type
               FROM {$wpdb->postmeta} m
               INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
              WHERE m.meta_key = '_elementor_data'
                AND m.meta_value LIKE '%engam_v2_ad_slot%'
                AND p.post_status IN ( 'publish', 'private' )
                AND p.post_type NOT IN ( 'revision' )"
        );
        if ( is_array( $el_rows ) ) {
            foreach ( $el_rows as $el_row ) {
                $data = json_decode( $el_row->meta_value, true );
                if ( ! is_array( $data ) ) continue;

                // Count slots within this post separately so we know which presets it contains.
                $local_counts = array();
                engam_v2_walk_ad_slots( $data, $local_counts );

                // Accumulate into global totals.
                foreach ( $local_counts as $preset => $n ) {
                    if ( ! isset( $ad_slot_counts[ $preset ] ) ) $ad_slot_counts[ $preset ] = 0;
                    $ad_slot_counts[ $preset ] += $n;
                }

                // Record placement label for each preset found in this post.
                if ( ! empty( $local_counts ) ) {
                    $is_tmpl   = ( $el_row->post_type === 'elementor_library' );
                    $tmpl_type = $is_tmpl ? get_post_meta( (int) $el_row->post_id, '_elementor_template_type', true ) : '';
                    $pl_label  = $el_row->post_title ?: '(untitled)';
                    if ( $is_tmpl && $tmpl_type ) {
                        $pl_label .= ' (' . $tmpl_type . ' template)';
                    }
                    foreach ( array_keys( $local_counts ) as $preset ) {
                        if ( ! isset( $ad_slot_placements[ $preset ] ) ) $ad_slot_placements[ $preset ] = array();
                        $ad_slot_placements[ $preset ][] = array(
                            'title'   => $pl_label,
                            'post_id' => (int) $el_row->post_id,
                        );
                    }
                }
            }
        }
    } catch ( \Throwable $e ) {
        // Non-fatal — counts and placements stay empty if the query fails.
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

<!-- ACTIVE AD PLACEMENT METRICS -->
<?php
$med_half = isset( $ad_slot_counts['med_half'] ) ? (int) $ad_slot_counts['med_half'] : 0;

// Leaderboards: hardcoded header/footer (active) + Elementor widget placements.
$m_leaderboard = $lb_active + (int) ( $ad_slot_counts['leaderboard'] ?? 0 );
// Elementor widget placements by size.
$m_medium_rect = (int) ( $ad_slot_counts['medium_rect'] ?? 0 ) + $med_half;
$m_half_page   = (int) ( $ad_slot_counts['half_page'] ?? 0 ) + $med_half;

// Carousels: active AND placed on a page.
$m_carousel = 0;
foreach ( $carousels as $car ) {
    $is_active = ! ( isset( $car['active'] ) && empty( $car['active'] ) );
    $usage     = class_exists( 'Equinenetwork_Gam_V2_Carousel_Render' )
        ? Equinenetwork_Gam_V2_Carousel_Render::usage( $car['id'] ?? '' )
        : array();
    if ( $is_active && ! empty( $usage ) ) $m_carousel++;
}

// Takeovers split by type (active only).
$m_masthead = count( array_filter( $all_takeovers, function( $t ) { return ! empty( $t['active'] ) && ( ( $t['type'] ?? 'wrap' ) === 'masthead' ); } ) );
$m_wrap     = count( array_filter( $all_takeovers, function( $t ) { return ! empty( $t['active'] ) && ( ( $t['type'] ?? 'wrap' ) === 'wrap' ); } ) );

// Stackers: single global injection config (migrated from legacy list).
$stk_settings = get_option( 'engam_v2_stacker_settings', null );
if ( is_array( $stk_settings ) ) {
    $m_stacker = ! empty( $stk_settings['active'] ) ? 1 : 0;
} else {
    $m_stacker = $stacker_active > 0 ? 1 : 0;
}

// Build leaderboard placement rows for the dashboard card (name → position label).
$lb_card_rows = array();
foreach ( $lb_detail_rows as $ldr ) {
    $lb_card_rows[] = $ldr['name'] . ' — ' . $ldr['pos'];
}

// Merge medium_rect and med_half placements; half_page and med_half placements.
$mr_placements = array_merge( $ad_slot_placements['medium_rect'] ?? array(), $ad_slot_placements['med_half'] ?? array() );
$hp_placements = array_merge( $ad_slot_placements['half_page']   ?? array(), $ad_slot_placements['med_half'] ?? array() );

$metric_cards = array(
    array( 'label' => 'Leaderboards',     'count' => $m_leaderboard, 'link' => 'engam-v2-leaderboards', 'rows' => $lb_card_rows ),
    array( 'label' => 'Medium Rectangle', 'count' => $m_medium_rect, 'link' => null, 'rows' => array_column( $mr_placements, 'title' ) ),
    array( 'label' => 'Half Page',        'count' => $m_half_page,   'link' => null, 'rows' => array_column( $hp_placements, 'title' ) ),
    array( 'label' => 'Carousel',         'count' => $m_carousel,    'link' => 'engam-v2-carousels' ),
    array( 'label' => 'Masthead',         'count' => $m_masthead,    'link' => 'engam-v2-takeovers' ),
    array( 'label' => 'Wrap Takeover',    'count' => $m_wrap,        'link' => 'engam-v2-takeovers' ),
    array( 'label' => 'Stackers',         'count' => $m_stacker,     'link' => 'engam-v2-stackers' ),
    array( 'label' => "Sponsor ID's",     'count' => $sponsor_count, 'link' => 'engam-v2-campaigns' ),
);
?>
<div style="margin:0 0 8px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:900;color:#555">Active Ad Placements</div>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:18px">
    <?php foreach ( $metric_cards as $mc ) :
        $count    = (int) $mc['count'];
        $tag_attr = $count > 0 ? '' : ' style="background:#eee;color:#999"';
        $has_link = ! empty( $mc['link'] );
        ob_start();
        ?>
        <div class="eg-card eg-metric">
            <div class="eg-metric-top">
                <h2><?php echo esc_html( $mc['label'] ); ?></h2>
                <span class="eg-tag"<?php echo $tag_attr; // phpcs:ignore ?>><?php echo $count; ?> Active</span>
            </div>
            <?php if ( ! empty( $mc['rows'] ) ) : ?>
            <div style="margin-top:10px;padding-top:10px;border-top:1px solid #eee">
                <?php foreach ( $mc['rows'] as $row_label ) : ?>
                <div style="font-size:12px;color:#555;padding:2px 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo esc_html( $row_label ); ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ( $has_link ) : ?>
            <div class="eg-metric-foot"><span class="eg-btn sm">Manage &rarr;</span></div>
            <?php endif; ?>
        </div>
        <?php
        $card = ob_get_clean();
        if ( $has_link ) :
        ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $mc['link'] ) ); ?>" style="text-decoration:none;color:inherit;display:block"><?php echo $card; // phpcs:ignore ?></a>
        <?php else : ?>
        <?php echo $card; // phpcs:ignore ?>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

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
