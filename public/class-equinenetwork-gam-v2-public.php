<?php
class Equinenetwork_Gam_V2_Public {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	public function gam_head_inject() {
		include EQUINENETWORK_GAM_V2_PATH . 'public/partials/equinenetwork-gam-v2-public-header.php';
	}

	public function gam_foot_logic() {
		include EQUINENETWORK_GAM_V2_PATH . 'public/partials/equinenetwork-gam-v2-public-footer.php';
	}

	/**
	 * Auto-inject the GAM stacker ad slot at the end of single posts.
	 * GAM's content targeting decides which posts actually fill it;
	 * empty slots collapse via the existing slotRenderEnded logic.
	 */
	public function inject_stacker( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( get_option( 'equinenetwork_gam_v2_stacker_enabled', '0' ) !== '1' ) {
			return $content;
		}

		$post_id    = get_the_ID();
		$post_slugs = array();
		foreach ( get_the_category( $post_id ) as $c ) {
			$post_slugs[] = strtolower( $c->slug );
		}

		// Show-only category limit.
		$cats_raw = trim( get_option( 'equinenetwork_gam_v2_stacker_cats', '' ) );
		if ( $cats_raw !== '' ) {
			$allowed = array_filter( array_map( 'trim', explode( ',', strtolower( $cats_raw ) ) ) );
			if ( empty( array_intersect( $allowed, $post_slugs ) ) ) {
				return $content;
			}
		}

		// Hide on specific categories.
		$hide_cats_raw = trim( get_option( 'equinenetwork_gam_v2_stacker_hide_cats', '' ) );
		if ( $hide_cats_raw !== '' ) {
			$hidden = array_filter( array_map( 'trim', explode( ',', strtolower( $hide_cats_raw ) ) ) );
			if ( ! empty( array_intersect( $hidden, $post_slugs ) ) ) {
				return $content;
			}
		}

		// Hide on specific post/page IDs.
		$hide_ids_raw = trim( get_option( 'equinenetwork_gam_v2_stacker_hide_ids', '' ) );
		if ( $hide_ids_raw !== '' ) {
			$hidden_ids = array_filter( array_map( 'intval', explode( ',', $hide_ids_raw ) ) );
			if ( in_array( (int) $post_id, $hidden_ids, true ) ) {
				return $content;
			}
		}

		// Hide when a sponsor override is assigned to this post.
		$hide_sponsors_raw = trim( get_option( 'equinenetwork_gam_v2_stacker_hide_sponsors', '' ) );
		if ( $hide_sponsors_raw !== '' ) {
			$post_sponsor = trim( (string) get_post_meta( $post_id, '_engam_v2_sponsor_id', true ) );
			if ( $post_sponsor !== '' ) {
				// "*" means hide on any post that has any sponsor override.
				if ( trim( $hide_sponsors_raw ) === '*' ) {
					return $content;
				}
				$hidden_sponsors = array_filter( array_map( 'trim', explode( ',', $hide_sponsors_raw ) ) );
				if ( in_array( $post_sponsor, $hidden_sponsors, true ) ) {
					return $content;
				}
			}
		}

		$slotname = get_option( 'equinenetwork_gam_v2_stacker_slotname', 'stacker' );
		if ( $slotname === '' ) {
			$slotname = 'stacker';
		}

		$div = sprintf(
			'<div class="equinenetworkad engam-stacker" data-sizeDesktop="[320,480]" data-sizeMobile="[320,480]" data-sizes="[320,480]" data-align="center" data-slotname="%s"></div>',
			esc_attr( $slotname )
		);

		return $content . $div;
	}

	public function return_categories_on_post( $content ) {
		if ( is_single() ) {
			$subcategories = array();
			$return        = array();
			$categories    = get_the_category();
			foreach ( $categories as $category ) {
				$name = str_replace( '+', '-', strtolower( $category->name ) );
				if ( $category->category_parent !== 0 ) {
					$subcategories[] = $name;
				} else {
					$return[] = $name;
				}
			}
			return $content
				. '<script>window.post_categories='    . wp_json_encode( $return )        . ';'
				. 'window.post_subcategories=' . wp_json_encode( $subcategories ) . ';</script>';
		}
		return $content;
	}
}
