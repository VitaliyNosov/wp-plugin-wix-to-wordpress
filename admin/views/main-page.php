<?php
/**
 * Main Administration Layout Template.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'migration';
$tabs       = array(
	'migration' => array(
		'title' => __( 'RSS Migration', 'wix-to-wp-migrator' ),
		'icon'  => 'dashicons-rss',
	),
	'rollback'  => array(
		'title' => __( 'Batch Rollback', 'wix-to-wp-migrator' ),
		'icon'  => 'dashicons-undo',
	),
	'health'    => array(
		'title' => __( 'System Health', 'wix-to-wp-migrator' ),
		'icon'  => 'dashicons-heart',
	),
	'redirects' => array(
		'title' => __( '301 Redirects', 'wix-to-wp-migrator' ),
		'icon'  => 'dashicons-randomize',
	),
	'logs'      => array(
		'title' => __( 'Logs', 'wix-to-wp-migrator' ),
		'icon'  => 'dashicons-media-text',
	),
);
?>
<div class="wrap w2w-admin-wrap">
	<header class="w2w-admin-header">
		<div class="w2w-header-brand">
			<span class="dashicons dashicons-cloud-saved w2w-brand-icon"></span>
			<div>
				<h1 class="wp-heading-inline"><?php esc_html_e( 'Wix to WordPress Post Migrator', 'wix-to-wp-migrator' ); ?></h1>
				<span class="w2w-version-badge">v<?php echo esc_html( defined( 'W2W_VERSION' ) ? W2W_VERSION : '1.0.0' ); ?></span>
			</div>
		</div>
		<p class="w2w-header-description">
			<?php esc_html_e( 'Enterprise-grade tool to seamlessly migrate Wix blog posts, media assets, categories, tags, and SEO metadata into WordPress.', 'wix-to-wp-migrator' ); ?>
		</p>
	</header>

	<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Plugin navigation tabs', 'wix-to-wp-migrator' ); ?>">
		<?php foreach ( $tabs as $tab_key => $tab_data ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wix-to-wp-migrator', 'tab' => $tab_key ), admin_url( 'tools.php' ) ) ); ?>"
			   class="nav-tab <?php echo ( $active_tab === $tab_key ) ? 'nav-tab-active' : ''; ?>">
				<span class="dashicons <?php echo esc_attr( $tab_data['icon'] ); ?>"></span>
				<?php echo esc_html( $tab_data['title'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="w2w-tab-content">
		<?php
		$tab_file = __DIR__ . '/tab-' . $active_tab . '.php';
		if ( file_exists( $tab_file ) ) {
			include $tab_file;
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Requested tab view not found.', 'wix-to-wp-migrator' ) . '</p></div>';
		}
		?>
	</div>
</div>
