<?php
/**
 * SEO Metadata Handler for Yoast SEO, Rank Math, and AIOSEO.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Maps and saves SEO titles, descriptions, and OpenGraph data to popular WordPress SEO plugin fields.
 */
class W2W_SEO_Handler {

	/**
	 * Saves SEO metadata for a migrated post across popular SEO plugin meta keys.
	 *
	 * @param int                  $post_id  Target WordPress post ID.
	 * @param array<string, mixed> $seo_meta Associative array of SEO metadata.
	 * @return void
	 */
	public function save_seo_meta( int $post_id, array $seo_meta = array() ): void {
		if ( empty( $seo_meta ) || $post_id <= 0 ) {
			return;
		}

		$title       = isset( $seo_meta['meta_title'] ) ? sanitize_text_field( (string) $seo_meta['meta_title'] ) : '';
		$description = isset( $seo_meta['meta_description'] ) ? sanitize_text_field( (string) $seo_meta['meta_description'] ) : '';
		$keywords    = isset( $seo_meta['keywords'] ) ? sanitize_text_field( (string) $seo_meta['keywords'] ) : '';
		$og_image    = isset( $seo_meta['og_image'] ) ? esc_url_raw( (string) $seo_meta['og_image'] ) : '';

		// 1. Core / Fallback Meta Fields.
		if ( ! empty( $title ) ) {
			update_post_meta( $post_id, '_w2w_seo_title', $title );
		}
		if ( ! empty( $description ) ) {
			update_post_meta( $post_id, '_w2w_seo_description', $description );
		}
		if ( ! empty( $keywords ) ) {
			update_post_meta( $post_id, '_w2w_seo_keywords', $keywords );
		}

		// 2. Yoast SEO compatibility fields.
		if ( ! empty( $title ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_title', $title );
		}
		if ( ! empty( $description ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $description );
		}
		if ( ! empty( $keywords ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_focuskw', $keywords );
		}
		if ( ! empty( $og_image ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_opengraph-image', $og_image );
		}

		// 3. Rank Math compatibility fields.
		if ( ! empty( $title ) ) {
			update_post_meta( $post_id, 'rank_math_title', $title );
		}
		if ( ! empty( $description ) ) {
			update_post_meta( $post_id, 'rank_math_description', $description );
		}
		if ( ! empty( $keywords ) ) {
			update_post_meta( $post_id, 'rank_math_focus_keyword', $keywords );
		}
		if ( ! empty( $og_image ) ) {
			update_post_meta( $post_id, 'rank_math_facebook_image', $og_image );
		}

		// 4. All in One SEO (AIOSEO) compatibility fields.
		if ( ! empty( $title ) ) {
			update_post_meta( $post_id, '_aioseo_title', $title );
		}
		if ( ! empty( $description ) ) {
			update_post_meta( $post_id, '_aioseo_description', $description );
		}
		if ( ! empty( $keywords ) ) {
			update_post_meta( $post_id, '_aioseo_keywords', $keywords );
		}
		if ( ! empty( $og_image ) ) {
			update_post_meta( $post_id, '_aioseo_og_image_custom_url', $og_image );
		}

		/**
		 * Action triggered after SEO metadata is saved for a migrated post.
		 *
		 * @param int                  $post_id  Post ID.
		 * @param array<string, mixed> $seo_meta Raw SEO metadata array.
		 */
		do_action( 'w2w_after_save_seo_meta', $post_id, $seo_meta );
	}

	/**
	 * Retrieves mapped SEO metadata from a WordPress post.
	 *
	 * @param int $post_id Target WordPress post ID.
	 * @return array<string, string> Normalized SEO data.
	 */
	public function get_seo_meta( int $post_id ): array {
		$title = get_post_meta( $post_id, '_yoast_wpseo_title', true )
			?: get_post_meta( $post_id, 'rank_math_title', true )
			?: get_post_meta( $post_id, '_aioseo_title', true )
			?: get_post_meta( $post_id, '_w2w_seo_title', true );

		$description = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true )
			?: get_post_meta( $post_id, 'rank_math_description', true )
			?: get_post_meta( $post_id, '_aioseo_description', true )
			?: get_post_meta( $post_id, '_w2w_seo_description', true );

		return array(
			'meta_title'       => (string) $title,
			'meta_description' => (string) $description,
		);
	}
}
