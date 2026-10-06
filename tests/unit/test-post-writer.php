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

// 4. Test Deduplication by URL (when original_id differs or is regenerated)
$dto_same_url = new W2W_Post_DTO(
	'wix-id-different-hash',
	'10 Tips for Better Health (Same URL)',
	'<p>Updated content</p>',
	'10-tips-for-better-health',
	'https://mysite.wixsite.com/blog/post/10-tips-for-better-health'
);
$same_url_post_id = $writer->write_post( $dto_same_url, $author_id );
w2w_assert_equals( $post_id, $same_url_post_id, 'Post with identical original URL must match and update existing post' );

// 5. Test Deduplication by Slug (when neither ID nor URL match, but slug matches)
$dto_same_slug = new W2W_Post_DTO(
	'wix-id-another-hash-99',
	'10 Tips for Better Health (Same Slug)',
	'<p>Slug match content</p>',
	'10-tips-for-better-health'
);
$same_slug_post_id = $writer->write_post( $dto_same_slug, $author_id );
w2w_assert_equals( $post_id, $same_slug_post_id, 'Post with identical slug must match and update existing post' );

// Direct method checks
w2w_assert_equals( $post_id, $writer->find_existing_by_wix_id( 'wix-id-another-hash-99' ), 'find_existing_by_wix_id must find existing post' );
w2w_assert_equals( $post_id, $writer->find_existing_by_url( 'https://mysite.wixsite.com/blog/post/10-tips-for-better-health' ), 'find_existing_by_url must find existing post' );
w2w_assert_equals( $post_id, $writer->find_existing_by_url( 'https://mysite.wixsite.com/blog/post/10-tips-for-better-health/' ), 'find_existing_by_url with trailing slash must find existing post' );
w2w_assert_equals( $post_id, $writer->find_existing_by_slug( '10-tips-for-better-health' ), 'find_existing_by_slug must find existing post' );
w2w_assert_equals( $post_id, $writer->find_existing_by_title( '10 Tips for Better Health (Same Slug)' ), 'find_existing_by_title must find existing post' );
w2w_assert_null( $writer->find_existing_by_slug( 'non-existent-slug-xyz' ), 'find_existing_by_slug must return null for missing slug' );
w2w_assert_null( $writer->find_existing_by_title( 'Non-Existent Title 12345' ), 'find_existing_by_title must return null for missing title' );

// 6. Test Deduplication by Title (when Wix ID, URL, and slug all differ)
$dto_same_title = new W2W_Post_DTO(
	'wix-id-random-1234',
	'10 Tips for Better Health (Same Slug)',
	'<p>Title match content</p>',
	'different-slug-xyz',
	'https://different-site.com/blog/different-url'
);
$same_title_post_id = $writer->write_post( $dto_same_title, $author_id );
w2w_assert_equals( $post_id, $same_title_post_id, 'Post with identical title must match and update existing post' );

// 7. Test invalid DTO throws InvalidArgumentException
$invalid_dto = new W2W_Post_DTO( '', '' );
$exception_thrown = false;
try {
	$writer->write_post( $invalid_dto );
} catch ( \InvalidArgumentException $e ) {
	$exception_thrown = true;
}
w2w_assert_true( $exception_thrown, 'Invalid DTO must trigger InvalidArgumentException' );


