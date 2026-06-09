<?php
/**
 * EquineNetwork GAM v2 — uninstall handler.
 *
 * This plugin intentionally PRESERVES all of its data when it is deleted:
 * settings, GAM API credentials, and saved ads (carousels, takeovers,
 * leaderboards, stacker rules), plus the sponsor IDs assigned to posts/terms.
 * Reinstalling restores everything as it was.
 *
 * The previous "Delete all data on uninstall" toggle has been removed, so there
 * is no destructive path here — even on a site where that option was previously
 * set to "1", nothing is deleted. (Ephemeral transient caches and the OAuth
 * token expire on their own and are rebuilt on demand, so they are left alone.)
 *
 * Deactivation and updates have never touched stored data and still don't.
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// No-op: all plugin data is preserved on uninstall by design.
