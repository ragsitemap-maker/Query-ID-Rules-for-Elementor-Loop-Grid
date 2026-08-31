<?php
/**
 * Plugin Name: Query ID Rules for Elementor Loop Grid
 * Description: Manage reusable Elementor Loop Grid Query IDs with taxonomy, ACF/meta, and sorting rules.
 * Version: 0.4.2
 * Author: Site Development Team
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: elementor-loop-grid-query-rules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ELGQR_VERSION', '0.4.2' );
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
