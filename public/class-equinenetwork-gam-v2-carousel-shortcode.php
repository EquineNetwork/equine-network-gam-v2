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

		$config = array();

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

			// Respect scheduling/active: if off, hide the carousel AND the
			// container it sits in (e.g. the Elementor section/column).
			if ( ! Equinenetwork_Gam_V2_Carousel_Render::is_visible( $config ) ) {
				// Admins still see nothing on the front-end, but we collapse the
				// wrapping container so no empty space is left behind.
				$mark = 'engam-car-off-' . wp_rand( 1000, 9999 );
				return '<div id="' . esc_attr( $mark ) . '" class="engam-car-hidden" style="display:none"></div>'
					. '<script>(function(){var m=document.getElementById("' . esc_js( $mark ) . '");if(!m)return;'
					. 'var n=m;for(var i=0;i<6&&n;i++){n=n.parentElement;if(!n)break;'
					. 'if(n.classList&&(n.classList.contains("elementor-widget")||n.classList.contains("elementor-column")||n.classList.contains("e-con")||n.classList.contains("elementor-section"))){n.style.display="none";break;}}'
					. 'm.parentElement&&(m.parentElement.style.display="none");})();</script>';
			}
		}

		// Inline attribute overrides (only when explicitly provided).
		foreach ( array( 'category', 'tag', 'posts_count', 'ad_interval', 'sponsor_id', 'slides_desktop', 'slides_mobile' ) as $key ) {
			if ( $atts[ $key ] !== '' ) {
				$config[ $key ] = $atts[ $key ];
			}
		}

		$uid = 'engam-carousel-sc-' . ( ! empty( $atts['id'] ) ? sanitize_html_class( $atts['id'] ) : wp_rand( 1000, 9999 ) );

		return Equinenetwork_Gam_V2_Carousel_Render::render( $config, $uid );
	}
}
