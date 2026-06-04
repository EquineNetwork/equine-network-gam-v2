<?php
class Equinenetwork_Gam_V2_Activator {
	public static function activate() {
		if ( ! wp_next_scheduled( 'engam_v2_refresh_line_items' ) ) {
			wp_schedule_event( time() + 45 * MINUTE_IN_SECONDS, 'engam_45min', 'engam_v2_refresh_line_items' );
		}
	}
}
