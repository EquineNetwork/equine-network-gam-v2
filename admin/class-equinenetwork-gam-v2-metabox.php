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

		$current = get_post_meta( $post->ID, '_engam_v2_sponsor_id', true );

		// Get campaign options — prefer live GAM API, fall back to manual list.
		$options = $this->get_campaign_options();
		?>
		<div class="engam-meta-wrap">
			<p class="engam-meta-desc">
				Assign a campaign to this <?php echo get_post_type_object( get_post_type( $post ) )->labels->singular_name; ?>.
				All ad slots on this page will target the selected campaign.
			</p>

			<label for="engam_v2_sponsor_id" class="engam-meta-label">Sponsor ID</label>

			<?php if ( empty( $options ) ) : ?>
				<p class="engam-meta-empty">
					No sponsors available. <a href="<?php echo esc_url( admin_url( 'admin.php?page=engam-v2-settings' ) ); ?>">Connect your Google Sheet</a> in EN Ads Settings.
				</p>
			<?php else : ?>
				<select name="engam_v2_sponsor_id" id="engam_v2_sponsor_id" class="engam-meta-select">
					<option value="">— No campaign override —</option>
					<?php foreach ( $options as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<?php if ( $current ) : ?>
				<p class="engam-meta-current">
					Current: <code><?php echo esc_html( $current ); ?></code>
					<a href="#" onclick="document.getElementById('engam_v2_sponsor_id').value='';return false;">Clear</a>
				</p>
			<?php endif; ?>
		</div>
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
				$options[ $s['id'] ] = $s['name'];
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
		.engam-meta-select:focus { border-color: #050505; outline: none; }
		.engam-meta-current { font-size: 12px; color: #555; margin: 8px 0 0; }
		.engam-meta-current code { background: #d0ff00; padding: 1px 5px; font-size: 11px; color: #111; }
		.engam-meta-current a { color: #cc0000; text-decoration: none; margin-left: 6px; }
		.engam-meta-empty { font-size: 12px; color: #888; margin: 6px 0 0; }
		.engam-meta-empty a { color: #050505; font-weight: 700; }
		#engam_v2_campaign .inside { padding: 10px 12px; }
		</style>
		<?php
	}
}
