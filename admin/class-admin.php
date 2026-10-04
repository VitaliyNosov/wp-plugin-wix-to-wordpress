<?php
/**
 * Admin Controller for Wix to WordPress Post Migrator.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Manages admin menu registration, asset enqueuing, and page rendering.
 */
class W2W_Admin {

	/**
	 * Menu slug.
	 */
	public const MENU_SLUG = 'wix-to-wp-migrator';

	/**
	 * Constructor. Registers admin hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registers the plugin submenu page under the "Tools" admin menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_management_page(
			__( 'Wix to WordPress Post Migrator', 'wix-to-wp-migrator' ),
			__( 'Wix to WP', 'wix-to-wp-migrator' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueues CSS and JS assets strictly on the plugin administration page.
	 *
	 * @param string $hook_suffix The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'tools_page_' . self::MENU_SLUG !== $hook_suffix ) {
			return;
		}

		$js_file = dirname( dirname( __FILE__ ) ) . '/admin/js/admin.js';
		$version = file_exists( $js_file ) ? (string) filemtime( $js_file ) : ( defined( 'W2W_VERSION' ) ? W2W_VERSION : '1.0.0' );
		$url     = defined( 'W2W_PLUGIN_URL' ) ? W2W_PLUGIN_URL : plugins_url( '/', dirname( __FILE__ ) );

		// Enqueue compiled native WordPress admin stylesheet.
		wp_enqueue_style(
			'w2w-admin-style',
			$url . 'admin/css/admin.css',
			array(),
			$version
		);

		// Enqueue modular bundled admin script.
		wp_enqueue_script(
			'w2w-admin-script',
			$url . 'admin/js/admin.js',
			array( 'jquery' ),
			$version,
			true
		);

		// Localize script with AJAX URL, security nonce, and internationalization strings.
		wp_localize_script(
			'w2w-admin-script',
			'w2wAdmin',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'w2w_admin_nonce' ),
				'plugin_url' => $url,
				'i18n'       => array(
					'confirm_rollback' => __( 'Are you sure you want to rollback this batch? All imported posts and media from this batch will be permanently deleted.', 'wix-to-wp-migrator' ),
					'select_batch'     => __( 'Please enter or select a Batch UUID.', 'wix-to-wp-migrator' ),
					'fetching_preview' => __( 'Fetching and parsing Wix RSS feed...', 'wix-to-wp-migrator' ),
					'no_posts_select'  => __( 'Please select at least one post to migrate.', 'wix-to-wp-migrator' ),
					'migrating'        => __( 'Migrating posts...', 'wix-to-wp-migrator' ),
					'paused'           => __( 'Migration paused.', 'wix-to-wp-migrator' ),
					'complete'         => __( 'Migration completed successfully!', 'wix-to-wp-migrator' ),
					'error_generic'    => __( 'An error occurred during request execution.', 'wix-to-wp-migrator' ),
					'copy_success'     => __( 'Copied to clipboard!', 'wix-to-wp-migrator' ),
				),
			)
		);
	}

	/**
	 * Renders the main administration layout and views.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized: You do not have permissions to access this page.', 'wix-to-wp-migrator' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'migration';
		$valid_tabs = array( 'migration', 'rollback', 'health', 'redirects', 'logs' );
		if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
			$active_tab = 'migration';
		}

		$view_file = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/views/main-page.php';
		if ( file_exists( $view_file ) ) {
			include $view_file;
		} else {
			echo '<div class="wrap"><h1>' . esc_html__( 'Wix to WordPress Post Migrator', 'wix-to-wp-migrator' ) . '</h1><p>' . esc_html__( 'Admin view file missing.', 'wix-to-wp-migrator' ) . '</p></div>';
		}
	}
}
