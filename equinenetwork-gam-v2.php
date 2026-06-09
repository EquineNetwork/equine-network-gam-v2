<?php
// Test comment added via Claude Code on 2026-06-03.
/**
 * @wordpress-plugin
 * Plugin Name:       Equine Network GAM v2
 * Description:       Inject Google Ad Manager tag and generate ads dynamically. Includes Elementor widgets, scheduled ads, child ad unit support, and fluid ad sizes.
 * Version:           3.4.0
 * Author:            Equine Network
 * Author URI:        https://equinenetwork.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       equinenetwork-gam-v2
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'EQUINENETWORK_GAM_V2_VERSION', '3.4.0' );
define( 'EQUINENETWORK_GAM_V2_PATH', plugin_dir_path( __FILE__ ) );
define( 'EQUINENETWORK_GAM_V2_URL', plugin_dir_url( __FILE__ ) );

function activate_equinenetwork_gam_v2() {
	require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-activator.php';
	Equinenetwork_Gam_V2_Activator::activate();
}

function deactivate_equinenetwork_gam_v2() {
	require_once EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2-deactivator.php';
	Equinenetwork_Gam_V2_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_equinenetwork_gam_v2' );
register_deactivation_hook( __FILE__, 'deactivate_equinenetwork_gam_v2' );

require EQUINENETWORK_GAM_V2_PATH . 'includes/class-equinenetwork-gam-v2.php';

// Plugin Update Checker — checks GitHub repo for new versions
require EQUINENETWORK_GAM_V2_PATH . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$engam_updater = PucFactory::buildUpdateChecker(
    'https://github.com/EquineNetwork/Equine-Network-GAM-v2/',
    __FILE__,
    'equinenetwork-gam-v2'
);
$engam_updater->setBranch( 'main' );

function run_equinenetwork_gam_v2() {
	$plugin = new Equinenetwork_Gam_V2();
	$plugin->run();
}

run_equinenetwork_gam_v2();
