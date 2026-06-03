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
		$pos      = isset( $lb['position'] ) && $lb['position'] === 'footer' ? 'footer' : 'header';
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

		return '<div class="engam-leaderboard engam-leaderboard-' . esc_attr( $pos ) . '" '
			. 'style="' . $band_style . $debug_style . '" '
			. 'data-engam-leaderboard="' . esc_attr( $pos ) . '">'
			. $debug_bar
			. '<div' . $attr_str . '></div>'
			. '</div>';
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
			$pos = isset( $lb['position'] ) && $lb['position'] === 'footer' ? 'footer' : 'header';
			if ( $pos === 'header' && $rendered_header ) continue;
			if ( $pos === 'footer' && $rendered_footer ) continue;

			echo self::slot_html( $lb, $debug ); // phpcs:ignore
			$has_output = true;
			if ( $pos === 'header' ) $rendered_header = true;
			if ( $pos === 'footer' ) $rendered_footer = true;
		}

		if ( ! $has_output ) return;

		?>
<script>
(function(){
	function move(){
		var hSlots=document.querySelectorAll('[data-engam-leaderboard="header"]');
		var fSlots=document.querySelectorAll('[data-engam-leaderboard="footer"]');
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
	}
	if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',move);}else{move();}
})();
</script>
		<?php
	}
}
