<?php
/**
 * Migration Batch Rollback Manager.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles safe, 1-click bulk removal of migrated posts and media assets by batch UUID.
 */
class W2W_Rollback_Manager {

	/**
	 * Rolls back all posts and attachments created within a specific migration batch.
	 *
	 * @param string $batch_id Batch UUID string.
	 * @return array{posts_deleted: int, attachments_deleted: int, success: bool, message: string} Rollback summary.
	 */
	public function rollback_batch( string $batch_id ): array {
		$clean_batch_id = sanitize_text_field( trim( $batch_id ) );
		if ( empty( $clean_batch_id ) ) {
			return array(
				'posts_deleted'       => 0,
				'attachments_deleted' => 0,
				'success'             => false,
				'message'             => __( 'Invalid or empty batch ID provided.', 'wix-to-wp-migrator' ),
			);
		}

		$posts_deleted       = 0;
		$attachments_deleted = 0;

		// 1. Delete Migrated Posts in Batch.
		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'meta_key'       => '_w2w_batch_id',
				'meta_value'     => $clean_batch_id,
				'posts_per_page' => -1,
			)
		);

		foreach ( $posts as $post ) {
			$post_id = (int) $post->ID;
			if ( wp_delete_post( $post_id, true ) ) {
				$posts_deleted++;
			}
		}

		// 2. Delete Uploaded Attachments in Batch.
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => '_w2w_batch_id',
				'meta_value'     => $clean_batch_id,
				'posts_per_page' => -1,
			)
		);

		foreach ( $attachments as $attachment ) {
			$att_id = (int) $attachment->ID;
			if ( wp_delete_attachment( $att_id, true ) ) {
				$attachments_deleted++;
			}
		}

		$result = array(
			'posts_deleted'       => $posts_deleted,
			'attachments_deleted' => $attachments_deleted,
			'success'             => true,
			'message'             => sprintf(
				/* translators: 1: number of posts, 2: number of attachments */
				__( 'Successfully rolled back %1$d posts and %2$d attachments.', 'wix-to-wp-migrator' ),
				$posts_deleted,
				$attachments_deleted
			),
		);

		/**
		 * Action triggered after a migration batch has been rolled back.
		 *
		 * @param string               $clean_batch_id Batch UUID.
		 * @param array<string, mixed> $result         Rollback execution statistics.
		 */
		do_action( 'w2w_after_rollback_batch', $clean_batch_id, $result );

		return $result;
	}

	/**
	 * Counts the number of items belonging to a batch without deleting them.
	 *
	 * @param string $batch_id Batch UUID.
	 * @return array{posts: int, attachments: int, total: int} Item counts.
	 */
	public function count_batch_items( string $batch_id ): array {
		$clean_batch_id = sanitize_text_field( trim( $batch_id ) );
		if ( empty( $clean_batch_id ) ) {
			return array(
				'posts'       => 0,
				'attachments' => 0,
				'total'       => 0,
			);
		}

		$posts = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'any',
				'meta_key'       => '_w2w_batch_id',
				'meta_value'     => $clean_batch_id,
				'posts_per_page' => -1,
			)
		);

		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => '_w2w_batch_id',
				'meta_value'     => $clean_batch_id,
				'posts_per_page' => -1,
			)
		);

		$post_count       = count( $posts );
		$attachment_count = count( $attachments );

		return array(
			'posts'       => $post_count,
			'attachments' => $attachment_count,
			'total'       => $post_count + $attachment_count,
		);
	}

	/**
	 * Retrieves distinct recent migration batches from database.
	 *
	 * @param int $limit Max batches to return.
	 * @return array<array{batch_id: string, count: int}>
	 */
	public function get_recent_batches( int $limit = 5 ): array {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! isset( $wpdb->postmeta ) ) {
			return array();
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value as batch_id, COUNT(post_id) as item_count FROM {$wpdb->postmeta} WHERE meta_key = '_w2w_batch_id' GROUP BY meta_value ORDER BY post_id DESC LIMIT %d",
				$limit
			)
		);

		$batches = array();
		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				$batches[] = array(
					'batch_id'   => (string) $row->batch_id,
					'item_count' => (int) $row->item_count,
				);
			}
		}

		return $batches;
	}
}
