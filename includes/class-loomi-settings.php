<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @deprecated 1.1.0 Use Settings_Repository / Settings_Sanitizer / Loomi_Settings_Page directly.
 * Kept as thin alias for backwards-compat. Will be removed in 1.2.0.
 */
class Loomi_Settings {

	const OPTION_KEY     = Plugin::OPTION_KEY;
	const OPTION_GROUP   = Plugin::SETTINGS_GROUP;
	const PAGE_SLUG      = Plugin::SETTINGS_PAGE;
	const PAGE_HOOK      = Loomi_Settings_Page::PAGE_HOOK;
	const HIDEABLE_MENUS = Settings_Repository::HIDEABLE_MENUS;
	const BLACKLISTED_MENUS = Settings_Repository::BLACKLISTED_MENUS;
	const RESERVED_SLUGS = Settings_Repository::RESERVED_SLUGS;

	public static function defaults() : array {
		_deprecated_function( __METHOD__, '1.1.0', 'Settings_Repository::defaults' );
		return Settings_Repository::defaults();
	}

	public static function all() : array {
		_deprecated_function( __METHOD__, '1.1.0', 'Settings_Repository::all' );
		return Settings_Repository::all();
	}

	public static function get( string $key, $default = null ) {
		_deprecated_function( __METHOD__, '1.1.0', 'Settings_Repository::get' );
		return Settings_Repository::get( $key, $default );
	}

	public static function clear_cache() : void {
		_deprecated_function( __METHOD__, '1.1.0', 'Settings_Repository::clear_cache' );
		Settings_Repository::clear_cache();
	}

	public static function sanitize( $input ) : array {
		_deprecated_function( __METHOD__, '1.1.0', 'Settings_Sanitizer::sanitize' );
		return Settings_Sanitizer::sanitize( $input );
	}
}
