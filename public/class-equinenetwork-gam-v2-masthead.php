<?php
if ( ! defined( 'WPINC' ) ) die;

class Equinenetwork_Gam_V2_Masthead {

	const OPTION = 'engam_v2_mastheads';

	public static function get_all() {
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) ? $saved : array();
	}

	private static function is_targeted( $m ) {
		if ( empty( $m['active'] ) ) return false;
		if ( ! empty( $m['show_home'] ) && ( is_front_page() || is_home() ) ) return true;
		if ( ! empty( $m['pages'] ) && is_singular() ) {
			$id = get_queried_object_id();
			if ( $id && in_array( (int) $id, array_map( 'intval', (array) $m['pages'] ), true ) ) return true;
		}
		return false;
	}

	private static function slot_html( $m, $debug = false ) {
		$slot = ! empty( $m['slotname'] ) ? $m['slotname'] : 'homepagetakeover';

		// Pull creative sizes live from GAM API (cached 1 hour).
		// Falls back to 2048x300 if API not configured or returns nothing.
		require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-api.php';
		$api   = new Equinenetwork_Gam_V2_API();
		$sizes = $api->is_configured() ? $api->get_slot_sizes( $slot ) : array();
		if ( empty( $sizes ) ) {
			$sizes = array( array( 2048, 300 ) );
		}

		$attrs = array(
			'class'        => 'equinenetworkad engam-masthead-ad',
			'data-align'   => 'center',
			'data-slotname' => $slot,
			'data-sizes'   => wp_json_encode( $sizes ),
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

	public function render_masthead() {
		if ( Equinenetwork_Gam_V2_Takeover::has_active() ) return;

		$mastheads = self::get_all();
		if ( empty( $mastheads ) ) return;

		$debug = isset( $_GET['engam_debug'] ) && current_user_can( 'edit_posts' );

		$rendered = false;
		foreach ( $mastheads as $m ) {
			if ( self::is_targeted( $m ) ) {
				echo self::slot_html( $m, $debug ); // phpcs:ignore
				$rendered = true;
				break; // one masthead per page — first match wins
			}
		}

		if ( ! $rendered ) return;
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
	}
}
