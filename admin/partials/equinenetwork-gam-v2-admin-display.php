<?php if ( ! defined( 'WPINC' ) ) die;

$gam_id      = get_option( 'equinenetwork_gam_v2_id', '' );
$id_active   = ! empty( $gam_id );
$campaigns   = get_option( 'equinenetwork_gam_v2_campaigns', array() );
if ( ! is_array( $campaigns ) ) $campaigns = array();

$has_credentials = ! empty( get_option( 'equinenetwork_gam_v2_credentials', '' ) );

// Check if GAM API has cached line items.
$api             = new Equinenetwork_Gam_V2_API();
$api_configured  = $api->is_configured();
$cached_items    = $api_configured ? get_transient( 'engam_v2_line_items' ) : false;
$api_item_count  = is_array( $cached_items ) ? count( $cached_items ) : 0;

$active_count = $api_configured && $api_item_count > 0
	? $api_item_count
	: count( array_filter( $campaigns, function( $c ) { return ! empty( $c['active'] ); } ) );

// Handle form submissions.
$notice = '';
if ( isset( $_POST['engam_v2_nonce'] ) && wp_verify_nonce( $_POST['engam_v2_nonce'], 'engam_v2_save' ) ) {

	// Save GAM ID and filter keyword.
	if ( isset( $_POST['equinenetwork_gam_v2_id'] ) ) {
		update_option( 'equinenetwork_gam_v2_id', sanitize_text_field( $_POST['equinenetwork_gam_v2_id'] ) );
		update_option( 'equinenetwork_gam_v2_filter', sanitize_text_field( $_POST['equinenetwork_gam_v2_filter'] ?? '' ) );
		delete_transient( 'engam_v2_line_items' );
		$gam_id    = get_option( 'equinenetwork_gam_v2_id', '' );
		$id_active = ! empty( $gam_id );
		$notice    = 'Settings saved.';
	}

	// Add campaign.
	if ( ! empty( $_POST['engam_add_label'] ) && ! empty( $_POST['engam_add_id'] ) ) {
		$new = array(
			'label'  => sanitize_text_field( $_POST['engam_add_label'] ),
			'gam_id' => sanitize_text_field( $_POST['engam_add_id'] ),
			'active' => true,
			'key'    => uniqid( 'cmp_' ),
		);
		$campaigns[] = $new;
		update_option( 'equinenetwork_gam_v2_campaigns', $campaigns );
		$notice = 'Campaign "' . esc_html( $new['label'] ) . '" added.';
	}

	// Toggle active.
	if ( isset( $_POST['engam_toggle_key'] ) ) {
		$toggle_key = sanitize_text_field( $_POST['engam_toggle_key'] );
		foreach ( $campaigns as &$c ) {
			if ( $c['key'] === $toggle_key ) {
				$c['active'] = empty( $c['active'] );
				break;
			}
		}
		unset( $c );
		update_option( 'equinenetwork_gam_v2_campaigns', $campaigns );
		$notice = 'Campaign updated.';
	}

	// Delete campaign.
	if ( isset( $_POST['engam_delete_key'] ) ) {
		$del_key   = sanitize_text_field( $_POST['engam_delete_key'] );
		$campaigns = array_values( array_filter( $campaigns, function( $c ) use ( $del_key ) {
			return $c['key'] !== $del_key;
		} ) );
		update_option( 'equinenetwork_gam_v2_campaigns', $campaigns );
		$notice = 'Campaign removed.';
	}

	$active_count = count( array_filter( $campaigns, function( $c ) { return ! empty( $c['active'] ); } ) );
}
?>
<style>
#engam-v2-wrap *{box-sizing:border-box}
#engam-v2-wrap{max-width:1200px;font-family:Arial,Helvetica,sans-serif;color:#050505;padding:0 0 60px}
#engam-v2-wrap .eg-mast{background:#050505;color:#fff;position:relative;overflow:hidden;padding:32px 38px;display:grid;grid-template-columns:1fr auto;gap:24px;align-items:center;margin-bottom:18px}
#engam-v2-wrap .eg-mast:before{content:"EN EN EN EN EN EN";position:absolute;right:-40px;bottom:-4px;font-size:88px;font-weight:900;letter-spacing:-10px;color:rgba(255,255,255,.035);transform:rotate(-8deg);pointer-events:none}
#engam-v2-wrap .eg-brand{display:flex;align-items:center;gap:14px;position:relative;z-index:1}
#engam-v2-wrap .eg-logo{width:52px;height:40px;background:#d0ff00;color:#111;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:900;letter-spacing:-4px;flex-shrink:0}
#engam-v2-wrap .eg-brand-text{position:relative;z-index:1}
#engam-v2-wrap .eg-brand-text small{color:#d0ff00;font-size:11px;letter-spacing:.14em;text-transform:uppercase;font-weight:800;display:block}
#engam-v2-wrap .eg-brand-text h1{font-size:42px;line-height:1;margin:4px 0 0;text-transform:uppercase;letter-spacing:-2px;color:#fff}
#engam-v2-wrap .eg-mast-actions{position:relative;z-index:1;display:flex;gap:10px}
#engam-v2-wrap .eg-btn{border:2px solid #050505;background:#d0ff00;color:#111;border-radius:999px;padding:10px 18px;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.04em;cursor:pointer;white-space:nowrap;line-height:1}
#engam-v2-wrap .eg-btn:hover{background:#b9e900}
#engam-v2-wrap .eg-btn.dark{background:#fff;color:#111;border-color:#fff}
#engam-v2-wrap .eg-btn.dark:hover{background:#e8e8e8}
#engam-v2-wrap .eg-btn.ghost{background:transparent;color:#fff;border-color:#777}
#engam-v2-wrap .eg-btn.ghost:hover{border-color:#d0ff00;color:#d0ff00}
#engam-v2-wrap .eg-btn.sm{padding:7px 14px;font-size:11px}
#engam-v2-wrap .eg-btn.danger{background:#fff;border-color:#cc0000;color:#cc0000}
#engam-v2-wrap .eg-btn.danger:hover{background:#cc0000;color:#fff}
/* stats */
#engam-v2-wrap .eg-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px}
#engam-v2-wrap .eg-stat{background:#fff;border:1px solid #deded8;padding:18px}
#engam-v2-wrap .eg-stat small{display:block;font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:900;color:#555}
#engam-v2-wrap .eg-stat strong{display:block;font-size:36px;letter-spacing:-2px;margin:8px 0 4px;line-height:1}
#engam-v2-wrap .eg-ok{background:#d0ff00;color:#111;padding:2px 8px;font-size:11px;font-weight:900;display:inline-block}
#engam-v2-wrap .eg-na{color:#777;font-size:12px;font-weight:700}
/* cards */
#engam-v2-wrap .eg-grid{display:grid;grid-template-columns:1fr 340px;gap:18px}
#engam-v2-wrap .eg-card{background:#fff;border:1px solid #deded8}
#engam-v2-wrap .eg-card.black{background:#080808;color:#fff;border-color:#080808}
#engam-v2-wrap .eg-head{padding:18px 24px;border-bottom:1px solid #deded8;display:flex;justify-content:space-between;align-items:center;gap:16px}
#engam-v2-wrap .eg-card.black .eg-head{border-color:#2c2c2c}
#engam-v2-wrap .eg-head h2{font-size:22px;text-transform:uppercase;letter-spacing:-1px;margin:0}
#engam-v2-wrap .eg-head p{margin:4px 0 0;color:#777;font-size:13px}
#engam-v2-wrap .eg-card.black .eg-head p{color:#bdbdb8}
#engam-v2-wrap .eg-tag{height:24px;display:inline-flex;align-items:center;background:#d0ff00;color:#111;padding:0 10px;font-size:11px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;flex-shrink:0}
#engam-v2-wrap .eg-body{padding:24px}
/* notice */
#engam-v2-wrap .eg-notice{padding:12px 18px;margin-bottom:18px;font-weight:700;font-size:13px;background:#d0ff00;color:#111;border-left:6px solid #050505}
/* add campaign form */
#engam-v2-wrap .eg-add-form{display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end;padding:18px 24px;background:#f5f5f2;border-bottom:1px solid #deded8}
#engam-v2-wrap .eg-field-inline label{font-size:11px;text-transform:uppercase;letter-spacing:.1em;font-weight:900;display:block;margin-bottom:6px}
#engam-v2-wrap .eg-input{width:100%;border:1px solid #bbb;background:#fff;padding:11px 12px;font-size:14px;font-weight:600;outline:none}
#engam-v2-wrap .eg-input:focus{border-color:#050505}
/* campaign table */
#engam-v2-wrap .eg-table{width:100%;border-collapse:collapse}
#engam-v2-wrap .eg-table th{background:#050505;color:#fff;text-align:left;padding:10px 16px;font-size:11px;text-transform:uppercase;letter-spacing:.1em;font-weight:900}
#engam-v2-wrap .eg-table td{padding:13px 16px;border-bottom:1px solid #ebebeb;font-size:13px;vertical-align:middle}
#engam-v2-wrap .eg-table tr:last-child td{border-bottom:none}
#engam-v2-wrap .eg-table tr:hover td{background:#fafaf8}
#engam-v2-wrap .eg-table .eg-campaign-name{font-weight:700;font-size:14px}
#engam-v2-wrap .eg-table .eg-campaign-id{font-family:Consolas,monospace;font-size:12px;color:#555;margin-top:2px}
#engam-v2-wrap .eg-badge{display:inline-block;padding:3px 8px;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.06em}
#engam-v2-wrap .eg-badge.active{background:#d0ff00;color:#111}
#engam-v2-wrap .eg-badge.inactive{background:#ebebeb;color:#777}
#engam-v2-wrap .eg-actions-cell{display:flex;gap:8px;align-items:center}
#engam-v2-wrap .eg-empty{text-align:center;padding:40px 24px;color:#777}
#engam-v2-wrap .eg-empty strong{display:block;font-size:18px;margin-bottom:6px;color:#050505}
/* settings card */
#engam-v2-wrap .eg-settings-field{margin-bottom:16px}
#engam-v2-wrap .eg-settings-field label{font-size:11px;text-transform:uppercase;letter-spacing:.1em;font-weight:900;display:block;margin-bottom:7px}
#engam-v2-wrap .eg-hint{font-size:12px;color:#777;margin-top:6px;font-weight:400;letter-spacing:0;text-transform:none}
#engam-v2-wrap .eg-accentline{height:5px;background:#d0ff00;margin-top:18px}
@media(max-width:900px){
  #engam-v2-wrap .eg-grid,#engam-v2-wrap .eg-stats{grid-template-columns:1fr}
  #engam-v2-wrap .eg-add-form{grid-template-columns:1fr;gap:12px}
  #engam-v2-wrap .eg-mast{grid-template-columns:1fr}
  #engam-v2-wrap .eg-mast h1{font-size:32px}
}
</style>

<div id="engam-v2-wrap">

<?php if ( $notice ) : ?>
	<div class="eg-notice"><?php echo esc_html( $notice ); ?></div>
<?php endif; ?>

<!-- MASTHEAD -->
<section class="eg-mast">
	<div class="eg-brand">
		<div class="eg-logo">EN</div>
		<div class="eg-brand-text">
			<small>Google Ad Manager &mdash; v2</small>
			<h1>EN Ads</h1>
		</div>
	</div>
	<div class="eg-mast-actions">
		<button class="eg-btn ghost" onclick="document.getElementById('engam-settings-card').scrollIntoView({behavior:'smooth'})">Settings</button>
		<button class="eg-btn dark" onclick="document.getElementById('engam-add-label').focus()">+ Add Campaign</button>
	</div>
</section>

<!-- STATS -->
<section class="eg-stats">
	<div class="eg-stat">
		<small>GAM Network ID</small>
		<strong style="font-size:<?php echo $id_active ? '22px' : '36px'; ?>;letter-spacing:<?php echo $id_active ? '0' : '-2px'; ?>"><?php echo $id_active ? esc_html( $gam_id ) : '—'; ?></strong>
		<?php if ( $id_active ) : ?>
			<span class="eg-ok">Configured</span>
		<?php else : ?>
			<span class="eg-na">Not set — see Settings</span>
		<?php endif; ?>
	</div>
	<div class="eg-stat">
		<small>Total Campaigns</small>
		<strong><?php echo count( $campaigns ); ?></strong>
		<span class="eg-na"><?php echo count( $campaigns ) === 1 ? '1 campaign' : count( $campaigns ) . ' campaigns'; ?> stored</span>
	</div>
	<div class="eg-stat">
		<small><?php echo $api_configured ? 'GAM Line Items' : 'Active Campaigns'; ?></small>
		<strong><?php echo $active_count; ?></strong>
		<?php if ( $api_configured ) : ?>
			<span class="eg-ok">Live from GAM API</span>
		<?php elseif ( $active_count > 0 ) : ?>
			<span class="eg-ok">Showing in widget</span>
		<?php else : ?>
			<span class="eg-na">None active</span>
		<?php endif; ?>
	</div>
	<div class="eg-stat">
		<small>GAM API</small>
		<strong><?php echo $api_configured ? '✓' : '—'; ?></strong>
		<?php if ( $api_configured ) : ?>
			<span class="eg-ok">Connected</span>
		<?php else : ?>
			<span class="eg-na">Not configured</span>
		<?php endif; ?>
	</div>
</section>

<!-- MAIN GRID -->
<div class="eg-grid">

	<!-- CAMPAIGN MANAGER -->
	<div class="eg-card">
		<div class="eg-head">
			<div>
				<h2>Campaign Manager</h2>
				<p>Active campaigns appear in the Sponsor ID dropdown in the Elementor EN Ad Slot widget.</p>
			</div>
			<span class="eg-tag"><?php echo $active_count; ?> Active</span>
		</div>

		<!-- ADD FORM -->
		<form method="post" action="" class="eg-add-form">
			<?php wp_nonce_field( 'engam_v2_save', 'engam_v2_nonce' ); ?>
			<div class="eg-field-inline">
				<label for="engam-add-label">Friendly Name</label>
				<input class="eg-input" type="text" name="engam_add_label" id="engam-add-label" placeholder="e.g. Cactus Ropes — Horses" required>
			</div>
			<div class="eg-field-inline">
				<label for="engam-add-id">GAM Campaign ID</label>
				<input class="eg-input" type="text" name="engam_add_id" id="engam-add-id" placeholder="e.g. CactusRopes_Horses" required>
			</div>
			<button type="submit" class="eg-btn">Add Campaign</button>
		</form>

		<!-- TABLE -->
		<?php if ( empty( $campaigns ) ) : ?>
			<div class="eg-empty">
				<strong>No campaigns yet</strong>
				Add your first campaign using the form above. Active campaigns will appear in the Elementor widget dropdown.
			</div>
		<?php else : ?>
			<table class="eg-table">
				<thead>
					<tr>
						<th>Campaign</th>
						<th>Status</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $campaigns as $campaign ) :
						$is_active = ! empty( $campaign['active'] );
					?>
					<tr>
						<td>
							<div class="eg-campaign-name"><?php echo esc_html( $campaign['label'] ); ?></div>
							<div class="eg-campaign-id"><?php echo esc_html( $campaign['gam_id'] ); ?></div>
						</td>
						<td>
							<span class="eg-badge <?php echo $is_active ? 'active' : 'inactive'; ?>">
								<?php echo $is_active ? 'Active' : 'Inactive'; ?>
							</span>
						</td>
						<td>
							<div class="eg-actions-cell">
								<form method="post" action="" style="display:inline">
									<?php wp_nonce_field( 'engam_v2_save', 'engam_v2_nonce' ); ?>
									<input type="hidden" name="engam_toggle_key" value="<?php echo esc_attr( $campaign['key'] ); ?>">
									<button type="submit" class="eg-btn sm dark" style="border-color:#111">
										<?php echo $is_active ? 'Deactivate' : 'Activate'; ?>
									</button>
								</form>
								<form method="post" action="" style="display:inline" onsubmit="return confirm('Remove <?php echo esc_js( $campaign['label'] ); ?>?')">
									<?php wp_nonce_field( 'engam_v2_save', 'engam_v2_nonce' ); ?>
									<input type="hidden" name="engam_delete_key" value="<?php echo esc_attr( $campaign['key'] ); ?>">
									<button type="submit" class="eg-btn sm danger">Remove</button>
								</form>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<div class="eg-accentline"></div>
	</div>

	<!-- SETTINGS SIDEBAR -->
	<div id="engam-settings-card">

		<div class="eg-card" style="margin-bottom:18px">
			<div class="eg-head">
				<div>
					<h2>Settings</h2>
					<p>One-time setup per site.</p>
				</div>
				<span class="eg-tag">Site</span>
			</div>
			<div class="eg-body">
				<form method="post" action="">
					<?php wp_nonce_field( 'engam_v2_save', 'engam_v2_nonce' ); ?>
					<div class="eg-settings-field">
						<label for="engam-gam-id">GAM Network ID</label>
						<input class="eg-input" type="text" name="equinenetwork_gam_v2_id" id="engam-gam-id"
							value="<?php echo esc_attr( $gam_id ); ?>"
							placeholder="/22345131513/sitename">
						<p class="eg-hint">Your full Google Ad Manager network path.</p>
					</div>
					<div class="eg-settings-field">
						<label for="engam-gam-filter">Campaign Filter Keyword</label>
						<input class="eg-input" type="text" name="equinenetwork_gam_v2_filter" id="engam-gam-filter"
							value="<?php echo esc_attr( get_option( 'equinenetwork_gam_v2_filter', '' ) ); ?>"
							placeholder="e.g. trj">
						<p class="eg-hint">Only show GAM line items whose name contains this keyword. Leave blank to show all. Ask your GAM team what prefix they use for this site.</p>
					</div>
					<button type="submit" class="eg-btn" style="width:100%;justify-content:center;display:flex">Save Settings</button>
				</form>
			</div>
		</div>

		<!-- GAM API CREDENTIALS -->
		<div class="eg-card" style="margin-bottom:18px">
			<div class="eg-head">
				<div>
					<h2>GAM API</h2>
					<p><?php echo $api_configured ? 'Credentials saved. API is active.' : 'Paste your service account JSON key to enable live campaign sync.'; ?></p>
				</div>
				<span class="eg-tag" style="<?php echo $api_configured ? '' : 'background:#111;color:#d0ff00;'; ?>"><?php echo $api_configured ? 'Active' : 'Setup'; ?></span>
			</div>
			<div class="eg-body">
				<?php if ( $api_configured ) : ?>
					<p style="font-size:13px;color:#555;margin:0 0 14px">Credentials are stored. The Elementor widget sponsor dropdown pulls live from GAM and caches for 1 hour.</p>
				<?php else : ?>
					<p style="font-size:13px;color:#555;margin:0 0 14px">Upload your service account JSON key file to enable live campaign sync from GAM.</p>
				<?php endif; ?>

				<div class="eg-settings-field">
					<label>Service Account JSON Key <?php echo $api_configured ? '<span style="color:#777;font-weight:400;text-transform:none;letter-spacing:0">(upload to replace)</span>' : ''; ?></label>
					<div id="engam-upload-area" style="border:2px dashed #bbb;background:#f8f8f5;padding:10px 12px;cursor:pointer;transition:border-color .2s;display:flex;align-items:center;gap:10px"
						onclick="document.getElementById('engam-credentials-file').click()"
						ondragover="event.preventDefault();this.style.borderColor='#050505'"
						ondragleave="this.style.borderColor='#bbb'"
						ondrop="engamHandleDrop(event)">
						<input type="file" id="engam-credentials-file" accept=".json,application/json" style="display:none" onchange="engamHandleFile(this.files[0])">
						<span style="font-size:18px;flex-shrink:0">📁</span>
						<div id="engam-upload-label" style="min-width:0">
							<strong style="font-size:12px;display:block">Click to upload or drag &amp; drop</strong>
							<span style="font-size:11px;color:#777">.json service account key file</span>
						</div>
					</div>
					<p class="eg-hint">The key is stored securely in the WordPress database — never in a file or repository.</p>
				</div>

				<div style="display:flex;gap:10px;flex-wrap:wrap">
					<button class="eg-btn" id="engam-save-credentials" style="pointer-events:none;opacity:.4" disabled>Save Credentials</button>
					<button class="eg-btn dark" id="engam-test-connection"
						<?php echo ! $api_configured ? 'style="pointer-events:none;opacity:.4;border-color:#bbb"' : 'style="border-color:#111"'; ?>>
						Test Connection
					</button>
					<?php if ( $api_configured ) : ?>
					<button class="eg-btn dark" style="border-color:#111" id="engam-refresh-cache">Refresh Cache</button>
					<?php endif; ?>
				</div>
				<div id="engam-api-status" style="display:none;margin-top:12px;padding:10px 14px;font-size:13px;font-weight:700"></div>
				<style>
				#engam-api-status.success{background:#d0ff00;color:#111}
				#engam-api-status.error{background:#ffdddd;color:#a00}
				#engam-api-status.info{background:#f0f0f0;color:#555}
				</style>
			</div>
		</div>

		<div class="eg-card black">
			<div class="eg-head">
				<div><h2>How It Works</h2></div>
				<span class="eg-tag">Guide</span>
			</div>
			<div class="eg-body" style="font-size:13px;line-height:1.6;color:#d8d8d2">
				<p style="margin:0 0 12px"><strong style="color:#d0ff00">1. GAM API</strong> syncs active line items automatically — no manual list to maintain.</p>
				<p style="margin:0 0 12px"><strong style="color:#d0ff00">2. On any post or page</strong>, use the <strong style="color:#fff">EN Campaign</strong> sidebar panel to assign a sponsor ID that overrides all ads on that page.</p>
				<p style="margin:0 0 12px"><strong style="color:#d0ff00">3. In Elementor</strong>, drop the <strong style="color:#fff">EN Ad Slot</strong> widget and pick a preset — the sponsor dropdown pulls from GAM live.</p>
				<p style="margin:0"><strong style="color:#d0ff00">4. GAM handles</strong> creative scheduling, fallbacks, and targeting automatically.</p>
				<div class="eg-accentline"></div>
			</div>
		</div>

	</div>
</div><!-- .eg-grid -->

</div><!-- #engam-v2-wrap -->
