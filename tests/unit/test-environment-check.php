<?php
/**
 * Unit Test: Environment Check & Logger Utilities.
 *
 * @package WixToWordPressMigrator
 */

// 1. Verify Autoloader loads W2W_Logger and W2W_Environment_Check
w2w_assert_true( class_exists( 'W2W_Logger' ), 'Autoloader should load W2W_Logger' );
w2w_assert_true( class_exists( 'W2W_Environment_Check' ), 'Autoloader should load W2W_Environment_Check' );

// 2. Test W2W_Logger
$logger = new W2W_Logger();
$logger->clear_log();

$logger->info( 'Test info message', array( 'step' => 1 ) );
$logger->warning( 'Test warning message' );
$logger->error( 'Test error message' );

$entries = $logger->get_recent_entries( 10 );
w2w_assert_equals( 3, count( $entries ), 'Logger should have 3 buffered entries' );
w2w_assert_equals( 'INFO', $entries[0]['level'], 'First entry must be INFO' );
w2w_assert_equals( 'WARNING', $entries[1]['level'], 'Second entry must be WARNING' );
w2w_assert_equals( 'ERROR', $entries[2]['level'], 'Third entry must be ERROR' );

$logger->clear_log();
w2w_assert_equals( 0, count( $logger->get_recent_entries() ), 'Clear log should empty entries' );

// 3. Test W2W_Environment_Check
$env = new W2W_Environment_Check();
$checks = $env->check_all();

w2w_assert_true( isset( $checks['php_version'] ), 'Checks should include php_version' );
w2w_assert_true( $checks['php_version']['passed'], 'PHP version check must pass on PHP >= 7.4' );

// 4. Test SSRF URL validation
$valid_url = 'https://my-awesome-wix-site.com/feed.xml';
w2w_assert_true( $env->validate_source_url( $valid_url ), 'Valid external URL should pass validation' );

$localhost_url = 'http://localhost/private/data';
w2w_assert_false( $env->validate_source_url( $localhost_url ), 'Localhost URL must be rejected for SSRF protection' );

$ip_url = 'http://127.0.0.1:8080/admin';
w2w_assert_false( $env->validate_source_url( $ip_url ), '127.0.0.1 must be rejected for SSRF protection' );

$invalid_string = 'not_a_valid_url';
w2w_assert_false( $env->validate_source_url( $invalid_string ), 'Malformed URL string must be rejected' );
