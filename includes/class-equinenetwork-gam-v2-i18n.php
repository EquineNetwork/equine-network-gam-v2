<?php
class Equinenetwork_Gam_V2_i18n {
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'equinenetwork-gam-v2',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
