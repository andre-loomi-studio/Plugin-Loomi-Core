<?php
/**
 * PHPUnit bootstrap — loads WP-PHPUnit + the Loomi plugin.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php. WP-PHPUnit not installed?\n";
	echo "Run tests/install-wp-tests.sh first.\n";
	exit( 1 );
}

// Redirect plugin logs to a temp directory so PHPUnit runs don't pollute the
// production logs/ folder that the admin Logs tab reads from.
$loomi_test_log_dir = sys_get_temp_dir() . '/loomi-test-logs';
if ( ! is_dir( $loomi_test_log_dir ) ) {
	@mkdir( $loomi_test_log_dir, 0777, true );
}
if ( ! defined( 'LOOMI_LOG_DIR_OVERRIDE' ) ) {
	define( 'LOOMI_LOG_DIR_OVERRIDE', $loomi_test_log_dir );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', static function () {
	require dirname( __DIR__ ) . '/loomi-studio-setup.php';
} );

require $_tests_dir . '/includes/bootstrap.php';

require __DIR__ . '/helpers/BaseTestCase.php';
require __DIR__ . '/helpers/LogTestCase.php';
