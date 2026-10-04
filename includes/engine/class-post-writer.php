<?php
/**
 * Post Writer with Deduplication & Exact Slug Preservation.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles creation and updating of WordPress posts from DTOs with deduplication and meta storage.
 */
class W2W_Post_Writer {

	/**
	 * Writes a post DTO to the WordPress database.
	 *
	 * Performs deduplication against _w2w_original_wix_id, preserves exact slug, and saves metadata.
	 *
	 * @param W2W_Post_DTO $dto             Post DTO to insert or update.
	 * @param int          $author_id       WordPress user ID to assign as post author.
	 * @param int|null     $thumbnail_id    Optional attachment ID for the featured image.
	 * @param string|null  $batch_id        Optional migration batch UUID for rollback tracking.
	 * @param bool         $update_existing Whether to update if an existing post is found.
	 * @return int Created or updated WordPress post ID.
	 * @throws \InvalidArgumentException If DTO validation fails.
	 * @throws \RuntimeException If post insertion fails.
	 */
	public function write_post(
		W2W_Post_DTO $dto,
		int $author_id = 1,
		?int $thumbnail_id = null,
		?string $batch_id = null,
		bool $update_existing = true
	): int {
		if ( ! $dto->validate() ) {
			throw new \InvalidArgumentException( 'Invalid DTO: Title and Original ID are required.' );
		}

		// 1. Deduplication check: Find existing post by Wix ID.
		$existing_id = $this->find_existing_by_wix_id( $dto->original_id );
		if ( $existing_id && ! $update_existing ) {
			return $existing_id;
		}

		// 2. Prepare post array.
		$postarr = array(
			'post_title'   => sanitize_text_field( $dto->title ),
			'post_content' => $dto->content,
			'post_status'  => ! empty( $dto->status ) ? sanitize_key( $dto->status ) : 'publish',
			'post_author'  => $author_id > 0 ? $author_id : 1,
			'post_type'    => 'post',
			'post_date'    => ! empty( $dto->date_published ) ? $dto->date_published : gmdate( 'Y-m-d H:i:s' ),
		);

		// Preserve exact slug if present.
		if ( ! empty( $dto->slug ) ) {
			$postarr['post_name'] = sanitize_title( $dto->slug );
		}

		if ( $existing_id ) {
			$postarr['ID'] = $existing_id;
			$post_id       = wp_insert_post( $postarr );
		} else {
			$post_id = wp_insert_post( $postarr );
		}

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			$error_message = is_wp_error( $post_id ) ? $post_id->get_error_message() : 'Failed to insert post';
			throw new \RuntimeException( $error_message );
		}

		$post_id = (int) $post_id;

		// 3. Save core migration metadata.
		update_post_meta( $post_id, '_w2w_original_wix_id', sanitize_text_field( $dto->original_id ) );

		if ( ! empty( $dto->original_url ) ) {
			update_post_meta( $post_id, '_w2w_original_url', esc_url_raw( $dto->original_url ) );
		}

		if ( ! empty( $batch_id ) ) {
			update_post_meta( $post_id, '_w2w_batch_id', sanitize_text_field( $batch_id ) );
		}

		// 4. Assign Featured Image if provided.
		if ( $thumbnail_id && $thumbnail_id > 0 ) {
			update_post_meta( $post_id, '_thumbnail_id', $thumbnail_id );
		}

		/**
		 * Action triggered after a post is saved by the Wix migrator.
		 *
		 * @param int          $post_id Target WordPress post ID.
		 * @param W2W_Post_DTO $dto     Post DTO.
		 */
		do_action( 'w2w_after_write_post', $post_id, $dto );

		return $post_id;
	}

	/**
	 * Finds an existing WordPress post ID by its original Wix ID.
	 *
	 * @param string $wix_id Original Wix post ID or GUID.
	 * @return int|null Existing WordPress post ID or null if not found.
	 */
	public function find_existing_by_wix_id( string $wix_id ): ?int {
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'meta_key'       => '_w2w_original_wix_id',
				'meta_value'     => $wix_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		return ! empty( $posts ) ? (int) $posts[0]->ID : null;
	}
}
