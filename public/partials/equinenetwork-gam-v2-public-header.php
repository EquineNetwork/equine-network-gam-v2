<?php if ( ! defined( 'WPINC' ) ) die; ?>
<style>
.equinenetworkad iframe {
	max-width: 728px !important;
	display: inline-block !important;
}
#adModal iframe {
	max-width: 640px !important;
	display: inline-block !important;
}
@media only screen and (max-width: 728px) {
	.equinenetworkad iframe {
		max-width: 320px !important;
		display: inline-block !important;
	}
	#adModal iframe {
		max-width: 320px !important;
		display: inline-block !important;
	}
}
/* Collapse empty ad slots and their Elementor containers */
.equinenetworkad.engam-empty {
	display: none !important;
	height: 0 !important;
	padding: 0 !important;
	margin: 0 !important;
}
.engam-container-empty {
	display: none !important;
	height: 0 !important;
	min-height: 0 !important;
	padding: 0 !important;
	margin: 0 !important;
	border: none !important;
	overflow: hidden !important;
}
/* Admin debug: show empty GAM slots instead of collapsing them */
.engam-debug-empty {
	display: flex !important;
	align-items: center;
	justify-content: center;
	min-width: 200px;
	min-height: 80px;
	padding: 10px;
	border: 2px dashed #cc0000;
	background: #fff5f5;
	color: #cc0000;
	font: 12px/1.4 monospace;
	text-align: center;
	word-break: break-all;
}
.engam-debug-empty:before {
	content: 'GAM empty → ' attr(data-engam-debug);
}
/* Collapse Elementor columns/sections that contain only empty ad widgets */
.elementor-column.engam-container-empty,
.elementor-col-100.engam-container-empty,
.e-con.engam-container-empty,
.e-con-inner.engam-container-empty {
	display: none !important;
	height: 0 !important;
	min-height: 0 !important;
	padding: 0 !important;
	margin: 0 !important;
}
</style>
<script async src="https://securepubads.g.doubleclick.net/tag/js/gpt.js"></script>
<script>
window.equinenetwork_gam_v2_id = '<?php echo esc_js( get_option( 'equinenetwork_gam_v2_id' ) ); ?>';
<?php if ( current_user_can( 'manage_options' ) && isset( $_GET['engam_debug'] ) ) : ?>
window.equinenetwork_gam_v2_debug = true;
<?php endif; ?>
</script>
