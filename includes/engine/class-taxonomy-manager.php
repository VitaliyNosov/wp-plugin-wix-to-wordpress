<?php
/**
 * Taxonomy Manager for Categories and Tags.
 *
 * @package WixToWordPressMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles creation and association of categories and tags for migrated posts.
 */
class W2W_Taxonomy_Manager {

	/**
	 * Assigns categories and tags to a specified WordPress post.
	 *
	 * @param int           $post_id    Target post ID.
	 * @param array<string> $categories Array of category names.
	 * @param array<string> $tags       Array of tag names.
	 * @return array{category_ids: array<int>, tag_ids: array<int>} Assigned term IDs.
	 */
	public function assign_taxonomies( int $post_id, array $categories = array(), array $tags = array() ): array {
		$category_ids = array();
		$tag_ids      = array();

		// 1. Process Categories (hierarchical).
		foreach ( $categories as $cat_name ) {
			$term_id = $this->ensure_term( (string) $cat_name, 'category' );
			if ( $term_id && ! in_array( $term_id, $category_ids, true ) ) {
				$category_ids[] = $term_id;
			}
		}

		if ( ! empty( $category_ids ) ) {
			wp_set_post_terms( $post_id, $category_ids, 'category', false );
		}

		// 2. Process Tags (non-hierarchical).
		foreach ( $tags as $tag_name ) {
			$term_id = $this->ensure_term( (string) $tag_name, 'post_tag' );
			if ( $term_id && ! in_array( $term_id, $tag_ids, true ) ) {
				$tag_ids[] = $term_id;
			}
		}

		if ( ! empty( $tag_ids ) ) {
			wp_set_post_terms( $post_id, $tag_ids, 'post_tag', false );
		}

		return array(
			'category_ids' => $category_ids,
			'tag_ids'      => $tag_ids,
		);
	}

	/**
	 * Ensures a taxonomy term exists in the database, creating it if needed.
	 *
	 * @param string $term_name Name of the term.
	 * @param string $taxonomy  Taxonomy slug ('category', 'post_tag').
	 * @param int    $parent    Optional parent term ID.
	 * @return int|null Term ID on success, or null on failure.
	 */
	public function ensure_term( string $term_name, string $taxonomy = 'category', int $parent = 0 ): ?int {
		$clean_name = trim( sanitize_text_field( $term_name ) );
		if ( empty( $clean_name ) ) {
			return null;
		}

		// Check if term already exists.
		$existing = term_exists( $clean_name, $taxonomy, $parent ?: null );
		if ( $existing ) {
			return is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
		}

		// Create new term.
		$inserted = wp_insert_term(
			$clean_name,
			$taxonomy,
			array(
				'parent' => $parent,
			)
		);

		if ( is_wp_error( $inserted ) || ! isset( $inserted['term_id'] ) ) {
			return null;
		}

		return (int) $inserted['term_id'];
	}
}
