<?php
/**
 * Class Autoloader conforming to WordPress Coding Standards.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Autoloader for W2W_ prefixed classes and interfaces.
 */
class W2W_Autoloader {

	/**
	 * Prefix for all plugin classes.
	 *
	 * @var string
	 */
	private const CLASS_PREFIX = 'W2W_';

	/**
	 * Directories to search for class/interface files.
	 *
	 * @var array<string>
	 */
	private static array $directories = array(
		'includes',
		'includes/dto',
		'includes/interfaces',
		'includes/sources',
		'includes/engine',
		'includes/utils',
		'includes/ajax',
		'admin',
	);

	/**
	 * Registers the autoloader with SPL.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Unregisters the autoloader.
	 *
	 * @return void
	 */
	public static function unregister(): void {
		spl_autoload_unregister( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload callback.
	 *
	 * @param string $class_name Class name to load.
	 * @return bool True if loaded, false otherwise.
	 */
	public static function autoload( string $class_name ): bool {
		if ( 0 !== strpos( $class_name, self::CLASS_PREFIX ) ) {
			return false;
		}

		$relative_class = substr( $class_name, strlen( self::CLASS_PREFIX ) );

		// Determine if this is an interface.
		$is_interface = false;
		if ( substr( $relative_class, -10 ) === '_Interface' ) {
			$is_interface   = true;
			$relative_class = substr( $relative_class, 0, -10 );
		}

		// Convert class name to WPCS filename (e.g., Post_DTO -> post-dto).
		$slug      = strtolower( str_replace( '_', '-', $relative_class ) );
		$file_name = ( $is_interface ? 'interface-' : 'class-' ) . $slug . '.php';

		$base_dir = defined( 'W2W_PLUGIN_DIR' )
			? W2W_PLUGIN_DIR
			: dirname( __DIR__ ) . DIRECTORY_SEPARATOR;

		// Ensure trailing slash.
		$base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;

		foreach ( self::$directories as $dir ) {
			$file_path = $base_dir . str_replace( '/', DIRECTORY_SEPARATOR, $dir ) . DIRECTORY_SEPARATOR . $file_name;
			if ( file_exists( $file_path ) ) {
				require_once $file_path;
				return true;
			}
		}

		return false;
	}
}
