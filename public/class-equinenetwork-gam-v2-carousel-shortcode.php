<?php
if ( ! defined( 'WPINC' ) ) die;

/**
 * [en_carousel id="to_xxxx"] — renders a saved carousel from the
 * Carousels dashboard page. Also accepts inline overrides via attributes.
 */
class Equinenetwork_Gam_V2_Carousel_Shortcode {

	public static function register() {
		add_shortcode( 'en_carousel', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts( array(
			'id'             => '',
			'category'       => '',
			'tag'            => '',
			'posts_count'    => '',
			'ad_interval'    => '',
			'sponsor_id'     => '',
			'slides_desktop' => '',
			'slides_mobile'  => '',
		), $atts, 'en_carousel' );

		$config       = array();
		$has_schedule = false;

		// Load saved carousel definition if an ID was given.
		if ( ! empty( $atts['id'] ) ) {
			$saved = get_option( 'engam_v2_carousels', array() );
			if ( is_array( $saved ) ) {
				foreach ( $saved as $car ) {
					if ( isset( $car['id'] ) && $car['id'] === $atts['id'] ) {
						$config = $car;
						break;
					}
				}
			}
			if ( empty( $config ) ) {
				return ''; // Unknown ID — render nothing.
			}

			$has_schedule = ! empty( $config['schedule_start'] ) || ! empty( $config['schedule_end'] );

			// Non-scheduled carousels use the manual Activate/Deactivate flag, enforced
			// server-side. (A manual toggle isn't time-based, so it can't be evaluated in the
			// browser — it takes effect on the next page-cache refresh.) Scheduled carousels are
			// always rendered and gated client-side below, so they flip exactly on schedule even
			// when the page is fully cached.
			if ( ! $has_schedule && ! Equinenetwork_Gam_V2_Carousel_Render::is_visible( $config ) ) {
				return self::collapse_marker();
			}
		}

		// Inline attribute overrides (only when explicitly provided).
		foreach ( array( 'category', 'tag', 'posts_count', 'ad_interval', 'sponsor_id', 'slides_desktop', 'slides_mobile' ) as $key ) {
			if ( $atts[ $key ] !== '' ) {
				$config[ $key ] = $atts[ $key ];
			}
		}

		$uid = 'engam-carousel-sc-' . ( ! empty( $atts['id'] ) ? sanitize_html_class( $atts['id'] ) : wp_rand( 1000, 9999 ) );

		$html = Equinenetwork_Gam_V2_Carousel_Render::render( $config, $uid );

		// Cache-proof scheduling: gate the rendered carousel in the browser against the viewer's
		// clock so it appears/disappears at the scheduled instant even on a fully cached page.
		if ( ! empty( $atts['id'] ) && $has_schedule ) {
			$start_utc = ! empty( $config['schedule_start'] )
				? strtotime( get_gmt_from_date( str_replace( 'T', ' ', $config['schedule_start'] ) ) ) : 0;
			$end_utc   = ! empty( $config['schedule_end'] )
				? strtotime( get_gmt_from_date( str_replace( 'T', ' ', $config['schedule_end'] ) ) ) : 0;
			return self::gate_scheduled( $html, $uid, (int) $start_utc, (int) $end_utc );
		}

		return $html;
	}

	/**
	 * Hidden marker + script that collapses the wrapping Elementor container, leaving no gap.
	 * Used when a non-scheduled carousel is manually deactivated.
	 */
	private static function collapse_marker() {
		// NOTE: inline scripts returned from a shortcode pass through WordPress content filters,
		// which HTML-encode "&&" to "&#038;&" and break the script. Avoid "&&" here — use nested
		// ifs / guard clauses instead.
		$mark = 'engam-car-off-' . wp_rand( 1000, 9999 );
		return '<div id="' . esc_attr( $mark ) . '" class="engam-car-hidden" style="display:none"></div>'
			. '<script>(function(){var m=document.getElementById("' . esc_js( $mark ) . '");if(!m)return;'
			. 'var n=m;for(var i=0;i<6;i++){n=n.parentElement;if(!n)break;var cl=n.classList;if(!cl)continue;'
			. 'if(cl.contains("elementor-widget")||cl.contains("elementor-column")||cl.contains("e-con")||cl.contains("elementor-section")){n.style.display="none";break;}}'
			. 'if(m.parentElement)m.parentElement.style.display="none";})();</script>';
	}

	/**
	 * Wraps a rendered carousel in a client-side schedule gate. The wrapper starts hidden; an
	 * inline script reveals it when the viewer's clock is within [start, end] (UTC epochs, 0 =
	 * open-ended), or marks it "off" and collapses its container otherwise. The ad-init in the
	 * footer skips slots inside an "off" gate, so out-of-schedule carousels never request ads.
	 */
	private static function gate_scheduled( $html, $uid, $start, $end ) {
		$wrap = $uid . '-sched';
		ob_start();
		?>
<div id="<?php echo esc_attr( $wrap ); ?>" class="engam-car-sched" data-start="<?php echo (int) $start; ?>" data-end="<?php echo (int) $end; ?>" style="display:none"><?php
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?></div>
<script>
<?php // NOTE: no "&&" below — shortcode content filters encode it to "&#038;&" and break the script. ?>
(function(){
	var w=document.getElementById("<?php echo esc_js( $wrap ); ?>");
	if(!w)return;
	var s=parseInt(w.getAttribute("data-start"),10)||0;
	var e=parseInt(w.getAttribute("data-end"),10)||0;
	var now=Math.floor(Date.now()/1000);
	var afterStart=(!s)||(now>=s);
	var beforeEnd=(!e)||(now<=e);
	var on=afterStart?beforeEnd:false;
	if(on){
		w.style.display="";
		w.removeAttribute("data-engam-sched-off");
		return;
	}
	w.setAttribute("data-engam-sched-off","1");
	// Collapse the wrapping Elementor container so no empty space remains.
	var n=w;
	for(var i=0;i<6;i++){
		n=n.parentElement;
		if(!n)break;
		var cl=n.classList;
		if(!cl)continue;
		if(cl.contains("elementor-widget")||cl.contains("elementor-column")||cl.contains("e-con")||cl.contains("elementor-section")){n.style.display="none";break;}
	}
})();
</script>
		<?php
		return ob_get_clean();
	}
}
