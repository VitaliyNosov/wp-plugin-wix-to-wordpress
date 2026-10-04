<?php
/**
 * Unit Tests for W2W_Ajax_Handler.
 *
 * @package WixToWordPressMigrator
 */

class W2W_Test_Ajax_Handler {

	/**
	 * Sample realistic RSS feed.
	 *
	 * @var string
	 */
	private static string $sample_feed = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel>
    <title>Dental Health Blog</title>
    <link>https://smile-clinic.wixsite.com/dental</link>
    <item>
      <title>Teeth Whitening 101</title>
      <link>https://smile-clinic.wixsite.com/dental/post/teeth-whitening-101</link>
      <guid>wix-ajax-001</guid>
      <pubDate>Mon, 01 Jul 2024 10:00:00 GMT</pubDate>
      <dc:creator>Dr. Elena Rostova</dc:creator>
      <content:encoded><![CDATA[<p>Full guide to safe teeth whitening.</p>]]></content:encoded>
      <category>Cosmetic</category>
    </item>
    <item>
      <title>Preventing Cavities in Children</title>
      <link>https://smile-clinic.wixsite.com/dental/post/preventing-cavities</link>
      <guid>wix-ajax-002</guid>
      <pubDate>Tue, 02 Jul 2024 11:00:00 GMT</pubDate>
      <dc:creator>Dr. Elena Rostova</dc:creator>
      <content:encoded><![CDATA[<p>Essential pediatric dental advice for parents.</p>]]></content:encoded>
      <category>Pediatric</category>
    </item>
  </channel>
</rss>
XML;

	/**
	 * Run all assertions.
	 *
	 * @return void
	 */
	public function run(): void {
		global $w2w_test_valid_nonces, $w2w_test_current_user_caps;

		$handler = new W2W_Ajax_Handler();

		// -------------------------------------------------------------
		// 1. Security: Nonce Rejection
		// -------------------------------------------------------------
		$_REQUEST = array( 'nonce' => 'invalid_nonce_token' );
		$_POST    = array( 'nonce' => 'invalid_nonce_token' );

		$nonce_rejected = false;
		try {
			$handler->verify_request();
		} catch ( W2W_Test_Ajax_Exception $e ) {
			$nonce_rejected = 403 === $e->status_code && false === $e->response['success'];
		}
		w2w_assert_true( $nonce_rejected, 'Invalid nonce must be rejected with HTTP 403.' );

		// Set valid nonce.
		$_REQUEST = array( 'nonce' => 'valid_nonce' );
		$_POST    = array( 'nonce' => 'valid_nonce' );

		// -------------------------------------------------------------
		// 2. Security: Capability Rejection
		// -------------------------------------------------------------
		$w2w_test_current_user_caps['manage_options'] = false;
		$cap_rejected                                 = false;
		try {
			$handler->verify_request();
		} catch ( W2W_Test_Ajax_Exception $e ) {
			$cap_rejected = 403 === $e->status_code;
		}
		w2w_assert_true( $cap_rejected, 'User without manage_options capability must be rejected with HTTP 403.' );

		// Restore manage_options.
		$w2w_test_current_user_caps['manage_options'] = true;

		// -------------------------------------------------------------
		// 3. Feed Preview: ajax_preview_feed
		// -------------------------------------------------------------
		$_POST = array(
			'nonce'       => 'valid_nonce',
			'source_type' => 'rss',
			'raw_xml'     => self::$sample_feed,
		);

		$preview_response = null;
		try {
			$handler->ajax_preview_feed();
		} catch ( W2W_Test_Ajax_Exception $e ) {
			$preview_response = $e->response;
		}

		w2w_assert_not_null( $preview_response, 'Preview must return a JSON response.' );
		w2w_assert_true( $preview_response['success'], 'Preview response must indicate success.' );
		w2w_assert_equals( 2, $preview_response['data']['total'], 'Preview must report total 2 posts.' );
		w2w_assert_equals( 2, count( $preview_response['data']['posts'] ), 'Preview must contain 2 post preview items.' );

		$session_id = $preview_response['data']['session_id'];
		w2w_assert_true( ! empty( $session_id ), 'Preview must return a session_id token.' );

		// Verify first item in preview.
		$item1 = $preview_response['data']['posts'][0];
		w2w_assert_equals( 'Teeth Whitening 101', $item1['title'], 'Preview item 1 title must match.' );
		w2w_assert_equals( 'teeth-whitening-101', $item1['slug'], 'Preview item 1 slug must match.' );
		w2w_assert_equals( 'Dr. Elena Rostova', $item1['author_name'], 'Preview item 1 author must match.' );

		// -------------------------------------------------------------
		// 4. Chunked Import: ajax_import_chunk
		// -------------------------------------------------------------
		$batch_id = 'batch-test-uuid-999';

		// Import Chunk 1 (First post: index 0).
		$_POST = array(
			'nonce'            => 'valid_nonce',
			'batch_id'         => $batch_id,
			'session_id'       => $session_id,
			'indices'          => array( 0 ),
			'author_id'        => 1,
			'default_category' => 'Dental Care',
			'import_images'    => 'false',
		);

		$chunk1_response = null;
		try {
			$handler->ajax_import_chunk();
		} catch ( W2W_Test_Ajax_Exception $e ) {
			$chunk1_response = $e->response;
		}

		w2w_assert_not_null( $chunk1_response, 'Chunk 1 must return a response.' );
		w2w_assert_true( $chunk1_response['success'], 'Chunk 1 import must succeed.' );
		w2w_assert_equals( 1, $chunk1_response['data']['chunk_count'], 'Chunk 1 count must be 1.' );
		w2w_assert_true( $chunk1_response['data']['results'][0]['success'], 'First item import must be marked successful.' );

		$imported_post_id = $chunk1_response['data']['results'][0]['post_id'];
		$assigned_terms   = wp_get_post_terms( $imported_post_id, 'category' );
		w2w_assert_true( ! empty( $assigned_terms ), 'Imported post must have categories assigned.' );
		w2w_assert_equals( 'Cosmetic', $assigned_terms[0]->name, 'Category name must match Wix category.' );

		// Import Chunk 2 (Second post: index 1).
		$_POST['indices'] = array( 1 );
		$chunk2_response  = null;
		try {
			$handler->ajax_import_chunk();
		} catch ( W2W_Test_Ajax_Exception $e ) {
			$chunk2_response = $e->response;
		}

		w2w_assert_not_null( $chunk2_response, 'Chunk 2 must return a response.' );
		w2w_assert_true( $chunk2_response['success'], 'Chunk 2 import must succeed.' );

		// -------------------------------------------------------------
		// 5. Rollback Inspection: ajax_check_rollback
		// -------------------------------------------------------------
		$_POST = array(
			'nonce'    => 'valid_nonce',
			'batch_id' => $batch_id,
		);

		$check_response = null;
		try {
			$handler->ajax_check_rollback();
		} catch ( W2W_Test_Ajax_Exception $e ) {
			$check_response = $e->response;
		}

		w2w_assert_not_null( $check_response, 'Check rollback must return a response.' );
		w2w_assert_true( $check_response['success'], 'Check rollback must succeed.' );
		w2w_assert_equals( 2, $check_response['data']['posts'], 'Check rollback should find 2 posts for batch.' );

		// -------------------------------------------------------------
		// 6. 301 Redirects Export: ajax_export_redirects
		// -------------------------------------------------------------
		$_POST = array(
			'nonce' => 'valid_nonce',
		);

		$redirects_response = null;
		try {
			$handler->ajax_export_redirects();
		} catch ( W2W_Test_Ajax_Exception $e ) {
			$redirects_response = $e->response;
		}

		w2w_assert_not_null( $redirects_response, 'Export redirects must return a response.' );
		w2w_assert_true( $redirects_response['success'], 'Export redirects must succeed.' );
		w2w_assert_true( $redirects_response['data']['count'] >= 2, 'Export redirects must contain at least 2 entries.' );
		w2w_assert_contains( 'teeth-whitening-101', $redirects_response['data']['csv'], 'CSV redirect map must contain post slug.' );
		w2w_assert_contains( 'Redirect 301 /dental/post/teeth-whitening-101', $redirects_response['data']['htaccess'], '.htaccess must contain Redirect rule.' );

		// -------------------------------------------------------------
		// 7. Rollback Execution: ajax_rollback_batch
		// -------------------------------------------------------------
		$_POST = array(
			'nonce'    => 'valid_nonce',
			'batch_id' => $batch_id,
		);

		$rollback_response = null;
		try {
			$handler->ajax_rollback_batch();
		} catch ( W2W_Test_Ajax_Exception $e ) {
			$rollback_response = $e->response;
		}

		w2w_assert_not_null( $rollback_response, 'Rollback must return a response.' );
		w2w_assert_true( $rollback_response['success'], 'Rollback execution must succeed.' );
		w2w_assert_equals( 2, $rollback_response['data']['posts_deleted'], 'Rollback must report 2 posts deleted.' );

		// Verify posts were purged from database.
		$posts_left = get_posts(
			array(
				'meta_key'   => '_w2w_batch_id',
				'meta_value' => $batch_id,
			)
		);
		w2w_assert_equals( 0, count( $posts_left ), 'All posts in batch must be removed after rollback.' );

		// -------------------------------------------------------------
		// 8. Logs Management: ajax_get_logs and ajax_clear_logs
		// -------------------------------------------------------------
		$_POST = array(
			'nonce' => 'valid_nonce',
			'limit' => 20,
		);

		$logs_response = null;
		try {
			$handler->ajax_get_logs();
		} catch ( W2W_Test_Ajax_Exception $e ) {
			$logs_response = $e->response;
		}
		w2w_assert_true( $logs_response['success'], 'Get logs must succeed.' );
		w2w_assert_true( is_array( $logs_response['data']['entries'] ), 'Logs entries must be an array.' );

		// Clear logs.
		$clear_response = null;
		try {
			$handler->ajax_clear_logs();
		} catch ( W2W_Test_Ajax_Exception $e ) {
			$clear_response = $e->response;
		}
		w2w_assert_true( $clear_response['success'], 'Clear logs must succeed.' );
	}
}

// Execute the test suite.
( new W2W_Test_Ajax_Handler() )->run();
