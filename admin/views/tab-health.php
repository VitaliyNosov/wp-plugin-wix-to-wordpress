<?php
/**
 * Tab View: System Health Check.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$env_checker   = new W2W_Environment_Check();
$checks        = $env_checker->check_all();
$is_compatible = $env_checker->is_compatible();
?>
<div class="w2w-section w2w-health-tab">
	<div class="w2w-card">
		<div class="w2w-card-header-flex">
			<div>
				<h2 class="w2w-card-title">
					<span class="dashicons dashicons-heart"></span>
					<?php esc_html_e( 'System Health & Environment Diagnostics', 'wix-to-wp-migrator' ); ?>
				</h2>
				<p class="description">
					<?php esc_html_e( 'Pre-flight checklist to verify your server is fully prepared for high-performance content ingestion.', 'wix-to-wp-migrator' ); ?>
				</p>
			</div>
			<div>
				<?php if ( $is_compatible ) : ?>
					<span class="w2w-badge w2w-badge-success">
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Server Ready for Migration', 'wix-to-wp-migrator' ); ?>
					</span>
				<?php else : ?>
					<span class="w2w-badge w2w-badge-danger">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'Requirements Not Met', 'wix-to-wp-migrator' ); ?>
					</span>
				<?php endif; ?>
			</div>
		</div>

		<table class="wp-list-table widefat striped" style="margin-top: 15px;">
			<thead>
				<tr>
					<th scope="col" style="width: 25%;"><?php esc_html_e( 'Component / Check', 'wix-to-wp-migrator' ); ?></th>
					<th scope="col" style="width: 20%;"><?php esc_html_e( 'Status', 'wix-to-wp-migrator' ); ?></th>
					<th scope="col" style="width: 20%;"><?php esc_html_e( 'Current Value', 'wix-to-wp-migrator' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Details & Recommendations', 'wix-to-wp-migrator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $checks as $key => $check ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $check['name'] ); ?></strong></td>
						<td>
							<?php if ( ! empty( $check['passed'] ) ) : ?>
								<span class="w2w-status-pill w2w-status-passed">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Passed', 'wix-to-wp-migrator' ); ?>
								</span>
							<?php elseif ( ! empty( $check['critical'] ) ) : ?>
								<span class="w2w-status-pill w2w-status-failed">
									<span class="dashicons dashicons-dismiss"></span>
									<?php esc_html_e( 'Critical Failure', 'wix-to-wp-migrator' ); ?>
								</span>
							<?php else : ?>
								<span class="w2w-status-pill w2w-status-warning">
									<span class="dashicons dashicons-warning"></span>
									<?php esc_html_e( 'Warning', 'wix-to-wp-migrator' ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( $check['current'] ?? 'N/A' ); ?></code></td>
						<td><?php echo esc_html( $check['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
