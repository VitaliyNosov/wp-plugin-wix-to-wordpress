<?php
/**
 * Unit Test: Post Writer with Slug Preservation & Deduplication.
 *
 * @package WixToWordPressMigrator
 */

// 1. Verify Autoloader loads W2W_Post_Writer
w2w_assert_true( class_exists( 'W2W_Post_Writer' ), 'Autoloader should load W2W_Post_Writer' );

$writer = new W2W_Post_Writer();

// 2. Test inserting a new post with slug, batch ID, and thumbnail ID
$dto = new W2W_Post_DTO(
	'wix-post-9876',
	'10 Tips for Better Health',
	'<p>Article body content here</p>',
	'10-tips-for-better-health',
	'https://mysite.wixsite.com/blog/post/10-tips-for-better-health',
	'https://static.wixstatic.com/media/thumb.jpg',
	array( 'Health' ),
	array( 'wellness' ),
	'Dr. Smith',
	array(),
	'2026-09-15 08:30:00',
	'publish'
);

$author_id    = 5;
$thumbnail_id = 42;
$batch_id     = 'batch-test-uuid-555';

$post_id = $writer->write_post( $dto, $author_id, $thumbnail_id, $batch_id );

w2w_assert_not_null( $post_id, 'Post ID should be returned' );
w2w_assert_true( $post_id > 0, 'Post ID must be positive integer' );

// Verify post fields in DB
$post = get_post( $post_id );
w2w_assert_equals( '10 Tips for Better Health', $post->post_title, 'Post title must match' );
w2w_assert_equals( '<p>Article body content here</p>', $post->post_content, 'Post content must match' );
w2w_assert_equals( '10-tips-for-better-health', $post->post_name, 'Slug must be preserved exactly' );
w2w_assert_equals( 5, $post->post_author, 'Author ID must match' );
w2w_assert_equals( 'publish', $post->post_status, 'Status must match' );

// Verify metadata in DB
w2w_assert_equals( 'wix-post-9876', get_post_meta( $post_id, '_w2w_original_wix_id', true ), 'Wix ID must match' );
w2w_assert_equals( 'https://mysite.wixsite.com/blog/post/10-tips-for-better-health', get_post_meta( $post_id, '_w2w_original_url', true ), 'Original URL for 301 redirects must match' );
w2w_assert_equals( 'batch-test-uuid-555', get_post_meta( $post_id, '_w2w_batch_id', true ), 'Batch ID must match' );
w2w_assert_equals( 42, get_post_meta( $post_id, '_thumbnail_id', true ), 'Thumbnail ID must match' );

// 3. Test Deduplication: Writing the same DTO with updated title updates existing post
$dto_updated = clone $dto;
$dto_updated->title = '10 Tips for Better Health (Updated)';

$updated_post_id = $writer->write_post( $dto_updated, $author_id, $thumbnail_id, $batch_id, true );
w2w_assert_equals( $post_id, $updated_post_id, 'Updating existing post must return the exact same post ID' );

$updated_post = get_post( $post_id );
w2w_assert_equals( '10 Tips for Better Health (Updated)', $updated_post->post_title, 'Updated post title must be reflected' );

// 4. Test invalid DTO throws InvalidArgumentException
$invalid_dto = new W2W_Post_DTO( '', '' );
$exception_thrown = false;
try {
	$writer->write_post( $invalid_dto );
} catch ( \InvalidArgumentException $e ) {
	$exception_thrown = true;
}
w2w_assert_true( $exception_thrown, 'Invalid DTO must trigger InvalidArgumentException' );
