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

		// Single global stacker injection config (migrated from the legacy list).
		$stacker = get_option( 'engam_v2_stacker_settings', null );
		if ( ! is_array( $stacker ) ) {
			$legacy = get_option( 'engam_v2_stackers_list', array() );
			$stacker = null;
			if ( is_array( $legacy ) ) {
				foreach ( $legacy as $ls ) {
					if ( ! empty( $ls['active'] ) ) { $stacker = $ls; break; }
				}
			}
		}
		if ( ! is_array( $stacker ) || empty( $stacker['active'] ) ) {
			return $content;
		}

		$post_id      = get_the_ID();
		$post_slugs   = array();
		foreach ( get_the_category( $post_id ) as $c ) {
			$post_slugs[] = strtolower( $c->slug );
		}
		$post_sponsor = trim( (string) get_post_meta( $post_id, '_engam_v2_sponsor_id', true ) );

		$slotname = ! empty( $stacker['slotname'] ) ? $stacker['slotname'] : 'stacker';

		// Hide on specific categories.
		$hide_cats_raw = trim( $stacker['hide_cats'] ?? '' );
		if ( $hide_cats_raw !== '' ) {
			$hidden = array_filter( array_map( 'trim', explode( ',', strtolower( $hide_cats_raw ) ) ) );
			if ( ! empty( array_intersect( $hidden, $post_slugs ) ) ) {
				return $content;
			}
		}

		// Hide on specific post IDs.
		$hide_ids_raw = trim( $stacker['hide_ids'] ?? '' );
		if ( $hide_ids_raw !== '' ) {
			$hidden_ids = array_filter( array_map( 'intval', explode( ',', $hide_ids_raw ) ) );
			if ( in_array( (int) $post_id, $hidden_ids, true ) ) {
				return $content;
			}
		}

		// Hide when a matching sponsor override is active.
		$hide_sponsors_raw = trim( $stacker['hide_sponsors'] ?? '' );
		if ( $hide_sponsors_raw !== '' && $post_sponsor !== '' ) {
			if ( $hide_sponsors_raw === '*' ) {
				return $content;
			}
			$hidden_sponsors = array_filter( array_map( 'trim', explode( ',', $hide_sponsors_raw ) ) );
			if ( in_array( $post_sponsor, $hidden_sponsors, true ) ) {
				return $content;
			}
		}

		$div = sprintf(
			'<div class="equinenetworkad engam-stacker" data-sizeDesktop="[320,480]" data-sizeMobile="[320,480]" data-sizes="[320,480]" data-align="center" data-slotname="%s"></div>',
			esc_attr( $slotname )
		);

		// "End of post" placement — append after all content.
		if ( ( $stacker['placement'] ?? 'paragraph' ) === 'end' ) {
			return $content . $div;
		}

		$after = max( 1, (int) ( $stacker['after_paragraph'] ?? 5 ) );
		$parts = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		$para_count = 0;
		$output     = '';
		$inserted   = false;
		for ( $i = 0; $i < count( $parts ); $i++ ) {
			$output .= $parts[ $i ];
			if ( strtolower( $parts[ $i ] ) === '</p>' ) {
				$para_count++;
				if ( ! $inserted && $para_count >= $after ) {
					$output  .= $div;
					$inserted = true;
				}
			}
		}
		if ( ! $inserted ) {
			$output .= $div;
		}

		return $output;
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
