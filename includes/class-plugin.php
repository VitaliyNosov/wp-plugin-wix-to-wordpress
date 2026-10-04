<?php
/**
 * Main Plugin Orchestrator Class (Singleton).
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main plugin orchestrator class.
 */
final class W2W_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var W2W_Plugin|null
	 */
	private static ?W2W_Plugin $instance = null;

	/**
	 * Source Manager instance.
	 *
	 * @var W2W_Source_Manager|null
	 */
	private ?W2W_Source_Manager $source_manager = null;

	/**
	 * Logger instance.
	 *
	 * @var W2W_Logger|null
	 */
	private ?W2W_Logger $logger = null;

	/**
	 * Protected constructor to prevent direct instantiation.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Gets the single instance of the class.
	 *
	 * @return W2W_Plugin
	 */
	public static function get_instance(): W2W_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Resets the singleton instance (primarily used for unit testing).
	 *
	 * @return void
	 */
	public static function reset_instance(): void {
		self::$instance = null;
	}

	/**
	 * Initializes WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Loads internationalization text domain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wix-to-wp-migrator',
			false,
			dirname( plugin_basename( W2W_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Core initialization callback.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( is_admin() ) {
			new W2W_Admin();
			new W2W_Ajax_Handler( $this->get_source_manager(), null, null, $this->get_logger() );
		}

		/**
		 * Action triggered when Wix to WordPress Migrator initializes.
		 *
		 * @param W2W_Plugin $this Current plugin instance.
		 */
		do_action( 'w2w_init', $this );
	}

	/**
	 * Returns the Source Manager instance.
	 *
	 * @return W2W_Source_Manager
	 */
	public function get_source_manager(): W2W_Source_Manager {
		if ( null === $this->source_manager ) {
			$this->source_manager = new W2W_Source_Manager();
		}
		return $this->source_manager;
	}

	/**
	 * Returns the Logger instance.
	 *
	 * @return W2W_Logger
	 */
	public function get_logger(): W2W_Logger {
		if ( null === $this->logger ) {
			$this->logger = new W2W_Logger();
		}
		return $this->logger;
	}
}
