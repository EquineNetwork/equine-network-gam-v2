<?php
if ( ! defined( 'WPINC' ) ) die;

/**
 * EN Post Carousel — a self-contained posts carousel that interleaves
 * 300x250 GAM ad slides at a configurable interval. Built from scratch
 * (not Unlimited Elements) so there's no infinite-scroll cloning to break
 * GAM rendering. Empty ad slides collapse out automatically.
 */
class Equinenetwork_Gam_V2_Carousel_Widget extends \Elementor\Widget_Base {

	public function get_name()       { return 'engam_v2_post_carousel'; }
	public function get_title()      { return 'EN Post Carousel'; }
	public function get_icon()       { return 'eicon-post-slider'; }
	public function get_categories() { return array( 'general' ); }
	public function get_keywords()   { return array( 'carousel', 'posts', 'ad', 'gam', 'slider', 'equinenetwork' ); }

	protected function register_controls() {

		// ── Content Source ──────────────────────────────────────────────
		$this->start_controls_section( 'section_source', array(
			'label' => 'Posts',
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$cats = array( '' => '— All categories —' );
		foreach ( get_categories( array( 'hide_empty' => false ) ) as $c ) {
			$cats[ $c->term_id ] = $c->name;
		}

		$tags = array( '' => '— Any tag —' );
		foreach ( get_tags( array( 'hide_empty' => false ) ) as $t ) {
			$tags[ $t->term_id ] = $t->name;
		}

		$this->add_control( 'category', array(
			'label'   => 'Category',
			'type'    => \Elementor\Controls_Manager::SELECT2,
			'options' => $cats,
			'default' => '',
		) );

		$this->add_control( 'tag', array(
			'label'   => 'Tag',
			'type'    => \Elementor\Controls_Manager::SELECT2,
			'options' => $tags,
			'default' => '',
		) );

		$this->add_control( 'posts_count', array(
			'label'   => 'Number of Posts',
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'min'     => 1,
			'max'     => 50,
			'default' => 12,
		) );

		$this->add_control( 'orderby', array(
			'label'   => 'Order By',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array(
				'date'  => 'Newest first',
				'title' => 'Title (A–Z)',
				'rand'  => 'Random',
			),
			'default' => 'date',
		) );

		$this->end_controls_section();

		// ── Ad Slides ───────────────────────────────────────────────────
		$this->start_controls_section( 'section_ads', array(
			'label' => 'Ad Slides',
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'ads_enabled', array(
			'label'        => 'Insert Ad Slides',
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => 'Yes',
			'label_off'    => 'No',
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		$this->add_control( 'ad_interval', array(
			'label'       => 'Ad After Every N Posts',
			'type'        => \Elementor\Controls_Manager::NUMBER,
			'min'         => 1,
			'max'         => 10,
			'default'     => 3,
			'description' => 'e.g. 3 = post, post, post, ad, post, post, post, ad…',
			'condition'   => array( 'ads_enabled' => 'yes' ),
		) );

		$this->add_control( 'ad_slotname', array(
			'label'       => 'GAM Child Ad Unit',
			'type'        => \Elementor\Controls_Manager::TEXT,
			'default'     => 'carousel',
			'description' => 'Appended to the site GAM Network ID. Ad size is 300×250.',
			'condition'   => array( 'ads_enabled' => 'yes' ),
		) );

		$this->add_control( 'sponsor_id', array(
			'label'       => 'Lock to Sponsor / Campaign',
			'type'        => \Elementor\Controls_Manager::SELECT,
			'options'     => Equinenetwork_Gam_V2_Carousel_Render::sponsor_options(),
			'default'     => '',
			'description' => 'Optional — force a specific advertiser into the carousel ad slides. Leave as “None” to let GAM decide. Manage campaigns in EN Ads → Campaigns.',
			'condition'   => array( 'ads_enabled' => 'yes' ),
		) );

		$this->end_controls_section();

		// ── Layout ──────────────────────────────────────────────────────
		$this->start_controls_section( 'section_layout', array(
			'label' => 'Layout',
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'slides_desktop', array(
			'label'   => 'Slides Visible (Desktop)',
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'min'     => 1,
			'max'     => 6,
			'default' => 3,
		) );

		$this->add_control( 'slides_mobile', array(
			'label'   => 'Slides Visible (Mobile)',
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'min'     => 1,
			'max'     => 3,
			'default' => 1,
		) );

		$this->add_control( 'show_arrows', array(
			'label'        => 'Navigation Arrows',
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => 'Show',
			'label_off'    => 'Hide',
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		$this->end_controls_section();

		// ── Card Styling ────────────────────────────────────────────────
		$this->start_controls_section( 'section_style', array(
			'label' => 'Card Styling',
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		) );

		$this->add_control( 'image_height', array(
			'label'       => 'Image Height (px)',
			'type'        => \Elementor\Controls_Manager::NUMBER,
			'min'         => 0,
			'default'     => 0,
			'description' => '0 = automatic 16:9 ratio.',
		) );

		$this->add_control( 'show_category', array(
			'label'        => 'Show Category Label',
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		$this->add_control( 'show_title', array(
			'label'        => 'Show Title',
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => 'yes',
		) );

		$this->add_control( 'show_excerpt', array(
			'label'        => 'Show Excerpt',
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'return_value' => 'yes',
			'default'      => '',
		) );

		$this->add_control( 'excerpt_words', array(
			'label'     => 'Excerpt Length (words)',
			'type'      => \Elementor\Controls_Manager::NUMBER,
			'min'       => 1,
			'default'   => 20,
			'condition' => array( 'show_excerpt' => 'yes' ),
		) );

		$this->add_control( 'card_bg', array(
			'label'   => 'Card Background',
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => '#ffffff',
		) );

		$this->add_control( 'card_radius', array(
			'label'   => 'Card Corner Radius (px)',
			'type'    => \Elementor\Controls_Manager::NUMBER,
			'min'     => 0,
			'max'     => 40,
			'default' => 8,
		) );

		$this->add_control( 'font_family', array(
			'label'       => 'Base Font Family (whole card)',
			'type'        => \Elementor\Controls_Manager::SELECT,
			'options'     => Equinenetwork_Gam_V2_Carousel_Render::google_fonts(),
			'default'     => '',
			'description' => 'Each element below can override this. Google Fonts load automatically.',
		) );

		$gf = Equinenetwork_Gam_V2_Carousel_Render::google_fonts();
		$fw = Equinenetwork_Gam_V2_Carousel_Render::font_weights();

		// ── Title ──
		$this->add_control( 'heading_title', array( 'label' => 'Title', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control( 'title_size', array(   'label' => 'Title Size (px)', 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1, 'default' => 16 ) );
		$this->add_control( 'title_family', array( 'label' => 'Title Font Family', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => $gf, 'default' => '' ) );
		$this->add_control( 'title_weight', array( 'label' => 'Title Font Weight', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => $fw, 'default' => '' ) );
		$this->add_control( 'title_color', array(  'label' => 'Title Color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#111111' ) );

		// ── Category Label ──
		$this->add_control( 'heading_cat', array( 'label' => 'Category Label', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control( 'cat_size', array(   'label' => 'Category Size (px)', 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1, 'default' => 11 ) );
		$this->add_control( 'cat_family', array( 'label' => 'Category Font Family', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => $gf, 'default' => '' ) );
		$this->add_control( 'cat_weight', array( 'label' => 'Category Font Weight', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => $fw, 'default' => '700' ) );
		$this->add_control( 'cat_color', array(  'label' => 'Category Label Color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#cc0000' ) );

		// ── Excerpt ──
		$this->add_control( 'heading_excerpt', array( 'label' => 'Excerpt', 'type' => \Elementor\Controls_Manager::HEADING, 'separator' => 'before' ) );
		$this->add_control( 'excerpt_size', array(   'label' => 'Excerpt Size (px)', 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1, 'default' => 13 ) );
		$this->add_control( 'excerpt_family', array( 'label' => 'Excerpt Font Family', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => $gf, 'default' => '' ) );
		$this->add_control( 'excerpt_weight', array( 'label' => 'Excerpt Font Weight', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => $fw, 'default' => '' ) );
		$this->add_control( 'excerpt_color', array(  'label' => 'Excerpt Color', 'type' => \Elementor\Controls_Manager::COLOR, 'default' => '#555555' ) );

		$this->add_control( 'arrow_bg', array(
			'label'     => 'Nav Arrow Background',
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#050505',
			'condition' => array( 'show_arrows' => 'yes' ),
		) );

		$this->add_control( 'arrow_color', array(
			'label'     => 'Nav Arrow Icon Color',
			'type'      => \Elementor\Controls_Manager::COLOR,
			'default'   => '#ffffff',
			'condition' => array( 'show_arrows' => 'yes' ),
		) );

		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();

		$config = array(
			'category'       => $s['category'],
			'tag'            => $s['tag'],
			'posts_count'    => $s['posts_count'],
			'orderby'        => $s['orderby'],
			'ads_enabled'    => $s['ads_enabled'] === 'yes',
			'ad_interval'    => $s['ad_interval'],
			'ad_slotname'    => $s['ad_slotname'],
			'sponsor_id'     => $s['sponsor_id'],
			'slides_desktop' => $s['slides_desktop'],
			'slides_mobile'  => $s['slides_mobile'],
			'show_arrows'    => $s['show_arrows'] === 'yes',
			'image_height'   => $s['image_height'],
			'show_category'  => $s['show_category'] === 'yes',
			'show_title'     => $s['show_title'] === 'yes',
			'show_excerpt'   => $s['show_excerpt'] === 'yes',
			'excerpt_words'  => $s['excerpt_words'],
			'card_bg'        => $s['card_bg'],
			'card_radius'    => $s['card_radius'],
			'font_family'    => $s['font_family'],
			'title_size'     => $s['title_size'],
			'title_family'   => $s['title_family'],
			'title_weight'   => $s['title_weight'],
			'title_color'    => $s['title_color'],
			'cat_size'       => $s['cat_size'],
			'cat_family'     => $s['cat_family'],
			'cat_weight'     => $s['cat_weight'],
			'cat_color'      => $s['cat_color'],
			'excerpt_size'   => $s['excerpt_size'],
			'excerpt_family' => $s['excerpt_family'],
			'excerpt_weight' => $s['excerpt_weight'],
			'excerpt_color'  => $s['excerpt_color'],
			'arrow_bg'       => $s['arrow_bg'],
			'arrow_color'    => $s['arrow_color'],
		);

		$html = Equinenetwork_Gam_V2_Carousel_Render::render( $config, 'engam-carousel-' . $this->get_id() );

		if ( $html === '' && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			echo '<div style="padding:20px;border:2px dashed #aaa;text-align:center;font-family:sans-serif;color:#777">EN Post Carousel — no posts match this category/tag.</div>';
			return;
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	protected function content_template() {
		?>
		<div style="padding:24px;border:2px dashed #aaa;text-align:center;font-family:sans-serif;background:#f7f7f7">
			<strong style="display:block;font-size:15px;margin-bottom:4px">EN Post Carousel</strong>
			<span style="color:#666;font-size:12px">Posts {{ settings.category ? '(filtered)' : '' }} with a 300×250 ad every {{ settings.ad_interval }} posts. Preview on the live page.</span>
		</div>
		<?php
	}
}
