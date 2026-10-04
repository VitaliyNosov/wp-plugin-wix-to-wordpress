<?php
/**
 * Tab View: Migration Logs.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$logger  = new W2W_Logger();
$entries = $logger->get_recent_entries( 100 );
?>
<div class="w2w-section w2w-logs-tab">
	<div class="w2w-card">
		<div class="w2w-card-header-flex">
			<div>
				<h2 class="w2w-card-title">
					<span class="dashicons dashicons-media-text"></span>
					<?php esc_html_e( 'System & Migration Logs', 'wix-to-wp-migrator' ); ?>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Real-time structured logs recording all content processing, media downloads, deduplications, and database queries.', 'wix-to-wp-migrator' ); ?>
				</p>
			</div>
			<div>
				<button type="button" class="button button-secondary" id="w2w-btn-refresh-logs">
					<span class="dashicons dashicons-image-rotate"></span>
					<?php esc_html_e( 'Refresh', 'wix-to-wp-migrator' ); ?>
				</button>
				<button type="button" class="button button-link-delete" id="w2w-btn-clear-logs" style="margin-left: 10px;">
					<span class="dashicons dashicons-trash"></span>
					<?php esc_html_e( 'Clear Log File', 'wix-to-wp-migrator' ); ?>
				</button>
			</div>
		</div>

		<div class="w2w-terminal" id="w2w-terminal">
			<?php if ( empty( $entries ) ) : ?>
				<div class="w2w-terminal-empty"><?php esc_html_e( 'No log entries recorded yet.', 'wix-to-wp-migrator' ); ?></div>
			<?php else : ?>
				<?php foreach ( $entries as $entry ) : ?>
					<div class="w2w-terminal-line">
						<?php echo esc_html( $entry['raw'] ?? ( ( $entry['timestamp'] ?? '' ) . ' [' . ( $entry['level'] ?? '' ) . '] ' . ( $entry['message'] ?? '' ) ) ); ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
</div>
