<?php if ( ! defined( 'WPINC' ) ) die;

$carousels = get_option( 'engam_v2_carousels', array() );
if ( ! is_array( $carousels ) ) $carousels = array();

$notice  = '';
$edit_id = isset( $_GET['edit'] ) ? sanitize_text_field( wp_unslash( $_GET['edit'] ) ) : '';

// Carousel field defaults — mirror Equinenetwork_Gam_V2_Carousel_Render.
$engam_car_defaults = array(
    'id'             => '',
    'name'           => '',
    'source'         => 'posts',   // 'posts' | 'manual'
    'slides'         => array(),   // manual slides
    'post_btn'       => false,
    'post_btn_label' => 'Read More',
    'category'       => '',
    'tag'            => '',
    'posts_count'    => 12,
    'orderby'        => 'date',
    'ad_size'        => '300x250',
    'ads_enabled'    => true,
    'ad_interval'    => 3,
    'ad_slotname'    => 'carousel',
    'sponsor_id'     => '',
    'gam_line_item_id' => '',
    'slides_desktop' => 3,
    'slides_mobile'  => 1,
    'show_arrows'    => true,
    'image_height'   => 0,
    'show_category'  => true,
    'show_title'     => true,
    'show_excerpt'   => false,
    'excerpt_words'  => 20,
    'title_size'     => 16,
    'title_family'   => '',
    'title_weight'   => '',
    'title_color'    => '#111111',
    'cat_size'       => 11,
    'cat_family'     => '',
    'cat_weight'     => '700',
    'cat_color'      => '#cc0000',
    'excerpt_size'   => 13,
    'excerpt_family' => '',
    'excerpt_weight' => '',
    'excerpt_color'  => '#555555',
    'card_bg'        => '#ffffff',
    'card_radius'    => 8,
    'font_family'    => '',
    'arrow_bg'       => '#050505',
    'arrow_color'    => '#ffffff',
    'btn_bg'         => '#050505',
    'btn_color'      => '#ffffff',
    'active'         => true,
    'schedule_start' => '',
    'schedule_end'   => '',
);

// ---- Handle POST ----
if ( isset( $_POST['engam_v2_carousel_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['engam_v2_carousel_nonce'] ) ), 'engam_v2_carousel_save' ) ) {
    if ( ! current_user_can( 'edit_posts' ) ) wp_die( -1 );

    // ---- Delete ----
    if ( isset( $_POST['engam_carousel_delete'] ) ) {
        $cid = sanitize_text_field( wp_unslash( $_POST['engam_carousel_delete'] ) );
        $carousels = array_values( array_filter( $carousels, function( $c ) use ( $cid ) {
            return ( $c['id'] ?? '' ) !== $cid;
        } ) );
        update_option( 'engam_v2_carousels', $carousels );
        $notice  = 'Carousel deleted.';
        $edit_id = '';

    // ---- Activate / Deactivate ----
    } elseif ( isset( $_POST['engam_carousel_toggle'] ) ) {
        $cid = sanitize_text_field( wp_unslash( $_POST['engam_carousel_toggle'] ) );
        foreach ( $carousels as &$c ) {
            if ( ( $c['id'] ?? '' ) === $cid ) {
                $c['active'] = empty( $c['active'] );
                $notice = 'Carousel "' . esc_html( $c['name'] ?? '' ) . '" ' . ( $c['active'] ? 'activated.' : 'deactivated.' );
                break;
            }
        }
        unset( $c );
        update_option( 'engam_v2_carousels', $carousels );
        $edit_id = '';

    // ---- Duplicate ----
    } elseif ( isset( $_POST['engam_carousel_duplicate'] ) ) {
        $cid = sanitize_text_field( wp_unslash( $_POST['engam_carousel_duplicate'] ) );
        foreach ( $carousels as $c ) {
            if ( ( $c['id'] ?? '' ) === $cid ) {
                $copy         = $c;
                $copy['id']   = uniqid( 'car_' );
                $copy['name'] = ( $c['name'] ?? 'Carousel' ) . ' (copy)';
                $carousels[]  = $copy;
                $notice = 'Carousel "' . esc_html( $copy['name'] ) . '" created.';
                break;
            }
        }
        update_option( 'engam_v2_carousels', $carousels );
        $edit_id = '';

    // ---- Save (add or update) ----
    } else {
        $car_id = isset( $_POST['engam_car_id'] ) ? sanitize_text_field( wp_unslash( $_POST['engam_car_id'] ) ) : '';
        $is_new = empty( $car_id );
        if ( $is_new ) $car_id = uniqid( 'car_' );

        // Preserve the manual Activate/Deactivate flag (set from the list, not this form). New carousels start active.
        $prev_active = true;
        if ( ! $is_new ) {
            foreach ( $carousels as $c ) {
                if ( ( $c['id'] ?? '' ) === $car_id ) { $prev_active = ! ( isset( $c['active'] ) && empty( $c['active'] ) ); break; }
            }
        }

        $hex = function( $k, $fallback ) {
            $v = sanitize_hex_color( wp_unslash( $_POST[ $k ] ?? '' ) );
            return $v ? $v : $fallback;
        };
        $wt  = function( $k ) { return preg_replace( '/[^0-9]/', '', (string) ( $_POST[ $k ] ?? '' ) ); };
        $fam = function( $k ) { return sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) ); };

        // Manual slides repeater.
        $slides = array();
        if ( isset( $_POST['engam_car_slides'] ) && is_array( $_POST['engam_car_slides'] ) ) {
            foreach ( $_POST['engam_car_slides'] as $s ) {
                $img = esc_url_raw( wp_unslash( $s['image'] ?? '' ) );
                $t   = sanitize_text_field( wp_unslash( $s['title'] ?? '' ) );
                $ct  = sanitize_textarea_field( wp_unslash( $s['content'] ?? '' ) );
                $bl  = sanitize_text_field( wp_unslash( $s['btn_label'] ?? '' ) );
                $bu  = esc_url_raw( wp_unslash( $s['btn_url'] ?? '' ) );
                if ( $img === '' && $t === '' && $ct === '' && $bu === '' ) continue; // skip blank rows
                $slides[] = array(
                    'image'     => $img,
                    'title'     => $t,
                    'content'   => $ct,
                    'btn'       => ! empty( $s['btn'] ),
                    'btn_label' => $bl,
                    'btn_url'   => $bu,
                );
            }
        }

        $source = ( ( $_POST['engam_car_source'] ?? 'posts' ) === 'manual' ) ? 'manual' : 'posts';

        $record = array(
            'id'             => $car_id,
            'name'           => sanitize_text_field( wp_unslash( $_POST['engam_car_name'] ?? '' ) ),
            'source'         => $source,
            'slides'         => $slides,
            'post_btn'       => isset( $_POST['engam_car_post_btn'] ),
            'post_btn_label' => sanitize_text_field( wp_unslash( $_POST['engam_car_post_btn_label'] ?? 'Read More' ) ),
            'category'       => isset( $_POST['engam_car_category'] ) && $_POST['engam_car_category'] !== '' ? intval( $_POST['engam_car_category'] ) : '',
            'tag'            => isset( $_POST['engam_car_tag'] ) && $_POST['engam_car_tag'] !== '' ? intval( $_POST['engam_car_tag'] ) : '',
            'posts_count'    => max( 1, intval( $_POST['engam_car_posts_count'] ?? 12 ) ),
            'orderby'        => in_array( ( $_POST['engam_car_orderby'] ?? 'date' ), array( 'date', 'title', 'rand' ), true ) ? sanitize_text_field( wp_unslash( $_POST['engam_car_orderby'] ) ) : 'date',
            'ad_size'        => '300x250',
            'ads_enabled'    => true,
            'ad_interval'    => max( 1, intval( $_POST['engam_car_ad_interval'] ?? 3 ) ),
            'ad_slotname'    => 'carousel',
            'sponsor_id'     => '',
            'gam_line_item_id' => sanitize_text_field( wp_unslash( $_POST['engam_car_gam_line_item_id'] ?? '' ) ),
            'slides_desktop' => max( 1, intval( $_POST['engam_car_slides_desktop'] ?? 3 ) ),
            'slides_mobile'  => max( 1, intval( $_POST['engam_car_slides_mobile'] ?? 1 ) ),
            'show_arrows'    => isset( $_POST['engam_car_show_arrows'] ),
            'image_height'   => max( 0, intval( $_POST['engam_car_image_height'] ?? 0 ) ),
            'show_category'  => isset( $_POST['engam_car_show_category'] ),
            'show_title'     => isset( $_POST['engam_car_show_title'] ),
            'show_excerpt'   => isset( $_POST['engam_car_show_excerpt'] ),
            'excerpt_words'  => max( 1, intval( $_POST['engam_car_excerpt_words'] ?? 20 ) ),
            'card_bg'        => $hex( 'engam_car_card_bg', '#ffffff' ),
            'card_radius'    => max( 0, intval( $_POST['engam_car_card_radius'] ?? 8 ) ),
            'font_family'    => $fam( 'engam_car_font_family' ),
            'title_size'     => max( 1, intval( $_POST['engam_car_title_size'] ?? 16 ) ),
            'title_family'   => $fam( 'engam_car_title_family' ),
            'title_weight'   => $wt( 'engam_car_title_weight' ),
            'title_color'    => $hex( 'engam_car_title_color', '#111111' ),
            'cat_size'       => max( 1, intval( $_POST['engam_car_cat_size'] ?? 11 ) ),
            'cat_family'     => $fam( 'engam_car_cat_family' ),
            'cat_weight'     => $wt( 'engam_car_cat_weight' ),
            'cat_color'      => $hex( 'engam_car_cat_color', '#cc0000' ),
            'excerpt_size'   => max( 1, intval( $_POST['engam_car_excerpt_size'] ?? 13 ) ),
            'excerpt_family' => $fam( 'engam_car_excerpt_family' ),
            'excerpt_weight' => $wt( 'engam_car_excerpt_weight' ),
            'excerpt_color'  => $hex( 'engam_car_excerpt_color', '#555555' ),
            'arrow_bg'       => $hex( 'engam_car_arrow_bg', '#050505' ),
            'arrow_color'    => $hex( 'engam_car_arrow_color', '#ffffff' ),
            'btn_bg'         => $hex( 'engam_car_btn_bg', '#050505' ),
            'btn_color'      => $hex( 'engam_car_btn_color', '#ffffff' ),
            'active'         => $prev_active,
            'schedule_start' => sanitize_text_field( wp_unslash( $_POST['engam_car_schedule_start'] ?? '' ) ),
            'schedule_end'   => sanitize_text_field( wp_unslash( $_POST['engam_car_schedule_end'] ?? '' ) ),
        );
        if ( $record['ad_slotname'] === '' ) $record['ad_slotname'] = 'carousel';

        if ( $is_new ) {
            $carousels[] = $record;
            $notice = 'Carousel "' . esc_html( $record['name'] ) . '" created.';
        } else {
            foreach ( $carousels as &$c ) {
                if ( ( $c['id'] ?? '' ) === $car_id ) { $c = $record; break; }
            }
            unset( $c );
            $notice = 'Carousel "' . esc_html( $record['name'] ) . '" updated.';
        }
        update_option( 'engam_v2_carousels', $carousels );
        $edit_id = '';
    }
}

// Find the record being edited
$editing = null;
if ( $edit_id ) {
    foreach ( $carousels as $c ) {
        if ( ( $c['id'] ?? '' ) === $edit_id ) { $editing = wp_parse_args( $c, $engam_car_defaults ); break; }
    }
}

// Value helper: editing value or default.
$cv = function( $key ) use ( $editing, $engam_car_defaults ) {
    if ( $editing ) return $editing[ $key ];
    return $engam_car_defaults[ $key ];
};

// Build dropdown option arrays.
$cat_options = array( '' => '— All categories —' );
foreach ( get_categories( array( 'hide_empty' => false ) ) as $term ) {
    $cat_options[ (int) $term->term_id ] = $term->name;
}
$tag_options = array( '' => '— Any tag —' );
foreach ( get_tags( array( 'hide_empty' => false ) ) as $term ) {
    $tag_options[ (int) $term->term_id ] = $term->name;
}

function engam_car_usage( $car_id ) {
    return Equinenetwork_Gam_V2_Carousel_Render::usage( $car_id );
}

// Source label for the list.
function engam_car_source_label( $c, $cat_options, $tag_options ) {
    if ( ( $c['source'] ?? 'posts' ) === 'manual' ) {
        $n = is_array( $c['slides'] ?? null ) ? count( $c['slides'] ) : 0;
        return 'Manual: ' . $n . ' slide' . ( $n === 1 ? '' : 's' );
    }
    $parts = array();
    if ( ! empty( $c['category'] ) && isset( $cat_options[ (int) $c['category'] ] ) ) {
        $parts[] = 'Category: ' . $cat_options[ (int) $c['category'] ];
    }
    if ( ! empty( $c['tag'] ) && isset( $tag_options[ (int) $c['tag'] ] ) ) {
        $parts[] = 'Tag: ' . $tag_options[ (int) $c['tag'] ];
    }
    return empty( $parts ) ? 'All posts' : implode( ' · ', $parts );
}

// Status label (deactivated / scheduled / expired / active).
// A schedule overrides the manual Activate/Deactivate flag.
function engam_car_status( $c ) {
    $now   = current_time( 'timestamp' );
    $start = ! empty( $c['schedule_start'] ) ? strtotime( $c['schedule_start'] ) : 0;
    $end   = ! empty( $c['schedule_end'] )   ? strtotime( $c['schedule_end'] )   : 0;
    if ( $start || $end ) {
        if ( $start && $now < $start ) return array( 'scheduled', 'Scheduled' );
        if ( $end   && $now > $end   ) return array( 'expired',   'Expired'   );
        return array( 'active', 'Active' );
    }
    if ( isset( $c['active'] ) && empty( $c['active'] ) ) return array( 'inactive', 'Deactivated' );
    return array( 'active', 'Active' );
}

include EQUINENETWORK_GAM_V2_PATH . 'admin/partials/engam-shared-styles.php';
?>
<style>
#engam-v2-wrap .engam-tabs{display:flex;gap:6px;border-bottom:2px solid #deded8;margin-bottom:22px}
#engam-v2-wrap .engam-tab-btn{appearance:none;border:none;background:transparent;padding:12px 22px;font-weight:700;font-size:14px;cursor:pointer;color:#777;border-bottom:3px solid transparent;margin-bottom:-2px;letter-spacing:.01em}
#engam-v2-wrap .engam-tab-btn.active{color:#050505;border-bottom-color:#129b6f}
#engam-v2-wrap .engam-tab-panel{display:none}
#engam-v2-wrap .engam-tab-panel.active{display:block}
#engam-v2-wrap .engam-slide-row{border:1px solid #deded8;border-radius:8px;padding:16px;margin-bottom:14px;background:#fafaf8}
#engam-v2-wrap .engam-slide-row .engam-slide-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
#engam-v2-wrap .engam-slide-row .engam-slide-head strong{font-size:13px;text-transform:uppercase;letter-spacing:.04em}
#engam-v2-wrap .engam-slide-img-preview{display:block;max-width:100%;height:90px;object-fit:cover;border-radius:6px;background:#eee;margin-bottom:8px}
#engam-v2-wrap .engam-slide-img-preview:not([src]),#engam-v2-wrap .engam-slide-img-preview[src=""]{display:none}
</style>
<div id="engam-v2-wrap">

<!-- MASTHEAD -->
<section class="eg-mast">
    <div class="eg-brand">
        <div class="eg-logo">EN</div>
        <div class="eg-brand-text">
            <small>Google Ad Manager &mdash; v2</small>
            <h1>Carousels</h1>
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

<!-- TABLE OF EXISTING CAROUSELS -->
<div class="eg-card" style="margin-top:18px;margin-bottom:18px">
    <div class="eg-head">
        <div>
            <h2>Carousel List</h2>
            <p>Build reusable carousels, then drop each one into any page or post with its shortcode.</p>
        </div>
        <button class="eg-btn" id="engam-car-add-btn" onclick="document.getElementById('engam-car-form-wrap').style.display='block';this.style.display='none';document.getElementById('engam-car-name').focus()">+ Add New Carousel</button>
    </div>

    <?php if ( empty( $carousels ) ) : ?>
        <div class="eg-empty">
            <strong>No carousels yet</strong>
            Click "Add New Carousel" above to build your first carousel.
        </div>
    <?php else : ?>
        <table class="eg-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Used On</th>
                    <th>Ads</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $engam_list_net = preg_replace( '/[^0-9]/', '', get_option( 'equinenetwork_gam_v2_id', '' ) );
                foreach ( $carousels as $car ) :
                    $car       = wp_parse_args( $car, $engam_car_defaults );
                    $shortcode = '[en_carousel id="' . $car['id'] . '"]';
                    $car_gam_id = (string) ( $car['gam_line_item_id'] ?? '' );
                    $source    = engam_car_source_label( $car, $cat_options, $tag_options );
                    $ads_label = ! empty( $car['ads_enabled'] ) ? 'Every ' . (int) $car['ad_interval'] : 'Off';
                    $usage     = engam_car_usage( $car['id'] );
                    list( $st_class, $st_label ) = engam_car_status( $car );
                    $is_active     = ! ( isset( $car['active'] ) && empty( $car['active'] ) );
                    $has_schedule  = ! empty( $car['schedule_start'] ) || ! empty( $car['schedule_end'] );
                ?>
                <tr>
                    <td>
                        <div class="eg-campaign-name"><?php echo esc_html( $car['name'] ); ?><?php
                            if ( $car_gam_id && $engam_list_net ) {
                                echo '<a href="https://admanager.google.com/' . esc_attr( $engam_list_net )
                                    . '#delivery/line_item/detail/line_item_id=' . rawurlencode( $car_gam_id )
                                    . '" target="_blank" rel="noopener" style="font-size:10px;background:#d0ff00;color:#111;padding:1px 6px;border-radius:3px;font-weight:700;margin-left:6px;text-decoration:none">GAM ↗</a>'; // phpcs:ignore
                            }
                        ?></div>
                        <code class="engam-car-shortcode" data-shortcode="<?php echo esc_attr( $shortcode ); ?>"
                            title="Click to copy"
                            style="display:inline-block;margin-top:4px;font-size:12px;background:#f3f3ee;border:1px solid #deded8;border-radius:4px;padding:3px 8px;cursor:pointer;font-family:monospace"><?php echo esc_html( $shortcode ); ?></code>
                        <span class="engam-car-copyhint" style="font-size:11px;color:#888;margin-left:6px">click to copy</span>
                    </td>
                    <td style="font-size:12px;color:#555"><?php echo esc_html( $source ); ?></td>
                    <td><span class="eg-badge <?php echo esc_attr( $st_class ); ?>"><?php echo esc_html( $st_label ); ?></span></td>
                    <td style="font-size:12px;color:#555">
                        <?php if ( empty( $usage ) ) : ?>
                            <span style="color:#999">Not placed yet</span>
                        <?php else : ?>
                            <?php foreach ( $usage as $u ) :
                                $badge = $u['status'] !== 'publish' ? ' <em style="color:#999;font-style:normal">(' . esc_html( $u['status'] ) . ')</em>' : '';
                            ?>
                                <div style="margin-bottom:3px">
                                    <a href="<?php echo esc_url( $u['edit'] ); ?>" style="font-weight:700;text-decoration:none;color:#050505"><?php echo esc_html( $u['title'] ); ?></a><?php echo $badge; // phpcs:ignore ?>
                                    <?php if ( $u['view'] ) : ?>
                                        <a href="<?php echo esc_url( $u['view'] ); ?>" target="_blank" rel="noopener" title="View" style="text-decoration:none;margin-left:4px">↗</a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:#555"><?php echo esc_html( $ads_label ); ?></td>
                    <td>
                        <div class="eg-actions-cell">
                            <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'engam-v2-carousels', 'edit' => $car['id'] ), admin_url( 'admin.php' ) ) ); ?>"
                               class="eg-btn sm dark">Edit</a>
                            <?php if ( $has_schedule ) : ?>
                            <button type="button" class="eg-btn sm dark" style="opacity:.45;cursor:not-allowed" disabled
                                title="A schedule is set — this carousel activates and deactivates automatically.">Scheduled</button>
                            <?php else : ?>
                            <form method="post" action="" style="display:inline">
                                <?php wp_nonce_field( 'engam_v2_carousel_save', 'engam_v2_carousel_nonce' ); ?>
                                <input type="hidden" name="engam_carousel_toggle" value="<?php echo esc_attr( $car['id'] ); ?>">
                                <button type="submit" class="eg-btn sm <?php echo $is_active ? 'dark' : ''; ?>"><?php echo $is_active ? 'Deactivate' : 'Activate'; ?></button>
                            </form>
                            <?php endif; ?>
                            <form method="post" action="" style="display:inline">
                                <?php wp_nonce_field( 'engam_v2_carousel_save', 'engam_v2_carousel_nonce' ); ?>
                                <input type="hidden" name="engam_carousel_duplicate" value="<?php echo esc_attr( $car['id'] ); ?>">
                                <button type="submit" class="eg-btn sm dark">Duplicate</button>
                            </form>
                            <form method="post" action="" style="display:inline"
                                onsubmit="return confirm('Delete carousel &laquo;<?php echo esc_js( $car['name'] ); ?>&raquo;? This cannot be undone.')">
                                <?php wp_nonce_field( 'engam_v2_carousel_save', 'engam_v2_carousel_nonce' ); ?>
                                <input type="hidden" name="engam_carousel_delete" value="<?php echo esc_attr( $car['id'] ); ?>">
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

<!-- ADD / EDIT FORM -->
<div id="engam-car-form-wrap" style="<?php echo $editing ? '' : 'display:none'; ?>">
<div class="eg-card" style="margin-top:18px">
    <div class="eg-head">
        <div>
            <h2><?php echo $editing ? 'Edit Carousel' : 'New Carousel'; ?></h2>
            <p>Set up the slides and ads, then style it under Settings.</p>
        </div>
        <?php if ( ! $editing ) : ?>
        <button class="eg-btn dark" style="border-color:#111" onclick="document.getElementById('engam-car-form-wrap').style.display='none';document.getElementById('engam-car-add-btn').style.display='inline-block'">Cancel</button>
        <?php else : ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-carousels' ) ); ?>" class="eg-btn dark" style="border-color:#111">Cancel</a>
        <?php endif; ?>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field( 'engam_v2_carousel_save', 'engam_v2_carousel_nonce' ); ?>
        <input type="hidden" name="engam_car_id" value="<?php echo $editing ? esc_attr( $editing['id'] ) : ''; ?>">

        <!-- Name / GAM Line Item -->
        <div class="eg-form-section" style="padding-bottom:18px;border-bottom:1px solid #deded8">
            <div class="eg-settings-field">
                <label for="engam-car-name">Carousel Name <span style="color:#cc0000">*</span></label>
                <input class="eg-input" type="text" name="engam_car_name" id="engam-car-name"
                    value="<?php echo esc_attr( $cv( 'name' ) ); ?>"
                    placeholder="e.g. Horse Health — Top Stories" required>
            </div>
            <?php
            $car_current_gam_id = $cv( 'gam_line_item_id' );
            $car_cached_li_all  = get_transient( 'engam_v2_line_items' );
            $engam_net_code     = preg_replace( '/[^0-9]/', '', get_option( 'equinenetwork_gam_v2_id', '' ) );
            $car_gam_link       = ( $car_current_gam_id && $engam_net_code )
                ? 'https://admanager.google.com/' . $engam_net_code . '#delivery/line_item/detail/line_item_id=' . rawurlencode( $car_current_gam_id )
                : '';
            ?>
            <!-- GAM Line Item Picker -->
            <div class="eg-settings-field" style="margin-top:18px">
                <label for="engam-car-gam-li-search">GAM Line Item <span style="font-weight:400;color:#888">(links schedule from GAM)</span></label>
                <input type="hidden" name="engam_car_gam_line_item_id" id="engam-car-gam-li-id" value="<?php echo esc_attr( $car_current_gam_id ); ?>">
                <input type="text" id="engam-car-gam-li-search" class="eg-input" autocomplete="off"
                    placeholder="Search by name or GAM ID…"
                    value="<?php
                        if ( $car_current_gam_id && is_array( $car_cached_li_all ) ) {
                            foreach ( $car_cached_li_all as $cli ) {
                                if ( isset( $cli['gam_id'] ) && (string) $cli['gam_id'] === (string) $car_current_gam_id ) {
                                    echo esc_attr( $cli['name'] . ' (' . $cli['gam_id'] . ')' );
                                    break;
                                }
                            }
                        }
                    ?>">
                <div id="engam-car-gam-li-results" style="display:none;border:1px solid #deded8;border-top:none;border-radius:0 0 6px 6px;background:#fff;max-height:220px;overflow-y:auto;position:relative;z-index:100"></div>
                <p class="eg-hint">Select the GAM line item that delivers the ads in this carousel — flight dates display automatically.
                    <a id="engam-car-gam-li-link" href="<?php echo esc_url( $car_gam_link ); ?>" target="_blank" rel="noopener"
                       style="<?php echo $car_gam_link ? '' : 'display:none;'; ?>font-weight:700;text-decoration:none;margin-left:6px">View in GAM ↗</a>
                </p>
                <?php if ( ! is_array( $car_cached_li_all ) || empty( $car_cached_li_all ) ) : ?>
                <p style="color:#cc8800;font-size:12px;margin:4px 0 0">No line items cached — go to <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-settings' ) ); ?>">Settings</a> and click Refresh Cache first.</p>
                <?php endif; ?>
            </div>
            <!-- Line items data + search for JS -->
            <script>
            window.engamLineItems = <?php echo wp_json_encode( is_array( $car_cached_li_all ) ? array_map( function( $li ) {
                return array( 'gam_id' => $li['gam_id'] ?? '', 'name' => $li['name'] ?? '', 'start' => $li['start_time'] ?? '', 'end' => $li['end_time'] ?? '' );
            }, $car_cached_li_all ) : array() ); ?>;
            (function(){
                var searchEl = document.getElementById('engam-car-gam-li-search');
                var idEl     = document.getElementById('engam-car-gam-li-id');
                var results  = document.getElementById('engam-car-gam-li-results');
                var linkEl   = document.getElementById('engam-car-gam-li-link');
                var netCode  = '<?php echo esc_js( $engam_net_code ); ?>';
                if (!searchEl) return;
                function updateLink(id) {
                    if (!linkEl) return;
                    if (id && netCode) {
                        linkEl.href = 'https://admanager.google.com/' + netCode + '#delivery/line_item/detail/line_item_id=' + encodeURIComponent(id);
                        linkEl.style.display = '';
                    } else {
                        linkEl.style.display = 'none';
                    }
                }
                function showResults(q) {
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
                        return '<div class="engam-car-gam-li-opt" data-id="'+li.gam_id+'" data-label="'+li.name.replace(/"/g,'&quot;')+' ('+li.gam_id+')" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f0f0ea;font-size:13px">'
                            + '<strong>'+li.name+'</strong><br>'
                            + '<span style="color:#888;font-size:11px">ID: '+li.gam_id+' &nbsp;|&nbsp; '+s+' → '+e+'</span>'
                            + '</div>';
                    }).join('');
                    results.style.display = 'block';
                }
                searchEl.addEventListener('input', function() { showResults(this.value); });
                searchEl.addEventListener('focus', function() { if(this.value) showResults(this.value); });
                results.addEventListener('mousedown', function(e) {
                    var opt = e.target.closest('.engam-car-gam-li-opt');
                    if (!opt) return;
                    idEl.value = opt.dataset.id;
                    searchEl.value = opt.dataset.label;
                    updateLink(opt.dataset.id);
                    results.style.display = 'none';
                });
                // If the field is cleared, hide the GAM link.
                searchEl.addEventListener('input', function() { if (!this.value) { idEl.value = ''; updateLink(''); } });
                document.addEventListener('click', function(e) {
                    if (e.target !== searchEl && !results.contains(e.target)) results.style.display = 'none';
                });
            })();
            </script>
        </div>

        <!-- TABS -->
        <div class="engam-tabs">
            <button type="button" class="engam-tab-btn active" data-tab="slides">Slides &amp; Ads</button>
            <button type="button" class="engam-tab-btn" data-tab="settings">Settings</button>
        </div>

        <!-- ============ SLIDES TAB ============ -->
        <div class="engam-tab-panel active" id="engam-tab-slides">

            <input type="hidden" name="engam_car_source" value="manual">

            <!-- SLIDES -->
            <div class="eg-form-section">
                <h3>Slides</h3>
                <p class="eg-hint" style="margin-top:-6px">Each slide has an image, title, content, and an optional button.</p>
                <div id="engam-slides-list">
                    <?php
                    $existing_slides = ( $editing && is_array( $cv( 'slides' ) ) ) ? $cv( 'slides' ) : array();
                    if ( ! empty( $existing_slides ) ) {
                        foreach ( $existing_slides as $i => $s ) {
                            $s = wp_parse_args( (array) $s, array( 'image' => '', 'title' => '', 'content' => '', 'btn' => false, 'btn_label' => '', 'btn_url' => '' ) );
                            echo engam_car_slide_row( $i, $s ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                    }
                    ?>
                </div>
                <button type="button" class="eg-btn dark" id="engam-add-slide" style="border-color:#111">+ Add Slide</button>
            </div>

            <!-- AD SLIDES -->
            <div class="eg-form-section">
                <h3>Ad Slides</h3>
                <p class="eg-hint" style="margin-top:-6px">Ad slides are inserted automatically between content slides. GAM decides if each ad slot fills — empty ad slides collapse automatically.</p>
                <div class="eg-settings-field" style="max-width:320px">
                    <label for="engam-car-ad-interval">Ad After Every N Slides</label>
                    <input class="eg-input" type="number" min="1" name="engam_car_ad_interval" id="engam-car-ad-interval"
                        value="<?php echo esc_attr( $cv( 'ad_interval' ) ); ?>">
                </div>
            </div>
        </div><!-- /slides tab -->

        <!-- ============ SETTINGS TAB ============ -->
        <div class="engam-tab-panel" id="engam-tab-settings">

            <!-- SCHEDULE -->
            <div class="eg-form-section">
                <h3>Schedule &amp; Visibility</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
                    <div class="eg-settings-field">
                        <label for="engam-car-start">Schedule Start</label>
                        <input class="eg-input" type="datetime-local" name="engam_car_schedule_start" id="engam-car-start"
                            value="<?php echo $cv( 'schedule_start' ) ? esc_attr( str_replace( ' ', 'T', $cv( 'schedule_start' ) ) ) : ''; ?>">
                        <p class="eg-hint">Leave blank to show immediately. When out of schedule, the carousel and its container are hidden.</p>
                    </div>
                    <div class="eg-settings-field">
                        <label for="engam-car-end">Schedule End</label>
                        <input class="eg-input" type="datetime-local" name="engam_car_schedule_end" id="engam-car-end"
                            value="<?php echo $cv( 'schedule_end' ) ? esc_attr( str_replace( ' ', 'T', $cv( 'schedule_end' ) ) ) : ''; ?>">
                        <p class="eg-hint">Leave blank for no end date.</p>
                    </div>
                </div>
            </div>

            <!-- LAYOUT -->
            <div class="eg-form-section">
                <h3>Layout</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px">
                    <div class="eg-settings-field">
                        <label for="engam-car-slides-desktop">Slides Visible (Desktop)</label>
                        <input class="eg-input" type="number" min="1" name="engam_car_slides_desktop" id="engam-car-slides-desktop"
                            value="<?php echo esc_attr( $cv( 'slides_desktop' ) ); ?>">
                    </div>
                    <div class="eg-settings-field">
                        <label for="engam-car-slides-mobile">Slides Visible (Mobile)</label>
                        <input class="eg-input" type="number" min="1" name="engam_car_slides_mobile" id="engam-car-slides-mobile"
                            value="<?php echo esc_attr( $cv( 'slides_mobile' ) ); ?>">
                    </div>
                    <div class="eg-settings-field" style="padding-top:20px">
                        <label class="eg-toggle">
                            <input type="checkbox" name="engam_car_show_arrows" value="1" <?php checked( ! empty( $cv( 'show_arrows' ) ) ); ?>>
                            <span class="eg-toggle-track"><span class="eg-toggle-thumb"></span></span>
                            Show Navigation Arrows
                        </label>
                    </div>
                </div>
            </div>

            <!-- CARD -->
            <div class="eg-form-section">
                <h3>Card</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px">
                    <div class="eg-settings-field">
                        <label for="engam-car-image-height">Image Height (px)</label>
                        <input class="eg-input" type="number" min="0" name="engam_car_image_height" id="engam-car-image-height"
                            value="<?php echo esc_attr( $cv( 'image_height' ) ); ?>">
                        <p class="eg-hint">0 = auto 16:9</p>
                    </div>
                    <div class="eg-settings-field">
                        <label for="engam-car-card-radius">Card Corner Radius (px)</label>
                        <input class="eg-input" type="number" min="0" name="engam_car_card_radius" id="engam-car-card-radius"
                            value="<?php echo esc_attr( $cv( 'card_radius' ) ); ?>">
                    </div>
                    <div class="eg-settings-field">
                        <label>Card Background</label>
                        <div class="eg-color-row">
                            <input type="color" id="engam-car-card-bg-picker" data-hex="engam-car-card-bg-hex"
                                value="<?php echo esc_attr( $cv( 'card_bg' ) ); ?>">
                            <input class="eg-input engam-car-hex" type="text" name="engam_car_card_bg" id="engam-car-card-bg-hex"
                                data-picker="engam-car-card-bg-picker"
                                value="<?php echo esc_attr( $cv( 'card_bg' ) ); ?>" placeholder="#ffffff" maxlength="7" style="width:120px">
                        </div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:18px">
                    <div class="eg-settings-field" style="display:flex;flex-direction:column;gap:12px">
                        <label class="eg-toggle">
                            <input type="checkbox" name="engam_car_show_title" value="1" <?php checked( ! empty( $cv( 'show_title' ) ) ); ?>>
                            <span class="eg-toggle-track"><span class="eg-toggle-thumb"></span></span>
                            Show Title
                        </label>
                    </div>
                    <div class="eg-settings-field">
                        <label for="engam-car-base-font">Base Font Family (whole card)</label>
                        <select class="eg-input" name="engam_car_font_family" id="engam-car-base-font">
                            <?php foreach ( Equinenetwork_Gam_V2_Carousel_Render::google_fonts() as $val => $lbl ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( (string) $cv( 'font_family' ), (string) $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="eg-hint">Each text element below can override this. Google Fonts load automatically on the front-end.</p>
                    </div>
                </div>
            </div>

            <?php
            // Reusable typography row: Size / Family / Weight / Color.
            $engam_type_row = function( $cv, $prefix, $size_key, $family_key, $weight_key, $color_key, $color_ph ) {
                $gf = Equinenetwork_Gam_V2_Carousel_Render::google_fonts();
                $fw = Equinenetwork_Gam_V2_Carousel_Render::font_weights();
                ?>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:18px">
                    <div class="eg-settings-field">
                        <label>Size (px)</label>
                        <input class="eg-input" type="number" min="1" name="engam_car_<?php echo esc_attr( $size_key ); ?>"
                            value="<?php echo esc_attr( $cv( $size_key ) ); ?>">
                    </div>
                    <div class="eg-settings-field">
                        <label>Family</label>
                        <select class="eg-input" name="engam_car_<?php echo esc_attr( $family_key ); ?>">
                            <?php foreach ( $gf as $val => $lbl ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( (string) $cv( $family_key ), (string) $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="eg-settings-field">
                        <label>Weight</label>
                        <select class="eg-input" name="engam_car_<?php echo esc_attr( $weight_key ); ?>">
                            <?php foreach ( $fw as $val => $lbl ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( (string) $cv( $weight_key ), (string) $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="eg-settings-field">
                        <label>Color</label>
                        <div class="eg-color-row">
                            <input type="color" id="<?php echo esc_attr( $prefix ); ?>-picker" data-hex="<?php echo esc_attr( $prefix ); ?>-hex"
                                value="<?php echo esc_attr( $cv( $color_key ) ); ?>">
                            <input class="eg-input engam-car-hex" type="text" name="engam_car_<?php echo esc_attr( $color_key ); ?>" id="<?php echo esc_attr( $prefix ); ?>-hex"
                                data-picker="<?php echo esc_attr( $prefix ); ?>-picker"
                                value="<?php echo esc_attr( $cv( $color_key ) ); ?>" placeholder="<?php echo esc_attr( $color_ph ); ?>" maxlength="7" style="width:110px">
                        </div>
                    </div>
                </div>
                <?php
            };
            ?>

            <div class="eg-form-section">
                <h3>Title</h3>
                <?php $engam_type_row( $cv, 'engam-car-title-color', 'title_size', 'title_family', 'title_weight', 'title_color', '#111111' ); ?>
            </div>

            <div class="eg-form-section">
                <h3>Content</h3>
                <?php $engam_type_row( $cv, 'engam-car-excerpt-color', 'excerpt_size', 'excerpt_family', 'excerpt_weight', 'excerpt_color', '#555555' ); ?>
            </div>

            <div class="eg-form-section">
                <h3>Button</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
                    <div class="eg-settings-field">
                        <label>Button Background</label>
                        <div class="eg-color-row">
                            <input type="color" id="engam-car-btn-bg-picker" data-hex="engam-car-btn-bg-hex"
                                value="<?php echo esc_attr( $cv( 'btn_bg' ) ); ?>">
                            <input class="eg-input engam-car-hex" type="text" name="engam_car_btn_bg" id="engam-car-btn-bg-hex"
                                data-picker="engam-car-btn-bg-picker"
                                value="<?php echo esc_attr( $cv( 'btn_bg' ) ); ?>" placeholder="#050505" maxlength="7" style="width:120px">
                        </div>
                    </div>
                    <div class="eg-settings-field">
                        <label>Button Text Color</label>
                        <div class="eg-color-row">
                            <input type="color" id="engam-car-btn-color-picker" data-hex="engam-car-btn-color-hex"
                                value="<?php echo esc_attr( $cv( 'btn_color' ) ); ?>">
                            <input class="eg-input engam-car-hex" type="text" name="engam_car_btn_color" id="engam-car-btn-color-hex"
                                data-picker="engam-car-btn-color-picker"
                                value="<?php echo esc_attr( $cv( 'btn_color' ) ); ?>" placeholder="#ffffff" maxlength="7" style="width:120px">
                        </div>
                    </div>
                </div>
            </div>

            <div class="eg-form-section">
                <h3>Navigation Arrows</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
                    <div class="eg-settings-field">
                        <label>Arrow Background</label>
                        <div class="eg-color-row">
                            <input type="color" id="engam-car-arrow-bg-picker" data-hex="engam-car-arrow-bg-hex"
                                value="<?php echo esc_attr( $cv( 'arrow_bg' ) ); ?>">
                            <input class="eg-input engam-car-hex" type="text" name="engam_car_arrow_bg" id="engam-car-arrow-bg-hex"
                                data-picker="engam-car-arrow-bg-picker"
                                value="<?php echo esc_attr( $cv( 'arrow_bg' ) ); ?>" placeholder="#050505" maxlength="7" style="width:120px">
                        </div>
                    </div>
                    <div class="eg-settings-field">
                        <label>Arrow Icon Color</label>
                        <div class="eg-color-row">
                            <input type="color" id="engam-car-arrow-color-picker" data-hex="engam-car-arrow-color-hex"
                                value="<?php echo esc_attr( $cv( 'arrow_color' ) ); ?>">
                            <input class="eg-input engam-car-hex" type="text" name="engam_car_arrow_color" id="engam-car-arrow-color-hex"
                                data-picker="engam-car-arrow-color-picker"
                                value="<?php echo esc_attr( $cv( 'arrow_color' ) ); ?>" placeholder="#ffffff" maxlength="7" style="width:120px">
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /settings tab -->

        <!-- SAVE -->
        <div class="eg-form-section" style="border-top:1px solid #deded8">
            <button type="submit" class="eg-btn" style="padding:14px 32px;font-size:14px">
                <?php echo $editing ? 'Update Carousel' : 'Save Carousel'; ?>
            </button>
        </div>

    </form>
</div><!-- .eg-card -->
</div><!-- #engam-car-form-wrap -->

</div><!-- .eg-content -->
</div><!-- #engam-v2-wrap -->

<script>
(function(){
    <?php if ( $editing ) : ?>
    document.getElementById('engam-car-form-wrap').style.display = 'block';
    var addBtn = document.getElementById('engam-car-add-btn');
    if(addBtn) addBtn.style.display = 'none';
    <?php endif; ?>

    // Tabs
    document.querySelectorAll('.engam-tab-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var tab = btn.getAttribute('data-tab');
            document.querySelectorAll('.engam-tab-btn').forEach(function(b){ b.classList.toggle('active', b===btn); });
            document.querySelectorAll('.engam-tab-panel').forEach(function(p){ p.classList.toggle('active', p.id==='engam-tab-'+tab); });
        });
    });

    // Color pickers <-> hex
    function bindColors(scope){
        (scope||document).querySelectorAll('input[type=color][data-hex]').forEach(function(picker){
            if(picker._bound) return; picker._bound = true;
            var hex = document.getElementById(picker.getAttribute('data-hex'));
            if(!hex) return;
            picker.addEventListener('input', function(){ hex.value = picker.value; });
            hex.addEventListener('input', function(){ if(/^#[0-9a-fA-F]{6}$/.test(hex.value)) picker.value = hex.value; });
        });
    }
    bindColors(document);

    // Slides repeater
    var list = document.getElementById('engam-slides-list');
    var slideIndex = <?php echo (int) ( $editing && is_array( $cv( 'slides' ) ) ? count( $cv( 'slides' ) ) : 0 ); ?>;

    function slideTemplate(idx){
        return ''
        + '<div class="engam-slide-row" data-idx="'+idx+'">'
        +   '<div class="engam-slide-head"><strong>Slide</strong>'
        +     '<button type="button" class="eg-btn sm danger engam-remove-slide">Remove</button></div>'
        +   '<div style="display:grid;grid-template-columns:200px 1fr;gap:18px">'
        +     '<div class="eg-settings-field">'
        +       '<label>Image</label>'
        +       '<img class="engam-slide-img-preview" src="" alt="">'
        +       '<input type="hidden" class="engam-slide-img-input" name="engam_car_slides['+idx+'][image]" value="">'
        +       '<button type="button" class="eg-btn sm engam-slide-img-btn">Select Image</button>'
        +     '</div>'
        +     '<div>'
        +       '<div class="eg-settings-field"><label>Title</label>'
        +         '<input class="eg-input" type="text" name="engam_car_slides['+idx+'][title]" value=""></div>'
        +       '<div class="eg-settings-field"><label>Content</label>'
        +         '<textarea class="eg-input" rows="2" name="engam_car_slides['+idx+'][content]"></textarea></div>'
        +       '<div style="display:grid;grid-template-columns:auto 1fr 1fr;gap:14px;align-items:end">'
        +         '<div class="eg-settings-field" style="padding-bottom:10px"><label class="eg-toggle">'
        +           '<input type="checkbox" name="engam_car_slides['+idx+'][btn]" value="1">'
        +           '<span class="eg-toggle-track"><span class="eg-toggle-thumb"></span></span> Button</label></div>'
        +         '<div class="eg-settings-field"><label>Button Label</label>'
        +           '<input class="eg-input" type="text" name="engam_car_slides['+idx+'][btn_label]" value="" placeholder="Learn More"></div>'
        +         '<div class="eg-settings-field"><label>Button URL</label>'
        +           '<input class="eg-input" type="url" name="engam_car_slides['+idx+'][btn_url]" value="" placeholder="https://..."></div>'
        +       '</div>'
        +     '</div>'
        +   '</div>'
        + '</div>';
    }

    var addBtnSlide = document.getElementById('engam-add-slide');
    if(addBtnSlide){
        addBtnSlide.addEventListener('click', function(){
            var wrap = document.createElement('div');
            wrap.innerHTML = slideTemplate(slideIndex);
            list.appendChild(wrap.firstChild);
            slideIndex++;
        });
    }

    // Remove slide (delegated)
    if(list){
        list.addEventListener('click', function(e){
            var btn = e.target.closest('.engam-remove-slide');
            if(btn){ var row = btn.closest('.engam-slide-row'); if(row) row.remove(); }
        });
    }

    // Slide image media picker (delegated)
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.engam-slide-img-btn');
        if(!btn) return;
        var row = btn.closest('.engam-slide-row');
        var input = row.querySelector('.engam-slide-img-input');
        var preview = row.querySelector('.engam-slide-img-preview');
        var frame = wp.media({ title:'Select Slide Image', button:{text:'Use this image'}, multiple:false, library:{type:'image'} });
        frame.on('select', function(){
            var att = frame.state().get('selection').first().toJSON();
            input.value = att.url;
            preview.src = att.url;
        });
        frame.open();
    });

    // Click-to-copy shortcode
    document.querySelectorAll('.engam-car-shortcode').forEach(function(el){
        el.addEventListener('click', function(){
            var text = el.getAttribute('data-shortcode');
            var done = function(){
                var hint = el.nextElementSibling;
                if(hint){ var orig = hint.textContent; hint.textContent = 'copied!'; setTimeout(function(){ hint.textContent = orig; }, 1500); }
            };
            if(navigator.clipboard && navigator.clipboard.writeText){
                navigator.clipboard.writeText(text).then(done, done);
            } else {
                var ta = document.createElement('textarea');
                ta.value = text; document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); } catch(e){}
                document.body.removeChild(ta); done();
            }
        });
    });
})();
</script>
<?php
// Server-side renderer for an existing manual slide row (keeps markup in sync with JS template).
function engam_car_slide_row( $idx, $s ) {
    $idx = (int) $idx;
    ob_start();
    ?>
    <div class="engam-slide-row" data-idx="<?php echo $idx; ?>">
        <div class="engam-slide-head"><strong>Slide</strong>
            <button type="button" class="eg-btn sm danger engam-remove-slide">Remove</button></div>
        <div style="display:grid;grid-template-columns:200px 1fr;gap:18px">
            <div class="eg-settings-field">
                <label>Image</label>
                <img class="engam-slide-img-preview" src="<?php echo esc_url( $s['image'] ); ?>" alt="">
                <input type="hidden" class="engam-slide-img-input" name="engam_car_slides[<?php echo $idx; ?>][image]" value="<?php echo esc_attr( $s['image'] ); ?>">
                <button type="button" class="eg-btn sm engam-slide-img-btn">Select Image</button>
            </div>
            <div>
                <div class="eg-settings-field"><label>Title</label>
                    <input class="eg-input" type="text" name="engam_car_slides[<?php echo $idx; ?>][title]" value="<?php echo esc_attr( $s['title'] ); ?>"></div>
                <div class="eg-settings-field"><label>Content</label>
                    <textarea class="eg-input" rows="2" name="engam_car_slides[<?php echo $idx; ?>][content]"><?php echo esc_textarea( $s['content'] ); ?></textarea></div>
                <div style="display:grid;grid-template-columns:auto 1fr 1fr;gap:14px;align-items:end">
                    <div class="eg-settings-field" style="padding-bottom:10px"><label class="eg-toggle">
                        <input type="checkbox" name="engam_car_slides[<?php echo $idx; ?>][btn]" value="1" <?php checked( ! empty( $s['btn'] ) ); ?>>
                        <span class="eg-toggle-track"><span class="eg-toggle-thumb"></span></span> Button</label></div>
                    <div class="eg-settings-field"><label>Button Label</label>
                        <input class="eg-input" type="text" name="engam_car_slides[<?php echo $idx; ?>][btn_label]" value="<?php echo esc_attr( $s['btn_label'] ); ?>" placeholder="Learn More"></div>
                    <div class="eg-settings-field"><label>Button URL</label>
                        <input class="eg-input" type="url" name="engam_car_slides[<?php echo $idx; ?>][btn_url]" value="<?php echo esc_attr( $s['btn_url'] ); ?>" placeholder="https://..."></div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
