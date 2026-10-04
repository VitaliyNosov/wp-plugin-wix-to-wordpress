<?php
/**
 * Unit Test: Migration Batch Rollback Manager.
 *
 * @package WixToWordPressMigrator
 */

// 1. Verify Autoloader loads W2W_Rollback_Manager
w2w_assert_true( class_exists( 'W2W_Rollback_Manager' ), 'Autoloader should load W2W_Rollback_Manager' );

$rollback = new W2W_Rollback_Manager();

$target_batch = 'batch-target-uuid-777';
$other_batch  = 'batch-other-uuid-888';

// 2. Create posts & attachments for target batch
$p1 = wp_insert_post( array( 'post_title' => 'Post 1' ) );
update_post_meta( $p1, '_w2w_batch_id', $target_batch );

$p2 = wp_insert_post( array( 'post_title' => 'Post 2' ) );
update_post_meta( $p2, '_w2w_batch_id', $target_batch );

$a1 = wp_insert_attachment( array( 'post_title' => 'Att 1' ) );
update_post_meta( $a1, '_w2w_batch_id', $target_batch );

$a2 = wp_insert_attachment( array( 'post_title' => 'Att 2' ) );
update_post_meta( $a2, '_w2w_batch_id', $target_batch );

// Create 1 post for another batch that must NOT be deleted
$p_safe = wp_insert_post( array( 'post_title' => 'Safe Post' ) );
update_post_meta( $p_safe, '_w2w_batch_id', $other_batch );

// 3. Test count_batch_items
$counts = $rollback->count_batch_items( $target_batch );
w2w_assert_equals( 2, $counts['posts'], 'Batch should have 2 posts' );
w2w_assert_equals( 2, $counts['attachments'], 'Batch should have 2 attachments' );
w2w_assert_equals( 4, $counts['total'], 'Batch should have 4 total items' );

// 4. Test rollback execution
$result = $rollback->rollback_batch( $target_batch );
w2w_assert_true( $result['success'], 'Rollback must report success' );
w2w_assert_equals( 2, $result['posts_deleted'], 'Must delete 2 posts' );
w2w_assert_equals( 2, $result['attachments_deleted'], 'Must delete 2 attachments' );

// Verify target items are gone from DB
w2w_assert_null( get_post( $p1 ), 'Post 1 must be deleted from DB' );
w2w_assert_null( get_post( $p2 ), 'Post 2 must be deleted from DB' );
w2w_assert_null( get_post( $a1 ), 'Attachment 1 must be deleted from DB' );
w2w_assert_null( get_post( $a2 ), 'Attachment 2 must be deleted from DB' );

// Verify other batch item is untouched
w2w_assert_not_null( get_post( $p_safe ), 'Unrelated post from other batch must NOT be deleted' );

// 5. Test empty batch ID
$empty_result = $rollback->rollback_batch( '' );
w2w_assert_false( $empty_result['success'], 'Empty batch ID must fail rollback' );
w2w_assert_equals( 0, $empty_result['posts_deleted'], 'Empty batch ID should delete 0 posts' );
