<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Log_Writer {

	/**
	 * Append one NDJSON line to today's log file. Best-effort: returns false on any I/O failure
	 * (disk full, no permissions, etc.) without throwing — the logger must never crash the host request.
	 */
	public static function append_line( string $json_line ) : bool {
		$path = self::log_path_for( self::today() );
		$fp   = @fopen( $path, 'ab' );
		if ( ! $fp ) {
			return false;
		}
		$ok = false;
		try {
			if ( flock( $fp, LOCK_EX ) ) {
				$bytes = fwrite( $fp, $json_line . "\n" );
				fflush( $fp );
				flock( $fp, LOCK_UN );
				$ok = ( $bytes !== false );
			}
		} catch ( Throwable $e ) {
			$ok = false;
		} finally {
			fclose( $fp );
		}
		@chmod( $path, 0640 );
		return $ok;
	}

	public static function log_path_for( string $date_ymd ) : string {
		return Plugin::log_dir() . '/' . Plugin::LOG_FILE_PREFIX . $date_ymd . '.log';
	}

	public static function date_from_filename( string $filename ) : ?string {
		$base = basename( $filename );
		$prefix = Plugin::LOG_FILE_PREFIX;
		if ( strpos( $base, $prefix ) !== 0 ) {
			return null;
		}
		$rest = substr( $base, strlen( $prefix ) );
		if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2})\.log$/', $rest, $m ) ) {
			return null;
		}
		return $m[1];
	}

	/**
	 * @return string[] absolute paths of existing log files, sorted ascending by date.
	 */
	public static function list_log_files() : array {
		$pattern = Plugin::log_dir() . '/' . Plugin::LOG_FILE_PREFIX . '*.log';
		$files   = glob( $pattern ) ?: [];
		sort( $files );
		return $files;
	}

	public static function today() : string {
		if ( function_exists( 'current_time' ) ) {
			return gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) );
		}
		return gmdate( 'Y-m-d' );
	}
}
