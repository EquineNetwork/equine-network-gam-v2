<?php
if ( ! defined( 'WPINC' ) ) die;
if ( ! get_option( 'equinenetwork_gam_v2_id' ) ) return;

// Determine content-type targeting.
$taxonomy = '';
if ( is_category() || is_archive() ) $taxonomy = 'category';
if ( is_single() )                   $taxonomy = 'article';
if ( is_page() )                     $taxonomy = 'page';
if ( is_tag() )                      $taxonomy = 'tag';
if ( is_front_page() || is_home() )  $taxonomy = 'home';
if ( is_search() )                   $taxonomy = 'search';

// Build URL path segments.
global $wp;
$current_url  = home_url( add_query_arg( array(), $wp->request ) );
$paths        = explode( '/', $current_url );
$final_paths  = array_values( array_filter( $paths ) );

// ACF-style scrubber for ai_category values (keeps GAM happy).
function engam_v2_scrub_for_gam( $str ) {
	$replacements = array(
		'Neonatal Maladjustment Syndrome Dummy Foal' => 'Neonatal Maladjustment Synd. Dummy Foal',
		'Exercise Induced Pulmonary Hemorrhage EIPH'  => 'Exercise Induced Pulmonary Hemorr. EIPH',
	);
	if ( isset( $replacements[ $str ] ) ) $str = $replacements[ $str ];
	$str = preg_replace( '/\&/', 'and', $str );
	$str = preg_replace( "/[\,\']/", '', $str );
	$str = preg_replace( '/\//', '-', $str );
	return addslashes( $str );
}

// Sponsor ID overrides: post meta (no ACF needed) → exceptions.json.
$sponsor_override     = '';
$post_meta_sponsor    = get_post_meta( get_the_ID(), '_engam_v2_sponsor_id', true );
$term_meta_sponsor    = '';
$queried              = get_queried_object();
if ( $queried instanceof WP_Term ) {
	$term_meta_sponsor = get_term_meta( $queried->term_id, '_engam_v2_sponsor_id', true );
}

if ( $post_meta_sponsor ) {
	$sponsor_override = esc_js( $post_meta_sponsor );
} elseif ( $term_meta_sponsor ) {
	$sponsor_override = esc_js( $term_meta_sponsor );
} else {
	// Legacy fallback: read the values the old ACF fields STORED, directly from
	// post/term meta rather than via get_field(). ACF leaves these meta values in
	// the database when a field is deleted, so reading them directly keeps any
	// ACF-assigned sponsor ID working even after the ACF field itself is removed.
	$legacy_pid = get_the_ID();
	$acf_val    = $legacy_pid ? ( get_post_meta( $legacy_pid, 'sponlineitemid', true ) ?: get_post_meta( $legacy_pid, 'sponsorship_id', true ) ) : '';
	if ( ! $acf_val && $queried instanceof WP_Term ) {
		$acf_val = get_term_meta( $queried->term_id, 'sponlineitemid', true ) ?: get_term_meta( $queried->term_id, 'sponsorship_id', true );
	}
	if ( $acf_val ) $sponsor_override = esc_js( $acf_val );
	// exceptions.json fallback.
	$exceptions_file = EQUINENETWORK_GAM_V2_PATH . 'public/exceptions/exceptions.json';
	if ( file_exists( $exceptions_file ) ) {
		$exceptions = json_decode( file_get_contents( $exceptions_file ), true );
		if ( isset( $exceptions[ get_the_ID() ] ) ) {
			$sponsor_override = esc_js( $exceptions[ get_the_ID() ] );
		}
	}
}

// AI category targeting from native post meta (falls back to ACF if present).
$ai_category     = get_post_meta( get_the_ID(), '_engam_v2_ai_category', true );
$ai_sub_category = get_post_meta( get_the_ID(), '_engam_v2_ai_sub_category', true );
if ( ! $ai_category && function_exists( 'get_field' ) )     $ai_category     = get_field( 'ai_category' );
if ( ! $ai_sub_category && function_exists( 'get_field' ) ) $ai_sub_category = get_field( 'ai_sub_category' );
?>
<script>
document.addEventListener('DOMContentLoaded', function () {

var MD5=function(d){var r=M(V(Y(X(d),8*d.length)));return r.toLowerCase()};function M(d){for(var _,m="0123456789ABCDEF",f="",r=0;r<d.length;r++)_=d.charCodeAt(r),f+=m.charAt(_>>>4&15)+m.charAt(15&_);return f}function X(d){for(var _=Array(d.length>>2),m=0;m<_.length;m++)_[m]=0;for(m=0;m<8*d.length;m+=8)_[m>>5]|=(255&d.charCodeAt(m/8))<<m%32;return _}function V(d){for(var _="",m=0;m<32*d.length;m+=8)_+=String.fromCharCode(d[m>>5]>>>m%32&255);return _}function Y(d,_){d[_>>5]|=128<<_%32,d[14+(_+64>>>9<<4)]=_;for(var m=1732584193,f=-271733879,r=-1732584194,i=271733878,n=0;n<d.length;n+=16){var h=m,t=f,g=r,e=i;f=md5_ii(f=md5_ii(f=md5_ii(f=md5_ii(f=md5_hh(f=md5_hh(f=md5_hh(f=md5_hh(f=md5_gg(f=md5_gg(f=md5_gg(f=md5_gg(f=md5_ff(f=md5_ff(f=md5_ff(f=md5_ff(f,r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+0],7,-680876936),f,r,d[n+1],12,-389564586),m,f,d[n+2],17,606105819),i,m,d[n+3],22,-1044525330),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+4],7,-176418897),f,r,d[n+5],12,1200080426),m,f,d[n+6],17,-1473231341),i,m,d[n+7],22,-45705983),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+8],7,1770035416),f,r,d[n+9],12,-1958414417),m,f,d[n+10],17,-42063),i,m,d[n+11],22,-1990404162),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+12],7,1804603682),f,r,d[n+13],12,-40341101),m,f,d[n+14],17,-1502002290),i,m,d[n+15],22,1236535329),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+1],5,-165796510),f,r,d[n+6],9,-1069501632),m,f,d[n+11],14,643717713),i,m,d[n+0],20,-373897302),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+5],5,-701558691),f,r,d[n+10],9,38016083),m,f,d[n+15],14,-660478335),i,m,d[n+4],20,-405537848),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+9],5,568446438),f,r,d[n+14],9,-1019803690),m,f,d[n+3],14,-187363961),i,m,d[n+8],20,1163531501),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+13],5,-1444681467),f,r,d[n+2],9,-51403784),m,f,d[n+7],14,1735328473),i,m,d[n+12],20,-1926607734),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+5],4,-378558),f,r,d[n+8],11,-2022574463),m,f,d[n+11],16,1839030562),i,m,d[n+14],23,-35309556),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+1],4,-1530992060),f,r,d[n+4],11,1272893353),m,f,d[n+7],16,-155497632),i,m,d[n+10],23,-1094730640),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+13],4,681279174),f,r,d[n+0],11,-358537222),m,f,d[n+3],16,-722521979),i,m,d[n+6],23,76029189),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+9],4,-640364487),f,r,d[n+12],11,-421815835),m,f,d[n+15],16,530742520),i,m,d[n+2],23,-995338651),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+0],6,-198630844),f,r,d[n+7],10,1126891415),m,f,d[n+14],15,-1416354905),i,m,d[n+5],21,-57434055),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+12],6,1700485571),f,r,d[n+3],10,-1894986606),m,f,d[n+10],15,-1051523),i,m,d[n+1],21,-2054922799),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+8],6,1873313359),f,r,d[n+15],10,-30611744),m,f,d[n+6],15,-1560198380),i,m,d[n+13],21,1309151649),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+4],6,-145523070),f,r,d[n+11],10,-1120210379),m,f,d[n+2],15,718787259),i,m,d[n+9],21,-343485551),m=safe_add(m,h),f=safe_add(f,t),r=safe_add(r,g),i=safe_add(i,e)}return Array(m,f,r,i)}function md5_cmn(d,_,m,f,r,i){return safe_add(bit_rol(safe_add(safe_add(_,d),safe_add(f,i)),r),m)}function md5_ff(d,_,m,f,r,i,n){return md5_cmn(_&m|~_&f,d,_,r,i,n)}function md5_gg(d,_,m,f,r,i,n){return md5_cmn(_&f|m&~f,d,_,r,i,n)}function md5_hh(d,_,m,f,r,i,n){return md5_cmn(_^m^f,d,_,r,i,n)}function md5_ii(d,_,m,f,r,i,n){return md5_cmn(m^(_|~f),d,_,r,i,n)}function safe_add(d,_){var m=(65535&d)+(65535&_);return(d>>16)+(_>>16)+(m>>16)<<16|65535&m}function bit_rol(d,_){return d<<_|d>>>32-_}

// IMPORTANT: every ad slot on the page is registered inside the single
// googletag.cmd queue below. If any one slot throws during registration
// (e.g. defineSlot returns null for a misconfigured/zero-area widget, then
// .addService() is called on null), GPT aborts the ENTIRE queue and no slots
// register — so leaderboards and every other ad silently stop firing.
// The guards below (null check after defineSlot, zero-area size filtering,
// duplicate-divID tracking, conditional sponsor targeting) make each slot
// self-contained so one bad widget can never block the rest. Do not remove.
var callbacks = 0;
var adSlots   = document.getElementsByClassName('equinenetworkad');
var registeredDivIDs = {};

window.googletag = window.googletag || {cmd: []};
googletag.cmd.push(function() {

	// Normalize to an array of valid [w,h] size pairs, dropping zero-area sizes.
	// Accepts either a single pair ([728,90]) or an array of pairs
	// ([[320,50],[728,90]]). The Elementor widget and the stacker emit
	// sizeDesktop/sizeMobile as single pairs; without this normalization they
	// failed the Array.isArray(s) test, validSizes returned null, and the size
	// mapping below was silently skipped — so GAM could serve a 320x50 creative
	// into a 728x90 desktop leaderboard.
	function validSizes(arr) {
		if (!Array.isArray(arr) || arr.length === 0) return null;
		// A single pair like [728, 90] → wrap as [[728, 90]].
		if (typeof arr[0] === 'number') arr = [arr];
		var filtered = arr.filter(function(s) {
			return Array.isArray(s) ? (s[0] > 0 && s[1] > 0) : false;
		});
		return filtered.length ? filtered : null;
	}

	// Global GPT config — must be set once before any slot is defined.
	googletag.setConfig({centering: true});
	var displayQueue = [];

	for (var i = 0; i < adSlots.length; i++) {
		var slot       = adSlots[i];
		// Skip ad slots inside a scheduled carousel that resolved to "off" (outside its
		// schedule window). Its inline gate marks the wrapper before GPT runs, so we never
		// request ads for a carousel that isn't being shown.
		if (slot.closest && slot.closest('.engam-car-sched[data-engam-sched-off]')) continue;
		var sizeDesktop = (typeof slot.dataset.sizedesktop === 'undefined') ? null : JSON.parse(slot.dataset.sizedesktop);
		var sizeMobile  = (typeof slot.dataset.sizemobile  === 'undefined') ? null : JSON.parse(slot.dataset.sizemobile);
		var adSize      = (typeof slot.dataset.sizes       === 'undefined') ? null : JSON.parse(slot.dataset.sizes);
		var adAlign     = slot.dataset.align    || 'center';
		var popup       = slot.dataset.popup    || false;
		var sponsorID   = slot.dataset.sponsorid || null;
		var slotName    = slot.dataset.slotname  || null;

		// Server-side sponsor override (from post/term meta or ACF).
		<?php if ( $sponsor_override ) : ?>
		sponsorID = '<?php echo $sponsor_override; ?>';
		<?php endif; ?>

		// Build size mapping only when explicit sizes are provided.
		// Masthead and leaderboard slots omit sizes — GAM serves fluid creatives.
		// Filter out zero-area sizes to prevent GPT errors.
		var validDsk = validSizes(sizeDesktop);
		var validMob = validSizes(sizeMobile);
		var mapping = null;
		if (validDsk && validMob) {
			mapping = googletag.sizeMapping()
				.addSize([728, 0], validDsk)
				.addSize([0,   0], validMob)
				.build();
		}

		var divID       = MD5(i + 'equinenetwork');
		var injectedHTML = '';
		var displayAd   = true;

		// Skip if this divID was already registered (prevents duplicate-slot errors).
		if (registeredDivIDs[divID]) continue;

		if ( popup === false || popup === 'false' ) {
			injectedHTML = '<div align="' + adAlign + '"><div id="' + divID + '" style="text-align:' + adAlign + '" align="' + adAlign + '"></div></div>';
		} else {
			// 5-minute cooldown for popup ads.
			var lastClose = parseInt(localStorage.getItem('engamModalLastClose')) || 0;
			if ( (Date.now() - lastClose) / 1000 / 60 <= 5 ) {
				injectedHTML = '';
				displayAd    = false;
			} else {
				injectedHTML  = '<div id="adModal" style="z-index:300000000;display:none;padding-top:100px;position:fixed;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,.4)">';
				injectedHTML += '<div style="margin:auto;position:relative;padding:0;outline:0;">';
				injectedHTML += '<div onclick="localStorage.setItem(\'engamModalLastClose\',Date.now());document.getElementById(\'adModal\').style.display=\'none\'" style="text-align:center;margin:0 0 3px 0;cursor:pointer;"><span style="background:white;padding:3px;">[X] CLOSE</span></div>';
				injectedHTML += '<div id="' + divID + '" style="text-align:' + adAlign + '" align="' + adAlign + '"></div>';
				injectedHTML += '</div></div>';

				setTimeout(function() {
					var modal = document.getElementById('adModal');
					if (modal) {
						modal.style.display = 'none';
						localStorage.setItem('engamModalLastClose', Date.now());
					}
				}, 10000);
			}
		}

		if ( displayAd ) {
			var gamNetworkID = window.equinenetwork_gam_v2_id;
			var fullSlotName = slotName ? (gamNetworkID + '/' + slotName) : gamNetworkID;

			// Use 'fluid' for slots with no explicit size (leaderboards, mastheads).
			var slotSize = (adSize !== null && adSize !== undefined) ? adSize : 'fluid';
			var gamSlot = googletag.defineSlot(fullSlotName, slotSize, divID);
			if (!gamSlot) continue; // defineSlot returns null on invalid args — skip this slot
			gamSlot.addService(googletag.pubads());
			registeredDivIDs[divID] = true;
			if (mapping) gamSlot.defineSizeMapping(mapping);

			gamSlot.setTargeting('content-type', '<?php echo esc_js( $taxonomy ); ?>');
			if (sponsorID) gamSlot.setTargeting('sponlineitemid', [sponsorID]);

			<?php
			echo 'var paths = ' . wp_json_encode( $final_paths ) . ';';
			if ( is_single() ) :
				echo "gamSlot.setTargeting('categories', window.post_categories);\n";
				echo "gamSlot.setTargeting('subcategories', window.post_subcategories);\n";
				echo "gamSlot.setTargeting('postid', '" . esc_js( get_the_ID() ) . "');\n";
				echo "gamSlot.setTargeting('path', [paths, window.post_categories, window.post_subcategories, '" . esc_js( get_the_ID() ) . "']);\n";
				if ( $ai_category ) :
					echo "gamSlot.setTargeting('ai_category', ['" . esc_js( engam_v2_scrub_for_gam( $ai_category ) ) . "']);\n";
					echo "googletag.pubads().setTargeting('ai_category', ['" . esc_js( engam_v2_scrub_for_gam( $ai_category ) ) . "']);\n";
				endif;
				if ( $ai_sub_category ) :
					echo "gamSlot.setTargeting('ai_sub_category', ['" . esc_js( engam_v2_scrub_for_gam( $ai_sub_category ) ) . "']);\n";
					echo "googletag.pubads().setTargeting('ai_sub_category', ['" . esc_js( engam_v2_scrub_for_gam( $ai_sub_category ) ) . "']);\n";
				endif;
			else :
				echo "gamSlot.setTargeting('path', paths);\n";
			endif;
			?>

			slot.innerHTML += injectedHTML;

			displayQueue.push(divID);
		}

		popup = false;

		// Collapse empty slots + their Elementor container.
		if ( callbacks === 0 ) {
			googletag.pubads().addEventListener('slotRenderEnded', function(event) {
				// Takeover wrap panel slots have their own dedicated handler in the
				// takeover class. Skip them here to avoid margin/display interference.
				var _eid = event.slot.getSlotElementId();
				if (_eid === 'engam-wrap-slot-left' || _eid === 'engam-wrap-slot-right' || _eid === 'engam-wrap-slot-bg') return;

				if ( event.advertiserId === null ) {
					var emptySlot = document.getElementById(event.slot.getSlotElementId());
					if ( emptySlot ) {
						// Walk up to the .equinenetworkad wrapper and hide it.
						var wrapper = emptySlot.closest ? emptySlot.closest('.equinenetworkad') : emptySlot.parentNode;
						if ( wrapper ) {
							// Admin debug: show the empty slot + its ad unit path
							// instead of collapsing, so placement can be verified.
							if ( window.equinenetwork_gam_v2_debug ) {
								try { wrapper.setAttribute('data-engam-debug', event.slot.getAdUnitPath()); } catch(e){}
								wrapper.classList.add('engam-debug-empty');
								return;
							}
							wrapper.classList.add('engam-empty');

							// Mid-content leaderboard injected into the page body (e.g. a
							// calendar): collapse only its own band, never walk up to the
							// page's containers or the surrounding content would disappear.
							var engamMidBand = wrapper.closest ? wrapper.closest('.engam-leaderboard-midpoint') : null;
							if ( engamMidBand ) {
								engamMidBand.classList.add('engam-empty');
								return;
							}

							// If this ad lives inside an EN carousel, the carousel
							// collapses its own empty slide via CSS (:has(.engam-empty)).
							// Do NOT walk up to the Elementor containers — that would
							// hide the entire carousel widget. Bail out here.
							if ( wrapper.closest('.engam-car') ) {
								return;
							}

							// Stacker divs are injected inside post content — never walk
							// up to Elementor containers or the whole post body disappears.
							if ( wrapper.classList.contains('engam-stacker') ) {
								return;
							}

							// Collapse widget-level containers.
							var elContainer = wrapper.closest('.elementor-widget-container');
							if ( elContainer ) elContainer.classList.add('engam-container-empty');
							var elWidget = wrapper.closest('.elementor-widget');
							if ( elWidget ) elWidget.classList.add('engam-container-empty');

							// Walk up to Elementor column / Flex Container and collapse
							// if ALL sibling widgets inside it are also empty.
							var colSelectors = [
								'.elementor-column',
								'.elementor-col-100',
								'.e-con',
								'.e-con-inner'
							];
							colSelectors.forEach(function(sel) {
								var col = wrapper.closest(sel);
								if ( ! col ) return;
								// Check that every .elementor-widget inside this column is empty.
								var allWidgets = col.querySelectorAll('.elementor-widget');
								if ( allWidgets.length === 0 ) return;
								var allEmpty = true;
								for ( var w = 0; w < allWidgets.length; w++ ) {
									if ( ! allWidgets[w].classList.contains('engam-container-empty') ) {
										allEmpty = false;
										break;
									}
								}
								if ( allEmpty ) col.classList.add('engam-container-empty');
							});

							// Also collapse Elementor sections that only contain empty columns.
							var section = wrapper.closest('.elementor-section');
							if ( section ) {
								var allCols = section.querySelectorAll('.elementor-column');
								var allColsEmpty = allCols.length > 0;
								for ( var c = 0; c < allCols.length; c++ ) {
									if ( ! allCols[c].classList.contains('engam-container-empty') ) {
										allColsEmpty = false;
										break;
									}
								}
								if ( allColsEmpty ) section.classList.add('engam-container-empty');
							}
						}
					}
				} else {
					var filledSlot = document.getElementById(event.slot.getSlotElementId());
					if ( filledSlot ) {
						// event.size is an array for fixed sizes, 'fluid' string for fluid creatives.
						if ( Array.isArray(event.size) ) {
							var isMasthead = !!filledSlot.closest('.engam-masthead');
							if ( isMasthead && event.size[0] > 0 ) {
								// Scale masthead to fill 100% width, preserve aspect ratio.
								// Use `transform:scale()`, NOT `zoom`. iOS Safari (WebKit) does
								// not reliably apply `zoom` to a cross-origin ad iframe — it left
								// the masthead a blank black bar on real iPhones (while Chrome's
								// device-emulation, which uses Chrome's engine, rendered it fine,
								// masking the bug). `transform` is solidly supported on WebKit.
								// Because transform doesn't reflow the box, the overflow:hidden
								// wrapper would still show the un-scaled footprint, so we set the
								// wrapper height explicitly to the scaled height. (The desktop
								// right-crop was the 728px iframe max-width cap, fixed separately
								// — never the scale technique.)
								var adW = event.size[0];
								var adH = event.size[1];
								filledSlot.style.transformOrigin = 'top left';
								filledSlot.style.width  = adW + 'px';
								filledSlot.style.height = adH + 'px';
								filledSlot.style.zoom = '';  // clear any stale zoom from a prior build
								var mastheadWrap = filledSlot.closest('.engam-masthead');
								function scaleMasthead() {
									// Measure the wrapper's ACTUAL rendered width, then clamp to
									// the visual viewport. On mobile, document.documentElement
									// .clientWidth can report the LAYOUT viewport (~980px) rather
									// than the real ~390px visual width — that under-scaled the
									// 2048px creative so it overflowed and the overflow:hidden
									// wrapper clipped it (the black cut-off bar on phones).
									// getBoundingClientRect().width + window.innerWidth reflect
									// the real on-screen width on every device.
									var rectW = mastheadWrap.getBoundingClientRect().width;
									var vw    = window.innerWidth || document.documentElement.clientWidth || rectW;
									var avail = Math.min( rectW || vw, vw );
									if ( avail <= 0 ) return;
									var scale = avail / adW;
									filledSlot.style.transform = ( scale === 1 ) ? 'none' : ( 'scale(' + scale + ')' );
									mastheadWrap.style.height = Math.round( adH * scale ) + 'px';
								}
								scaleMasthead();
								// Re-measure after layout settles (header repositioning, late
								// reflows, mobile address-bar resize) so a first-paint
								// mis-measure self-corrects.
								setTimeout( scaleMasthead, 250 );
								setTimeout( scaleMasthead, 1000 );
								window.addEventListener( 'resize', scaleMasthead );
								window.addEventListener( 'orientationchange', scaleMasthead );
							} else if ( !isMasthead ) {
								// Fixed-size content ad (medium rectangle, half page, etc).
								// A 300px creative in a narrow column overflows ("flies off
								// the side"); in a wide column it should render at its native
								// width. We scale with transform (the masthead technique) —
								// DOWN to fit a narrow column, and only UP to fill the column
								// when the slot opts in via data-fluid="1".
								var adW   = event.size[0];
								var adH   = event.size[1];
								var sizer = filledSlot.parentNode;          // the <div align="..."> wrapper
								var wrap  = filledSlot.closest('.equinenetworkad');
								var align = (wrap && wrap.dataset.align) ? wrap.dataset.align : 'center';
								var fluid = !!(wrap && wrap.dataset.fluid);

								// Carousels and in-content stackers manage their own layout —
								// keep the simple fixed-size center and skip column scaling.
								var special = wrap && ( (wrap.closest && wrap.closest('.engam-car')) || (wrap.classList && wrap.classList.contains('engam-stacker')) );
								if ( special ) {
									filledSlot.style.width     = adW + 'px';
									filledSlot.style.maxWidth  = adW + 'px';
									filledSlot.style.height    = adH + 'px';
									filledSlot.style.maxHeight = adH + 'px';
									if ( !wrap || align === 'center' ) {
										filledSlot.style.display = 'block';
										filledSlot.style.margin  = '0 auto';
									}
								} else {
									filledSlot.style.transformOrigin = 'top left';
									filledSlot.style.width  = adW + 'px';
									filledSlot.style.height = adH + 'px';

									// Measure the column from a stable, full-width block ancestor.
									// The .equinenetworkad wrapper holds an inline-block iframe,
									// so it shrink-wraps to the ad — measuring it would feed a
									// too-small width back into the scale and lock the creative
									// smaller than its column, leaving empty space around it.
									// Elementor's widget container is block-level and always
									// spans the column, so it gives the true available width.
									var availWidth = function() {
										var box = wrap;
										while ( box && box.parentElement ) {
											box = box.parentElement;
											if ( box.classList && (
												box.classList.contains('elementor-widget-container') ||
												box.classList.contains('elementor-widget') ||
												box.classList.contains('elementor-column') ||
												box.classList.contains('e-con-inner') ||
												box.classList.contains('e-con')
											) && box.clientWidth > 0 ) {
												return box.clientWidth;
											}
										}
										return ( wrap && wrap.clientWidth > 0 ) ? wrap.clientWidth : adW;
									};

									var scaleContentAd = function() {
										var avail = availWidth();
										// Native width when there's room; only shrink to fit a
										// narrower column. data-fluid lets a slot fill wider.
										var scale = fluid ? ( avail / adW ) : Math.min( 1, avail / adW );
										filledSlot.style.transform = ( scale === 1 ) ? 'none' : ( 'scale(' + scale + ')' );
										if ( sizer ) {
											// Sizer takes the scaled footprint so the column
											// height collapses and alignment works.
											sizer.style.width  = Math.round( adW * scale ) + 'px';
											sizer.style.height = Math.round( adH * scale ) + 'px';
											if ( align === 'left' )       sizer.style.margin = '0 auto 0 0';
											else if ( align === 'right' ) sizer.style.margin = '0 0 0 auto';
											else                          sizer.style.margin = '0 auto';
										}
									};
									scaleContentAd();
									window.addEventListener( 'resize', scaleContentAd );
								}
							}
							var modal = document.getElementById('adModal');
							if ( modal && filledSlot.querySelector('iframe') ) modal.style.display = 'block';
						}
					}
				}
			});
		}
		callbacks++;
	}
	// Register services once after all slots are defined.
	googletag.enableServices();
	displayQueue.forEach(function(id){googletag.display(id);});
});

}, false);
</script>
<?php
