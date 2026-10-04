<?php
/**
 * Fired when the Wix to WordPress Post Migrator plugin is uninstalled.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 1. Delete all registered plugin options.
delete_option( 'w2w_version' );
delete_option( 'w2w_installed_at' );
delete_option( 'w2w_migration_batches' );
delete_option( 'w2w_settings' );

// 2. Clean up transient cache entries created during preview sessions.
global $wpdb;

if ( isset( $wpdb->options ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_w2w_session_%' OR option_name LIKE '_transient_timeout_w2w_session_%'" );
}

// 3. Clean up log files in wp-content/uploads/w2w-logs or plugin directory if writable.
$upload_dir = wp_upload_dir();
$log_dirs   = array(
	trailingslashit( $upload_dir['basedir'] ) . 'w2w-logs',
	dirname( __FILE__ ) . '/w2w-logs',
);

foreach ( $log_dirs as $log_dir ) {
	if ( is_dir( $log_dir ) ) {
		$files = glob( trailingslashit( $log_dir ) . '*' );
		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFileSystemMethod.unlink
					@unlink( $file );
				}
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFileSystemMethod.rmdir
		@rmdir( $log_dir );
	}
}
