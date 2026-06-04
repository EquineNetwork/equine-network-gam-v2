<?php
class Equinenetwork_Gam_V2_Deactivator {
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'engam_v2_refresh_line_items' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'engam_v2_refresh_line_items' );
		}
	}
}
