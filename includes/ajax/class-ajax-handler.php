<?php
/**
 * AJAX Controller for Asynchronous Migration Tasks.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles all admin AJAX requests for preview, batch importing, rollback, and diagnostics.
 */
class W2W_Ajax_Handler {

	/**
	 * Source Manager instance.
	 *
	 * @var W2W_Source_Manager
	 */
	private W2W_Source_Manager $source_manager;

	/**
	 * Migration Coordinator instance.
	 *
	 * @var W2W_Migration_Coordinator
	 */
	private W2W_Migration_Coordinator $coordinator;

	/**
	 * Rollback Manager instance.
	 *
	 * @var W2W_Rollback_Manager
	 */
	private W2W_Rollback_Manager $rollback_manager;

	/**
	 * Logger instance.
	 *
	 * @var W2W_Logger
	 */
	private W2W_Logger $logger;

	/**
	 * Constructor. Registers AJAX action hooks.
	 *
	 * @param W2W_Source_Manager|null        $source_manager   Source Manager.
	 * @param W2W_Migration_Coordinator|null $coordinator      Coordinator.
	 * @param W2W_Rollback_Manager|null      $rollback_manager Rollback Manager.
	 * @param W2W_Logger|null                $logger           Logger.
	 */
	public function __construct(
		?W2W_Source_Manager $source_manager = null,
		?W2W_Migration_Coordinator $coordinator = null,
		?W2W_Rollback_Manager $rollback_manager = null,
		?W2W_Logger $logger = null
	) {
		$this->source_manager   = $source_manager ?? new W2W_Source_Manager();
		$this->coordinator      = $coordinator ?? new W2W_Migration_Coordinator();
		$this->rollback_manager = $rollback_manager ?? new W2W_Rollback_Manager();
		$this->logger           = $logger ?? new W2W_Logger();

		$this->register_hooks();
	}

	/**
	 * Registers WordPress AJAX action hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		$actions = array(
			'w2w_preview_feed'     => 'ajax_preview_feed',
			'w2w_import_chunk'     => 'ajax_import_chunk',
			'w2w_rollback_batch'   => 'ajax_rollback_batch',
			'w2w_check_rollback'   => 'ajax_check_rollback',
			'w2w_export_redirects' => 'ajax_export_redirects',
			'w2w_get_logs'         => 'ajax_get_logs',
			'w2w_clear_logs'       => 'ajax_clear_logs',
		);

		foreach ( $actions as $action => $method ) {
			add_action( 'wp_ajax_' . $action, array( $this, $method ) );
		}
	}

	/**
	 * Verifies security nonce and admin capabilities.
	 *
	 * @return void
	 */
	public function verify_request(): void {
		if ( ! check_ajax_referer( 'w2w_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'wix-to-wp-migrator' ) ),
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized: You do not have sufficient permissions to manage migrations.', 'wix-to-wp-migrator' ) ),
				403
			);
		}
	}

	/**
	 * AJAX endpoint: Previews posts from a Wix RSS feed without saving to database.
	 *
	 * @return void
	 */
	public function ajax_preview_feed(): void {
		$this->verify_request();

		$source_type = sanitize_text_field( $_POST['source_type'] ?? 'rss' );
		$source_url  = isset( $_POST['source_url'] ) ? sanitize_text_field( wp_unslash( $_POST['source_url'] ) ) : '';
		$raw_xml     = isset( $_POST['raw_xml'] ) ? wp_unslash( $_POST['raw_xml'] ) : '';

		$input = ! empty( $source_url ) ? $source_url : $raw_xml;

		if ( empty( $input ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a valid Wix post URL, sitemap XML, or RSS feed.', 'wix-to-wp-migrator' ) ), 400 );
		}

		// Automatically detect source type (single_post, sitemap, or rss) if not explicitly set or set to rss.
		if ( empty( $source_type ) || 'auto' === $source_type || 'rss' === $source_type ) {
			$source_type = W2W_Source_Manager::detect_source_type( $input );
		}

		// Auto-normalize protocol if domain was supplied without http(s)://.
		if ( ! preg_match( '~^https?://~i', $input ) && 0 !== strpos( $input, '<' ) && preg_match( '~^[a-z0-9\-]+(\.[a-z0-9\-]+)+[/\\?#]?~i', $input ) ) {
			$input = 'https://' . $input;
		}

		$adapter = $this->source_manager->get_adapter( $source_type );
		if ( ! $adapter ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'Source adapter "%s" is not registered.', 'wix-to-wp-migrator' ), esc_html( $source_type ) ) ), 400 );
		}

		try {
			$this->logger->info( 'Fetching feed for preview', array( 'source_type' => $source_type, 'url' => $input ) );
			$posts = $adapter->fetch_posts( $input );

			if ( empty( $posts ) ) {
				throw new \RuntimeException( __( 'No blog posts were found in the provided Wix source.', 'wix-to-wp-migrator' ) );
			}

			// Generate a unique session token for this preview.
			$session_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'w2w_sess_', true );

			// Store serialized DTOs in a transient cache (expires in 2 hours).
			$serialized_items = array_map(
				function( W2W_Post_DTO $dto ) {
					return $dto->to_array();
				},
				$posts
			);
			set_transient( 'w2w_session_' . $session_id, $serialized_items, 2 * HOUR_IN_SECONDS );

			// Prepare lightweight summary for admin UI preview table.
			$preview_items = array();
			foreach ( $posts as $index => $dto ) {
				$preview_items[] = array(
					'index'              => $index,
					'original_id'        => $dto->original_id,
					'title'              => $dto->title,
					'slug'               => $dto->slug,
					'original_url'       => $dto->original_url,
					'featured_image_url' => $dto->featured_image_url,
					'categories'         => $dto->categories,
					'author_name'        => $dto->author_name ?: __( 'Author from Wix', 'wix-to-wp-migrator' ),
					'date_published'     => $dto->date_published,
					'has_content'        => ! empty( $dto->content ),
				);
			}

			$this->logger->info( 'Preview generated successfully', array( 'posts_found' => count( $posts ), 'source_type' => $source_type ) );
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Preview failed: ' . $e->getMessage(), array( 'url' => $input, 'source_type' => $source_type ) );
			wp_send_json_error( array( 'message' => $e->getMessage() ), 500 );
			return;
		}

		wp_send_json_success(
			array(
				'session_id'  => $session_id,
				'source_type' => $source_type,
				'total'       => count( $preview_items ),
				'posts'       => $preview_items,
			)
		);
	}

	/**
	 * AJAX endpoint: Imports a small batch (chunk) of posts asynchronously.
	 *
	 * @return void
	 */
	public function ajax_import_chunk(): void {
		$this->verify_request();

		$batch_id   = sanitize_text_field( $_POST['batch_id'] ?? '' );
		$session_id = sanitize_text_field( $_POST['session_id'] ?? '' );
		$author_id  = isset( $_POST['author_id'] ) ? (int) $_POST['author_id'] : get_current_user_id();
		$default_cat = sanitize_text_field( $_POST['default_category'] ?? '' );
		$import_img  = ! isset( $_POST['import_images'] ) || 'false' !== $_POST['import_images'];

		if ( empty( $batch_id ) ) {
			$batch_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'w2w_batch_', true );
		}

		$dtos = array();

		// 1. Retrieve DTOs via session transient if session_id and indices provided.
		if ( ! empty( $session_id ) && isset( $_POST['indices'] ) ) {
			$raw_indices = $_POST['indices'];
			if ( is_string( $raw_indices ) ) {
				$decoded = json_decode( wp_unslash( $raw_indices ), true );
				if ( is_array( $decoded ) ) {
					$raw_indices = $decoded;
				} else {
					$clean_str   = trim( (string) wp_unslash( $raw_indices ), "[] \t\n\r\0\x0B" );
					$raw_indices = '' !== $clean_str ? explode( ',', $clean_str ) : array();
				}
			}

			$indices = array();
			foreach ( (array) $raw_indices as $idx ) {
				$val = trim( (string) $idx );
				if ( '' !== $val && is_numeric( $val ) ) {
					$indices[] = (int) $val;
				}
			}

			$cached = get_transient( 'w2w_session_' . $session_id );
			if ( is_array( $cached ) ) {
				foreach ( $indices as $idx ) {
					if ( isset( $cached[ $idx ] ) && is_array( $cached[ $idx ] ) ) {
						$dtos[] = W2W_Post_DTO::from_array( $cached[ $idx ] );
					}
				}
			}
		}

		// 2. Fallback: Parse direct JSON items if sent in payload.
		if ( empty( $dtos ) && isset( $_POST['items'] ) ) {
			$raw_items = is_array( $_POST['items'] ) ? $_POST['items'] : json_decode( wp_unslash( $_POST['items'] ), true );
			if ( is_array( $raw_items ) ) {
				foreach ( $raw_items as $item_data ) {
					if ( is_array( $item_data ) ) {
						$dtos[] = W2W_Post_DTO::from_array( $item_data );
					}
				}
			}
		}

		if ( empty( $dtos ) ) {
			wp_send_json_error( array( 'message' => __( 'No items found to process in this chunk.', 'wix-to-wp-migrator' ) ), 400 );
		}

		$options = array(
			'author_id'        => $author_id,
			'default_category' => $default_cat,
			'import_images'    => $import_img,
		);

		$results = array();
		foreach ( $dtos as $dto ) {
			// If post has no content (e.g. previewed from sitemap), lazily fetch full content via scraper.
			if ( empty( $dto->content ) && ! empty( $dto->original_url ) ) {
				try {
					$scraper = $this->source_manager->get_adapter( 'single_post' );
					if ( $scraper ) {
						$scraped_list = $scraper->fetch_posts( $dto->original_url );
						if ( ! empty( $scraped_list[0] ) ) {
							$scraped_dto = $scraped_list[0];
							$dto->content = $scraped_dto->content;
							if ( ! empty( $scraped_dto->title ) ) {
								$dto->title = $scraped_dto->title;
							}
							if ( empty( $dto->featured_image_url ) ) {
								$dto->featured_image_url = $scraped_dto->featured_image_url;
							}
							if ( ! empty( $scraped_dto->author_name ) ) {
								$dto->author_name = $scraped_dto->author_name;
							}
							if ( ! empty( $scraped_dto->seo_meta ) ) {
								$dto->seo_meta = $scraped_dto->seo_meta;
							}
							if ( ! empty( $scraped_dto->categories ) ) {
								$dto->categories = $scraped_dto->categories;
							}
							if ( ! empty( $scraped_dto->tags ) ) {
								$dto->tags = $scraped_dto->tags;
							}
						}
					}
				} catch ( \Throwable $e ) {
					$this->logger->warning( 'Lazy scrape failed for post: ' . $dto->original_url, array( 'error' => $e->getMessage() ) );
				}
			}

			// Apply fallback default category if post has no categories.
			if ( empty( $dto->categories ) && ! empty( $default_cat ) ) {
				$dto->categories = array( $default_cat );
			}

			$result    = $this->coordinator->process_single_post( $dto, $batch_id, $options );
			$results[] = $result;
		}

		wp_send_json_success(
			array(
				'batch_id'    => $batch_id,
				'chunk_count' => count( $results ),
				'results'     => $results,
			)
		);
	}

	/**
	 * AJAX endpoint: Checks batch rollback statistics (count of posts and attachments).
	 *
	 * @return void
	 */
	public function ajax_check_rollback(): void {
		$this->verify_request();

		$batch_id = sanitize_text_field( $_POST['batch_id'] ?? '' );
		if ( empty( $batch_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Batch ID is required.', 'wix-to-wp-migrator' ) ), 400 );
		}

		$counts = $this->rollback_manager->count_batch_items( $batch_id );
		wp_send_json_success( $counts );
	}

	/**
	 * AJAX endpoint: Executes 1-click batch rollback.
	 *
	 * @return void
	 */
	public function ajax_rollback_batch(): void {
		$this->verify_request();

		$batch_id = sanitize_text_field( $_POST['batch_id'] ?? '' );
		if ( empty( $batch_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Batch ID is required for rollback.', 'wix-to-wp-migrator' ) ), 400 );
		}

		$result = $this->rollback_manager->rollback_batch( $batch_id );
		$this->logger->info( 'Batch rollback executed', $result );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result, 400 );
		}
	}

	/**
	 * AJAX endpoint: Generates 301 redirect map for imported posts.
	 *
	 * @return void
	 */
	public function ajax_export_redirects(): void {
		$this->verify_request();

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'meta_key'       => '_w2w_original_url',
				'posts_per_page' => -1,
			)
		);

		$csv_lines      = array( '"source_url","target_url","match_type"' );
		$htaccess_lines = array( '# Wix to WordPress 301 Redirect Rules' );
		$nginx_lines    = array( '# Wix to WordPress Nginx Rewrite Rules' );
		$count          = 0;

		foreach ( $posts as $post ) {
			$orig_url = (string) get_post_meta( $post->ID, '_w2w_original_url', true );
			if ( empty( $orig_url ) ) {
				continue;
			}

			$target_url = get_permalink( $post->ID );
			$count++;

			// CSV line.
			$csv_lines[] = sprintf( '"%s","%s","301"', esc_url_raw( $orig_url ), esc_url_raw( $target_url ) );

			// Extract relative path from original Wix URL for .htaccess and Nginx.
			$orig_path = wp_parse_url( $orig_url, PHP_URL_PATH );
			if ( ! empty( $orig_path ) ) {
				$htaccess_lines[] = sprintf( 'Redirect 301 %s %s', $orig_path, esc_url_raw( $target_url ) );
				$nginx_lines[]    = sprintf( 'rewrite ^%s$ %s permanent;', preg_quote( $orig_path, '/' ), esc_url_raw( $target_url ) );
			}
		}

		wp_send_json_success(
			array(
				'count'    => $count,
				'csv'      => implode( "\n", $csv_lines ),
				'htaccess' => implode( "\n", $htaccess_lines ),
				'nginx'    => implode( "\n", $nginx_lines ),
			)
		);
	}

	/**
	 * AJAX endpoint: Returns recent migration logs.
	 *
	 * @return void
	 */
	public function ajax_get_logs(): void {
		$this->verify_request();

		$limit   = isset( $_POST['limit'] ) ? max( 10, min( 200, (int) $_POST['limit'] ) ) : 50;
		$entries = $this->logger->get_recent_entries( $limit );

		wp_send_json_success( array( 'entries' => $entries ) );
	}

	/**
	 * AJAX endpoint: Clears migration logs.
	 *
	 * @return void
	 */
	public function ajax_clear_logs(): void {
		$this->verify_request();

		$this->logger->clear_log();
		wp_send_json_success( array( 'message' => __( 'Logs have been cleared.', 'wix-to-wp-migrator' ) ) );
	}
}
