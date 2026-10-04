<?php
/**
 * Plugin Name:       Wix to WordPress Post Migrator
 * Plugin URI:        https://github.com/VitaliyNosov/wp-plugin-wix-to-wordpress
 * Description:       Enterprise-grade, extensible, and standards-compliant plugin to migrate blog posts, media assets, categories, and SEO metadata from Wix to WordPress.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Vitaliy Nosov
 * Author URI:        https://github.com/VitaliyNosov
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wix-to-wp-migrator
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define core plugin constants.
if ( ! defined( 'W2W_VERSION' ) ) {
	define( 'W2W_VERSION', '1.0.0' );
}

if ( ! defined( 'W2W_PLUGIN_FILE' ) ) {
	define( 'W2W_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'W2W_PLUGIN_DIR' ) ) {
	define( 'W2W_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'W2W_PLUGIN_URL' ) ) {
	define( 'W2W_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'W2W_PLUGIN_BASENAME' ) ) {
	define( 'W2W_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

// Load autoloader.
require_once W2W_PLUGIN_DIR . 'includes/class-autoloader.php';

// Register autoloader.
W2W_Autoloader::register();

/**
 * Returns the main plugin instance.
 *
 * @return W2W_Plugin
 */
function w2w_plugin(): W2W_Plugin {
	return W2W_Plugin::get_instance();
}

// Initialize plugin.
w2w_plugin();
