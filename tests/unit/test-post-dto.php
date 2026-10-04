<?php
/**
 * Unit Test: Post DTO Model & Validation.
 *
 * @package WixToWordPressMigrator
 */

// 1. Verify Autoloader loads W2W_Post_DTO
w2w_assert_true( class_exists( 'W2W_Post_DTO' ), 'Autoloader should load W2W_Post_DTO' );

// 2. Test instantiation and default values
$dto = new W2W_Post_DTO(
	'wix-12345',
	'Test Article Title',
	'<p>Hello world content</p>',
	'test-article-title',
	'https://mywixsite.com/post/test-article-title',
	'https://static.wixstatic.com/media/img.jpg',
	array( 'News', 'Tech' ),
	array( 'wix', 'wordpress' ),
	'John Doe',
	array(
		'meta_title'       => 'Custom SEO Title',
		'meta_description' => 'Custom SEO Description',
	),
	'2026-10-04 12:00:00',
	'publish'
);

w2w_assert_equals( 'wix-12345', $dto->original_id, 'Original ID should match' );
w2w_assert_equals( 'Test Article Title', $dto->title, 'Title should match' );
w2w_assert_equals( 'test-article-title', $dto->slug, 'Slug should match' );
w2w_assert_equals( 'John Doe', $dto->author_name, 'Author should match' );
w2w_assert_equals( 'https://static.wixstatic.com/media/img.jpg', $dto->featured_image_url, 'Image should match' );
w2w_assert_true( $dto->validate(), 'Valid DTO should pass validation' );

// 3. Test invalid DTO validation
$invalid_dto1 = new W2W_Post_DTO( '', 'Some Title' );
w2w_assert_false( $invalid_dto1->validate(), 'DTO with empty original_id should fail validation' );

$invalid_dto2 = new W2W_Post_DTO( 'wix-999', '   ' );
w2w_assert_false( $invalid_dto2->validate(), 'DTO with empty/whitespace title should fail validation' );

// 4. Test from_array() and to_array() roundtrip
$raw_data = array(
	'original_id'        => 'wix-abc',
	'title'              => 'Array Created Post',
	'content'            => '<p>Array content</p>',
	'slug'               => 'array-created-post',
	'original_url'       => 'https://example.com/post/array-created-post',
	'featured_image_url' => null,
	'categories'         => array( 'Updates' ),
	'tags'               => array(),
	'author_name'        => null,
	'seo_meta'           => array( 'meta_title' => 'Array Title' ),
	'date_published'     => '2026-05-01 10:00:00',
	'status'             => 'draft',
);

$from_array_dto = W2W_Post_DTO::from_array( $raw_data );
w2w_assert_equals( 'wix-abc', $from_array_dto->original_id, 'from_array should hydrate original_id' );
w2w_assert_equals( 'Array Created Post', $from_array_dto->title, 'from_array should hydrate title' );
w2w_assert_null( $from_array_dto->featured_image_url, 'from_array should handle null featured_image_url' );
w2w_assert_equals( 'draft', $from_array_dto->status, 'from_array should hydrate status' );

$exported_array = $from_array_dto->to_array();
w2w_assert_equals( 'wix-abc', $exported_array['original_id'], 'to_array should export original_id' );
w2w_assert_equals( 'Array Created Post', $exported_array['title'], 'to_array should export title' );
w2w_assert_equals( 'draft', $exported_array['status'], 'to_array should export status' );
