<?php
if ( ! defined( 'WPINC' ) ) die;

class Equinenetwork_Gam_V2_Takeover {

    public function __construct() {}

    /**
     * Returns true if any entry (masthead OR wrap) is active and in-schedule.
     * Used by leaderboard and masthead suppression logic.
     */
    public static function has_active() {
        $takeovers = get_option( 'engam_v2_takeovers', array() );
        if ( ! is_array( $takeovers ) ) return false;
        $now = current_time( 'timestamp' );
        foreach ( $takeovers as $to ) {
            if ( ! self::entry_is_live( $to, $now ) ) continue;
            return true;
        }
        return false;
    }

    /**
     * Returns true only if a wrap-type entry is active and in-schedule.
     * Mastheads should not block wrap-takeover checks.
     */
    public static function has_active_wrap() {
        $takeovers = get_option( 'engam_v2_takeovers', array() );
        if ( ! is_array( $takeovers ) ) return false;
        $now = current_time( 'timestamp' );
        foreach ( $takeovers as $to ) {
            $type = isset( $to['type'] ) ? $to['type'] : 'wrap';
            if ( 'wrap' !== $type ) continue;
            if ( ! self::entry_is_live( $to, $now ) ) continue;
            return true;
        }
        return false;
    }

    /**
     * Single source of truth for whether an entry should serve:
     * marked active, has a GAM line item selected, and is within its schedule.
     * An entry without a line item is NEVER considered live — it has nothing
     * to deliver, so it must not suppress leaderboards or render an empty slot.
     */
    public static function entry_is_live( $to, $now = null ) {
        if ( empty( $to['active'] ) ) return false;
        if ( empty( $to['gam_line_item_id'] ) ) return false;
        if ( null === $now ) $now = current_time( 'timestamp' );

        // Explicit (stored) schedule — used by mastheads. Naive local datetime strings.
        $start = ! empty( $to['schedule_start'] ) ? strtotime( $to['schedule_start'] ) : 0;
        $end   = ! empty( $to['schedule_end'] )   ? strtotime( $to['schedule_end'] )   : 0;
        if ( $start && $now < $start ) return false;
        if ( $end   && $now > $end   ) return false;

        // No stored schedule (wraps don't store one) — inherit the linked GAM line item's flight
        // window so the takeover starts and stops automatically with the GAM flight. GAM timestamps
        // are timezone-aware (RFC3339), so they're compared against real UTC time, not WP-local.
        if ( ! $start || ! $end ) {
            $li = self::gam_line_item_flight( $to['gam_line_item_id'] );
            if ( $li ) {
                $utc_now = time();
                if ( ! $start && ! empty( $li['start_time'] ) ) {
                    $li_start = strtotime( $li['start_time'] );
                    if ( $li_start && $utc_now < $li_start ) return false;
                }
                if ( ! $end && ! empty( $li['end_time'] ) ) {
                    $li_end = strtotime( $li['end_time'] );
                    if ( $li_end && $utc_now > $li_end ) return false;
                }
            }
        }
        return true;
    }

    /**
     * Looks up a cached GAM line item by its numeric ID and returns its flight dates (or null).
     * Used so wraps inherit their schedule from GAM without storing a copy that can go stale.
     */
    public static function gam_line_item_flight( $gam_id ) {
        $gam_id = (string) $gam_id;
        $cached = get_transient( 'engam_v2_line_items' );
        if ( is_array( $cached ) ) {
            foreach ( $cached as $li ) {
                $by_gam = isset( $li['gam_id'] ) ? (string) $li['gam_id'] : '';
                $by_id  = isset( $li['id'] ) ? (string) $li['id'] : '';
                if ( $by_gam === $gam_id || strcasecmp( $by_id, $gam_id ) === 0 ) {
                    return $li;
                }
            }
        }
        // Durable fallback (engam_v2_li_flights): last-known flight dates persisted by the API on
        // every cache rebuild / GAM-ID lookup. Survives the 1-hour line-items cache expiring, so the
        // schedule display and start/stop enforcement keep working without a manual Refresh Cache.
        $flights = get_option( 'engam_v2_li_flights', array() );
        if ( is_array( $flights ) && isset( $flights[ $gam_id ] ) ) {
            return $flights[ $gam_id ];
        }
        return null;
    }

    /**
     * Find the first active + in-schedule entry of ANY type.
     */
    private function get_active() {
        $takeovers = get_option( 'engam_v2_takeovers', array() );
        if ( ! is_array( $takeovers ) ) return null;
        $now = current_time( 'timestamp' );
        foreach ( $takeovers as $to ) {
            if ( ! self::entry_is_live( $to, $now ) ) continue;
            return $to;
        }
        return null;
    }

    /**
     * Inline masthead HTML generation.
     */
    private function masthead_html( $m, $debug = false ) {
        $slot = ! empty( $m['slotname'] ) ? $m['slotname'] : 'homepagetakeover';

        require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-api.php';
        $api        = new Equinenetwork_Gam_V2_API();
        $cache_key  = 'engam_v2_sizes_' . md5( $slot );
        $cached     = get_transient( $cache_key );
        if ( $cached !== false && ! empty( $cached ) ) {
            $sizes = $cached;
        } else {
            // Use default immediately to avoid blocking the page on a cold cache.
            $sizes = array( array( 2048, 300 ) );
            // Warm the cache in the background so the next load is fast.
            if ( $api->is_configured() ) {
                wp_schedule_single_event( time(), 'engam_warm_slot_sizes', array( $slot ) );
            }
        }

        $attrs = array(
            'class'         => 'equinenetworkad engam-masthead-ad',
            'data-align'    => 'center',
            'data-slotname' => $slot,
            'data-sizes'    => wp_json_encode( $sizes ),
        );
        if ( ! empty( $m['sponsor_id'] ) ) {
            $attrs['data-sponsorid'] = $m['sponsor_id'];
        }

        $attr_str = '';
        foreach ( $attrs as $k => $v ) {
            $attr_str .= ' ' . $k . '="' . esc_attr( $v ) . '"';
        }

        $bg_val   = ! empty( $m['bg_color'] ) ? $m['bg_color'] : '';
        $bg_style = $bg_val ? 'background:' . esc_attr( $bg_val ) . ';' : '';

        $debug_bar   = '';
        $debug_style = '';
        if ( $debug ) {
            $debug_style = 'height:300px;';
            $debug_bar   = '<div style="position:absolute;top:0;left:0;right:0;height:300px;'
                . 'display:flex;align-items:center;justify-content:center;'
                . 'background:repeating-linear-gradient(45deg,#c00 0,#c00 10px,#fff 10px,#fff 20px);'
                . 'color:#fff;font:bold 14px/1 sans-serif;text-shadow:0 1px 3px #000;pointer-events:none;z-index:1;">'
                . 'MASTHEAD &mdash; ' . esc_html( $slot )
                . '</div>';
        }

        return '<div class="engam-masthead" style="width:100%;overflow:hidden;text-align:center;line-height:0;position:relative;' . $debug_style . $bg_style . '">'
            . $debug_bar
            . '<div' . $attr_str . '></div>'
            . '</div>';
    }

    /**
     * Check wrap targeting: if categories or pages are set, only show on matching pages.
     * No targeting set = show everywhere.
     */
    private function wrap_is_targeted( $to ) {
        $cats_raw   = trim( $to['wrap_cats']  ?? '' );
        // wrap_pages and wrap_posts are both WP post IDs — merge for the singular check.
        $page_ids   = array_filter( array_map( 'intval', (array) ( $to['wrap_pages'] ?? array() ) ) );
        $post_ids   = array_filter( array_map( 'intval', (array) ( $to['wrap_posts'] ?? array() ) ) );
        $id_targets = array_merge( $page_ids, $post_ids );
        $has_cats   = $cats_raw !== '';
        $has_pages  = ! empty( $id_targets );

        // No targeting set — show everywhere.
        if ( ! $has_cats && ! $has_pages ) return true;

        // Category targeting (posts only).
        if ( $has_cats && is_singular( 'post' ) ) {
            $allowed = array_filter( array_map( 'trim', explode( ',', strtolower( $cats_raw ) ) ) );
            $post_slugs = array();
            foreach ( get_the_category( get_queried_object_id() ) as $c ) {
                $post_slugs[] = strtolower( $c->slug );
            }
            if ( ! empty( array_intersect( $allowed, $post_slugs ) ) ) return true;
        }

        // Page/post ID targeting.
        if ( $has_pages && is_singular() ) {
            $id = get_queried_object_id();
            if ( $id && in_array( (int) $id, $id_targets, true ) ) return true;
        }

        return false;
    }

    /**
     * Check masthead targeting: show_home or matching page ID.
     */
    private function masthead_is_targeted( $m ) {
        if ( ! empty( $m['show_home'] ) && ( is_front_page() || is_home() ) ) return true;
        if ( ! empty( $m['pages'] ) && is_singular() ) {
            $id = get_queried_object_id();
            if ( $id && in_array( (int) $id, array_map( 'intval', (array) $m['pages'] ), true ) ) return true;
        }
        return false;
    }

    /**
     * Render in wp_footer. Handles both masthead and wrap types.
     */
    public function render_takeover() {
        $to = $this->get_active();
        if ( ! $to ) return;

        $type           = isset( $to['type'] ) ? $to['type'] : 'wrap';
        $is_admin_user  = current_user_can( 'edit_posts' );

        // ---- MASTHEAD type ----
        if ( 'masthead' === $type ) {
            // Masthead respects page targeting
            if ( ! $this->masthead_is_targeted( $to ) ) return;

            $debug = isset( $_GET['engam_debug'] ) && $is_admin_user;

            echo $this->masthead_html( $to, $debug ); // phpcs:ignore
            ?>
<script>
(function(){
    function move(){
        var slots=document.querySelectorAll('.engam-masthead');
        if(!slots.length) return;
        var header=document.querySelector('header.site-header,header#masthead,header#site-header,.site-header,header');
        if(header&&header.parentNode){
            slots.forEach(function(s){header.parentNode.insertBefore(s,header);});
        }
    }
    if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',move);}else{move();}
})();
</script>
            <?php
            return;
        }

        // ---- WRAP TAKEOVER type ----

        // Respect page/category targeting.
        if ( ! $this->wrap_is_targeted( $to ) ) return;

        $show_to_admins = ! empty( $to['show_to_admins'] );

        // Show admin notice bar instead of full takeover when show_to_admins = false
        if ( $is_admin_user && ! $show_to_admins ) {
            // Wraps store no schedule of their own — inherit the linked GAM line item's flight window
            // (the same source entry_is_live() enforces) so the bar shows the real start/stop dates
            // instead of a misleading "Now → No end date".
            $bar_start = ! empty( $to['schedule_start'] ) ? $to['schedule_start'] : '';
            $bar_end   = ! empty( $to['schedule_end'] )   ? $to['schedule_end']   : '';
            if ( ( $bar_start === '' || $bar_end === '' ) && ! empty( $to['gam_line_item_id'] ) ) {
                $li = self::gam_line_item_flight( $to['gam_line_item_id'] );
                if ( $li ) {
                    if ( $bar_start === '' && ! empty( $li['start_time'] ) ) $bar_start = $li['start_time'];
                    if ( $bar_end   === '' && ! empty( $li['end_time'] ) )   $bar_end   = $li['end_time'];
                }
            }
            $start_fmt = $bar_start !== '' ? date_i18n( 'M j, Y g:i a', strtotime( $bar_start ) ) : 'Now';
            $end_fmt   = $bar_end   !== '' ? date_i18n( 'M j, Y g:i a', strtotime( $bar_end ) )   : 'No end date';
            echo '<div style="position:fixed;bottom:0;left:0;right:0;z-index:999999;background:#d0ff00;color:#111;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:700;padding:10px 20px;text-align:center;border-top:3px solid #050505">';
            echo 'TAKEOVER ACTIVE (admins see this bar only): ';
            echo esc_html( $to['name'] );
            echo ' &mdash; ' . esc_html( $start_fmt ) . ' &rarr; ' . esc_html( $end_fmt );
            echo '</div>';
            return;
        }

        // GPT-based wrap: render 3 ad slots into fixed containers.
        // slot_name is legacy/optional — when empty the slots target the network
        // root ad unit and panels are differentiated purely by the pos targeting.
        $slot_name          = esc_js( $to['slot_name'] ?? '' );
        $bg_color           = esc_js( $to['bg_color']  ?? '#000000' );
        $admin_bar_offset   = is_admin_bar_showing() ? 32 : 0;
        $configured_line_id = esc_js( $to['gam_line_item_id'] ?? '' );
        $takeover_name      = esc_js( $to['name'] ?? '' );
        $slot_check_nonce   = wp_create_nonce( 'engam_v2_slot_check' );

        ?>
<script id="engam-wrap-gpt-script">
(function(){
    var MIN_VW = 1280;
    var adminBarOffset   = <?php echo (int) $admin_bar_offset; ?>;
    var bgColor          = '<?php echo $bg_color; ?>';
    var slotName         = '<?php echo $slot_name; ?>';
    var networkId        = String( window.equinenetwork_gam_v2_id || '' );
    var configuredLineId = '<?php echo $configured_line_id; ?>';
    var takeoverName     = '<?php echo $takeover_name; ?>';
    var slotCheckNonce   = '<?php echo esc_js( $slot_check_nonce ); ?>';
    var ajaxUrl          = '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>';
    var isAdminUser      = <?php echo $is_admin_user ? 'true' : 'false'; ?>;
    var reportedMismatches = {};

    // Creative sizes — discovered from GAM's slotRenderEnded event, not hardcoded.
    var creativeW = 0, creativeH = 0, bgW = 0, bgH = 0;

    var lastRendered = { left: false, right: false, bg: false };
    var resizeTimer  = null;

    function getPanelWidth() {
        if ( creativeW === 0 ) return 0;
        var vw = Math.max( document.documentElement.clientWidth || 0, window.innerWidth || 0 );
        if ( vw < MIN_VW ) return 0;
        return Math.min( 200, 160 + Math.max( 0, vw - 1366 ) * 0.1 );
    }

    // Create panel containers (sized dynamically after GAM responds)
    var panelLeft = document.createElement('div');
    panelLeft.id = 'engam-wrap-panel-left';
    panelLeft.style.cssText = 'position:fixed;left:0;top:' + adminBarOffset + 'px;bottom:0;z-index:0;overflow:hidden;display:none;background:' + bgColor;
    var slotLeftDiv = document.createElement('div');
    slotLeftDiv.id = 'engam-wrap-slot-left';
    slotLeftDiv.style.cssText = 'transform-origin:top left';
    panelLeft.appendChild( slotLeftDiv );

    var panelRight = document.createElement('div');
    panelRight.id = 'engam-wrap-panel-right';
    panelRight.style.cssText = 'position:fixed;right:0;top:' + adminBarOffset + 'px;bottom:0;z-index:0;overflow:hidden;display:none;background:' + bgColor;
    var slotRightWrapper = document.createElement('div');
    slotRightWrapper.style.cssText = 'position:absolute;right:0;top:0';
    var slotRightDiv = document.createElement('div');
    slotRightDiv.id = 'engam-wrap-slot-right';
    slotRightDiv.style.cssText = 'transform-origin:top right';
    slotRightWrapper.appendChild( slotRightDiv );
    panelRight.appendChild( slotRightWrapper );

    var panelBg = document.createElement('div');
    panelBg.id = 'engam-wrap-panel-bg';
    panelBg.style.cssText = 'position:relative;overflow:hidden;display:none;line-height:0;background:' + bgColor;
    var slotBgDiv = document.createElement('div');
    slotBgDiv.id = 'engam-wrap-slot-bg';
    slotBgDiv.style.cssText = 'transform-origin:top left';
    panelBg.appendChild( slotBgDiv );

    // Panels injected into DOM only after a slot renders.
    var panelsInjected = false;
    function injectPanels() {
        if ( panelsInjected ) return;
        panelsInjected = true;
        document.body.appendChild( panelLeft );
        document.body.appendChild( panelRight );
        document.body.insertBefore( panelBg, document.body.firstChild );
    }

    function updateLayout( rendered ) {
        lastRendered = rendered;
        var panelWidth   = getPanelWidth();
        var canShowSides = panelWidth > 0 && creativeW > 0;
        var scale        = canShowSides ? panelWidth / creativeW : 0;
        var vw           = Math.max( document.documentElement.clientWidth || 0, window.innerWidth || 0 );
        var leftPad      = 0;

        if ( canShowSides && rendered.left ) {
            panelLeft.style.width   = panelWidth + 'px';
            panelLeft.style.display = 'block';
            slotLeftDiv.style.transform = 'scale(' + scale + ')';
            leftPad = panelWidth;
        } else {
            panelLeft.style.display = 'none';
        }
        document.body.style.paddingLeft = leftPad ? leftPad + 'px' : '';

        if ( canShowSides && rendered.right ) {
            panelRight.style.width   = panelWidth + 'px';
            panelRight.style.display = 'block';
            slotRightDiv.style.transform = 'scale(' + scale + ')';
            document.body.style.paddingRight = panelWidth + 'px';
        } else {
            panelRight.style.display = 'none';
            document.body.style.paddingRight = '';
        }

        if ( rendered.bg && bgW > 0 ) {
            var rightPad = ( canShowSides && rendered.right ) ? panelWidth : 0;
            var bgWidth  = vw - leftPad - rightPad;
            var bgScale  = bgWidth / bgW;  // always fill — scale up or down as needed
            slotBgDiv.style.transform = 'scale(' + bgScale + ')';
            panelBg.style.height  = ( bgH * bgScale ) + 'px';
            panelBg.style.width   = bgWidth + 'px';
            panelBg.style.display = 'block';
        } else {
            panelBg.style.display = 'none';
        }

        if ( ( canShowSides && ( rendered.left || rendered.right ) ) || rendered.bg ) {
            document.body.classList.add('equineads-takeover-active');
        }
    }

    window.addEventListener('resize', function() {
        if ( resizeTimer ) clearTimeout( resizeTimer );
        resizeTimer = setTimeout( function() { updateLayout( lastRendered ); }, 100 );
    });

    // Broad size arrays — covers all common wrap creative sizes.
    // GAM matches the creative size it has; slotRenderEnded tells us what was actually served.
    var SIDE_SIZES = [[450,1200],[300,1050],[300,600],[160,600],[120,600],[200,600],[300,250]];
    var BG_SIZES   = [[2000,333],[1000,150],[970,250],[728,90],[800,200],[1800,200],[1600,300]];

    window.googletag = window.googletag || { cmd: [] };
    googletag.cmd.push(function() {
        var rendered    = { left: false, right: false, bg: false };
        var layoutTimer = null;

        function scheduleLayout() {
            if ( layoutTimer ) clearTimeout( layoutTimer );
            layoutTimer = setTimeout( function() { updateLayout( rendered ); }, 50 );
        }

        setTimeout( function() {
            if ( rendered.left || rendered.right || rendered.bg ) {
                injectPanels();
                scheduleLayout();
            }
        }, 5000 );

        googletag.pubads().addEventListener('slotRenderEnded', function(event) {
            var elId = event.slot.getSlotElementId();
            var sz   = Array.isArray( event.size ) ? event.size : null;

            if ( elId === 'engam-wrap-slot-left' ) {
                rendered.left = ! event.isEmpty;
                if ( sz && sz[0] > 0 ) {
                    creativeW = sz[0]; creativeH = sz[1];
                    slotLeftDiv.style.width  = creativeW + 'px';
                    slotLeftDiv.style.height = creativeH + 'px';
                }
            }
            if ( elId === 'engam-wrap-slot-right' ) {
                rendered.right = ! event.isEmpty;
                if ( sz && sz[0] > 0 ) {
                    if ( creativeW === 0 ) { creativeW = sz[0]; creativeH = sz[1]; }
                    slotRightDiv.style.width  = sz[0] + 'px';
                    slotRightDiv.style.height = sz[1] + 'px';
                }
            }
            if ( elId === 'engam-wrap-slot-bg' ) {
                rendered.bg = ! event.isEmpty;
                if ( sz && sz[0] > 0 ) {
                    bgW = sz[0]; bgH = sz[1];
                    slotBgDiv.style.width  = bgW + 'px';
                    slotBgDiv.style.height = bgH + 'px';
                }
            }

            // Mismatch detection: only check the wrap panel slots — leaderboards,
            // rectangles, and other standard display slots always serve different
            // line items and never compete with the wrap.
            var isWrapSlot = ( elId === 'engam-wrap-slot-left' || elId === 'engam-wrap-slot-right' || elId === 'engam-wrap-slot-bg' );
            if ( isWrapSlot && ! event.isEmpty && configuredLineId ) {
                var servedId = String( event.lineItemId || '' );
                if ( servedId && servedId !== '0' && servedId !== configuredLineId ) {
                    // Key by slot too — otherwise a single foreign line item winning
                    // left + right + bg collapses into one warning row and the admin
                    // only sees one slot, making a 3-rail conflict look like one.
                    var mismatchKey = configuredLineId + '_' + servedId + '_' + elId;
                    if ( ! reportedMismatches[ mismatchKey ] ) {
                        reportedMismatches[ mismatchKey ] = true;

                        // Persist the warning via AJAX so WP admin sees it.
                        if ( ajaxUrl ) {
                            var body = 'action=engam_v2_report_slot_mismatch'
                                + '&nonce=' + encodeURIComponent( slotCheckNonce )
                                + '&configured_id=' + encodeURIComponent( configuredLineId )
                                + '&served_id=' + encodeURIComponent( servedId )
                                + '&slot=' + encodeURIComponent( elId )
                                + '&takeover_name=' + encodeURIComponent( takeoverName )
                                + '&page_url=' + encodeURIComponent( window.location.href );
                            fetch( ajaxUrl, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: body
                            } ).catch( function(){} );
                        }

                        // Real-time notice for logged-in admins browsing the front end.
                        if ( isAdminUser ) {
                            var bar = document.getElementById('engam-slot-mismatch-bar');
                            if ( ! bar ) {
                                bar = document.createElement('div');
                                bar.id = 'engam-slot-mismatch-bar';
                                bar.style.cssText = 'position:fixed;bottom:0;left:0;right:0;z-index:9999999;'
                                    + 'background:#ef4444;color:#fff;font-family:Arial,Helvetica,sans-serif;'
                                    + 'font-size:13px;font-weight:700;padding:10px 20px;text-align:center;'
                                    + 'border-top:3px solid #b91c1c;cursor:pointer';
                                bar.title = 'Click to dismiss';
                                bar.addEventListener('click', function(){ bar.remove(); });
                                document.body.appendChild( bar );
                            }
                            bar.innerHTML = '⚠ EN Ads: Unexpected line item serving on <strong>'
                                + takeoverName + '</strong> — configured: ' + configuredLineId
                                + ', served: <strong>' + servedId + '</strong>.'
                                + ' Another active GAM line item is winning the auction. Check GAM. &times;';
                        }
                    }
                }
            }

            if ( rendered.left || rendered.right || rendered.bg ) injectPanels();
            scheduleLayout();
        });

        // Ad unit path: append slotName as a child unit only if one was set;
        // otherwise target the network root ad unit. Panels are always
        // differentiated by the pos targeting key (left / right / bg).
        var adUnitPath = slotName ? ( networkId + '/' + slotName ) : networkId;

        var slotLeft = googletag.defineSlot( adUnitPath, SIDE_SIZES, 'engam-wrap-slot-left' );
        if ( slotLeft ) slotLeft.setTargeting('pos', 'left').addService( googletag.pubads() );
        var slotRight = googletag.defineSlot( adUnitPath, SIDE_SIZES, 'engam-wrap-slot-right' );
        if ( slotRight ) slotRight.setTargeting('pos', 'right').addService( googletag.pubads() );
        var slotBg = googletag.defineSlot( adUnitPath, BG_SIZES, 'engam-wrap-slot-bg' );
        if ( slotBg ) slotBg.setTargeting('pos', 'bg').addService( googletag.pubads() );

        googletag.enableServices();

        // The slot divs must exist in the DOM before googletag.display() runs,
        // or GPT throws "could not find div" and the slot never requests.
        // Inject the panels now (they stay display:none until a creative
        // actually renders — updateLayout() reveals them on slotRenderEnded).
        injectPanels();

        if ( slotLeft )  googletag.display('engam-wrap-slot-left');
        if ( slotRight ) googletag.display('engam-wrap-slot-right');
        if ( slotBg )    googletag.display('engam-wrap-slot-bg');
    });
})();
</script>
        <?php
    }
}
