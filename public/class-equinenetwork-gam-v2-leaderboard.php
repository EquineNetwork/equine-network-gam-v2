<?php
if ( ! defined( 'WPINC' ) ) die;

class Equinenetwork_Gam_V2_Leaderboard {

	const OPTION = 'engam_v2_leaderboards_list';

	public static function get_all() {
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) ? $saved : array();
	}

	private static function is_active( $lb ) {
		return ! empty( $lb['active'] );
	}

	private static function slot_html( $lb, $debug = false ) {
		$pos      = in_array( ( $lb['position'] ?? '' ), array( 'footer', 'midpoint' ), true ) ? $lb['position'] : 'header';
		$slotname = ! empty( $lb['slotname'] ) ? $lb['slotname'] : 'leaderboard';

		$dw = ! empty( $lb['dw'] ) ? (int) $lb['dw'] : 728;
		$dh = ! empty( $lb['dh'] ) ? (int) $lb['dh'] : 90;
		$mw = ! empty( $lb['mw'] ) ? (int) $lb['mw'] : 320;
		$mh = ! empty( $lb['mh'] ) ? (int) $lb['mh'] : 50;

		$attrs = array(
			'class'            => 'equinenetworkad engam-leaderboard-ad',
			'data-align'       => 'center',
			'data-slotname'    => $slotname,
			'data-sizedesktop' => '[['  . $dw . ',' . $dh . ']]',
			'data-sizemobile'  => '[[' . $mw . ',' . $mh . ']]',
		);
		if ( ! empty( $lb['sponsor_id'] ) ) {
			$attrs['data-sponsorid'] = $lb['sponsor_id'];
		}

		$attr_str = '';
		foreach ( $attrs as $k => $v ) {
			$attr_str .= ' ' . $k . '="' . esc_attr( $v ) . '"';
		}

		$pt = max( 0, (int) ( $lb['padding_top']    ?? 0 ) );
		$pr = max( 0, (int) ( $lb['padding_right']  ?? 0 ) );
		$pb = max( 0, (int) ( $lb['padding_bottom'] ?? 0 ) );
		$pl = max( 0, (int) ( $lb['padding_left']   ?? 0 ) );

		$band_style = 'width:100%;box-sizing:border-box;text-align:center;line-height:0;position:relative;';
		if ( ! empty( $lb['bg_color'] ) ) {
			$band_style .= 'background:' . esc_attr( $lb['bg_color'] ) . ';';
		}
		$band_style .= 'padding:' . $pt . 'px ' . $pr . 'px ' . $pb . 'px ' . $pl . 'px;';

		$debug_bar   = '';
		$debug_style = '';

		if ( $debug ) {
			$debug_style = 'min-height:' . ( 90 + $pt + $pb ) . 'px;';
			$debug_bar   = '<div style="position:absolute;top:0;left:0;right:0;bottom:0;'
				. 'display:flex;align-items:center;justify-content:center;'
				. 'background:repeating-linear-gradient(45deg,#c00 0,#c00 10px,#fff 10px,#fff 20px);'
				. 'color:#fff;font:bold 13px/1 sans-serif;text-shadow:0 1px 3px #000;pointer-events:none;z-index:1;">'
				. strtoupper( $pos ) . ' LEADERBOARD &mdash; ' . esc_html( $slotname )
				. '</div>';
		}

		// Mid-content slots carry the optional CSS selector that tells the front-end JS
		// where, inside the page, to drop the band (before the middle matching element).
		$selector_attr = '';
		if ( $pos === 'midpoint' && ! empty( $lb['target_selector'] ) ) {
			$selector_attr = ' data-engam-selector="' . esc_attr( $lb['target_selector'] ) . '"';
		}

		return '<div class="engam-leaderboard engam-leaderboard-' . esc_attr( $pos ) . '" '
			. 'style="' . $band_style . $debug_style . '" '
			. 'data-engam-leaderboard="' . esc_attr( $pos ) . '"' . $selector_attr . '>'
			. $debug_bar
			. '<div' . $attr_str . '></div>'
			. '</div>';
	}

	/**
	 * True when the current front-end view matches one of the comma-separated page
	 * targets (numeric IDs or post slugs). Used to scope mid-content leaderboards
	 * to specific pages. An empty target list never matches.
	 */
	private static function page_matches( $targets_raw ) {
		$targets_raw = trim( (string) $targets_raw );
		if ( $targets_raw === '' ) return false;

		$obj_id = (int) get_queried_object_id();
		if ( $obj_id <= 0 ) return false;
		$post = get_post( $obj_id );
		$slug = $post ? strtolower( $post->post_name ) : '';

		foreach ( array_filter( array_map( 'trim', explode( ',', $targets_raw ) ) ) as $t ) {
			if ( is_numeric( $t ) ) {
				if ( (int) $t === $obj_id ) return true;
			} elseif ( $slug !== '' && strcasecmp( $t, $slug ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	public function render_leaderboards() {
		$leaderboards = self::get_all();
		if ( empty( $leaderboards ) ) return;

		$debug = isset( $_GET['engam_debug'] ) && current_user_can( 'edit_posts' );

		$rendered_header = false;
		$rendered_footer = false;
		$has_output      = false;

		foreach ( $leaderboards as $lb ) {
			if ( ! self::is_active( $lb ) ) continue;
			$pos = in_array( ( $lb['position'] ?? '' ), array( 'footer', 'midpoint' ), true ) ? $lb['position'] : 'header';

			// Mid-content leaderboards only appear on their targeted page(s);
			// multiple may exist (one per page), so they are not de-duplicated.
			if ( $pos === 'midpoint' ) {
				if ( ! self::page_matches( $lb['target_pages'] ?? '' ) ) continue;
				echo self::slot_html( $lb, $debug ); // phpcs:ignore
				$has_output = true;
				continue;
			}

			if ( $pos === 'header' && $rendered_header ) continue;
			if ( $pos === 'footer' && $rendered_footer ) continue;

			echo self::slot_html( $lb, $debug ); // phpcs:ignore
			$has_output = true;
			if ( $pos === 'header' ) $rendered_header = true;
			if ( $pos === 'footer' ) $rendered_footer = true;
		}

		if ( ! $has_output ) return;

		?>
<style>
/* An unfilled mid-content leaderboard collapses its whole band so the page
   (e.g. a calendar) doesn't show an empty gap where the ad would have gone. */
.engam-leaderboard-midpoint.engam-empty,
.engam-leaderboard-midpoint:has(.equinenetworkad.engam-empty){display:none!important;height:0!important;padding:0!important;margin:0!important}
</style>
<script>
(function(){
	function midpointInto(container,node){
		var kids=container.children;
		if(!kids||!kids.length){container.appendChild(node);return;}
		var rect=container.getBoundingClientRect();
		var midY=rect.top+rect.height/2;
		for(var j=0;j<kids.length;j++){
			if(kids[j]===node||node.contains(kids[j]))continue;
			var kr=kids[j].getBoundingClientRect();
			if(kr.top+kr.height/2>=midY){container.insertBefore(node,kids[j]);return;}
		}
		container.appendChild(node);
	}
	function placeMidpoint(s){
		var sel=(s.getAttribute('data-engam-selector')||'').trim();
		if(sel){
			try{
				var matches=document.querySelectorAll(sel),list=[];
				for(var i=0;i<matches.length;i++){if(!s.contains(matches[i]))list.push(matches[i]);}
				if(list.length>1){
					var mid=list[Math.floor(list.length/2)];
					if(mid&&mid.parentNode){mid.parentNode.insertBefore(s,mid);return;}
				}else if(list.length===1){midpointInto(list[0],s);return;}
			}catch(e){}
		}
		// No (or unmatched) selector: drop at the visual midpoint of the main content.
		var defs=['.entry-content','main article','main .e-con-inner','main .elementor-section-wrap','main','article','#primary','#content','.site-content'];
		for(var d=0;d<defs.length;d++){
			var el=document.querySelector(defs[d]);
			if(el&&el.children.length){midpointInto(el,s);return;}
		}
	}
	function move(){
		var hSlots=document.querySelectorAll('[data-engam-leaderboard="header"]');
		var fSlots=document.querySelectorAll('[data-engam-leaderboard="footer"]');
		var mSlots=document.querySelectorAll('[data-engam-leaderboard="midpoint"]');
		if(hSlots.length){
			var header=document.querySelector('header.site-header,header#masthead,header#site-header,.site-header,header');
			if(header&&header.parentNode){
				hSlots.forEach(function(s){header.parentNode.insertBefore(s,header.nextSibling);});
			}
		}
		if(fSlots.length){
			var footer=document.querySelector('footer.site-footer,footer#colophon,footer#site-footer,.site-footer,footer');
			if(footer){
				fSlots.forEach(function(s){footer.insertBefore(s,footer.firstChild);});
			}
		}
		if(mSlots.length){
			mSlots.forEach(function(s){placeMidpoint(s);});
		}
	}
	if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',move);}else{move();}
})();
</script>
		<?php
	}
}
