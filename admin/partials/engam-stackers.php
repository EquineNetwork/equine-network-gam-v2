<?php if ( ! defined( 'WPINC' ) ) die;

$notice    = '';
$gam_netid = get_option( 'equinenetwork_gam_v2_id', '' );

// Default global stacker injection settings. Migrate from the legacy per-stacker
// list (first active entry) the first time this page is loaded.
$stk_defaults = array(
    'active'          => true,
    'placement'       => 'paragraph',
    'after_paragraph' => 5,
    'cats'            => '',
    'hide_cats'       => '',
    'hide_ids'        => '',
    'hide_sponsors'   => '',
);
$settings = get_option( 'engam_v2_stacker_settings', null );
if ( ! is_array( $settings ) ) {
    $settings = $stk_defaults;
    $legacy   = get_option( 'engam_v2_stackers_list', array() );
    if ( is_array( $legacy ) ) {
        foreach ( $legacy as $ls ) {
            if ( ! empty( $ls['active'] ) ) {
                $settings = array(
                    'active'          => true,
                    'after_paragraph' => max( 1, (int) ( $ls['after_paragraph'] ?? 5 ) ),
                    'cats'            => (string) ( $ls['cats'] ?? '' ),
                    'hide_cats'       => (string) ( $ls['hide_cats'] ?? '' ),
                    'hide_ids'        => (string) ( $ls['hide_ids'] ?? '' ),
                    'hide_sponsors'   => (string) ( $ls['hide_sponsors'] ?? '' ),
                );
                break;
            }
        }
    }
}
$settings = wp_parse_args( $settings, $stk_defaults );

// ---- Handle POST ----
if ( isset( $_POST['engam_v2_stacker_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['engam_v2_stacker_nonce'] ) ), 'engam_v2_stacker_save' ) ) {
    if ( ! current_user_can( 'edit_posts' ) ) wp_die( -1 );

    $action = isset( $_POST['engam_stacker_action'] ) ? sanitize_text_field( wp_unslash( $_POST['engam_stacker_action'] ) ) : '';

    if ( 'save_settings' === $action ) {
        $settings = array(
            'active'          => true, // Stackers always inject — no off switch.
            'placement'       => ( ( $_POST['engam_stacker_placement'] ?? 'paragraph' ) === 'end' ) ? 'end' : 'paragraph',
            'after_paragraph' => max( 1, absint( $_POST['engam_stacker_after_paragraph'] ?? 5 ) ),
            'hide_cats'       => implode( ',', array_filter( array_map( 'sanitize_title', (array) ( $_POST['engam_stacker_hide_cats_multi'] ?? array() ) ) ) ),
            'hide_ids'        => implode( ',', array_filter( array_map( 'intval', (array) ( $_POST['engam_stacker_hide_ids_multi'] ?? array() ) ) ) ),
            'hide_sponsors'   => sanitize_text_field( wp_unslash( $_POST['engam_stacker_hide_sponsors'] ?? '' ) ),
        );
        update_option( 'engam_v2_stacker_settings', $settings );
        $notice = 'Stacker settings saved.';
    }
}

// Build {id:slug, title:name, type:'category'} data for the category pickers.
$engam_cat_picker_data = function( $csv ) {
    $out = array();
    foreach ( array_filter( array_map( 'trim', explode( ',', (string) $csv ) ) ) as $slug ) {
        $term  = get_term_by( 'slug', $slug, 'category' );
        $out[] = array( 'id' => $slug, 'title' => $term ? $term->name : $slug, 'type' => 'category' );
    }
    return $out;
};
$hide_cats_data = $engam_cat_picker_data( $settings['hide_cats'] );

$hide_ids_data = array();
$hide_ids_raw  = trim( (string) $settings['hide_ids'] );
if ( $hide_ids_raw !== '' ) {
    $hide_ids_arr = array_filter( array_map( 'intval', explode( ',', $hide_ids_raw ) ) );
    if ( $hide_ids_arr ) {
        foreach ( get_posts( array( 'post__in' => $hide_ids_arr, 'post_type' => array( 'post', 'page' ), 'posts_per_page' => -1, 'post_status' => 'any' ) ) as $sp ) {
            $hide_ids_data[] = array( 'id' => $sp->ID, 'title' => $sp->post_title, 'type' => $sp->post_type );
        }
    }
}

$stacker_slot = $gam_netid ? rtrim( $gam_netid, '/' ) . '/stacker' : '/networkId/stacker';

// Read-only "ai_category" taxonomy from GAM (cached 12h inside the API class).
require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-api.php';
$engam_api     = new Equinenetwork_Gam_V2_API();
$ai_categories = $engam_api->get_ai_category_values();

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

<!-- STACKERS — single consolidated card -->
<div class="eg-card" style="margin-top:18px">
    <div class="eg-head">
        <div>
            <h2>Stackers</h2>
            <p>Stackers are created and managed in GAM — the plugin injects the <code><?php echo esc_html( $stacker_slot ); ?></code> 320&times;480 slot into posts and GAM serves the right creative by its own AI-category targeting. Use the settings below to control where the slot is placed and where it should be hidden.</p>
        </div>
    </div>

    <!-- AI CATEGORIES — read-only, pulled live from GAM -->
    <div class="eg-form-section">
        <h3>AI Categories Targeted in GAM</h3>
        <?php if ( is_wp_error( $ai_categories ) ) : ?>
            <p class="eg-hint" style="margin-top:0">Couldn&rsquo;t load categories from GAM: <?php echo esc_html( $ai_categories->get_error_message() ); ?></p>
        <?php elseif ( empty( $ai_categories ) ) : ?>
            <p class="eg-hint" style="margin-top:0">No <code>ai_category</code> values are defined in GAM yet — they&rsquo;ll appear here automatically once they exist.</p>
        <?php else : ?>
            <p class="eg-hint" style="margin:0 0 12px">Read-only — the <code>ai_category</code> values defined in GAM that stacker creatives target against. Managed in GAM; shown here for reference (<?php echo count( $ai_categories ); ?> total).</p>
            <div style="display:flex;flex-wrap:wrap;gap:8px">
                <?php foreach ( $ai_categories as $cat ) : ?>
                <span style="display:inline-block;padding:7px 16px;background:#050505;color:#fff;border-radius:100px;font-size:12px;font-weight:700;letter-spacing:.02em;line-height:1.2"><?php echo esc_html( $cat ); ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field( 'engam_v2_stacker_save', 'engam_v2_stacker_nonce' ); ?>
        <input type="hidden" name="engam_stacker_action" value="save_settings">

        <!-- PLACEMENT -->
        <div class="eg-form-section">
            <h3>Placement</h3>
            <?php $place = ( ( $settings['placement'] ?? 'paragraph' ) === 'end' ) ? 'end' : 'paragraph'; ?>
            <div style="display:flex;gap:28px;flex-wrap:wrap;align-items:flex-start">
                <div class="eg-settings-field">
                    <label>Where to inject</label>
                    <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;margin-bottom:6px">
                        <input type="radio" name="engam_stacker_placement" value="paragraph" <?php checked( $place, 'paragraph' ); ?> onchange="document.getElementById('engam-stacker-para-wrap').style.display=this.checked?'block':'none'">
                        After a paragraph
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer">
                        <input type="radio" name="engam_stacker_placement" value="end" <?php checked( $place, 'end' ); ?> onchange="document.getElementById('engam-stacker-para-wrap').style.display=this.checked?'none':'block'">
                        At the end of the post
                    </label>
                </div>
                <div class="eg-settings-field" id="engam-stacker-para-wrap" style="max-width:200px;<?php echo $place === 'end' ? 'display:none' : ''; ?>">
                    <label for="engam-stacker-paragraph">Inject After Paragraph</label>
                    <input class="eg-input" type="number" min="1" max="20" name="engam_stacker_after_paragraph" id="engam-stacker-paragraph"
                        value="<?php echo esc_attr( $settings['after_paragraph'] ); ?>" style="max-width:100px">
                    <p class="eg-hint">Appears after this paragraph number. If the post is shorter, it falls back to the end.</p>
                </div>
            </div>
        </div>

        <!-- HIDE RULES -->
        <div class="eg-form-section">
            <h3>Hide Rules</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
                <div class="eg-settings-field">
                    <label>Hide on Categories</label>
                    <div id="engam-stacker-hide-cats-picker"></div>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        engamPostPicker({
                            wrap:        '#engam-stacker-hide-cats-picker',
                            name:        'engam_stacker_hide_cats_multi[]',
                            action:      'engam_v2_search_terms',
                            taxonomy:    'category',
                            placeholder: 'Search categories…',
                            selected:    <?php echo wp_json_encode( $hide_cats_data ); ?>
                        });
                    });
                    </script>
                    <p class="eg-hint">Search and select categories where the stacker should never appear.</p>
                </div>
                <div class="eg-settings-field">
                    <label>Hide on Specific Posts / Pages</label>
                    <div id="engam-stacker-hide-ids-picker"></div>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        engamPostPicker({
                            wrap:     '#engam-stacker-hide-ids-picker',
                            name:     'engam_stacker_hide_ids_multi[]',
                            types:    ['post', 'page'],
                            selected: <?php echo wp_json_encode( $hide_ids_data ); ?>
                        });
                    });
                    </script>
                    <p class="eg-hint">Search and select posts/pages where the stacker should never appear.</p>
                </div>
                <div class="eg-settings-field">
                    <label for="engam-stacker-hide-sponsors">Hide When Sponsor ID Is Active</label>
                    <input class="eg-input" type="text" name="engam_stacker_hide_sponsors" id="engam-stacker-hide-sponsors"
                        value="<?php echo esc_attr( $settings['hide_sponsors'] ); ?>" placeholder="e.g. CactusRopes_Horses, Equinety_Salute">
                    <p class="eg-hint">Suppress when a post has one of these sponsor overrides. Use <code>*</code> to hide on <strong>any</strong> sponsored post.</p>
                </div>
            </div>
        </div>

        <div class="eg-form-section" style="border-top:1px solid #deded8">
            <button type="submit" class="eg-btn" style="padding:14px 32px;font-size:14px">Save Settings</button>
        </div>
    </form>
    <div class="eg-accentline"></div>
</div>

</div><!-- .eg-content -->
</div><!-- #engam-v2-wrap -->
