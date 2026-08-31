<?php
/**
 * Plugin Name: Query ID Rules for Elementor Loop Grid
 * Plugin URI: https://github.com/ragsitemap-maker/Query-ID-Rules-for-Elementor-Loop-Grid
 * Description: Manage reusable Elementor Loop Grid Query IDs with taxonomy, ACF/meta, and sorting rules.
 * Version: 0.5.0
 * Author: Site Development Team
 * Author URI: https://github.com/ragsitemap-maker
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: query-id-rules-for-elementor-loop-grid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ELGQR_VERSION', '0.5.0' );
define( 'ELGQR_FILE', __FILE__ );
define( 'ELGQR_PATH', plugin_dir_path( __FILE__ ) );
define( 'ELGQR_URL', plugin_dir_url( __FILE__ ) );

require_once ELGQR_PATH . 'includes/class-rule-repository.php';
require_once ELGQR_PATH . 'includes/class-context-resolver.php';
require_once ELGQR_PATH . 'includes/class-polylang-adapter.php';
require_once ELGQR_PATH . 'includes/class-query-applier.php';
require_once ELGQR_PATH . 'includes/class-query-registry.php';
require_once ELGQR_PATH . 'includes/class-empty-result-visibility.php';
require_once ELGQR_PATH . 'includes/admin/class-admin.php';
require_once ELGQR_PATH . 'includes/class-plugin.php';

add_action(
	'plugins_loaded',
	static function () {
		\ELGQR\Plugin::instance()->boot();
	}
);
