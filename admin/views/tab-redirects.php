<?php
/**
 * Tab View: 301 Redirects Export.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<div class="w2w-section w2w-redirects-tab">
	<div class="w2w-card">
		<h2 class="w2w-card-title">
			<span class="dashicons dashicons-randomize"></span>
			<?php esc_html_e( 'SEO 301 Redirects Generator', 'wix-to-wp-migrator' ); ?>
		</h2>
		<p class="description">
			<?php esc_html_e( 'Preserve your existing search engine rankings and backlink equity by redirecting historical Wix URLs to their newly imported WordPress permalinks.', 'wix-to-wp-migrator' ); ?>
		</p>

		<p class="submit">
			<button type="button" id="w2w-btn-generate-redirects" class="button button-primary">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Scan & Generate 301 Rules', 'wix-to-wp-migrator' ); ?>
			</button>
			<span class="spinner" id="w2w-redirects-spinner"></span>
		</p>

		<div id="w2w-redirects-output-container" style="display: none; margin-top: 25px;">
			<div class="notice notice-info inline">
				<p>
					<strong><?php esc_html_e( 'Found', 'wix-to-wp-migrator' ); ?></strong>
					<span id="w2w-redirects-count">0</span>
					<?php esc_html_e( 'migrated posts with original Wix URLs mapped.', 'wix-to-wp-migrator' ); ?>
				</p>
			</div>

			<!-- CSV Export Option -->
			<div class="w2w-code-block-section" style="margin-top: 20px;">
				<div class="w2w-card-header-flex">
					<h3><?php esc_html_e( '1. CSV Format (For "Redirection" or "Rank Math" plugins)', 'wix-to-wp-migrator' ); ?></h3>
					<button type="button" class="button button-secondary" id="w2w-btn-download-csv">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Download CSV', 'wix-to-wp-migrator' ); ?>
					</button>
				</div>
				<textarea id="w2w-redirects-csv" class="large-text code" rows="6" readonly="readonly"></textarea>
			</div>

			<!-- Apache .htaccess Option -->
			<div class="w2w-code-block-section" style="margin-top: 20px;">
				<div class="w2w-card-header-flex">
					<h3><?php esc_html_e( '2. Apache (.htaccess) Directives', 'wix-to-wp-migrator' ); ?></h3>
					<button type="button" class="button button-small w2w-btn-copy" data-target="#w2w-redirects-htaccess">
						<span class="dashicons dashicons-clipboard"></span>
						<?php esc_html_e( 'Copy to Clipboard', 'wix-to-wp-migrator' ); ?>
					</button>
				</div>
				<textarea id="w2w-redirects-htaccess" class="large-text code" rows="6" readonly="readonly"></textarea>
			</div>

			<!-- Nginx Option -->
			<div class="w2w-code-block-section" style="margin-top: 20px;">
				<div class="w2w-card-header-flex">
					<h3><?php esc_html_e( '3. Nginx Server Block Rewrite Directives', 'wix-to-wp-migrator' ); ?></h3>
					<button type="button" class="button button-small w2w-btn-copy" data-target="#w2w-redirects-nginx">
						<span class="dashicons dashicons-clipboard"></span>
						<?php esc_html_e( 'Copy to Clipboard', 'wix-to-wp-migrator' ); ?>
					</button>
				</div>
				<textarea id="w2w-redirects-nginx" class="large-text code" rows="6" readonly="readonly"></textarea>
			</div>
		</div>
	</div>
</div>
