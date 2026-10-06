<?php
/**
 * Unit Test: Full Migration Pipeline Coordinator.
 *
 * @package WixToWordPressMigrator
 */

// 1. Verify Autoloader loads W2W_Migration_Coordinator
w2w_assert_true( class_exists( 'W2W_Migration_Coordinator' ), 'Autoloader should load W2W_Migration_Coordinator' );

$coordinator = new W2W_Migration_Coordinator();

// 2. Prepare comprehensive post DTO
$dto = new W2W_Post_DTO(
	'wix-full-test-01',
	'Comprehensive End-to-End Migration Test',
	'<div data-mesh-id="wix-container-01"><p>Introductory paragraph</p><img src="https://static.wixstatic.com/media/hero.jpg/v1/fill/w_1200,h_630/hero.jpg" alt="Hero" /><p>&nbsp;</p></div>',
	'comprehensive-end-to-end-migration-test',
	'https://mywixsite.com/blog/post/comprehensive-end-to-end-migration-test',
	'https://static.wixstatic.com/media/featured.jpg',
	array( 'Architecture', 'Engineering' ),
	array( 'php', 'solid', 'migration' ),
	'Vitaliy Nosov',
	array(
		'meta_title'       => 'SEO Optimized Title',
		'meta_description' => 'Complete migration pipeline verification.',
	),
	'2026-10-04 14:00:00',
	'publish'
);

$author_id = 1;
$batch_id  = 'batch-e2e-uuid-999';

// 3. Execute process_post
$result = $coordinator->process_post( $dto, $author_id, $batch_id );

w2w_assert_true( $result['success'], 'Pipeline processing must succeed' );
w2w_assert_not_null( $result['post_id'], 'Post ID must be returned' );
w2w_assert_true( $result['post_id'] > 0, 'Post ID must be a positive integer' );
w2w_assert_not_null( $result['thumbnail_id'], 'Thumbnail ID must be resolved' );

$created_post = get_post( $result['post_id'] );
w2w_assert_equals( 'comprehensive-end-to-end-migration-test', $created_post->post_name, 'Slug must be preserved' );
w2w_assert_false( strpos( $created_post->post_content, 'data-mesh-id' ), 'Wix artifacts must be cleaned from post body' );
w2w_assert_contains( 'max-width: 100%', $created_post->post_content, 'Content image must receive inline max-width: 100% style to prevent layout breakout' );
w2w_assert_contains( '<figure class="wp-block-image size-full">', $created_post->post_content, 'Content image must be wrapped in WordPress standard wp-block-image figure' );
w2w_assert_contains( 'wp-image-', $created_post->post_content, 'Content image must have wp-image class with attachment ID' );

// Verify categories
$assigned_cats = wp_get_post_terms( $result['post_id'], 'category' );
w2w_assert_equals( 2, count( $assigned_cats ), 'Both categories must be assigned' );

// Verify SEO
w2w_assert_equals( 'SEO Optimized Title', get_post_meta( $result['post_id'], '_yoast_wpseo_title', true ), 'SEO title must be saved' );

// 4. Test batch processing
$batch_dtos = array(
	new W2W_Post_DTO( 'batch-item-1', 'Batch Post 1', '<p>Post 1 Content</p>' ),
	new W2W_Post_DTO( 'batch-item-2', 'Batch Post 2', '<p>Post 2 Content</p>' ),
);

$batch_res = $coordinator->process_batch( $batch_dtos, 1, 'batch-e2e-uuid-999' );
w2w_assert_equals( 2, $batch_res['total'], 'Batch total must be 2' );
w2w_assert_equals( 2, $batch_res['succeeded'], 'Batch succeeded must be 2' );
w2w_assert_equals( 0, $batch_res['failed'], 'Batch failed must be 0' );
