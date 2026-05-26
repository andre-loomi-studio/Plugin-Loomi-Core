<?php

abstract class Loomi_LogTestCase extends Loomi_TestCase {

	public function set_up() : void {
		parent::set_up();
		$this->reset_log_state();
	}

	public function tear_down() : void {
		$this->reset_log_state();
		remove_all_filters( 'loomi_log_test_last_error' );
		parent::tear_down();
	}

	protected function reset_log_state() : void {
		// Wipe log files
		foreach ( glob( Plugin::log_dir() . '/loomi-critical-*.log' ) ?: [] as $file ) {
			@unlink( $file );
		}
		// Wipe protection files (some tests recreate them)
		foreach ( [ '.htaccess', 'web.config', 'index.php' ] as $name ) {
			@unlink( Plugin::log_dir() . '/' . $name );
		}
		// Wipe dedup transients + recent count cache via direct option query
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_loomi_log_%' OR option_name LIKE '_transient_timeout_loomi_log_%'" );
		delete_option( Loomi_Critical_Logger::LAST_PRUNE_OPTION );
		// Ensure logs dir exists for tests that need to write
		if ( ! is_dir( Plugin::log_dir() ) ) {
			@mkdir( Plugin::log_dir(), 0750, true );
		}
	}

	protected function readLogEntries( ?string $date = null ) : array {
		$date = $date ?: Loomi_Log_Writer::today();
		$path = Loomi_Log_Writer::log_path_for( $date );
		if ( ! is_readable( $path ) ) {
			return [];
		}
		$lines = file( $path, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES );
		$out   = [];
		foreach ( (array) $lines as $line ) {
			$decoded = json_decode( $line, true );
			if ( is_array( $decoded ) ) {
				$out[] = $decoded;
			}
		}
		return $out;
	}

	protected function assertLogFileHasEntries( int $expected, ?string $date = null ) : void {
		$entries = $this->readLogEntries( $date );
		self::assertCount( $expected, $entries, 'Expected ' . $expected . ' entries in log file for date ' . ( $date ?: Loomi_Log_Writer::today() ) );
	}

	protected function loomi_file_path( string $relative = 'includes/modules/class-loomi-login.php' ) : string {
		return wp_normalize_path( LOOMI_STUDIO_DIR . $relative );
	}

	protected function inject_exception_origin( Throwable $e, string $file, int $line, array $trace = [] ) : Throwable {
		$ref = new ReflectionClass( Exception::class );
		$fp  = $ref->getProperty( 'file' );
		$fp->setAccessible( true );
		$fp->setValue( $e, $file );
		$lp = $ref->getProperty( 'line' );
		$lp->setAccessible( true );
		$lp->setValue( $e, $line );
		$tp = $ref->getProperty( 'trace' );
		$tp->setAccessible( true );
		$tp->setValue( $e, $trace );
		return $e;
	}

	protected function make_loomi_exception( string $message = 'test crash', string $relative = 'includes/modules/class-loomi-login.php', int $line = 99 ) : Throwable {
		$e = new RuntimeException( $message );
		return $this->inject_exception_origin( $e, $this->loomi_file_path( $relative ), $line, [] );
	}

	protected function make_external_exception( string $message = 'external crash', int $line = 50 ) : Throwable {
		$e        = new RuntimeException( $message );
		$external = '/var/www/html/wp-content/plugins/some-other-plugin/main.php';
		return $this->inject_exception_origin( $e, $external, $line, [] );
	}

	protected function make_external_with_loomi_in_trace( string $message = 'wrapped crash' ) : Throwable {
		$e        = new RuntimeException( $message );
		$external = '/var/www/html/wp-content/plugins/some-other-plugin/main.php';
		$trace    = [
			[ 'file' => $external, 'line' => 10, 'function' => 'do_thing' ],
			[ 'file' => $this->loomi_file_path( 'includes/modules/class-loomi-login.php' ), 'line' => 42, 'function' => 'register' ],
		];
		return $this->inject_exception_origin( $e, $external, 10, $trace );
	}

	protected function make_exception_with_long_trace( string $message, int $frame_count = 200 ) : Throwable {
		$e     = new RuntimeException( $message );
		$file  = $this->loomi_file_path( 'includes/modules/class-loomi-login.php' );
		$frames = [];
		for ( $i = 0; $i < $frame_count; $i++ ) {
			$frames[] = [ 'file' => $file, 'line' => $i, 'function' => str_repeat( 'F', 60 ) ];
		}
		return $this->inject_exception_origin( $e, $file, 1, $frames );
	}
}
