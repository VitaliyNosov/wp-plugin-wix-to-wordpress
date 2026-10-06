<?php
/**
 * Media Downloader with MIME Validation & Hash Deduplication.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles downloading of external images, filetype validation, deduplication, and WP attachment creation.
 */
class W2W_Media_Downloader {

	/**
	 * Content processor instance for URL normalization.
	 *
	 * @var W2W_Content_Processor
	 */
	private W2W_Content_Processor $content_processor;

	/**
	 * Allowed MIME types for media downloads.
	 *
	 * @var array<string, string>
	 */
	private const ALLOWED_MIMES = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'gif'          => 'image/gif',
		'webp'         => 'image/webp',
		'svg'          => 'image/svg+xml',
	);

	/**
	 * Constructor.
	 *
	 * @param W2W_Content_Processor|null $content_processor Optional content processor instance.
	 */
	public function __construct( ?W2W_Content_Processor $content_processor = null ) {
		$this->content_processor = $content_processor ?: new W2W_Content_Processor();
	}

	/**
	 * Downloads an external image, validates MIME/extension, deduplicates, and creates an attachment.
	 *
	 * @param string      $image_url      External image URL.
	 * @param int         $parent_post_id Parent post ID to associate with.
	 * @param string      $title          Optional title/caption for the attachment.
	 * @param string|null $batch_id       Optional batch UUID for migration rollback tracking.
	 * @return int|null Attachment ID on success, or null on failure.
	 */
	public function download_and_attach(
		string $image_url,
		int $parent_post_id = 0,
		string $title = '',
		?string $batch_id = null
	): ?int {
		// 1. Normalize image URL (strip dynamic Wix crop/compression parameters).
		$normalized_url = $this->content_processor->normalize_wix_image_url( $image_url );
		if ( empty( $normalized_url ) ) {
			return null;
		}

		// 2. Validate URL safety (prevent SSRF attacks).
		if ( ! filter_var( $normalized_url, FILTER_VALIDATE_URL ) ) {
			return null;
		}

		if ( ! W2W_Environment_Check::validate_safe_url( $normalized_url ) ) {
			return null;
		}

		// 3. Deduplication Check 1: Has this exact source URL already been imported?
		$existing_id = $this->find_existing_by_url( $normalized_url );
		if ( $existing_id ) {
			return $existing_id;
		}

		// 4. Determine filename and sanitize.
		$url_path  = parse_url( $normalized_url, PHP_URL_PATH );
		$file_name = sanitize_file_name( basename( (string) $url_path ) );

		if ( empty( $file_name ) || false === strpos( $file_name, '.' ) ) {
			$file_name = 'wix-media-' . substr( md5( $normalized_url ), 0, 8 ) . '.jpg';
		}

		// 5. Validate file extension and MIME type against allowed list.
		$checked = wp_check_filetype_and_ext( '', $file_name, self::ALLOWED_MIMES );
		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			return null;
		}

		// 6. Download file data via WordPress HTTP API.
		$response = wp_remote_get(
			$normalized_url,
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'sslverify'   => false,
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$file_contents = wp_remote_retrieve_body( $response );
		if ( empty( $file_contents ) ) {
			return null;
		}

		// 7. Deduplication Check 2: Has this identical content been imported under another URL?
		$content_hash = md5( $file_contents );
		$existing_hash_id = $this->find_existing_by_hash( $content_hash );
		if ( $existing_hash_id ) {
			// Record the additional source URL to the existing attachment for future lookups.
			update_post_meta( $existing_hash_id, '_w2w_source_media_url', $normalized_url );
			return $existing_hash_id;
		}

		// 8. Upload bits into WordPress uploads directory.
		$upload = wp_upload_bits( $file_name, null, $file_contents );
		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			return null;
		}

		$file_path = $upload['file'];
		$file_url  = $upload['url'];

		// 9. Prepare attachment post data.
		$attachment_data = array(
			'post_mime_type' => $checked['type'],
			'post_title'     => ! empty( $title ) ? sanitize_text_field( $title ) : sanitize_text_field( pathinfo( $file_name, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => $parent_post_id,
			'guid'           => $file_url,
		);

		$attachment_id = wp_insert_attachment( $attachment_data, $file_path, $parent_post_id );
		if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
			return null;
		}

		// 10. Store metadata for deduplication and rollback.
		update_post_meta( $attachment_id, '_w2w_source_media_url', $normalized_url );
		update_post_meta( $attachment_id, '_w2w_media_hash', $content_hash );

		if ( ! empty( $batch_id ) ) {
			update_post_meta( $attachment_id, '_w2w_batch_id', $batch_id );
		}

		return (int) $attachment_id;
	}

	/**
	 * Finds an existing attachment ID by source media URL.
	 *
	 * @param string $source_url Source URL to search.
	 * @return int|null Existing attachment ID or null.
	 */
	public function find_existing_by_url( string $source_url ): ?int {
		$posts = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'meta_key'         => '_w2w_source_media_url',
				'meta_value'       => $source_url,
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		$first = $posts[0];
		return is_object( $first ) ? (int) $first->ID : (int) $first;
	}

	/**
	 * Finds an existing attachment ID by content MD5 hash.
	 *
	 * @param string $hash MD5 hash string.
	 * @return int|null Existing attachment ID or null.
	 */
	public function find_existing_by_hash( string $hash ): ?int {
		$posts = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'meta_key'         => '_w2w_media_hash',
				'meta_value'       => $hash,
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		$first = $posts[0];
		return is_object( $first ) ? (int) $first->ID : (int) $first;
	}
}
