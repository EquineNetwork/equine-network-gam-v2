<?php
if ( ! defined( 'WPINC' ) ) die;

/**
 * Shared carousel renderer used by BOTH the Elementor widget and the
 * [en_carousel] shortcode, so there is a single source of truth.
 */
class Equinenetwork_Gam_V2_Carousel_Render {

	/**
	 * Find published/draft posts & pages where a carousel is placed.
	 * Scans classic post_content (shortcode) and Elementor's _elementor_data
	 * (Shortcode widget stores the shortcode there). Returns an array of
	 * arrays: id, title, status, view, edit.
	 */
	public static function usage( $car_id ) {
		global $wpdb;
		$car_id = trim( (string) $car_id );
		if ( $car_id === '' ) {
			return array();
		}
		$like = '%' . $wpdb->esc_like( $car_id ) . '%';
		$ids  = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT p.ID
			   FROM {$wpdb->posts} p
			   LEFT JOIN {$wpdb->postmeta} m
			          ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
			  WHERE p.post_status IN ( 'publish', 'private', 'draft' )
			    AND p.post_type NOT IN ( 'revision', 'nav_menu_item' )
			    AND ( p.post_content LIKE %s OR m.meta_value LIKE %s )",
			$like, $like
		) );
		$out = array();
		foreach ( $ids as $id ) {
			$out[] = array(
				'id'     => (int) $id,
				'title'  => get_the_title( $id ) ?: '(no title)',
				'status' => get_post_status( $id ),
				'view'   => get_permalink( $id ),
				'edit'   => get_edit_post_link( $id, '' ),
			);
		}
		return $out;
	}

	/**
	 * Curated list of Google Fonts offered in the carousel font dropdown.
	 * Key = family name (also used in the CSS2 request).
	 */
	public static function google_fonts() {
		return array(
			''                 => '— Inherit theme font —',
			'Inter'            => 'Inter',
			'Roboto'           => 'Roboto',
			'Open Sans'        => 'Open Sans',
			'Lato'             => 'Lato',
			'Montserrat'       => 'Montserrat',
			'Poppins'          => 'Poppins',
			'Raleway'          => 'Raleway',
			'Nunito'           => 'Nunito',
			'Work Sans'        => 'Work Sans',
			'Barlow'           => 'Barlow',
			'Source Sans 3'    => 'Source Sans 3',
			'PT Sans'          => 'PT Sans',
			'Oswald'           => 'Oswald',
			'Bebas Neue'       => 'Bebas Neue',
			'Anton'            => 'Anton',
			'Teko'             => 'Teko',
			'Archivo'          => 'Archivo',
			'Roboto Condensed' => 'Roboto Condensed',
			'Roboto Slab'      => 'Roboto Slab',
			'Merriweather'     => 'Merriweather',
			'Playfair Display' => 'Playfair Display',
			'Lora'             => 'Lora',
		);
	}

	/**
	 * Font weight options.
	 */
	public static function font_weights() {
		return array(
			''    => 'Default (inherit)',
			'300' => 'Light (300)',
			'400' => 'Regular (400)',
			'500' => 'Medium (500)',
			'600' => 'Semi-Bold (600)',
			'700' => 'Bold (700)',
			'800' => 'Extra-Bold (800)',
			'900' => 'Black (900)',
		);
	}

	/**
	 * Returns sponsor/campaign options from the dashboard campaign manager.
	 */
	public static function sponsor_options() {
		$options = array( '' => '— None (let GAM decide) —' );

		// Pull from Google Sheet if configured.
		require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-api.php';
		$api          = new Equinenetwork_Gam_V2_API();
		$sheet_rows   = $api->get_sponsor_options();
		if ( ! empty( $sheet_rows ) ) {
			foreach ( $sheet_rows as $row ) {
				// Show "Name - ID" so duplicate advertiser names stay distinguishable.
				$options[ $row['id'] ] = ( $row['name'] === $row['id'] )
					? $row['id']
					: $row['name'] . ' - ' . $row['id'];
			}
			return $options;
		}

		// Fallback: legacy stored campaigns.
		$stored = get_option( 'equinenetwork_gam_v2_campaigns', array() );
		if ( is_array( $stored ) ) {
			foreach ( $stored as $c ) {
				if ( ! empty( $c['active'] ) ) {
					$options[ $c['gam_id'] ] = $c['label'];
				}
			}
		}
		return $options;
	}

	/**
	 * Render a carousel from a config array. Returns HTML string.
	 *
	 * @param array  $c   Config: category, tag, posts_count, orderby,
	 *                     ads_enabled, ad_interval, ad_slotname, sponsor_id,
	 *                     slides_desktop, slides_mobile, show_arrows.
	 * @param string $uid Unique DOM id for this instance.
	 */
	public static function render( $c, $uid ) {
		$defaults = array(
			'category'       => '',
			'tag'            => '',
			'posts_count'    => 12,
			'orderby'        => 'date',
			'ad_interval'    => 3,
			'ad_slotname'    => 'carousel',
			'sponsor_id'     => '',
			'slides_desktop' => 3,
			'slides_mobile'  => 1,
			'show_arrows'    => true,
			// Styling
			'image_height'   => 0,        // px; 0 = 16:9 aspect ratio
			'show_category'  => true,
			'show_title'     => true,
			'show_excerpt'   => false,
			'excerpt_words'  => 20,
			'card_bg'        => '#ffffff',
			'card_radius'    => 8,        // px
			'font_family'    => '',       // base card font (inherit if blank)
			// Title type
			'title_size'     => 16,
			'title_family'   => '',
			'title_weight'   => '',
			'title_color'    => '#111111',
			// Category label type
			'cat_size'       => 11,
			'cat_family'     => '',
			'cat_weight'     => '700',
			'cat_color'      => '#cc0000',
			// Excerpt type
			'excerpt_size'   => 13,
			'excerpt_family' => '',
			'excerpt_weight' => '',
			'excerpt_color'  => '#555555',
			'arrow_bg'       => '#050505',
			'arrow_color'    => '#ffffff',
			// Source + manual slides
			'source'         => 'posts',     // 'posts' | 'manual'
			'slides'         => array(),     // manual slides
			'post_btn'       => false,       // show a button on post cards
			'post_btn_label' => 'Read More',
			'btn_bg'         => '#050505',
			'btn_color'      => '#ffffff',
			// Scheduling
			'active'         => true,
			'schedule_start' => '',
			'schedule_end'   => '',
		);
		$c = wp_parse_args( $c, $defaults );

		$source = ( ( $c['source'] ?? 'posts' ) === 'manual' ) ? 'manual' : 'posts';

		$interval    = max( 1, (int) $c['ad_interval'] );
		$slotname    = ( ! empty( $c['ad_slotname'] ) ) ? $c['ad_slotname'] : 'carousel';
		$sponsor_id  = trim( (string) ( $c['sponsor_id'] ?? '' ) );
		$sd          = max( 1, (int) $c['slides_desktop'] );
		$sm          = max( 1, (int) $c['slides_mobile'] );
		$show_arrows = ! empty( $c['show_arrows'] );

		// Styling
		$img_h         = (int) $c['image_height'];
		$show_cat      = ! empty( $c['show_category'] );
		$show_title    = ! empty( $c['show_title'] );
		$show_excerpt  = ! empty( $c['show_excerpt'] );
		$excerpt_words = max( 1, (int) $c['excerpt_words'] );
		$card_bg       = $c['card_bg'];
		$card_radius   = (int) $c['card_radius'];
		$font_family   = $c['font_family'];

		$title_size    = max( 1, (int) $c['title_size'] );
		$title_family  = $c['title_family'];
		$title_weight  = (string) $c['title_weight'];
		$title_color   = $c['title_color'];

		$cat_size      = max( 1, (int) $c['cat_size'] );
		$cat_family    = $c['cat_family'];
		$cat_weight    = (string) $c['cat_weight'];
		$cat_color     = $c['cat_color'];

		$excerpt_size   = max( 1, (int) $c['excerpt_size'] );
		$excerpt_family = $c['excerpt_family'];
		$excerpt_weight = (string) $c['excerpt_weight'];
		$excerpt_color  = $c['excerpt_color'];

		$arrow_bg      = $c['arrow_bg'];
		$arrow_color   = $c['arrow_color'];

		$post_btn       = ! empty( $c['post_btn'] );
		$post_btn_label = $c['post_btn_label'] !== '' ? $c['post_btn_label'] : 'Read More';
		$btn_bg         = $c['btn_bg'];
		$btn_color      = $c['btn_color'];

		// Load every chosen Google Font on the front-end (only ones we offer).
		$gfonts        = self::google_fonts();
		$want_families = array( $font_family, $title_family, $cat_family, $excerpt_family );
		$req           = array();
		foreach ( array_unique( array_filter( $want_families ) ) as $fam ) {
			if ( isset( $gfonts[ $fam ] ) ) {
				$req[] = 'family=' . str_replace( ' ', '+', $fam ) . ':wght@300;400;500;600;700;800;900';
			}
		}
		$google_link = $req
			? '<link rel="stylesheet" href="' . esc_url( 'https://fonts.googleapis.com/css2?' . implode( '&', $req ) . '&display=swap' ) . '">'
			: '';
		$font_stack = $font_family !== '' ? "'" . $font_family . "', sans-serif" : '';

		$slides = array();
		$num    = 0;

		if ( $source === 'manual' ) {
			$items = is_array( $c['slides'] ) ? $c['slides'] : array();
			foreach ( $items as $s ) {
				$slides[] = self::manual_slide( $s, $show_cat, $show_title, $show_excerpt, $excerpt_words, $post_btn_label );
				$num++;
				if ( $num % $interval === 0 ) {
					$slides[] = self::ad_slide( $slotname, $sponsor_id );
				}
			}
			if ( empty( $slides ) ) {
				return '';
			}
		} else {
			$args = array(
				'post_type'           => 'post',
				'posts_per_page'      => max( 1, (int) $c['posts_count'] ),
				'orderby'             => in_array( $c['orderby'], array( 'date', 'title', 'rand' ), true ) ? $c['orderby'] : 'date',
				'order'               => $c['orderby'] === 'title' ? 'ASC' : 'DESC',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			);
			if ( ! empty( $c['category'] ) ) {
				$args['cat'] = (int) $c['category'];
			}
			if ( ! empty( $c['tag'] ) ) {
				$args['tag_id'] = (int) $c['tag'];
			}
			$q = new WP_Query( $args );
			if ( ! $q->have_posts() ) {
				return '';
			}
			while ( $q->have_posts() ) {
				$q->the_post();
				$slides[] = self::post_slide( $show_cat, $show_title, $show_excerpt, $excerpt_words, $post_btn, $post_btn_label );
				$num++;
				if ( $num % $interval === 0 ) {
					$slides[] = self::ad_slide( $slotname, $sponsor_id );
				}
			}
			wp_reset_postdata();
		}

		$gap = 18;
		ob_start();
		echo $google_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<style>
		#<?php echo esc_attr( $uid ); ?>{position:relative}
		#<?php echo esc_attr( $uid ); ?> .engam-car-track{display:flex;gap:<?php echo (int) $gap; ?>px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;padding:4px;scrollbar-width:none}
		#<?php echo esc_attr( $uid ); ?> .engam-car-track::-webkit-scrollbar{display:none}
		#<?php echo esc_attr( $uid ); ?> .engam-car-slide{flex:0 0 calc((100% - <?php echo ( $sd - 1 ) * $gap; ?>px) / <?php echo $sd; ?>);scroll-snap-align:start;box-sizing:border-box}
		@media(max-width:767px){#<?php echo esc_attr( $uid ); ?> .engam-car-slide{flex:0 0 calc((100% - <?php echo ( $sm - 1 ) * $gap; ?>px) / <?php echo $sm; ?>)}}
		#<?php echo esc_attr( $uid ); ?> .engam-car-slide.engam-car-ad:has(.equinenetworkad.engam-empty){display:none}
		#<?php echo esc_attr( $uid ); ?> .engam-car-arrow{position:absolute;top:50%;transform:translateY(-50%);z-index:5;width:44px;height:44px;border-radius:999px;border:none;background:<?php echo esc_attr( $arrow_bg ); ?>;color:<?php echo esc_attr( $arrow_color ); ?>;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;opacity:.9}
		#<?php echo esc_attr( $uid ); ?> .engam-car-arrow:hover{opacity:1}
		#<?php echo esc_attr( $uid ); ?> .engam-car-prev{left:-10px}
		#<?php echo esc_attr( $uid ); ?> .engam-car-next{right:-10px}
		#<?php echo esc_attr( $uid ); ?> .engam-car-card{display:block;text-decoration:none;color:inherit;background:<?php echo esc_attr( $card_bg ); ?>;border:1px solid #e5e5e5;border-radius:<?php echo (int) $card_radius; ?>px;overflow:hidden;height:100%<?php echo $font_stack ? ';font-family:' . esc_attr( $font_stack ) : ''; ?>}
		#<?php echo esc_attr( $uid ); ?> .engam-car-card img{width:100%;display:block;object-fit:cover;<?php echo $img_h > 0 ? 'height:' . (int) $img_h . 'px' : 'height:auto;aspect-ratio:16/9'; ?>}
		#<?php echo esc_attr( $uid ); ?> .engam-car-card .engam-car-body{padding:14px}
		#<?php echo esc_attr( $uid ); ?> .engam-car-card .engam-car-cat{text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;display:block;font-size:<?php echo (int) $cat_size; ?>px;color:<?php echo esc_attr( $cat_color ); ?>;font-weight:<?php echo $cat_weight !== '' ? (int) $cat_weight : 700; ?><?php echo $cat_family !== '' ? ";font-family:'" . esc_attr( $cat_family ) . "',sans-serif" : ''; ?>}
		#<?php echo esc_attr( $uid ); ?> .engam-car-card h3{line-height:1.3;margin:0;font-size:<?php echo (int) $title_size; ?>px;color:<?php echo esc_attr( $title_color ); ?><?php echo $title_weight !== '' ? ';font-weight:' . (int) $title_weight : ''; ?><?php echo $title_family !== '' ? ";font-family:'" . esc_attr( $title_family ) . "',sans-serif" : ''; ?>}
		#<?php echo esc_attr( $uid ); ?> .engam-car-card .engam-car-excerpt{line-height:1.5;margin:8px 0 0;font-size:<?php echo (int) $excerpt_size; ?>px;color:<?php echo esc_attr( $excerpt_color ); ?><?php echo $excerpt_weight !== '' ? ';font-weight:' . (int) $excerpt_weight : ''; ?><?php echo $excerpt_family !== '' ? ";font-family:'" . esc_attr( $excerpt_family ) . "',sans-serif" : ''; ?>}
		#<?php echo esc_attr( $uid ); ?> .engam-car-card .engam-car-btn{display:inline-block;margin:12px 0 2px;padding:8px 16px;border-radius:6px;background:<?php echo esc_attr( $btn_bg ); ?>;color:<?php echo esc_attr( $btn_color ); ?>;text-decoration:none;font-weight:600;font-size:13px;line-height:1}
		#<?php echo esc_attr( $uid ); ?> .engam-car-card .engam-car-btn:hover{opacity:.9}
		#<?php echo esc_attr( $uid ); ?> .engam-car-slide.engam-car-ad{flex:0 0 auto;width:auto}
		#<?php echo esc_attr( $uid ); ?> .engam-car-ad{display:flex;align-items:center;justify-content:center;background:transparent;border:none;border-radius:0;padding:0;box-shadow:none}
		#<?php echo esc_attr( $uid ); ?> .engam-car-ad .equinenetworkad{margin:0;padding:0;border:none;border-radius:0;background:transparent;width:auto;display:flex;align-items:center;justify-content:center}
		#<?php echo esc_attr( $uid ); ?> .engam-car-ad .equinenetworkad img,#<?php echo esc_attr( $uid ); ?> .engam-car-ad .equinenetworkad iframe{border-radius:0;border:none}
		</style>
		<div id="<?php echo esc_attr( $uid ); ?>" class="engam-car">
			<?php if ( $show_arrows ) : ?>
			<button type="button" class="engam-car-arrow engam-car-prev" aria-label="Previous">&#8249;</button>
			<?php endif; ?>
			<div class="engam-car-track">
				<?php echo implode( '', $slides ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php if ( $show_arrows ) : ?>
			<button type="button" class="engam-car-arrow engam-car-next" aria-label="Next">&#8250;</button>
			<?php endif; ?>
		</div>
		<script>
		(function(){
			var root=document.getElementById("<?php echo esc_js( $uid ); ?>");
			if(!root)return;
			var track=root.querySelector(".engam-car-track");
			function step(){var slide=track.querySelector(".engam-car-slide");return slide?slide.offsetWidth+<?php echo (int) $gap; ?>:300;}
			var prev=root.querySelector(".engam-car-prev"),next=root.querySelector(".engam-car-next");
			if(prev)prev.addEventListener("click",function(){track.scrollBy({left:-step(),behavior:"smooth"});});
			if(next)next.addEventListener("click",function(){track.scrollBy({left:step(),behavior:"smooth"});});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	private static function post_slide( $show_cat = true, $show_title = true, $show_excerpt = false, $excerpt_words = 20, $show_btn = false, $btn_label = 'Read More' ) {
		$cats  = get_the_category();
		$cat   = ! empty( $cats ) ? $cats[0]->name : '';
		$thumb = get_the_post_thumbnail_url( get_the_ID(), 'medium_large' );
		$url   = get_permalink();

		$excerpt = '';
		if ( $show_excerpt ) {
			$raw     = has_excerpt() ? get_the_excerpt() : wp_strip_all_tags( get_the_content() );
			$excerpt = wp_trim_words( $raw, max( 1, (int) $excerpt_words ), '&hellip;' );
		}

		ob_start();
		?>
		<div class="engam-car-slide engam-car-post">
			<a class="engam-car-card" href="<?php echo esc_url( $url ); ?>">
				<?php if ( $thumb ) : ?>
					<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy">
				<?php endif; ?>
				<?php if ( $show_cat || $show_title || ( $show_excerpt && $excerpt ) || $show_btn ) : ?>
				<div class="engam-car-body">
					<?php if ( $show_cat && $cat ) : ?><span class="engam-car-cat"><?php echo esc_html( $cat ); ?></span><?php endif; ?>
					<?php if ( $show_title ) : ?><h3><?php echo esc_html( get_the_title() ); ?></h3><?php endif; ?>
					<?php if ( $show_excerpt && $excerpt ) : ?><p class="engam-car-excerpt"><?php echo esc_html( $excerpt ); ?></p><?php endif; ?>
					<?php if ( $show_btn ) : ?><span class="engam-car-btn"><?php echo esc_html( $btn_label ); ?></span><?php endif; ?>
				</div>
				<?php endif; ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a manually-defined slide (image / title / content / optional button).
	 */
	private static function manual_slide( $s, $show_cat, $show_title, $show_excerpt, $excerpt_words, $btn_label_default = 'Read More' ) {
		$s = wp_parse_args( (array) $s, array(
			'image'     => '',
			'title'     => '',
			'content'   => '',
			'btn'       => false,
			'btn_label' => '',
			'btn_url'   => '',
		) );

		$img       = trim( (string) $s['image'] );
		$title     = trim( (string) $s['title'] );
		$content   = trim( (string) $s['content'] );
		$has_btn   = ! empty( $s['btn'] ) && $s['btn_url'] !== '';
		$btn_label = $s['btn_label'] !== '' ? $s['btn_label'] : $btn_label_default;
		$btn_url   = $s['btn_url'];

		// If a button URL is set but the button is off, still make the whole card link to it.
		$card_url  = ( ! $has_btn && $s['btn_url'] !== '' ) ? $s['btn_url'] : '';

		ob_start();
		?>
		<div class="engam-car-slide engam-car-manual">
			<?php $tag = $card_url ? 'a' : 'div'; ?>
			<<?php echo $tag; ?> class="engam-car-card"<?php echo $card_url ? ' href="' . esc_url( $card_url ) . '"' : ''; ?>>
				<?php if ( $img ) : ?>
					<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
				<?php endif; ?>
				<?php if ( $title || $content || $has_btn ) : ?>
				<div class="engam-car-body">
					<?php if ( $show_title && $title ) : ?><h3><?php echo esc_html( $title ); ?></h3><?php endif; ?>
					<?php if ( $content ) : ?><p class="engam-car-excerpt"><?php echo esc_html( $content ); ?></p><?php endif; ?>
					<?php if ( $has_btn ) : ?><a class="engam-car-btn" href="<?php echo esc_url( $btn_url ); ?>"><?php echo esc_html( $btn_label ); ?></a><?php endif; ?>
				</div>
				<?php endif; ?>
			</<?php echo $tag; ?>>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Whether a saved carousel is currently visible based on its active flag
	 * and optional schedule window. Used by the shortcode to hide the carousel
	 * (and its container) when off or out of schedule.
	 */
	public static function is_visible( $c ) {
		$now   = current_time( 'timestamp' );
		$start = ! empty( $c['schedule_start'] ) ? strtotime( $c['schedule_start'] ) : 0;
		$end   = ! empty( $c['schedule_end'] )   ? strtotime( $c['schedule_end'] )   : 0;

		// A schedule overrides the manual Activate/Deactivate flag.
		if ( $start || $end ) {
			if ( $start && $now < $start ) return false;
			if ( $end   && $now > $end )   return false;
			return true;
		}

		// No schedule set — fall back to the manual flag.
		return ! ( isset( $c['active'] ) && empty( $c['active'] ) );
	}

	private static function ad_slide( $slotname, $sponsor_id = '' ) {
		// Multi-size slot — let GAM serve whichever creative size the line item has
		// set up (300x250, 300x300, 336x280, etc.). The slide auto-fits the served size.
		$dims         = '[[300,250],[300,300],[336,280],[250,250]]';
		$sponsor_attr = $sponsor_id !== '' ? sprintf( ' data-sponsorid="%s"', esc_attr( $sponsor_id ) ) : '';
		return sprintf(
			'<div class="engam-car-slide engam-car-ad"><div class="equinenetworkad" data-sizeDesktop="%s" data-sizeMobile="%s" data-sizes="%s" data-align="center" data-slotname="%s"%s></div></div>',
			esc_attr( $dims ),
			esc_attr( $dims ),
			esc_attr( $dims ),
			esc_attr( $slotname ),
			$sponsor_attr
		);
	}
}
