<?php
if ( ! defined( 'WPINC' ) ) die;

class Equinenetwork_Gam_V2_Widget extends \Elementor\Widget_Base {

	// Preset definitions — single source of truth.
	private static function presets() {
		return array(
			'leaderboard' => array(
				'label'   => 'Leaderboard (728x90 desktop / 320x50 mobile)',
				'desktop' => array( 728, 90 ),
				'mobile'  => array( 320, 50 ),
				'sizes'   => array( array( 320, 50 ), array( 728, 90 ) ),
			),
			'medium_rect' => array(
				'label'   => 'Medium Rectangle (300x250)',
				'desktop' => array( 300, 250 ),
				'mobile'  => array( 300, 250 ),
				'sizes'   => array( 300, 250 ),
			),
			'half_page' => array(
				'label'   => 'Half Page (300x600)',
				'desktop' => array( 300, 600 ),
				'mobile'  => array( 300, 600 ),
				'sizes'   => array( 300, 600 ),
			),
			'med_half' => array(
				'label'   => 'Medium Rectangle + Half Page (300x250 & 300x600)',
				'desktop' => array( array( 300, 250 ), array( 300, 600 ) ),
				'mobile'  => array( array( 300, 250 ), array( 300, 600 ) ),
				'sizes'   => array( array( 300, 250 ), array( 300, 600 ) ),
			),
			'takeover' => array(
				'label'    => 'Homepage Takeover (fluid)',
				'desktop'  => array( array( 2048, 300 ), 'fluid' ),
				'mobile'   => array( array( 2048, 300 ), 'fluid' ),
				'sizes'    => array( array( 2048, 300 ), 'fluid' ),
				'slotname' => 'homepagetakeover',
			),
		);
	}

	public function get_name()       { return 'engam_v2_ad_slot'; }
	public function get_title()      { return 'EN Ad Slot'; }
	public function get_icon()       { return 'eicon-banner'; }
	public function get_categories() { return array( 'general' ); }
	public function get_keywords()   { return array( 'ad', 'gam', 'google', 'advertisement', 'equinenetwork' ); }

	protected function register_controls() {

		// ── Ad Configuration ────────────────────────────────────────────
		$this->start_controls_section( 'section_ad', array(
			'label' => 'Ad Configuration',
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$preset_options = array();
		foreach ( self::presets() as $key => $preset ) {
			$preset_options[ $key ] = $preset['label'];
		}
		$preset_options['custom'] = 'Custom…';

		$this->add_control( 'ad_preset', array(
			'label'   => 'Ad Type',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => $preset_options,
			'default' => 'leaderboard',
		) );

		// Custom size controls (hidden unless preset = custom).
		$this->add_control( 'custom_desktop_width', array(
			'label'     => 'Desktop Width (px)',
			'type'      => \Elementor\Controls_Manager::NUMBER,
			'min'       => 1,
			'condition' => array( 'ad_preset' => 'custom' ),
		) );

		$this->add_control( 'custom_desktop_height', array(
			'label'     => 'Desktop Height (px)',
			'type'      => \Elementor\Controls_Manager::NUMBER,
			'min'       => 1,
			'condition' => array( 'ad_preset' => 'custom' ),
		) );

		$this->add_control( 'custom_mobile_width', array(
			'label'     => 'Mobile Width (px)',
			'type'      => \Elementor\Controls_Manager::NUMBER,
			'min'       => 1,
			'condition' => array( 'ad_preset' => 'custom' ),
		) );

		$this->add_control( 'custom_mobile_height', array(
			'label'     => 'Mobile Height (px)',
			'type'      => \Elementor\Controls_Manager::NUMBER,
			'min'       => 1,
			'condition' => array( 'ad_preset' => 'custom' ),
		) );

		$this->add_control( 'ad_align', array(
			'label'   => 'Alignment',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array(
				'center' => 'Center',
				'left'   => 'Left',
				'right'  => 'Right',
			),
			'default' => 'center',
		) );

		$this->add_control( 'is_popup', array(
			'label'        => 'Popup Ad',
			'type'         => \Elementor\Controls_Manager::SWITCHER,
			'label_on'     => 'Yes',
			'label_off'    => 'No',
			'return_value' => 'yes',
			'default'      => '',
		) );

		// Build campaign options from the dashboard campaign manager.
		$campaign_options = array( '' => '— None —' );
		$stored_campaigns = get_option( 'equinenetwork_gam_v2_campaigns', array() );
		if ( is_array( $stored_campaigns ) ) {
			foreach ( $stored_campaigns as $c ) {
				if ( ! empty( $c['active'] ) ) {
					$campaign_options[ $c['gam_id'] ] = $c['label'];
				}
			}
		}

		if ( count( $campaign_options ) > 1 ) {
			$this->add_control( 'sponsor_id', array(
				'label'       => 'Sponsor / Campaign ID',
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $campaign_options,
				'default'     => '',
				'description' => 'Manage campaigns in EN Ads → Campaign Manager.',
			) );
		} else {
			$this->add_control( 'sponsor_id', array(
				'label'       => 'Sponsor / Campaign ID',
				'type'        => \Elementor\Controls_Manager::RAW_HTML,
				'raw'         => '<p style="color:#d9534f;font-size:12px;margin:0">No active campaigns found. Add them in <strong>EN Ads → Campaign Manager</strong>.</p>',
			) );
		}

		$this->add_control( 'slot_name', array(
			'label'       => 'Slot Name Override',
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => 'Optional — e.g. homepagetakeover',
			'description' => 'For child ad units. Leave blank to use the site GAM Network ID.',
		) );

		$this->end_controls_section();

		// ── Page Visibility ─────────────────────────────────────────────
		$this->start_controls_section( 'section_visibility', array(
			'label' => 'Page Visibility',
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'visibility_notice', array(
			'type' => \Elementor\Controls_Manager::RAW_HTML,
			'raw'  => '<small>Control which pages this ad slot appears on. Useful for header/footer ads that are placed site-wide but should only show on certain pages.</small>',
			'content_classes' => 'elementor-descriptor',
		) );

		$this->add_control( 'visibility_rule', array(
			'label'   => 'Show On',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array(
				'all'        => 'All pages (default)',
				'front_page' => 'Homepage only',
				'not_front'  => 'Everywhere except Homepage',
				'singular'   => 'Single posts & pages only',
				'archive'    => 'Archive pages only',
				'page_ids'   => 'Specific pages…',
				'not_page_ids' => 'Everywhere except specific pages…',
			),
			'default' => 'all',
		) );

		$this->add_control( 'visibility_page_ids', array(
			'label'       => 'Page / Post IDs',
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => 'e.g. 12, 45, 300',
			'description' => 'Comma-separated post/page IDs. Find the ID in the URL when editing a page: post=<strong>ID</strong>',
			'condition'   => array( 'visibility_rule' => array( 'page_ids', 'not_page_ids' ) ),
		) );

		$this->end_controls_section();

		// ── Scheduling ──────────────────────────────────────────────────
		$this->start_controls_section( 'section_schedule', array(
			'label' => 'Scheduling (Optional)',
			'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
		) );

		$this->add_control( 'schedule_notice', array(
			'type'            => \Elementor\Controls_Manager::RAW_HTML,
			'raw'             => '<small>Set a primary ad type and date range below. Outside that range the widget falls back to the <strong>Fallback Ad Type</strong>. If no fallback is set the slot collapses to zero height when nothing renders.</small>',
			'content_classes' => 'elementor-descriptor',
		) );

		$this->add_control( 'scheduled_preset', array(
			'label'   => 'Primary Ad Type (during campaign)',
			'type'    => \Elementor\Controls_Manager::SELECT,
			'options' => array_merge( array( '' => '— same as Ad Type above —' ), $preset_options ),
			'default' => '',
		) );

		$this->add_control( 'schedule_start', array(
			'label'       => 'Campaign Start Date',
			'type'        => \Elementor\Controls_Manager::DATE_TIME,
			'description' => 'Leave blank for no start limit.',
		) );

		$this->add_control( 'schedule_end', array(
			'label'       => 'Campaign End Date',
			'type'        => \Elementor\Controls_Manager::DATE_TIME,
			'description' => 'Leave blank for no end limit.',
		) );

		$this->add_control( 'scheduled_sponsor_id', array(
			'label'       => 'Primary Campaign Sponsor ID',
			'type'        => \Elementor\Controls_Manager::TEXT,
			'placeholder' => 'Optional',
		) );

		$this->end_controls_section();
	}

	// Resolve which preset to use based on schedule.
	private function resolve_settings() {
		$s = $this->get_settings_for_display();

		$now          = current_time( 'timestamp' );
		$use_primary  = false;

		if ( ! empty( $s['scheduled_preset'] ) ) {
			$start = ! empty( $s['schedule_start'] ) ? strtotime( $s['schedule_start'] ) : 0;
			$end   = ! empty( $s['schedule_end'] )   ? strtotime( $s['schedule_end'] )   : PHP_INT_MAX;
			if ( $now >= $start && $now <= $end ) {
				$use_primary = true;
			}
		}

		if ( $use_primary ) {
			$preset_key = $s['scheduled_preset'];
			$sponsor_id = ! empty( $s['scheduled_sponsor_id'] ) ? $s['scheduled_sponsor_id'] : $s['sponsor_id'];
		} else {
			$preset_key = $s['ad_preset'];
			$sponsor_id = $s['sponsor_id'];
		}

		return array(
			'preset_key' => $preset_key,
			'sponsor_id' => $sponsor_id,
			'align'      => $s['ad_align'],
			'is_popup'   => $s['is_popup'],
			'slot_name'  => $s['slot_name'],
			'custom_dw'  => $s['custom_desktop_width'],
			'custom_dh'  => $s['custom_desktop_height'],
			'custom_mw'  => $s['custom_mobile_width'],
			'custom_mh'  => $s['custom_mobile_height'],
		);
	}

	private function passes_visibility_check() {
		$s    = $this->get_settings_for_display();
		$rule = isset( $s['visibility_rule'] ) ? $s['visibility_rule'] : 'all';

		switch ( $rule ) {
			case 'front_page':
				return is_front_page();
			case 'not_front':
				return ! is_front_page();
			case 'singular':
				return is_singular();
			case 'archive':
				return is_archive() || is_home();
			case 'page_ids':
			case 'not_page_ids':
				$raw = isset( $s['visibility_page_ids'] ) ? $s['visibility_page_ids'] : '';
				$ids = array_filter( array_map( 'intval', explode( ',', $raw ) ) );
				if ( empty( $ids ) ) return true;
				$match = is_page( $ids ) || is_single( $ids );
				return $rule === 'page_ids' ? $match : ! $match;
			default:
				return true;
		}
	}

	protected function render() {
		if ( ! $this->passes_visibility_check() ) {
			return;
		}

		$r       = $this->resolve_settings();
		$presets = self::presets();

		if ( $r['preset_key'] === 'custom' ) {
			$dw      = (int) $r['custom_dw'];
			$dh      = (int) $r['custom_dh'];
			$mw      = (int) $r['custom_mw'];
			$mh      = (int) $r['custom_mh'];
			$desktop = array( $dw, $dh );
			$mobile  = array( $mw, $mh );
			$sizes   = ( $dw === $mw && $dh === $mh ) ? array( $dw, $dh ) : array( array( $mw, $mh ), array( $dw, $dh ) );
			$slot_name_default = '';
		} elseif ( isset( $presets[ $r['preset_key'] ] ) ) {
			$p       = $presets[ $r['preset_key'] ];
			$desktop = $p['desktop'];
			$mobile  = $p['mobile'];
			$sizes   = $p['sizes'];
			$slot_name_default = isset( $p['slotname'] ) ? $p['slotname'] : '';
		} else {
			// Nothing configured — render nothing.
			return;
		}

		$slot_name = ! empty( $r['slot_name'] ) ? $r['slot_name'] : $slot_name_default;

		$attrs = array(
			'class'           => 'equinenetworkad',
			'data-sizeDesktop' => json_encode( $desktop ),
			'data-sizeMobile'  => json_encode( $mobile ),
			'data-sizes'       => json_encode( $sizes ),
			'data-align'       => esc_attr( $r['align'] ),
		);

		if ( $r['is_popup'] === 'yes' ) {
			$attrs['data-popup'] = 'true';
		}
		if ( ! empty( $r['sponsor_id'] ) ) {
			$attrs['data-sponsorid'] = esc_attr( $r['sponsor_id'] );
		}
		if ( ! empty( $slot_name ) ) {
			$attrs['data-slotname'] = esc_attr( $slot_name );
		}

		$attr_str = '';
		foreach ( $attrs as $key => $value ) {
			$attr_str .= ' ' . $key . '="' . $value . '"';
		}

		echo '<div' . $attr_str . '></div>';
	}

	// Elementor editor preview placeholder.
	protected function content_template() {
		$presets = self::presets();
		$preset_json = json_encode( array_map( function( $p ) { return $p['label']; }, $presets ) );
		?>
		<#
		var presetLabels = <?php echo $preset_json; ?>;
		var label = presetLabels[ settings.ad_preset ] || ( 'Custom (' + settings.custom_desktop_width + 'x' + settings.custom_desktop_height + ')' );
		var now = new Date();
		var inSchedule = false;
		if ( settings.scheduled_preset && settings.schedule_start ) {
			var start = settings.schedule_start ? new Date(settings.schedule_start) : null;
			var end   = settings.schedule_end   ? new Date(settings.schedule_end)   : null;
			if ( (!start || now >= start) && (!end || now <= end) ) {
				inSchedule = true;
				label = presetLabels[settings.scheduled_preset] || settings.scheduled_preset;
				label += ' (SCHEDULED CAMPAIGN ACTIVE)';
			}
		}
		#>
		<div style="background:#f0f0f0;border:2px dashed #aaa;padding:16px;text-align:center;font-family:sans-serif;">
			<strong style="display:block;margin-bottom:4px;">EN Ad Slot</strong>
			<span style="color:#555;font-size:12px;">{{ label }}</span>
			<# if ( settings.sponsor_id ) { #>
				<span style="display:block;color:#888;font-size:11px;margin-top:4px;">Campaign: {{ settings.sponsor_id }}</span>
			<# } #>
		</div>
		<?php
	}
}
