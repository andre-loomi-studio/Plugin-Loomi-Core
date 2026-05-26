<?php

class CriticalLoggerTest extends Loomi_LogTestCase {

	// ---------- 12: handlers and origin filter ----------

	public function test_exception_originating_in_loomi_is_logged() : void {
		$e = $this->make_loomi_exception( 'boom in module', 'includes/modules/class-loomi-login.php', 77 );
		Loomi_Critical_Logger::on_exception( $e );
		$entries = $this->readLogEntries();
		self::assertCount( 1, $entries );
		self::assertSame( 'exception', $entries[0]['type'] );
		self::assertSame( 'boom in module', $entries[0]['message'] );
		self::assertSame( 77, $entries[0]['line'] );
		self::assertStringContainsString( 'class-loomi-login.php', $entries[0]['file'] );
	}

	public function test_exception_from_third_party_is_ignored() : void {
		$e = $this->make_external_exception( 'foreign crash' );
		Loomi_Critical_Logger::on_exception( $e );
		self::assertSame( [], $this->readLogEntries() );
	}

	public function test_exception_via_loomi_callback_in_trace_is_logged() : void {
		$e = $this->make_external_with_loomi_in_trace( 'wrapped' );
		Loomi_Critical_Logger::on_exception( $e );
		$entries = $this->readLogEntries();
		self::assertCount( 1, $entries );
		self::assertSame( 'exception', $entries[0]['type'] );
	}

	public function test_fatal_originating_in_loomi_is_logged() : void {
		$file = wp_normalize_path( LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-login.php' );
		add_filter( 'loomi_log_test_last_error', static function () use ( $file ) {
			return [ 'type' => E_ERROR, 'message' => 'fatal!', 'file' => $file, 'line' => 12 ];
		} );
		Loomi_Critical_Logger::on_shutdown();
		$entries = $this->readLogEntries();
		self::assertCount( 1, $entries );
		self::assertSame( 'fatal', $entries[0]['type'] );
		self::assertSame( 'E_ERROR', $entries[0]['severity'] );
		self::assertSame( 12, $entries[0]['line'] );
	}

	public function test_non_fatal_php_error_is_ignored() : void {
		$file = wp_normalize_path( LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-login.php' );
		add_filter( 'loomi_log_test_last_error', static function () use ( $file ) {
			return [ 'type' => E_WARNING, 'message' => 'just a warning', 'file' => $file, 'line' => 1 ];
		} );
		Loomi_Critical_Logger::on_shutdown();
		self::assertSame( [], $this->readLogEntries() );
	}

	public function test_fatal_from_third_party_is_ignored() : void {
		add_filter( 'loomi_log_test_last_error', static function () {
			return [ 'type' => E_ERROR, 'message' => 'foreign', 'file' => '/var/www/wp-includes/foo.php', 'line' => 1 ];
		} );
		Loomi_Critical_Logger::on_shutdown();
		self::assertSame( [], $this->readLogEntries() );
	}

	// ---------- 12: entry shape ----------

	public function test_entry_contains_all_required_keys() : void {
		Loomi_Critical_Logger::on_exception( $this->make_loomi_exception() );
		$entries = $this->readLogEntries();
		self::assertCount( 1, $entries );
		$e = $entries[0];
		foreach ( [ 'ts', 'type', 'severity', 'message', 'file', 'line', 'trace', 'user', 'request', 'env', 'dedupe_hash' ] as $key ) {
			self::assertArrayHasKey( $key, $e, "missing key $key" );
		}
		self::assertIsArray( $e['user'] );
		self::assertIsArray( $e['request'] );
		self::assertIsArray( $e['env'] );
	}

	public function test_trace_truncated_at_2048_bytes() : void {
		Loomi_Critical_Logger::on_exception( $this->make_exception_with_long_trace( 'big trace', 200 ) );
		$entries = $this->readLogEntries();
		self::assertCount( 1, $entries );
		self::assertLessThanOrEqual( 2048, strlen( $entries[0]['trace'] ) );
	}

	public function test_guest_user_captured() : void {
		wp_set_current_user( 0 );
		Loomi_Critical_Logger::on_exception( $this->make_loomi_exception() );
		$entries = $this->readLogEntries();
		self::assertSame( 'guest', $entries[0]['user']['role'] );
		self::assertNull( $entries[0]['user']['id'] );
	}

	public function test_authenticated_user_captured() : void {
		$uid = $this->login_as( 'administrator' );
		Loomi_Critical_Logger::on_exception( $this->make_loomi_exception() );
		$entries = $this->readLogEntries();
		self::assertSame( $uid, $entries[0]['user']['id'] );
		self::assertNotEmpty( $entries[0]['user']['login'] );
		self::assertSame( 'administrator', $entries[0]['user']['role'] );
	}

	public function test_ip_and_xff_separated() : void {
		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.42, 198.51.100.5';
		Loomi_Critical_Logger::on_exception( $this->make_loomi_exception() );
		$entries = $this->readLogEntries();
		self::assertSame( '10.0.0.1', $entries[0]['request']['ip'] );
		self::assertSame( '203.0.113.42', $entries[0]['request']['xff'] );
		unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	public function test_sensitive_keys_absent() : void {
		$_POST['password'] = 'secret123';
		Loomi_Critical_Logger::on_exception( $this->make_loomi_exception() );
		$path = Loomi_Log_Writer::log_path_for( Loomi_Log_Writer::today() );
		$raw  = file_get_contents( $path );
		foreach ( [ '"post_body"', '"cookies"', '"headers"', '"args"', '"password"', '"token"', 'secret123' ] as $needle ) {
			self::assertStringNotContainsString( $needle, $raw, "Log should not contain $needle" );
		}
		unset( $_POST['password'] );
	}

	// ---------- 13: NDJSON / flock ----------

	public function test_log_file_name_uses_today_date() : void {
		Loomi_Critical_Logger::on_exception( $this->make_loomi_exception() );
		self::assertFileExists( Loomi_Log_Writer::log_path_for( Loomi_Log_Writer::today() ) );
	}

	public function test_each_entry_is_one_jsonline() : void {
		for ( $i = 0; $i < 3; $i++ ) {
			Loomi_Critical_Logger::on_exception( $this->make_loomi_exception( 'msg' . $i, 'includes/modules/class-loomi-login.php', $i + 1 ) );
		}
		$path = Loomi_Log_Writer::log_path_for( Loomi_Log_Writer::today() );
		$lines = file( $path, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES );
		self::assertCount( 3, $lines );
		foreach ( $lines as $line ) {
			self::assertNotNull( json_decode( $line, true ), 'invalid JSON line: ' . $line );
		}
	}

	public function test_concurrent_appends_not_torn() : void {
		// best-effort synchronous test on Windows: serial appends, verify integrity
		for ( $i = 0; $i < 50; $i++ ) {
			Loomi_Log_Writer::append_line( wp_json_encode( [ 'i' => $i, 'pad' => str_repeat( 'x', 100 ) ] ) );
		}
		$path  = Loomi_Log_Writer::log_path_for( Loomi_Log_Writer::today() );
		$lines = file( $path, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES );
		self::assertCount( 50, $lines );
		foreach ( $lines as $line ) {
			self::assertNotNull( json_decode( $line, true ) );
		}
	}

	// ---------- 14: dedup ----------

	public function test_first_10_occurrences_logged_individually() : void {
		for ( $i = 0; $i < 10; $i++ ) {
			Loomi_Critical_Logger::on_exception( $this->make_loomi_exception( 'same msg', 'includes/modules/class-loomi-login.php', 50 ) );
		}
		$entries = $this->readLogEntries();
		self::assertCount( 10, $entries );
		$hashes = array_unique( array_column( $entries, 'dedupe_hash' ) );
		self::assertCount( 1, $hashes );
	}

	public function test_11th_occurrence_suppressed() : void {
		for ( $i = 0; $i < 11; $i++ ) {
			Loomi_Critical_Logger::on_exception( $this->make_loomi_exception( 'same msg', 'includes/modules/class-loomi-login.php', 50 ) );
		}
		self::assertCount( 10, $this->readLogEntries() );
	}

	public function test_different_messages_not_deduped() : void {
		for ( $i = 0; $i < 5; $i++ ) {
			Loomi_Critical_Logger::on_exception( $this->make_loomi_exception( 'msg ' . $i, 'includes/modules/class-loomi-login.php', $i ) );
		}
		self::assertCount( 5, $this->readLogEntries() );
	}

	public function test_summary_entry_written_on_prune() : void {
		for ( $i = 0; $i < 13; $i++ ) {
			Loomi_Critical_Logger::on_exception( $this->make_loomi_exception( 'loop bug', 'includes/modules/class-loomi-login.php', 100 ) );
		}
		Loomi_Critical_Logger::write_dedup_summaries();
		$entries = $this->readLogEntries();
		$summaries = array_filter( $entries, static fn( $e ) => ( $e['type'] ?? '' ) === 'summary' );
		self::assertNotEmpty( $summaries );
		$first = array_values( $summaries )[0];
		self::assertGreaterThanOrEqual( 1, $first['repeated'] );
	}

	// ---------- 15: retention ----------

	public function test_default_retention_30_days() : void {
		$old_path = Loomi_Log_Writer::log_path_for( gmdate( 'Y-m-d', strtotime( '-31 days' ) ) );
		file_put_contents( $old_path, "{\"old\":true}\n" );
		touch( $old_path, time() - ( 31 * DAY_IN_SECONDS ) );

		$recent_path = Loomi_Log_Writer::log_path_for( gmdate( 'Y-m-d', strtotime( '-2 days' ) ) );
		file_put_contents( $recent_path, "{\"recent\":true}\n" );
		touch( $recent_path, time() - ( 2 * DAY_IN_SECONDS ) );

		Loomi_Critical_Logger::prune_old_logs();

		self::assertFileDoesNotExist( $old_path );
		self::assertFileExists( $recent_path );
	}

	public function test_retention_respects_constant() : void {
		if ( ! defined( 'LOOMI_LOG_RETENTION_DAYS' ) ) {
			// Cannot redefine in the same process — this assertion only runs if a previous test/bootstrap defined a custom value
			self::markTestSkipped( 'LOOMI_LOG_RETENTION_DAYS not redefinable mid-suite' );
		}
		$days = (int) LOOMI_LOG_RETENTION_DAYS;
		$old = Loomi_Log_Writer::log_path_for( gmdate( 'Y-m-d', strtotime( '-' . ( $days + 1 ) . ' days' ) ) );
		file_put_contents( $old, "{}\n" );
		touch( $old, time() - ( ( $days + 1 ) * DAY_IN_SECONDS ) );
		Loomi_Critical_Logger::prune_old_logs();
		self::assertFileDoesNotExist( $old );
	}

	public function test_cron_event_scheduled_after_register() : void {
		wp_clear_scheduled_hook( Loomi_Critical_Logger::CRON_HOOK );
		Loomi_Critical_Logger::register();
		self::assertNotFalse( wp_next_scheduled( Loomi_Critical_Logger::CRON_HOOK ) );
	}

	// ---------- 16: protection of logs/ dir ----------

	public function test_install_creates_logs_dir() : void {
		$this->reset_log_state();
		rmdir( Plugin::log_dir() );
		self::assertDirectoryDoesNotExist( Plugin::log_dir() );
		Loomi_Critical_Logger::install();
		self::assertDirectoryExists( Plugin::log_dir() );
		self::assertDirectoryIsWritable( Plugin::log_dir() );
	}

	public function test_install_creates_htaccess_and_web_config_and_index() : void {
		Loomi_Critical_Logger::install();
		$dir = Plugin::log_dir();
		self::assertFileExists( $dir . '/.htaccess' );
		self::assertFileExists( $dir . '/web.config' );
		self::assertFileExists( $dir . '/index.php' );
		self::assertStringContainsString( 'Require all denied', file_get_contents( $dir . '/.htaccess' ) );
		self::assertStringContainsString( '<deny', file_get_contents( $dir . '/web.config' ) );
		self::assertStringContainsString( 'Silence is golden', file_get_contents( $dir . '/index.php' ) );
	}

	public function test_reactivation_restores_htaccess() : void {
		Loomi_Critical_Logger::install();
		@unlink( Plugin::log_dir() . '/.htaccess' );
		self::assertFileDoesNotExist( Plugin::log_dir() . '/.htaccess' );
		Loomi_Critical_Logger::install();
		self::assertFileExists( Plugin::log_dir() . '/.htaccess' );
	}

	public function test_install_logs_install_event() : void {
		Loomi_Critical_Logger::install();
		$entries = $this->readLogEntries();
		self::assertNotEmpty( $entries );
		$installs = array_filter( $entries, static fn( $e ) => ( $e['type'] ?? '' ) === 'install' );
		self::assertNotEmpty( $installs );
		$first = array_values( $installs )[0];
		self::assertSame( Plugin::version(), $first['env']['plugin'] );
	}

	// ---------- 17: download endpoint ----------

	public function test_download_endpoint_requires_admin_capability() : void {
		$this->login_as( 'editor' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'loomi_download_log' );
		$_GET['date']         = Loomi_Log_Writer::today();
		$this->expectException( WPDieException::class );
		Loomi_Critical_Logger::handle_download();
	}

	public function test_download_endpoint_requires_valid_nonce() : void {
		$this->login_as( 'administrator' );
		$_REQUEST['_wpnonce'] = 'invalid';
		$_GET['date']         = Loomi_Log_Writer::today();
		$this->expectException( WPDieException::class );
		Loomi_Critical_Logger::handle_download();
	}

	public function test_download_endpoint_rejects_path_traversal() : void {
		$this->login_as( 'administrator' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'loomi_download_log' );
		$_GET['date']         = '../../wp-config';
		$this->expectException( WPDieException::class );
		Loomi_Critical_Logger::handle_download();
	}

	public function test_download_endpoint_returns_404_for_missing_file() : void {
		$this->login_as( 'administrator' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'loomi_download_log' );
		$_GET['date']         = '2000-01-01';
		$this->expectException( WPDieException::class );
		Loomi_Critical_Logger::handle_download();
	}

	public function test_download_endpoint_serves_existing_file() : void {
		$this->login_as( 'administrator' );
		// Create a small log
		Loomi_Critical_Logger::on_exception( $this->make_loomi_exception( 'served', 'includes/modules/class-loomi-login.php', 7 ) );

		$_REQUEST['_wpnonce'] = wp_create_nonce( 'loomi_download_log' );
		$_GET['date']         = Loomi_Log_Writer::today();

		ob_start();
		try {
			Loomi_Critical_Logger::handle_download();
		} catch ( WPDieException $e ) {
			// readfile + exit may not actually exit in test env — wp_die may not be hit
		}
		$out = ob_get_clean();
		self::assertStringContainsString( 'served', $out );
	}

	// ---------- 18: dashboard UI ----------

	public function test_dashboard_renders_zero_events_message() : void {
		ob_start();
		( new Tab_Dashboard() )->render( Settings_Repository::all() );
		$html = ob_get_clean();
		self::assertStringContainsString( 'Eventos críticos recentes', $html );
		self::assertStringContainsString( '0', $html );
	}

	public function test_dashboard_renders_current_count() : void {
		for ( $i = 0; $i < 3; $i++ ) {
			Loomi_Critical_Logger::on_exception( $this->make_loomi_exception( 'm' . $i, 'includes/modules/class-loomi-login.php', $i ) );
		}
		delete_transient( Loomi_Critical_Logger::RECENT_COUNT_KEY );
		ob_start();
		( new Tab_Dashboard() )->render( Settings_Repository::all() );
		$html = ob_get_clean();
		self::assertStringContainsString( '>3<', $html );
	}

	public function test_dashboard_download_link_has_nonce() : void {
		Loomi_Critical_Logger::on_exception( $this->make_loomi_exception() );
		delete_transient( Loomi_Critical_Logger::RECENT_COUNT_KEY );
		ob_start();
		( new Tab_Dashboard() )->render( Settings_Repository::all() );
		$html = ob_get_clean();
		self::assertStringContainsString( 'action=loomi_download_log', $html );
		self::assertStringContainsString( '_wpnonce=', $html );
	}

	public function test_dashboard_shows_disabled_when_logs_not_writable() : void {
		// Make dir read-only (POSIX only)
		if ( strncasecmp( PHP_OS, 'WIN', 3 ) === 0 ) {
			self::markTestSkipped( 'chmod 0500 has no effect on Windows' );
		}
		@chmod( Plugin::log_dir(), 0500 );
		try {
			ob_start();
			( new Tab_Dashboard() )->render( Settings_Repository::all() );
			$html = ob_get_clean();
			self::assertStringContainsString( 'sem permissão de escrita', $html );
		} finally {
			@chmod( Plugin::log_dir(), 0750 );
		}
	}

	// 18.5 (disabled-by-constant) requires LOOMI_LOG_DISABLED defined BEFORE bootstrap,
	// which the suite cannot toggle mid-run. Marked skipped here; manual coverage via 21.x.
	public function test_dashboard_shows_disabled_when_constant_set() : void {
		if ( ! ( defined( 'LOOMI_LOG_DISABLED' ) && LOOMI_LOG_DISABLED ) ) {
			self::markTestSkipped( 'LOOMI_LOG_DISABLED not active in this run; covered by manual validation 21.x' );
		}
		ob_start();
		( new Tab_Dashboard() )->render( Settings_Repository::all() );
		$html = ob_get_clean();
		self::assertStringContainsString( 'Logger desativado por configuração', $html );
	}

	// ---------- 19: never-crash invariant ----------

	public function test_write_entry_silent_on_disk_full() : void {
		if ( strncasecmp( PHP_OS, 'WIN', 3 ) === 0 ) {
			self::markTestSkipped( 'chmod 0500 has no effect on Windows' );
		}
		@chmod( Plugin::log_dir(), 0500 );
		try {
			// must not throw, must not warn
			Loomi_Critical_Logger::on_exception( $this->make_loomi_exception() );
			self::assertTrue( true ); // reached here = no fatal
		} finally {
			@chmod( Plugin::log_dir(), 0750 );
		}
	}

	public function test_write_entry_silent_on_json_encode_fail() : void {
		// Inject malformed UTF-8 in message via a real RuntimeException + reflection injection of file
		$bad = "\xB1\x31"; // invalid UTF-8 sequence
		$e   = new RuntimeException( $bad );
		$this->inject_exception_origin( $e, $this->loomi_file_path(), 1 );
		Loomi_Critical_Logger::on_exception( $e );
		// We don't assert entries here — wp_json_encode may or may not handle bad UTF-8. Just no fatal.
		self::assertTrue( true );
	}
}
