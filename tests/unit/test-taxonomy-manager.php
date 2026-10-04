<?php
/**
 * Unit Test: Taxonomy Manager.
 *
 * @package WixToWordPressMigrator
 */

// 1. Verify Autoloader loads W2W_Taxonomy_Manager
w2w_assert_true( class_exists( 'W2W_Taxonomy_Manager' ), 'Autoloader should load W2W_Taxonomy_Manager' );

$tax_manager = new W2W_Taxonomy_Manager();

// 2. Test ensure_term creates a new term
$cat_id = $tax_manager->ensure_term( 'Health & Wellness', 'category' );
w2w_assert_not_null( $cat_id, 'Should create new category and return ID' );
w2w_assert_true( $cat_id > 0, 'Category ID must be positive' );

// 3. Test ensure_term returns existing term ID without creating duplicate
$cat_id_second = $tax_manager->ensure_term( 'Health & Wellness', 'category' );
w2w_assert_equals( $cat_id, $cat_id_second, 'Subsequent call for same term must return existing ID' );

// 4. Test assigning taxonomies to a post
$post_id = wp_insert_post( array( 'post_title' => 'Sample Article' ) );
$res = $tax_manager->assign_taxonomies(
	$post_id,
	array( 'Health & Wellness', 'Nutrition' ),
	array( 'tips', 'healthy-eating' )
);

w2w_assert_equals( 2, count( $res['category_ids'] ), 'Should assign 2 categories' );
w2w_assert_equals( 2, count( $res['tag_ids'] ), 'Should assign 2 tags' );

// Verify with wp_get_post_terms
$assigned_cats = wp_get_post_terms( $post_id, 'category' );
w2w_assert_equals( 2, count( $assigned_cats ), 'Post should have 2 categories in DB' );

$assigned_tags = wp_get_post_terms( $post_id, 'post_tag' );
w2w_assert_equals( 2, count( $assigned_tags ), 'Post should have 2 tags in DB' );

// 5. Test empty inputs
$empty_res = $tax_manager->assign_taxonomies( $post_id, array(), array() );
w2w_assert_equals( 0, count( $empty_res['category_ids'] ), 'Empty category input should return empty array' );
w2w_assert_equals( 0, count( $empty_res['tag_ids'] ), 'Empty tag input should return empty array' );
