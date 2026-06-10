<?php
if ( ! defined( 'WPINC' ) ) die;

/**
 * Registers the "EN Campaign" meta box on posts, pages, and common CPTs.
 * The selected sponsor ID overrides all ad slots on that post/page.
 */
class Equinenetwork_Gam_V2_Metabox {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'save_post',      array( $this, 'save' ), 10, 2 );
		add_action( 'admin_head',     array( $this, 'styles' ) );
		add_action( 'admin_init',     array( $this, 'register_columns' ) );
	}

	public function register() {
		$post_types = apply_filters( 'engam_v2_metabox_post_types', array( 'post', 'page' ) );
		foreach ( $post_types as $pt ) {
			add_meta_box(
				'engam_v2_campaign',
				'EN Sponsor ID',
				array( $this, 'render' ),
				$pt,
				'side',
				'default'
			);
		}
	}

	public function render( $post ) {
		wp_nonce_field( 'engam_v2_metabox_save', 'engam_v2_metabox_nonce' );

		// Current saved value; fall back to legacy ACF fields for pre-migration posts.
		$current = get_post_meta( $post->ID, '_engam_v2_sponsor_id', true );
		if ( $current === '' || $current === false ) {
			$current = get_post_meta( $post->ID, 'sponlineitemid', true );
		}
		if ( $current === '' || $current === false ) {
			$current = get_post_meta( $post->ID, 'sponsorship_id', true );
		}
		if ( $current === false ) $current = '';

		// Get campaign options — prefer live GAM API, fall back to manual list.
		$options = $this->get_campaign_options();
		?>
		<div class="engam-meta-wrap">
			<p class="engam-meta-desc">
				Assign a campaign to this <?php echo get_post_type_object( get_post_type( $post ) )->labels->singular_name; ?>.
				All ad slots on this page will target the selected campaign.
			</p>

			<?php if ( ! empty( $options ) ) : ?>
				<label for="engam_v2_sponsor_id_select" class="engam-meta-label" style="margin-top:4px">Pick from spreadsheet</label>
				<select id="engam_v2_sponsor_id_select" class="engam-meta-select">
					<option value="">— select to fill field below —</option>
					<?php foreach ( $options as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>">
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<label for="engam_v2_sponsor_id" class="engam-meta-label" style="margin-top:10px">Sponsor ID</label>
			<input
				type="text"
				name="engam_v2_sponsor_id"
				id="engam_v2_sponsor_id"
				class="engam-meta-input"
				value="<?php echo esc_attr( $current ); ?>"
				placeholder="e.g. videotips_hr_bimeda"
			/>
			<?php if ( $current ) : ?>
				<a href="#" class="engam-meta-clear" onclick="document.getElementById('engam_v2_sponsor_id').value='';document.getElementById('engam_v2_sponsor_id_select') && (document.getElementById('engam_v2_sponsor_id_select').value='');return false;">Clear</a>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $options ) ) : ?>
		<script>
		(function(){
			var sel = document.getElementById('engam_v2_sponsor_id_select');
			var inp = document.getElementById('engam_v2_sponsor_id');
			if ( sel && inp ) {
				sel.addEventListener('change', function(){
					if ( this.value ) inp.value = this.value;
				});
			}
		})();
		</script>
		<?php endif; ?>
		<?php
	}

	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['engam_v2_metabox_nonce'] ) ) return;
		if ( ! wp_verify_nonce( $_POST['engam_v2_metabox_nonce'], 'engam_v2_metabox_save' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$value = isset( $_POST['engam_v2_sponsor_id'] )
			? sanitize_text_field( $_POST['engam_v2_sponsor_id'] )
			: '';

		if ( $value ) {
			update_post_meta( $post_id, '_engam_v2_sponsor_id', $value );
		} else {
			delete_post_meta( $post_id, '_engam_v2_sponsor_id' );
		}
	}

	private function get_campaign_options() {
		$options = array();

		// Pull from Google Sheet CSV (cached 1 hour).
		require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-api.php';
		$api      = new Equinenetwork_Gam_V2_API();
		$sponsors = $api->get_sponsor_options();
		if ( ! empty( $sponsors ) ) {
			foreach ( $sponsors as $s ) {
				// Append the Sponsorship ID (e.g. "videotips_hr_bimeda") so duplicate
				// advertiser names like the several "WF Young"/"Bimeda" rows can be
				// told apart in the dropdown.
				$options[ $s['id'] ] = ( $s['name'] === $s['id'] )
					? $s['id']
					: $s['name'] . ' - ' . $s['id'];
			}
			return $options;
		}

		// Fall back to manually managed campaign list.
		$manual = get_option( 'equinenetwork_gam_v2_campaigns', array() );
		if ( is_array( $manual ) ) {
			foreach ( $manual as $c ) {
				if ( ! empty( $c['active'] ) ) {
					$options[ $c['gam_id'] ] = $c['label'];
				}
			}
		}

		return $options;
	}

	public function styles() {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, array( 'post', 'page' ), true ) ) return;
		?>
		<style>
		.engam-meta-wrap { font-family: Arial, sans-serif; }
		.engam-meta-desc { font-size: 12px; color: #666; margin: 0 0 10px; line-height: 1.5; }
		.engam-meta-label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 6px; }
		.engam-meta-select { width: 100%; padding: 7px 8px; font-size: 13px; border: 1px solid #bbb; background: #fff; }
		.engam-meta-select:focus { border-color: #111111; outline: none; }
		.engam-meta-input { width: 100%; padding: 7px 8px; font-size: 13px; border: 1px solid #bbb; background: #fff; box-sizing: border-box; }
		.engam-meta-input:focus { border-color: #111111; outline: none; }
		.engam-meta-clear { display: block; font-size: 11px; color: #cc0000; text-decoration: none; margin-top: 5px; }
		.engam-meta-current { font-size: 12px; color: #555; margin: 8px 0 0; }
		.engam-meta-current code { background: #C8FF00; padding: 1px 5px; font-size: 11px; color: #111; }
		.engam-meta-current a { color: #cc0000; text-decoration: none; margin-left: 6px; }
		.engam-meta-empty { font-size: 12px; color: #888; margin: 6px 0 0; }
		.engam-meta-empty a { color: #111111; font-weight: 700; }
		#engam_v2_campaign .inside { padding: 10px 12px; }
		.column-engam_sponsor { width: 16%; }
		</style>
		<?php
	}

	/**
	 * Add a "Sponsor ID" column to the post/page list tables so editors can see
	 * at a glance which entries carry a sponsor assignment. Deferred to admin_init
	 * so the post-type filter is resolved after other code can register it.
	 */
	public function register_columns() {
		$post_types = apply_filters( 'engam_v2_metabox_post_types', array( 'post', 'page' ) );
		foreach ( (array) $post_types as $pt ) {
			add_filter( "manage_{$pt}_posts_columns",       array( $this, 'add_sponsor_column' ) );
			add_action( "manage_{$pt}_posts_custom_column", array( $this, 'render_sponsor_column' ), 10, 2 );
		}
	}

	public function add_sponsor_column( $columns ) {
		// Insert just before the Date column when present, otherwise append.
		$out = array();
		foreach ( $columns as $key => $label ) {
			if ( 'date' === $key ) $out['engam_sponsor'] = 'Sponsor ID';
			$out[ $key ] = $label;
		}
		if ( ! isset( $out['engam_sponsor'] ) ) $out['engam_sponsor'] = 'Sponsor ID';
		return $out;
	}

	public function render_sponsor_column( $column, $post_id ) {
		if ( 'engam_sponsor' !== $column ) return;

		// The plugin's own value wins; fall back to the raw legacy ACF meta (read
		// directly so it still shows after the ACF field itself is deleted).
		$val = get_post_meta( $post_id, '_engam_v2_sponsor_id', true );
		if ( $val === '' || $val === false ) {
			$val = get_post_meta( $post_id, 'sponlineitemid', true );
			if ( $val === '' || $val === false ) $val = get_post_meta( $post_id, 'sponsorship_id', true );
		}

		if ( $val === '' || $val === false ) {
			echo '<span style="color:#ccc">—</span>';
			return;
		}

		echo '<code style="font-size:12px;background:#f3f3ee;border:1px solid #e3e3dc;padding:2px 6px;border-radius:3px">' . esc_html( $val ) . '</code>';
	}
}
