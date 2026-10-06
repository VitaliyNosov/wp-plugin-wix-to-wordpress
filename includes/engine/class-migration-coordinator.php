<?php
/**
 * Migration Pipeline Coordinator.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Orchestrates the full migration pipeline: content cleaning, media downloading,
 * post persistence, taxonomy assignment, and SEO metadata synchronization.
 */
class W2W_Migration_Coordinator {

	/**
	 * Content processor.
	 *
	 * @var W2W_Content_Processor
	 */
	private W2W_Content_Processor $content_processor;

	/**
	 * Media downloader.
	 *
	 * @var W2W_Media_Downloader
	 */
	private W2W_Media_Downloader $media_downloader;

	/**
	 * Taxonomy manager.
	 *
	 * @var W2W_Taxonomy_Manager
	 */
	private W2W_Taxonomy_Manager $taxonomy_manager;

	/**
	 * Post writer.
	 *
	 * @var W2W_Post_Writer
	 */
	private W2W_Post_Writer $post_writer;

	/**
	 * SEO handler.
	 *
	 * @var W2W_SEO_Handler
	 */
	private W2W_SEO_Handler $seo_handler;

	/**
	 * Logger.
	 *
	 * @var W2W_Logger
	 */
	private W2W_Logger $logger;

	/**
	 * Constructor with dependency injection.
	 *
	 * @param W2W_Content_Processor|null $content_processor Content processor.
	 * @param W2W_Media_Downloader|null  $media_downloader  Media downloader.
	 * @param W2W_Taxonomy_Manager|null  $taxonomy_manager  Taxonomy manager.
	 * @param W2W_Post_Writer|null       $post_writer       Post writer.
	 * @param W2W_SEO_Handler|null       $seo_handler       SEO handler.
	 * @param W2W_Logger|null            $logger            Logger.
	 */
	public function __construct(
		?W2W_Content_Processor $content_processor = null,
		?W2W_Media_Downloader $media_downloader = null,
		?W2W_Taxonomy_Manager $taxonomy_manager = null,
		?W2W_Post_Writer $post_writer = null,
		?W2W_SEO_Handler $seo_handler = null,
		?W2W_Logger $logger = null
	) {
		$this->content_processor = $content_processor ?: new W2W_Content_Processor();
		$this->media_downloader  = $media_downloader ?: new W2W_Media_Downloader( $this->content_processor );
		$this->taxonomy_manager  = $taxonomy_manager ?: new W2W_Taxonomy_Manager();
		$this->post_writer       = $post_writer ?: new W2W_Post_Writer();
		$this->seo_handler       = $seo_handler ?: new W2W_SEO_Handler();
		$this->logger            = $logger ?: new W2W_Logger();
	}

	/**
	 * Convenience method to process a single post with options array.
	 *
	 * @param W2W_Post_DTO         $dto      Input post DTO.
	 * @param string|null          $batch_id Batch UUID.
	 * @param array<string, mixed> $options  Options array (author_id, default_category, import_images).
	 * @return array{success: bool, post_id: int|null, thumbnail_id: int|null, error: string|null}
	 */
	public function process_single_post( W2W_Post_DTO $dto, ?string $batch_id = null, array $options = array() ): array {
		$author_id = isset( $options['author_id'] ) ? (int) $options['author_id'] : 1;

		if ( empty( $dto->categories ) && ! empty( $options['default_category'] ) ) {
			$dto->categories = array( (string) $options['default_category'] );
		}

		return $this->process_post( $dto, $author_id, $batch_id );
	}

	/**
	 * Processes a single post DTO through the entire migration pipeline.
	 *
	 * @param W2W_Post_DTO $dto       Input post DTO.
	 * @param int          $author_id Assigned WordPress author ID.
	 * @param string|null  $batch_id  Batch UUID for tracking and rollback.
	 * @return array{success: bool, post_id: int|null, thumbnail_id: int|null, error: string|null} Execution summary.
	 */
	public function process_post( W2W_Post_DTO $dto, int $author_id = 1, ?string $batch_id = null ): array {
		if ( ! $dto->validate() ) {
			$this->logger->error( 'Invalid post DTO encountered', array( 'dto' => $dto->to_array() ) );
			return array(
				'success'      => false,
				'post_id'      => null,
				'thumbnail_id' => null,
				'error'        => 'Invalid post DTO: title or ID missing',
			);
		}

		$this->logger->info( "Starting migration pipeline for post: {$dto->title} ({$dto->original_id})" );

		// 1. Clean HTML Content.
		$cleaned_html = $this->content_processor->clean_html( $dto->content );

		// 2. Extract and Download Images in Content.
		$image_urls  = $this->content_processor->extract_image_urls( $cleaned_html );
		$url_mapping = array();
		$first_image_attachment_id = null;

		foreach ( $image_urls as $img_url ) {
			$attachment_id = $this->media_downloader->download_and_attach( $img_url, 0, '', $batch_id );
			if ( $attachment_id ) {
				if ( null === $first_image_attachment_id ) {
					$first_image_attachment_id = $attachment_id;
				}
				$local_url = function_exists( 'wp_get_attachment_url' )
					? wp_get_attachment_url( $attachment_id )
					: "http://example.org/wp-content/uploads/imported-{$attachment_id}.jpg";

				if ( $local_url ) {
					$url_mapping[ $img_url ] = array(
						'url'           => $local_url,
						'attachment_id' => $attachment_id,
					);
				}
			}
		}

		// Replace original external URLs with local uploads URLs.
		if ( ! empty( $url_mapping ) ) {
			$cleaned_html = $this->content_processor->replace_image_urls( $cleaned_html, $url_mapping );
		}

		// 3. Process Featured Image (Thumbnail).
		$thumbnail_id = null;
		if ( ! empty( $dto->featured_image_url ) ) {
			$thumbnail_id = $this->media_downloader->download_and_attach(
				$dto->featured_image_url,
				0,
				$dto->title . ' - Thumbnail',
				$batch_id
			);
		}

		// Fallback: If no explicit featured image, use the first content image.
		if ( ! $thumbnail_id && $first_image_attachment_id ) {
			$thumbnail_id = $first_image_attachment_id;
		}

		// 4. Update DTO with cleaned HTML and write post to WordPress DB.
		$dto->content = $cleaned_html;

		try {
			$post_id = $this->post_writer->write_post( $dto, $author_id, $thumbnail_id, $batch_id );
		} catch ( \Throwable $e ) {
			$this->logger->error( "Failed writing post: {$dto->title}", array( 'error' => $e->getMessage() ) );
			return array(
				'success'      => false,
				'post_id'      => null,
				'title'        => $dto->title,
				'media_count'  => 0,
				'thumbnail_id' => $thumbnail_id,
				'error'        => $e->getMessage(),
			);
		}

		// 5. Assign Taxonomies (Categories and Tags).
		$this->taxonomy_manager->assign_taxonomies( $post_id, $dto->categories, $dto->tags );

		// 6. Save SEO Metadata.
		if ( ! empty( $dto->seo_meta ) ) {
			$this->seo_handler->save_seo_meta( $post_id, $dto->seo_meta );
		}

		$this->logger->info( "Successfully migrated post #{$post_id}: {$dto->title}" );

		return array(
			'success'      => true,
			'post_id'      => $post_id,
			'title'        => $dto->title,
			'media_count'  => count( $url_mapping ) + ( $thumbnail_id ? 1 : 0 ),
			'thumbnail_id' => $thumbnail_id,
			'error'        => null,
		);
	}

	/**
	 * Processes a batch of post DTOs.
	 *
	 * @param array<W2W_Post_DTO> $dtos      Array of post DTOs.
	 * @param int                 $author_id Assigned WordPress author ID.
	 * @param string|null         $batch_id  Batch UUID for tracking and rollback.
	 * @return array{total: int, succeeded: int, failed: int, results: array<array<string, mixed>>} Batch results.
	 */
	public function process_batch( array $dtos, int $author_id = 1, ?string $batch_id = null ): array {
		$results   = array();
		$succeeded = 0;
		$failed    = 0;

		foreach ( $dtos as $dto ) {
			$res = $this->process_post( $dto, $author_id, $batch_id );
			$results[] = $res;
			if ( $res['success'] ) {
				$succeeded++;
			} else {
				$failed++;
			}
		}

		return array(
			'total'     => count( $dtos ),
			'succeeded' => $succeeded,
			'failed'    => $failed,
			'results'   => $results,
		);
	}
}
