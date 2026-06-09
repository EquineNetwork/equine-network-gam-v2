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
		$raw_pos = $lb['position'] ?? 'header';
		// Normalize to base type for CSS classes; keep raw value for data attribute so JS can target the specific template.
		if ( preg_match( '/^(header|footer)_tmpl_\d+$/', $raw_pos, $spm ) ) {
			$pos = $spm[1];
		} elseif ( in_array( $raw_pos, array( 'footer', 'midpoint' ), true ) ) {
			$pos = $raw_pos;
		} else {
			$pos     = 'header';
			$raw_pos = 'header';
		}
		$slotname = ! empty( $lb['slotname'] ) ? $lb['slotname'] : 'leaderboard';

		$dw = ! empty( $lb['dw'] ) ? (int) $lb['dw'] : 728;
		$dh = ! empty( $lb['dh'] ) ? (int) $lb['dh'] : 90;
		$mw = ! empty( $lb['mw'] ) ? (int) $lb['mw'] : 320;
		$mh = ! empty( $lb['mh'] ) ? (int) $lb['mh'] : 50;

		$attrs = array(
			'class'            => 'equinenetworkad engam-leaderboard-ad',
			'data-align'       => 'center',
			'data-slotname'    => $slotname,
			'data-sizeDesktop' => '[['  . $dw . ',' . $dh . ']]',
			'data-sizeMobile'  => '[[' . $mw . ',' . $mh . ']]',
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
			. 'data-engam-leaderboard="' . esc_attr( $raw_pos ) . '"' . $selector_attr . '>'
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

		$rendered_positions = array();
		$has_output         = false;

		foreach ( $leaderboards as $lb ) {
			if ( ! self::is_active( $lb ) ) continue;
			$raw_pos = $lb['position'] ?? 'header';

			// Resolve base type so we can detect midpoint regardless of template variant.
			if ( preg_match( '/^(header|footer)_tmpl_\d+$/', $raw_pos, $rpm ) ) {
				$pos_type = $rpm[1];
			} elseif ( in_array( $raw_pos, array( 'footer', 'midpoint' ), true ) ) {
				$pos_type = $raw_pos;
			} else {
				$pos_type = 'header';
				$raw_pos  = 'header';
			}

			// Mid-content leaderboards only appear on their targeted page(s);
			// multiple may exist (one per page), so they are not de-duplicated.
			if ( $pos_type === 'midpoint' ) {
				if ( ! self::page_matches( $lb['target_pages'] ?? '' ) ) continue;
				echo self::slot_html( $lb, $debug ); // phpcs:ignore
				$has_output = true;
				continue;
			}

			// Each exact position value (e.g. header_tmpl_123, footer, header) renders at most once.
			if ( isset( $rendered_positions[ $raw_pos ] ) ) continue;
			$rendered_positions[ $raw_pos ] = true;

			echo self::slot_html( $lb, $debug ); // phpcs:ignore
			$has_output = true;
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
	function spanFull(node){
		// Make the band span the full width of whatever container it lands in — even an
		// Elementor flex/grid — otherwise it shrinks to a narrow column pinned to one side.
		node.style.width='100%';
		node.style.maxWidth='100%';
		node.style.alignSelf='stretch';
		node.style.gridColumn='1 / -1';
		node.style.boxSizing='border-box';
	}
	function elChildren(el){
		return Array.prototype.filter.call(el.children,function(k){return k.nodeType===1;});
	}
	function isVerticalStack(el){
		var cs=getComputedStyle(el);
		if(cs.display==='grid'||cs.display==='inline-grid')return false;
		if(cs.display==='flex'||cs.display==='inline-flex')return cs.flexDirection.indexOf('column')===0;
		return true; // normal block flow stacks vertically
	}
	function insertBeforeMiddleChild(container,node){
		// Insert before the middle child BY COUNT — robust even before images load and heights
		// settle, which is what "halfway down the list" should mean for a feed of items.
		var kids=elChildren(container).filter(function(k){return k!==node&&!node.contains(k);});
		if(!kids.length){container.appendChild(node);spanFull(node);return;}
		var ref=kids[Math.floor(kids.length/2)];
		if(ref){container.insertBefore(node,ref);}else{container.appendChild(node);}
		spanFull(node);
	}
	function placeMidpoint(s){
		var sel=(s.getAttribute('data-engam-selector')||'').trim();
		if(sel){
			try{
				var matches=Array.prototype.filter.call(document.querySelectorAll(sel),function(m){return !s.contains(m);});
				if(matches.length>1){
					var mid=matches[Math.floor(matches.length/2)];
					if(mid&&mid.parentNode){mid.parentNode.insertBefore(s,mid);spanFull(s);return;}
				}else if(matches.length===1){insertBeforeMiddleChild(matches[0],s);return;}
			}catch(e){}
		}
		// No (or unmatched) selector: walk down into the DOMINANT content container — the one that
		// holds most of the page height (the listings) — then drop the band before its middle child.
		// Descending past the [filter, listings] split is what keeps the band inside the list
		// instead of landing above it.
		var sels=['main .elementor','.elementor','main .entry-content','.entry-content','main article','main','article','#primary','#content','.site-content'];
		var root=null;
		for(var d=0;d<sels.length;d++){
			var el=document.querySelector(sels[d]);
			if(el&&el.getBoundingClientRect().height>200){root=el;break;}
		}
		if(!root)root=document.querySelector('main')||document.body;
		var cur=root,guard=0;
		while(guard++<12){
			var kids=elChildren(cur).filter(function(k){return k!==s&&!s.contains(k);});
			if(kids.length===0)break;
			if(kids.length===1){cur=kids[0];continue;}             // unwrap single-child wrappers
			var curH=cur.getBoundingClientRect().height||1;
			var tallest=kids[0],hT=-1;
			kids.forEach(function(k){var h=k.getBoundingClientRect().height;if(h>hT){hT=h;tallest=k;}});
			if(hT>=0.6*curH){cur=tallest;continue;}                // one child dominates → descend into it
			break;                                                 // children distributed → this is the list level
		}
		insertBeforeMiddleChild(cur,s);
	}
	function drop(node){ if(node&&node.parentNode){ node.parentNode.removeChild(node); } }
	function move(){
		var allSlots=document.querySelectorAll('[data-engam-leaderboard]');
		allSlots.forEach(function(s){
			var pos=s.getAttribute('data-engam-leaderboard');
			if(pos==='midpoint'){placeMidpoint(s);return;}
			var tmplMatch=pos.match(/^(header|footer)_tmpl_(\d+)$/);
			var posType=tmplMatch?tmplMatch[1]:pos;
			// A template-specific leaderboard is scoped to its Elementor template: it renders ONLY on
			// pages where that template is the active header/footer (its wrapper, .elementor-<id>, is
			// present). If the template isn't on this page, drop the slot — never fall back to the
			// generic header/footer. Templates with no leaderboard assigned simply get none.
			var tmplEl=tmplMatch?document.querySelector('.elementor-'+tmplMatch[2]):null;
			if(tmplMatch&&!tmplEl){drop(s);return;}
			if(posType==='header'){
				var headerEl=tmplEl?(tmplEl.closest('header')||tmplEl.parentNode):document.querySelector('header.site-header,header#masthead,header#site-header,.site-header,header');
				if(!headerEl||!headerEl.parentNode){drop(s);return;}
				// Never stack two leaderboards on the same header.
				if(headerEl.getAttribute('data-engam-lb-done')){drop(s);return;}
				headerEl.parentNode.insertBefore(s,headerEl.nextSibling);
				headerEl.setAttribute('data-engam-lb-done','1');
			}else if(posType==='footer'){
				var footerEl=tmplEl?(tmplEl.closest('footer')||tmplEl):document.querySelector('footer.site-footer,footer#colophon,footer#site-footer,.site-footer,footer');
				if(!footerEl){drop(s);return;}
				if(footerEl.getAttribute('data-engam-lb-done')){drop(s);return;}
				footerEl.insertBefore(s,footerEl.firstChild);
				footerEl.setAttribute('data-engam-lb-done','1');
			}else{drop(s);return;}
		});
	}
	if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',move);}else{move();}
})();
</script>
		<?php
	}
}
