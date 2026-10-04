<?php
/**
 * Tab View: RSS Migration.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$current_user_id = get_current_user_id();
?>
<div class="w2w-section w2w-migration-tab">
	<!-- 1. Source & Configuration Card -->
	<div class="w2w-card">
		<h2 class="w2w-card-title">
			<span class="dashicons dashicons-rss"></span>
			<?php esc_html_e( '1. Connect Wix RSS Feed', 'wix-to-wp-migrator' ); ?>
		</h2>
		<p class="description">
			<?php esc_html_e( 'Enter the public RSS feed URL of your Wix blog (usually located at https://yourdomain.com/blog-feed.xml or feed.xml).', 'wix-to-wp-migrator' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="w2w_source_url"><?php esc_html_e( 'Wix RSS Feed URL', 'wix-to-wp-migrator' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<input name="w2w_source_url" type="url" id="w2w_source_url" class="regular-text code"
							   placeholder="https://mysite.wixsite.com/blog/blog-feed.xml" style="width: 100%; max-width: 600px;" />
						<p class="description">
							<?php esc_html_e( 'Make sure the Wix blog has RSS enabled in Wix Blog Settings.', 'wix-to-wp-migrator' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="w2w_target_author"><?php esc_html_e( 'Target WordPress Author', 'wix-to-wp-migrator' ); ?></label>
					</th>
					<td>
						<?php
						if ( function_exists( 'wp_dropdown_users' ) ) {
							wp_dropdown_users(
								array(
									'name'     => 'w2w_target_author',
									'id'       => 'w2w_target_author',
									'selected' => $current_user_id,
									'role__in' => array( 'administrator', 'editor', 'author' ),
								)
							);
						} else {
							echo '<input type="number" id="w2w_target_author" value="' . esc_attr( $current_user_id ) . '" class="small-text" />';
						}
						?>
						<p class="description">
							<?php esc_html_e( 'All imported posts will be assigned to this user unless matched to an existing WordPress author.', 'wix-to-wp-migrator' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="w2w_default_category"><?php esc_html_e( 'Default Category', 'wix-to-wp-migrator' ); ?></label>
					</th>
					<td>
						<input name="w2w_default_category" type="text" id="w2w_default_category" class="regular-text"
							   placeholder="<?php esc_attr_e( 'Migrated from Wix', 'wix-to-wp-migrator' ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Optional fallback category if a Wix post has no categories assigned.', 'wix-to-wp-migrator' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Media Ingestion Options', 'wix-to-wp-migrator' ); ?></th>
					<td>
						<fieldset>
							<label for="w2w_import_images">
								<input name="w2w_import_images" type="checkbox" id="w2w_import_images" value="1" checked="checked" />
								<strong><?php esc_html_e( 'Download and host images locally in WordPress Media Library', 'wix-to-wp-migrator' ); ?></strong>
							</label>
							<p class="description">
								<?php esc_html_e( 'Automatically extracts full-resolution Wix images, deduplicates by MD5 hash, and replaces content URLs.', 'wix-to-wp-migrator' ); ?>
							</p>
						</fieldset>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="button" id="w2w-btn-preview" class="button button-primary button-large">
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'Fetch & Preview Posts', 'wix-to-wp-migrator' ); ?>
			</button>
			<span class="spinner" id="w2w-preview-spinner"></span>
		</p>
	</div>

	<!-- 2. Preview Section (Initially hidden) -->
	<div class="w2w-card w2w-preview-section" id="w2w-preview-section" style="display: none;">
		<div class="w2w-card-header-flex">
			<div>
				<h2 class="w2w-card-title">
					<span class="dashicons dashicons-list-view"></span>
					<?php esc_html_e( '2. Select Posts to Migrate', 'wix-to-wp-migrator' ); ?>
				</h2>
				<span class="w2w-badge w2w-badge-info" id="w2w-posts-count-badge">0 posts found</span>
			</div>
			<div class="w2w-table-actions">
				<button type="button" class="button" id="w2w-select-all"><?php esc_html_e( 'Select All', 'wix-to-wp-migrator' ); ?></button>
				<button type="button" class="button" id="w2w-deselect-all"><?php esc_html_e( 'Deselect All', 'wix-to-wp-migrator' ); ?></button>
			</div>
		</div>

		<div class="w2w-table-scroll-container">
			<table class="wp-list-table widefat striped w2w-preview-table" id="w2w-preview-table">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column">
							<input type="checkbox" id="w2w-cb-select-all" checked="checked" />
						</td>
						<th scope="col" class="column-thumb" style="width: 70px;"><?php esc_html_e( 'Image', 'wix-to-wp-migrator' ); ?></th>
						<th scope="col" class="column-title"><?php esc_html_e( 'Title & Slug', 'wix-to-wp-migrator' ); ?></th>
						<th scope="col" class="column-categories"><?php esc_html_e( 'Categories', 'wix-to-wp-migrator' ); ?></th>
						<th scope="col" class="column-author"><?php esc_html_e( 'Author', 'wix-to-wp-migrator' ); ?></th>
						<th scope="col" class="column-date"><?php esc_html_e( 'Date', 'wix-to-wp-migrator' ); ?></th>
					</tr>
				</thead>
				<tbody id="w2w-preview-tbody">
					<!-- Dynamically injected rows -->
				</tbody>
			</table>
		</div>

		<div class="w2w-migration-controls">
			<div class="w2w-batch-options">
				<label for="w2w-chunk-size">
					<strong><?php esc_html_e( 'Batch Chunk Size:', 'wix-to-wp-migrator' ); ?></strong>
				</label>
				<select id="w2w-chunk-size">
					<option value="2">2 posts / request (Recommended for shared hosting)</option>
					<option value="3" selected="selected">3 posts / request (Standard)</option>
					<option value="5">5 posts / request (High performance VPS)</option>
				</select>
			</div>

			<button type="button" id="w2w-btn-start-migration" class="button button-primary button-hero">
				<span class="dashicons dashicons-migrate"></span>
				<?php esc_html_e( 'Start Migration Process', 'wix-to-wp-migrator' ); ?>
			</button>
		</div>
	</div>

	<!-- 3. Real-Time Migration Runner & Progress (Initially hidden) -->
	<div class="w2w-card w2w-runner-section" id="w2w-runner-section" style="display: none;">
		<h2 class="w2w-card-title">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( '3. Migration Progress', 'wix-to-wp-migrator' ); ?>
		</h2>

		<div class="w2w-progress-container">
			<div class="w2w-progress-bar" id="w2w-progress-bar" style="width: 0%;">
				<span class="w2w-progress-text" id="w2w-progress-percentage">0%</span>
			</div>
		</div>

		<div class="w2w-stats-grid">
			<div class="w2w-stat-box">
				<span class="w2w-stat-number" id="w2w-stat-total">0</span>
				<span class="w2w-stat-label"><?php esc_html_e( 'Total Selected', 'wix-to-wp-migrator' ); ?></span>
			</div>
			<div class="w2w-stat-box w2w-stat-success">
				<span class="w2w-stat-number" id="w2w-stat-imported">0</span>
				<span class="w2w-stat-label"><?php esc_html_e( 'Successfully Imported', 'wix-to-wp-migrator' ); ?></span>
			</div>
			<div class="w2w-stat-box w2w-stat-failed">
				<span class="w2w-stat-number" id="w2w-stat-failed">0</span>
				<span class="w2w-stat-label"><?php esc_html_e( 'Errors / Skipped', 'wix-to-wp-migrator' ); ?></span>
			</div>
			<div class="w2w-stat-box">
				<span class="w2w-stat-number" id="w2w-stat-media">0</span>
				<span class="w2w-stat-label"><?php esc_html_e( 'Media Assets Saved', 'wix-to-wp-migrator' ); ?></span>
			</div>
		</div>

		<div class="w2w-runner-actions">
			<button type="button" class="button" id="w2w-btn-pause"><?php esc_html_e( 'Pause', 'wix-to-wp-migrator' ); ?></button>
			<button type="button" class="button button-link-delete" id="w2w-btn-cancel"><?php esc_html_e( 'Cancel', 'wix-to-wp-migrator' ); ?></button>
		</div>

		<div class="w2w-activity-log">
			<h3><?php esc_html_e( 'Activity Stream', 'wix-to-wp-migrator' ); ?></h3>
			<div class="w2w-log-stream" id="w2w-log-stream">
				<div class="w2w-log-line w2w-log-info"><?php esc_html_e( 'Awaiting migration start...', 'wix-to-wp-migrator' ); ?></div>
			</div>
		</div>
	</div>
</div>
