<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	const SLUG             = 'loomi-studio-setup';
	const TEXT_DOMAIN      = 'loomi-studio-setup';
	const OPTION_KEY       = 'loomi_studio_setup_settings';
	const SETTINGS_PAGE    = 'loomi-studio-setup';
	const SETTINGS_GROUP   = 'loomi_studio';
	const NONCE_PREFIX     = 'loomi_';

	const WORDFENCE_FILE   = 'wordfence/wordfence.php';
	const UPDATE_TRANSIENT = 'loomi_update_check';
	const UPDATE_TTL       = 12 * HOUR_IN_SECONDS;
	const UPDATE_TIMEOUT   = 3;

	const LOG_DIR_NAME     = 'logs';
	const LOG_FILE_PREFIX  = 'loomi-critical-';

	public static function version() : string {
		return defined( 'LOOMI_STUDIO_VERSION' ) ? LOOMI_STUDIO_VERSION : '0.0.0';
	}

	public static function basename() : string {
		return defined( 'LOOMI_STUDIO_BASENAME' ) ? LOOMI_STUDIO_BASENAME : self::SLUG . '/' . self::SLUG . '.php';
	}

	public static function log_dir() : string {
		// Allow tests / external tooling to override the log location so PHPUnit
		// runs don't pollute the production logs/ folder served to the admin UI.
		if ( defined( 'LOOMI_LOG_DIR_OVERRIDE' ) && LOOMI_LOG_DIR_OVERRIDE ) {
			return rtrim( wp_normalize_path( (string) LOOMI_LOG_DIR_OVERRIDE ), '/' );
		}
		$base = defined( 'LOOMI_STUDIO_DIR' ) ? LOOMI_STUDIO_DIR : '';
		return rtrim( wp_normalize_path( $base ), '/' ) . '/' . self::LOG_DIR_NAME;
	}
}
