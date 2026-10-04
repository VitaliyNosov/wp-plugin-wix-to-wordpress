<?php
/**
 * Unit Test: Media Downloader, Deduplication & MIME Validation.
 *
 * @package WixToWordPressMigrator
 */

// 1. Verify Autoloader loads W2W_Media_Downloader
w2w_assert_true( class_exists( 'W2W_Media_Downloader' ), 'Autoloader should load W2W_Media_Downloader' );

$downloader = new W2W_Media_Downloader();

// 2. Test valid image download and attachment creation
$test_url = 'https://static.wixstatic.com/media/test_image_01.jpg/v1/fill/w_800,h_600/test_image_01.jpg';
$batch_id = 'batch-uuid-12345';

$attachment_id = $downloader->download_and_attach( $test_url, 101, 'Test Featured Image', $batch_id );

w2w_assert_not_null( $attachment_id, 'Attachment ID should be returned on successful download' );
w2w_assert_true( $attachment_id > 0, 'Attachment ID must be a positive integer' );

// Verify post was saved in database with attachment type
$post = get_post( $attachment_id );
w2w_assert_not_null( $post, 'Attachment post must exist in database' );
w2w_assert_equals( 'attachment', $post->post_type, 'Post type must be attachment' );

// Verify meta saved
$saved_source = get_post_meta( $attachment_id, '_w2w_source_media_url', true );
w2w_assert_equals( 'https://static.wixstatic.com/media/test_image_01.jpg', $saved_source, 'Normalized source URL must be stored in postmeta' );

$saved_batch = get_post_meta( $attachment_id, '_w2w_batch_id', true );
w2w_assert_equals( $batch_id, $saved_batch, 'Batch UUID must be stored in postmeta' );

// 3. Test Deduplication by URL
$second_call_id = $downloader->download_and_attach( $test_url, 102 );
w2w_assert_equals( $attachment_id, $second_call_id, 'Second download call for same URL must return existing attachment ID (deduplication)' );

// 4. Test Deduplication by Hash
$diff_url_same_content = 'https://example.com/different-name.jpg';
$third_call_id = $downloader->download_and_attach( $diff_url_same_content, 103 );
w2w_assert_equals( $attachment_id, $third_call_id, 'Download for identical content hash must return existing attachment ID' );

// 5. Test Disallowed Extension / MIME Validation
$disallowed_url = 'https://example.com/malicious.php';
$failed_id = $downloader->download_and_attach( $disallowed_url, 101 );
w2w_assert_null( $failed_id, 'Files with disallowed extensions (.php) must be rejected' );

$disallowed_exe = 'https://example.com/virus.exe';
$failed_exe_id = $downloader->download_and_attach( $disallowed_exe, 101 );
w2w_assert_null( $failed_exe_id, 'Executable files must be rejected' );

// 6. Test Invalid URL
$invalid_url = 'not-a-valid-url';
$invalid_res = $downloader->download_and_attach( $invalid_url );
w2w_assert_null( $invalid_res, 'Invalid URL string must return null' );
