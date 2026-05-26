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

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', static function () {
	require dirname( __DIR__ ) . '/loomi-studio-setup.php';
} );

require $_tests_dir . '/includes/bootstrap.php';

require __DIR__ . '/helpers/BaseTestCase.php';
require __DIR__ . '/helpers/LogTestCase.php';
