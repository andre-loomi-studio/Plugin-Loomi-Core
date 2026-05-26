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

	public static function version() : string {
		return defined( 'LOOMI_STUDIO_VERSION' ) ? LOOMI_STUDIO_VERSION : '0.0.0';
	}

	public static function basename() : string {
		return defined( 'LOOMI_STUDIO_BASENAME' ) ? LOOMI_STUDIO_BASENAME : self::SLUG . '/' . self::SLUG . '.php';
	}
}
