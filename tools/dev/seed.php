<?php
/**
 * Seed realistic EN-GAM dev data into the throwaway WordPress (tools/dev/setup-wp.sh).
 * Run: wp eval-file tools/dev/seed.php
 *
 * Fake GAM credentials make is_configured() true; cache transients/options are pre-filled so
 * screens render populated without a live GAM (no real API call succeeds in this container).
 */

// --- Terms & pages ---
$cat_ids = array();
foreach ( array( 'Horse Health' => 'horse-health', 'Rodeo' => 'rodeo', 'Ranching' => 'ranching', 'Sponsored Content' => 'sponsored-content' ) as $name => $slug ) {
    $t = term_exists( $slug, 'category' );
    if ( ! $t ) $t = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
    $cat_ids[ $slug ] = is_array( $t ) ? (int) $t['term_id'] : (int) $t;
}
function engam_seed_page( $title, $slug ) {
    $e = get_page_by_path( $slug );
    return $e ? $e->ID : wp_insert_post( array( 'post_title' => $title, 'post_name' => $slug, 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => str_repeat( 'Lorem ipsum dolor sit amet. ', 30 ) ) );
}
$page_events = engam_seed_page( 'Events Calendar', 'events-calendar' );
engam_seed_page( 'Horse Health', 'horse-health-hub' );

// --- Core options ---
update_option( 'equinenetwork_gam_v2_id', '/22845993592/equinenetwork' );
update_option( 'equinenetwork_gam_v2_credentials', json_encode( array(
    'type' => 'service_account', 'project_id' => 'en-gam-prod',
    'client_email' => 'en-gam-sync@en-gam-prod.iam.gserviceaccount.com',
    'private_key' => "-----BEGIN PRIVATE KEY-----\nMOCKKEY\n-----END PRIVATE KEY-----\n",
) ) );
update_option( 'engam_v2_onboarding_dismissed', 1 );
update_option( 'engam_v2_ms_tenant_id', 'a1b2c3d4-1111-2222-3333-abc123def456' );
update_option( 'engam_v2_ms_client_id', 'f9e8d7c6-9999-8888-7777-987zyx654wvu' );
update_option( 'engam_v2_ms_client_secret', 'shhh-secret-set' );
update_option( 'engam_v2_ms_file_url', 'https://equinenetwork.sharepoint.com/:x:/s/Home/EQUUS-Sponsorships.xlsx' );
update_option( 'engam_v2_ms_sheet_name', 'EQUUS' );
update_option( 'engam_v2_manual_sponsors', array( array( 'name' => 'Bloomer Trailers', 'id' => 'Bloomer_Trailers' ) ) );

update_option( 'engam_v2_leaderboards_list', array(
    array( 'id' => 'lb_header01', 'name' => 'Site-wide Header Leaderboard', 'position' => 'header', 'slotname' => 'leaderboard', 'target_pages' => '', 'target_selector' => '', 'bg_color' => '#f5f5f2', 'padding_top' => 10, 'padding_right' => 10, 'padding_bottom' => 10, 'padding_left' => 10, 'active' => true ),
    array( 'id' => 'lb_footer01', 'name' => 'Footer Leaderboard', 'position' => 'footer', 'slotname' => 'leaderboard', 'target_pages' => '', 'target_selector' => '', 'bg_color' => '', 'padding_top' => 16, 'padding_right' => 0, 'padding_bottom' => 16, 'padding_left' => 0, 'active' => true ),
    array( 'id' => 'lb_mid01', 'name' => 'Events Calendar — Half Page', 'position' => 'midpoint', 'slotname' => 'leaderboard', 'target_pages' => (string) $page_events, 'target_selector' => '.tribe-events-calendar-list__event', 'bg_color' => '', 'padding_top' => 20, 'padding_right' => 0, 'padding_bottom' => 20, 'padding_left' => 0, 'active' => false ),
) );
update_option( 'engam_v2_takeovers', array(
    array( 'id' => 'to_mast01', 'type' => 'masthead', 'name' => 'OutSmart Fly Spray — June 2026', 'slotname' => 'homepagetakeover', 'bg_color' => '', 'show_home' => true, 'pages' => array(), 'gam_line_item_id' => '6789012345', 'schedule_start' => '', 'schedule_end' => '', 'show_to_admins' => false, 'active' => true ),
    array( 'id' => 'to_wrap01', 'type' => 'wrap', 'name' => 'Equinety Salute — Site Wrap', 'gam_line_item_id' => '6789099999', 'bg_color' => '#101820', 'active' => false, 'show_to_admins' => true, 'wrap_cats' => 'horse-health', 'wrap_pages' => array(), 'wrap_posts' => array() ),
) );
update_option( 'engam_v2_carousels', array(
    array( 'id' => 'car_health01', 'name' => 'Horse Health — Top Stories', 'source' => 'posts', 'slides' => array(), 'category' => $cat_ids['horse-health'], 'tag' => '', 'posts_count' => 12, 'orderby' => 'date', 'ad_interval' => 3, 'ads_enabled' => true, 'gam_line_item_id' => '6789012345', 'active' => true, 'schedule_start' => '', 'schedule_end' => '' ),
    array( 'id' => 'car_feat01', 'name' => 'Featured Sponsors', 'source' => 'manual', 'slides' => array(
        array( 'image' => '', 'title' => 'CactusRopes Horses', 'content' => 'Trusted by champions.', 'btn' => true, 'btn_label' => 'Shop Now', 'btn_url' => 'https://example.com' ),
        array( 'image' => '', 'title' => 'Classic Equine', 'content' => 'Gear up for the season.', 'btn' => true, 'btn_label' => 'Learn More', 'btn_url' => 'https://example.com' ),
    ), 'category' => '', 'tag' => '', 'ad_interval' => 4, 'ads_enabled' => true, 'gam_line_item_id' => '', 'active' => true, 'schedule_start' => '', 'schedule_end' => '' ),
) );
update_option( 'engam_v2_stacker_settings', array( 'active' => true, 'placement' => 'paragraph', 'after_paragraph' => 5, 'cats' => '', 'hide_cats' => 'sponsored-content', 'hide_ids' => '', 'hide_sponsors' => 'CactusRopes_Horses' ) );

// --- Cache transients/options so screens render populated ---
$li = array(
    array( 'id' => 'li1', 'gam_id' => '6789012345', 'name' => 'OutSmart Fly Spray — Masthead June', 'start_time' => '2026-06-01T00:00:00Z', 'end_time' => '2026-06-30T23:59:59Z' ),
    array( 'id' => 'li2', 'gam_id' => '6789099999', 'name' => 'Equinety Salute — Wrap Takeover', 'start_time' => '2026-07-01T00:00:00Z', 'end_time' => '2026-07-15T23:59:59Z' ),
    array( 'id' => 'li3', 'gam_id' => '6789011111', 'name' => 'Classic Equine — Carousel', 'start_time' => '2026-05-15T00:00:00Z', 'end_time' => '' ),
);
set_transient( 'engam_v2_line_items', $li, 3600 );
$flights = array();
foreach ( $li as $x ) { $flights[ $x['gam_id'] ] = array( 'gam_id' => $x['gam_id'], 'name' => $x['name'], 'start_time' => $x['start_time'], 'end_time' => $x['end_time'] ); }
update_option( 'engam_v2_li_flights', $flights, false );

set_transient( 'engam_v2_sponsor_options', array(
    array( 'name' => 'CactusRopes Horses', 'id' => 'CactusRopes_Horses' ),
    array( 'name' => 'Equinety Salute', 'id' => 'Equinety_Salute' ),
    array( 'name' => 'OutSmart Fly Spray', 'id' => 'OutSmart_FlySpray' ),
    array( 'name' => 'Classic Equine', 'id' => 'Classic_Equine' ),
    array( 'name' => 'Purina Animal Nutrition', 'id' => 'Purina_AnimalNutrition' ),
), 3600 );
set_transient( 'engam_v2_ai_category_values', array( 'Cattle', 'Performance Horse', 'Western Lifestyle', 'Rodeo', 'Ranching', 'Horse Health' ), 3600 );
update_option( 'engam_v2_impressions_report', array(
    'rows' => array(
        array( 'gam_id' => '7302982995', 'name' => 'Horse&Rider - UltraShield Gold - ROS Banners - May 2026', 'status' => 'COMPLETED',  'impressions' => 75000 ),
        array( 'gam_id' => '7238428923', 'name' => 'Horse&Rider - Homepage Takeover - May 2026',              'status' => 'DELIVERING', 'impressions' => 42945 ),
        array( 'gam_id' => '7296964030', 'name' => 'Horse&Rider - ROS Banners - May 2026',                    'status' => 'READY',      'impressions' => 55000 ),
        array( 'gam_id' => '7268489406', 'name' => 'Horse&Rider - Zygolide - Floating Banner',                'status' => 'PAUSED',     'impressions' => 30493 ),
    ),
    'total' => 203438, 'range' => 'LAST_90_DAYS', 'updated' => time(),
), false );

echo "SEED OK\n";
