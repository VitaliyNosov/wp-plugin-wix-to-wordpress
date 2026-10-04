<?php
/**
 * Tab View: Batch Rollback (Undo).
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$rollback_manager = new W2W_Rollback_Manager();
$recent_batches   = $rollback_manager->get_recent_batches( 8 );
?>
<div class="w2w-section w2w-rollback-tab">
	<div class="w2w-card">
		<h2 class="w2w-card-title">
			<span class="dashicons dashicons-undo"></span>
			<?php esc_html_e( '1-Click Batch Rollback (Undo)', 'wix-to-wp-migrator' ); ?>
		</h2>
		<p class="description">
			<?php esc_html_e( 'Every migration run is tagged with a unique Batch UUID. You can cleanly and completely purge any test migration without touching the rest of your WordPress site.', 'wix-to-wp-migrator' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="w2w_rollback_batch_id"><?php esc_html_e( 'Batch UUID', 'wix-to-wp-migrator' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input name="w2w_rollback_batch_id" type="text" id="w2w_rollback_batch_id" class="regular-text code"
							   placeholder="e.g. 550e8400-e29b-41d4-a716-446655440000" style="width: 100%; max-width: 500px;" />
						<p class="description">
							<?php esc_html_e( 'Paste the Batch UUID or select from the recent migrations list below.', 'wix-to-wp-migrator' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="button" id="w2w-btn-check-rollback" class="button button-secondary">
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e( 'Inspect Batch Contents', 'wix-to-wp-migrator' ); ?>
			</button>
			<button type="button" id="w2w-btn-execute-rollback" class="button button-link-delete" style="margin-left: 15px; display: none;">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Permanently Purge This Batch', 'wix-to-wp-migrator' ); ?>
			</button>
			<span class="spinner" id="w2w-rollback-spinner"></span>
		</p>

		<!-- Rollback Inspection Report Box -->
		<div id="w2w-rollback-impact-box" class="notice notice-warning inline" style="display: none; margin-top: 20px;">
			<p>
				<strong><?php esc_html_e( 'Batch Contents Found:', 'wix-to-wp-migrator' ); ?></strong>
				<span id="w2w-rollback-posts-count">0</span> <?php esc_html_e( 'posts and', 'wix-to-wp-migrator' ); ?>
				<span id="w2w-rollback-media-count">0</span> <?php esc_html_e( 'media attachments will be deleted.', 'wix-to-wp-migrator' ); ?>
			</p>
		</div>
	</div>

	<?php if ( ! empty( $recent_batches ) ) : ?>
		<div class="w2w-card" style="margin-top: 20px;">
			<h3 class="w2w-card-title">
				<span class="dashicons dashicons-backup"></span>
				<?php esc_html_e( 'Recent Migration Batches', 'wix-to-wp-migrator' ); ?>
			</h3>
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Batch UUID', 'wix-to-wp-migrator' ); ?></th>
						<th><?php esc_html_e( 'Associated Items', 'wix-to-wp-migrator' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'wix-to-wp-migrator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent_batches as $batch ) : ?>
						<tr>
							<td><code><?php echo esc_html( $batch['batch_id'] ); ?></code></td>
							<td><span class="w2w-badge w2w-badge-neutral"><?php echo esc_html( $batch['item_count'] ); ?> items</span></td>
							<td>
								<button type="button" class="button button-small w2w-btn-select-batch" data-batch="<?php echo esc_attr( $batch['batch_id'] ); ?>">
									<?php esc_html_e( 'Select', 'wix-to-wp-migrator' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
