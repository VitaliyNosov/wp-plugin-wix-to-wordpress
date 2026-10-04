<?php
/**
 * Standalone CLI Test Runner for Wix to WordPress Post Migrator.
 *
 * Usage: php tests/run-tests.php [optional-specific-test.php]
 *
 * @package WixToWordPressMigrator
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

// Test statistics counters.
global $w2w_test_stats;
$w2w_test_stats = array(
	'assertions' => 0,
	'passed'     => 0,
	'failed'     => 0,
	'failures'   => array(),
);

// Current test suite name tracker.
global $w2w_current_test_file;
$w2w_current_test_file = '';

/**
 * Assert condition is strictly true.
 */
function w2w_assert_true( $condition, string $message = '' ): void {
	global $w2w_test_stats, $w2w_current_test_file;
	$w2w_test_stats['assertions']++;
	if ( true === $condition ) {
		$w2w_test_stats['passed']++;
	} else {
		$w2w_test_stats['failed']++;
		$w2w_test_stats['failures'][] = array(
			'file'    => $w2w_current_test_file,
			'message' => $message ?: 'Expected condition to be true, got ' . var_export( $condition, true ),
		);
	}
}

/**
 * Assert condition is strictly false.
 */
function w2w_assert_false( $condition, string $message = '' ): void {
	global $w2w_test_stats, $w2w_current_test_file;
	$w2w_test_stats['assertions']++;
	if ( false === $condition ) {
		$w2w_test_stats['passed']++;
	} else {
		$w2w_test_stats['failed']++;
		$w2w_test_stats['failures'][] = array(
			'file'    => $w2w_current_test_file,
			'message' => $message ?: 'Expected condition to be false, got ' . var_export( $condition, true ),
		);
	}
}

/**
 * Assert two values are equal.
 */
function w2w_assert_equals( $expected, $actual, string $message = '' ): void {
	global $w2w_test_stats, $w2w_current_test_file;
	$w2w_test_stats['assertions']++;
	if ( $expected === $actual ) {
		$w2w_test_stats['passed']++;
	} else {
		$w2w_test_stats['failed']++;
		$w2w_test_stats['failures'][] = array(
			'file'    => $w2w_current_test_file,
			'message' => $message ?: sprintf(
				'Failed asserting that actual %s matches expected %s',
				var_export( $actual, true ),
				var_export( $expected, true )
			),
		);
	}
}

/**
 * Assert value is null.
 */
function w2w_assert_null( $actual, string $message = '' ): void {
	global $w2w_test_stats, $w2w_current_test_file;
	$w2w_test_stats['assertions']++;
	if ( null === $actual ) {
		$w2w_test_stats['passed']++;
	} else {
		$w2w_test_stats['failed']++;
		$w2w_test_stats['failures'][] = array(
			'file'    => $w2w_current_test_file,
			'message' => $message ?: 'Failed asserting that value is null, got ' . var_export( $actual, true ),
		);
	}
}

/**
 * Assert value is not null.
 */
function w2w_assert_not_null( $actual, string $message = '' ): void {
	global $w2w_test_stats, $w2w_current_test_file;
	$w2w_test_stats['assertions']++;
	if ( null !== $actual ) {
		$w2w_test_stats['passed']++;
	} else {
		$w2w_test_stats['failed']++;
		$w2w_test_stats['failures'][] = array(
			'file'    => $w2w_current_test_file,
			'message' => $message ?: 'Failed asserting that value is not null',
		);
	}
}

/**
 * Assert object is instance of class.
 */
function w2w_assert_instance_of( string $expected_class, $actual, string $message = '' ): void {
	global $w2w_test_stats, $w2w_current_test_file;
	$w2w_test_stats['assertions']++;
	if ( is_object( $actual ) && $actual instanceof $expected_class ) {
		$w2w_test_stats['passed']++;
	} else {
		$w2w_test_stats['failed']++;
		$actual_type = is_object( $actual ) ? get_class( $actual ) : gettype( $actual );
		$w2w_test_stats['failures'][] = array(
			'file'    => $w2w_current_test_file,
			'message' => $message ?: "Failed asserting that object of type '{$actual_type}' is instance of '{$expected_class}'",
		);
	}
}

/**
 * Assert string or array contains needle.
 */
function w2w_assert_contains( $needle, $haystack, string $message = '' ): void {
	global $w2w_test_stats, $w2w_current_test_file;
	$w2w_test_stats['assertions']++;
	$contains = false;
	if ( is_string( $haystack ) && is_string( $needle ) ) {
		$contains = false !== strpos( $haystack, $needle );
	} elseif ( is_array( $haystack ) ) {
		$contains = in_array( $needle, $haystack, true );
	}
	if ( $contains ) {
		$w2w_test_stats['passed']++;
	} else {
		$w2w_test_stats['failed']++;
		$w2w_test_stats['failures'][] = array(
			'file'    => $w2w_current_test_file,
			'message' => $message ?: 'Failed asserting that haystack contains needle ' . var_export( $needle, true ),
		);
	}
}

// -------------------------------------------------------------
// Test Execution Engine
// -------------------------------------------------------------

echo "\n" . str_repeat( '=', 60 ) . "\n";
echo "  Wix to WordPress Post Migrator - CLI Test Runner\n";
echo str_repeat( '=', 60 ) . "\n\n";

$start_time = microtime( true );
$unit_dir   = __DIR__ . DIRECTORY_SEPARATOR . 'unit';

$test_files = array();
if ( isset( $argv[1] ) && ! empty( $argv[1] ) ) {
	$target = $unit_dir . DIRECTORY_SEPARATOR . basename( $argv[1] );
	if ( file_exists( $target ) ) {
		$test_files[] = $target;
	} else {
		echo "Error: Specified test file not found: {$target}\n";
		exit( 1 );
	}
} else {
	if ( is_dir( $unit_dir ) ) {
		$files = glob( $unit_dir . DIRECTORY_SEPARATOR . 'test-*.php' );
		if ( $files ) {
			$test_files = $files;
		}
	}
}

if ( empty( $test_files ) ) {
	echo "No unit tests found in {$unit_dir}\n";
	exit( 0 );
}

$suites_run = 0;

foreach ( $test_files as $file ) {
	$suites_run++;
	$w2w_current_test_file = basename( $file );
	$initial_failures      = $w2w_test_stats['failed'];

	// Reset mock db before each suite.
	w2w_test_reset_db();

	echo sprintf( "• Running suite: %-40s ", $w2w_current_test_file );

	try {
		require $file;
		$suite_failed = $w2w_test_stats['failed'] > $initial_failures;
		if ( $suite_failed ) {
			echo "[ FAIL ]\n";
		} else {
			echo "[ PASS ]\n";
		}
	} catch ( \Throwable $e ) {
		$w2w_test_stats['failed']++;
		$w2w_test_stats['failures'][] = array(
			'file'    => $w2w_current_test_file,
			'message' => 'Uncaught exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString(),
		);
		echo "[ ERROR ]\n";
	}
}

$execution_time = round( microtime( true ) - $start_time, 4 );

echo "\n" . str_repeat( '-', 60 ) . "\n";
echo sprintf( "Suites executed: %d\n", $suites_run );
echo sprintf( "Assertions:      %d\n", $w2w_test_stats['assertions'] );
echo sprintf( "Passed:          %d\n", $w2w_test_stats['passed'] );
echo sprintf( "Failed:          %d\n", $w2w_test_stats['failed'] );
echo sprintf( "Time:            %s seconds\n", $execution_time );
echo str_repeat( '-', 60 ) . "\n";

if ( $w2w_test_stats['failed'] > 0 ) {
	echo "\n❌ FAILURES (" . count( $w2w_test_stats['failures'] ) . "):\n";
	foreach ( $w2w_test_stats['failures'] as $i => $failure ) {
		echo sprintf( "\n%d) [%s]: %s\n", $i + 1, $failure['file'], $failure['message'] );
	}
	echo "\n";
	exit( 1 );
}

echo "\n✅ ALL TESTS PASSED SUCCESSFULLY! (100% PASS)\n\n";
exit( 0 );
