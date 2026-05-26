<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Admin_Menu implements Loomi_Module {

	public static function register() : void {
		if ( ! Settings_Repository::get_bool( 'hide_menus_enabled' ) ) {
			return;
		}
		add_action( 'admin_menu', [ __CLASS__, 'hide_menus' ], 999 );
	}

	public static function hide_menus() : void {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		$to_hide = (array) Settings_Repository::get( 'hidden_menus', [] );
		$allowed = Settings_Repository::hideable_menus(); // core + CPTs dinâmicos
		foreach ( $to_hide as $slug ) {
			if ( in_array( $slug, Settings_Repository::BLACKLISTED_MENUS, true ) ) continue;
			if ( ! array_key_exists( $slug, $allowed ) ) continue;
			remove_menu_page( $slug );
		}
	}
}
