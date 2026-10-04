<?php
/**
 * Unit Test: SEO Metadata Handler.
 *
 * @package WixToWordPressMigrator
 */

// 1. Verify Autoloader loads W2W_SEO_Handler
w2w_assert_true( class_exists( 'W2W_SEO_Handler' ), 'Autoloader should load W2W_SEO_Handler' );

$seo_handler = new W2W_SEO_Handler();

// 2. Create mock post and test saving SEO metadata
$post_id = wp_insert_post( array( 'post_title' => 'SEO Test Post' ) );

$seo_meta = array(
	'meta_title'       => 'High Performing SEO Title',
	'meta_description' => 'Comprehensive SEO description for search engines.',
	'keywords'         => 'wix, wordpress, migration',
	'og_image'         => 'https://example.org/og-image.jpg',
);

$seo_handler->save_seo_meta( $post_id, $seo_meta );

// Verify Fallback Keys
w2w_assert_equals( 'High Performing SEO Title', get_post_meta( $post_id, '_w2w_seo_title', true ), 'Fallback title meta must match' );
w2w_assert_equals( 'Comprehensive SEO description for search engines.', get_post_meta( $post_id, '_w2w_seo_description', true ), 'Fallback description meta must match' );

// Verify Yoast Keys
w2w_assert_equals( 'High Performing SEO Title', get_post_meta( $post_id, '_yoast_wpseo_title', true ), 'Yoast title meta must match' );
w2w_assert_equals( 'Comprehensive SEO description for search engines.', get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ), 'Yoast description meta must match' );
w2w_assert_equals( 'wix, wordpress, migration', get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ), 'Yoast keyword meta must match' );

// Verify Rank Math Keys
w2w_assert_equals( 'High Performing SEO Title', get_post_meta( $post_id, 'rank_math_title', true ), 'Rank Math title meta must match' );
w2w_assert_equals( 'Comprehensive SEO description for search engines.', get_post_meta( $post_id, 'rank_math_description', true ), 'Rank Math description meta must match' );

// Verify AIOSEO Keys
w2w_assert_equals( 'High Performing SEO Title', get_post_meta( $post_id, '_aioseo_title', true ), 'AIOSEO title meta must match' );
w2w_assert_equals( 'Comprehensive SEO description for search engines.', get_post_meta( $post_id, '_aioseo_description', true ), 'AIOSEO description meta must match' );

// 3. Test get_seo_meta retrieval
$retrieved = $seo_handler->get_seo_meta( $post_id );
w2w_assert_equals( 'High Performing SEO Title', $retrieved['meta_title'], 'get_seo_meta must retrieve normalized title' );
w2w_assert_equals( 'Comprehensive SEO description for search engines.', $retrieved['meta_description'], 'get_seo_meta must retrieve normalized description' );

// 4. Test empty handling
$empty_post_id = wp_insert_post( array( 'post_title' => 'Empty Post' ) );
$seo_handler->save_seo_meta( $empty_post_id, array() );
$empty_retrieved = $seo_handler->get_seo_meta( $empty_post_id );
w2w_assert_equals( '', $empty_retrieved['meta_title'], 'Empty SEO post should return empty title' );
