<?php
/**
 * Plugin Name: Microsub
 * Plugin URI: https://github.com/pfefferle/wordpress-microsub
 * Description: A Microsub server reference implementation for WordPress. Provides the Microsub API with hooks for reader plugins to integrate via adapters.
 * Version: 1.0.0
 * Author: Matthias Pfefferle
 * Author URI: https://notiz.blog
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: microsub
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: indieauth
 *
 * @package Microsub
 */

namespace Microsub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\define( 'MICROSUB_VERSION', '1.0.0' );

// Plugin related constants.
\define( 'MICROSUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
\define( 'MICROSUB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
\define( 'MICROSUB_PLUGIN_FILE', MICROSUB_PLUGIN_DIR . basename( __FILE__ ) );
\define( 'MICROSUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-autoloader.php';
require_once __DIR__ . '/includes/compat.php';

// Register the autoloader.
Autoloader::register_path( __NAMESPACE__, __DIR__ . '/includes' );

// Initialize the plugin.
$microsub = Microsub::get_instance();
$microsub->init();

/**
 * Plugin Version Number used for caching.
 *
 * @return string The plugin version.
 */
function version() {
	return Microsub::get_instance()->get_version();
}

/**
 * Activation Hook.
 */
function activation() {
	\flush_rewrite_rules();
}
\register_activation_hook( __FILE__, __NAMESPACE__ . '\activation' );

/**
 * Deactivation Hook.
 */
function deactivation() {
	\flush_rewrite_rules();
}
\register_deactivation_hook( __FILE__, __NAMESPACE__ . '\deactivation' );

/**
 * Register an adapter.
 *
 * Helper function for plugins to register their adapters.
 *
 * @param Adapter $adapter The adapter instance.
 */
function register_adapter( $adapter ) {
	if ( $adapter instanceof Adapter ) {
		$adapter->register();
	}
}

/**
 * Get registered adapters.
 *
 * @return array Array of registered adapters.
 */
function get_adapters() {
	/**
	 * Filters the list of registered adapters.
	 *
	 * @param array $adapters Array of registered adapters.
	 */
	return \apply_filters( 'microsub_adapters', array() );
}
