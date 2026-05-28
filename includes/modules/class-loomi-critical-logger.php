<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Critical_Logger implements Loomi_Module {

	const FATAL_TYPES = [
		E_ERROR,
		E_PARSE,
		E_CORE_ERROR,
		E_COMPILE_ERROR,
		E_USER_ERROR,
		E_RECOVERABLE_ERROR,
	];

	const MAX_TRACE_BYTES = 2048;
	const DEDUP_LIMIT     = 10;
	const TRANSIENT_PREFIX     = 'loomi_log_dedupe_';
	const CRON_HOOK            = 'loomi_log_retention_cleanup';
	const RECENT_COUNT_KEY     = 'loomi_log_recent_count';
	const LAST_PRUNE_OPTION    = 'loomi_log_last_prune';

	const HTACCESS_CONTENT = <<<HTACCESS
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>
<FilesMatch "\.log$">
    Require all denied
</FilesMatch>
HTACCESS;

	const WEB_CONFIG_CONTENT = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <security>
      <authorization>
        <remove users="*" roles="" verbs="" />
        <add accessType="Deny" users="*" />
      </authorization>
    </security>
  </system.webServer>
</configuration>
XML;

	const INDEX_PHP_CONTENT = "<?php // Silence is golden.\n";

	public static function register() : void {
		if ( self::is_disabled() ) {
			return;
		}
		if ( ! self::ensure_log_dir() ) {
			return;
		}

		set_exception_handler( [ __CLASS__, 'on_exception' ] );
		register_shutdown_function( [ __CLASS__, 'on_shutdown' ] );

		add_action( self::CRON_HOOK, [ __CLASS__, 'prune_old_logs' ] );
		add_action( 'admin_post_loomi_download_log', [ __CLASS__, 'handle_download' ] );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function install() : void {
		if ( self::is_disabled() ) {
			return;
		}
		$dir = Plugin::log_dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return;
		}
		@chmod( $dir, 0750 );

		self::write_protection_file( $dir . '/.htaccess', self::HTACCESS_CONTENT );
		self::write_protection_file( $dir . '/web.config', self::WEB_CONFIG_CONTENT );
		self::write_protection_file( $dir . '/index.php', self::INDEX_PHP_CONTENT );

		self::write_install_entry();
	}

	public static function on_exception( $e ) : void {
		if ( ! ( $e instanceof Throwable ) ) {
			return;
		}
		try {
			if ( ! self::originates_in_loomi( (string) $e->getFile(), (array) $e->getTrace() ) ) {
				return;
			}
			self::write_entry(
				'exception',
				null,
				(string) $e->getMessage(),
				(string) $e->getFile(),
				(int) $e->getLine(),
				(string) $e->getTraceAsString()
			);
		} catch ( Throwable $ignored ) {
			// Never crash the host request because of the logger.
		}
	}

	public static function on_shutdown() : void {
		try {
			$err = self::get_last_error();
			if ( ! is_array( $err ) || empty( $err['type'] ) ) {
				return;
			}
			if ( ! in_array( (int) $err['type'], self::FATAL_TYPES, true ) ) {
				return;
			}
			$file = (string) ( $err['file'] ?? '' );
			if ( ! self::originates_in_loomi( $file, [] ) ) {
				return;
			}
			self::write_entry(
				'fatal',
				(int) $err['type'],
				(string) ( $err['message'] ?? '' ),
				$file,
				(int) ( $err['line'] ?? 0 ),
				''
			);
		} catch ( Throwable $ignored ) {
			// silent
		}
	}

	/**
	 * Indirection seam: allows tests to inject a fake last-error via a WP filter.
	 */
	protected static function get_last_error() : ?array {
		$err = error_get_last();
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter the last-error array seen by the logger. Test-only seam.
			 * @param array|null $err
			 */
			$err = apply_filters( 'loomi_log_test_last_error', $err );
		}
		return is_array( $err ) ? $err : null;
	}

	private static function originates_in_loomi( string $file, array $trace ) : bool {
		$base = wp_normalize_path( defined( 'LOOMI_STUDIO_DIR' ) ? LOOMI_STUDIO_DIR : '' );
		if ( $base === '' ) {
			return false;
		}
		if ( $file !== '' && strpos( wp_normalize_path( $file ), $base ) === 0 ) {
			return true;
		}
		foreach ( $trace as $frame ) {
			if ( ! empty( $frame['file'] ) && strpos( wp_normalize_path( (string) $frame['file'] ), $base ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	private static function severity_name( ?int $type ) : ?string {
		if ( $type === null ) {
			return null;
		}
		$map = [
			E_ERROR             => 'E_ERROR',
			E_WARNING           => 'E_WARNING',
			E_PARSE             => 'E_PARSE',
			E_NOTICE            => 'E_NOTICE',
			E_CORE_ERROR        => 'E_CORE_ERROR',
			E_CORE_WARNING      => 'E_CORE_WARNING',
			E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
			E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
			E_USER_ERROR        => 'E_USER_ERROR',
			E_USER_WARNING      => 'E_USER_WARNING',
			E_USER_NOTICE       => 'E_USER_NOTICE',
			E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
			E_DEPRECATED        => 'E_DEPRECATED',
			E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
		];
		return $map[ $type ] ?? ( 'E_UNKNOWN_' . $type );
	}

	private static function write_entry( string $type, ?int $severity_type, string $message, string $file, int $line, string $trace ) : void {
		if ( strlen( $trace ) > self::MAX_TRACE_BYTES ) {
			$trace = substr( $trace, 0, self::MAX_TRACE_BYTES );
		}

		$hash     = substr( hash( 'sha256', $file . ':' . $line . ':' . $message ), 0, 16 );
		$date_key = Loomi_Log_Writer::today();
		$key      = self::TRANSIENT_PREFIX . $hash . '_' . $date_key;

		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, DAY_IN_SECONDS );

		if ( $count >= self::DEDUP_LIMIT ) {
			return;
		}

		$entry = [
			'ts'          => Loomi_Log_Context::timestamp(),
			'type'        => $type,
			'severity'    => self::severity_name( $severity_type ),
			'message'     => $message,
			'file'        => $file,
			'line'        => $line,
			'trace'       => $trace,
			'user'        => Loomi_Log_Context::user(),
			'request'     => Loomi_Log_Context::request(),
			'env'         => Loomi_Log_Context::env(),
			'dedupe_hash' => $hash,
		];

		$json = wp_json_encode( $entry );
		if ( $json === false ) {
			// Fallback: try removing trace (most likely culprit for malformed UTF-8).
			$entry['trace'] = '';
			$json = wp_json_encode( $entry );
			if ( $json === false ) {
				return;
			}
		}

		Loomi_Log_Writer::append_line( $json );
	}

	private static function write_install_entry() : void {
		$entry = [
			'ts'          => Loomi_Log_Context::timestamp(),
			'type'        => 'install',
			'severity'    => null,
			'message'     => 'Plugin activated',
			'file'        => '',
			'line'        => 0,
			'trace'       => '',
			'user'        => Loomi_Log_Context::user(),
			'request'     => Loomi_Log_Context::request(),
			'env'         => Loomi_Log_Context::env(),
			'dedupe_hash' => '',
		];
		$json = wp_json_encode( $entry );
		if ( $json === false ) {
			return;
		}
		Loomi_Log_Writer::append_line( $json );
	}

	public static function prune_old_logs() : void {
		try {
			self::write_dedup_summaries();

			$days   = self::retention_days();
			$cutoff = time() - ( $days * DAY_IN_SECONDS );
			foreach ( Loomi_Log_Writer::list_log_files() as $file ) {
				$mtime = @filemtime( $file );
				if ( $mtime !== false && $mtime < $cutoff ) {
					@unlink( $file );
				}
			}
			update_option( self::LAST_PRUNE_OPTION, time(), false );
			delete_transient( self::RECENT_COUNT_KEY );
		} catch ( Throwable $ignored ) {
			// silent
		}
	}

	public static function write_dedup_summaries() : void {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return;
		}

		$transient_prefix = '_transient_' . self::TRANSIENT_PREFIX;
		$site_prefix      = '_site_transient_' . self::TRANSIENT_PREFIX;
		$like1            = $wpdb->esc_like( $transient_prefix ) . '%';
		$like2            = $wpdb->esc_like( $site_prefix ) . '%';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$like1,
				$like2
			)
		);
		if ( ! is_array( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$name  = (string) $row->option_name;
			$count = (int) $row->option_value;
			if ( $count <= self::DEDUP_LIMIT ) {
				continue;
			}
			// Parse "_transient_loomi_log_dedupe_<hash>_<YYYY-MM-DD>"
			$inner = preg_replace( '/^_(?:site_)?transient_/', '', $name );
			$key   = substr( $inner, strlen( self::TRANSIENT_PREFIX ) );
			if ( ! preg_match( '/^([0-9a-f]{16})_(\d{4}-\d{2}-\d{2})$/', $key, $m ) ) {
				continue;
			}
			$hash = $m[1];
			$date = $m[2];

			$entry = [
				'ts'          => Loomi_Log_Context::timestamp(),
				'type'        => 'summary',
				'severity'    => null,
				'message'     => 'Dedup summary',
				'file'        => '',
				'line'        => 0,
				'trace'       => '',
				'user'        => [ 'id' => null, 'login' => '', 'role' => 'system' ],
				'request'     => [ 'uri' => '', 'method' => 'CRON', 'ua' => '', 'ip' => '', 'xff' => '' ],
				'env'         => Loomi_Log_Context::env(),
				'dedupe_hash' => $hash,
				'date'        => $date,
				'repeated'    => $count - self::DEDUP_LIMIT,
			];
			$json = wp_json_encode( $entry );
			if ( $json !== false ) {
				// Append to the SOURCE day's file (not necessarily today), to keep evidence on the right date.
				$path = Loomi_Log_Writer::log_path_for( $date );
				$fp   = @fopen( $path, 'ab' );
				if ( $fp ) {
					try {
						if ( flock( $fp, LOCK_EX ) ) {
							fwrite( $fp, $json . "\n" );
							fflush( $fp );
							flock( $fp, LOCK_UN );
						}
					} finally {
						fclose( $fp );
					}
					@chmod( $path, 0640 );
				}
			}
			delete_transient( self::TRANSIENT_PREFIX . $hash . '_' . $date );
		}
	}

	public static function handle_download() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden', 'Forbidden', [ 'response' => 403 ] );
		}
		check_admin_referer( 'loomi_download_log' );

		$date = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : Loomi_Log_Writer::today();
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			wp_die( 'Bad date', 'Bad date', [ 'response' => 400 ] );
		}

		$path = Loomi_Log_Writer::log_path_for( $date );
		if ( ! is_readable( $path ) ) {
			wp_die( 'Not found', 'Not found', [ 'response' => 404 ] );
		}

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename="' . Plugin::LOG_FILE_PREFIX . $date . '.log"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path );
		exit;
	}

	public static function count_recent_events( int $days = 7 ) : int {
		$cached = get_transient( self::RECENT_COUNT_KEY );
		if ( $cached !== false ) {
			return (int) $cached;
		}

		$cutoff = time() - ( $days * DAY_IN_SECONDS );
		$count  = 0;
		foreach ( Loomi_Log_Writer::list_log_files() as $file ) {
			$mtime = @filemtime( $file );
			if ( $mtime === false || $mtime < $cutoff ) {
				continue;
			}
			$lines = @file( $file, FILE_SKIP_EMPTY_LINES );
			if ( is_array( $lines ) ) {
				$count += count( $lines );
			}
		}
		set_transient( self::RECENT_COUNT_KEY, $count, HOUR_IN_SECONDS );
		return $count;
	}

	/**
	 * List parsed critical events within the last $days, newest first.
	 *
	 * Reads NDJSON log files, decodes each line, and returns up to $limit entries.
	 * Malformed lines are skipped silently. Used by the Logs settings tab.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_recent_events( int $days = 7, int $limit = 50 ) : array {
		$cutoff  = time() - ( $days * DAY_IN_SECONDS );
		$entries = [];
		foreach ( Loomi_Log_Writer::list_log_files() as $file ) {
			$mtime = @filemtime( $file );
			if ( $mtime === false || $mtime < $cutoff ) {
				continue;
			}
			$lines = @file( $file, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES );
			if ( ! is_array( $lines ) ) {
				continue;
			}
			foreach ( $lines as $line ) {
				$decoded = json_decode( $line, true );
				if ( ! is_array( $decoded ) ) {
					continue;
				}
				$entries[] = $decoded;
			}
		}

		// Sort by timestamp descending (newest first). Fall back to insertion order if ts missing.
		usort( $entries, static function ( $a, $b ) {
			$ta = isset( $a['ts'] ) ? strtotime( (string) $a['ts'] ) : 0;
			$tb = isset( $b['ts'] ) ? strtotime( (string) $b['ts'] ) : 0;
			return $tb <=> $ta;
		} );

		if ( count( $entries ) > $limit ) {
			$entries = array_slice( $entries, 0, $limit );
		}
		return $entries;
	}

	public static function retention_days() : int {
		return defined( 'LOOMI_LOG_RETENTION_DAYS' ) ? (int) LOOMI_LOG_RETENTION_DAYS : 30;
	}

	public static function is_disabled() : bool {
		return defined( 'LOOMI_LOG_DISABLED' ) && LOOMI_LOG_DISABLED;
	}

	public static function ensure_log_dir() : bool {
		$dir = Plugin::log_dir();
		if ( ! is_dir( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				return false;
			}
			@chmod( $dir, 0750 );
			// Restore protection artifacts if dir had to be recreated outside of activation.
			self::write_protection_file( $dir . '/.htaccess', self::HTACCESS_CONTENT );
			self::write_protection_file( $dir . '/web.config', self::WEB_CONFIG_CONTENT );
			self::write_protection_file( $dir . '/index.php', self::INDEX_PHP_CONTENT );
		}
		return is_writable( $dir );
	}

	private static function write_protection_file( string $path, string $content ) : void {
		if ( ! is_dir( dirname( $path ) ) ) {
			return;
		}
		@file_put_contents( $path, $content );
		@chmod( $path, 0640 );
	}
}
