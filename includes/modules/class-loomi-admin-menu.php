<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Admin_Menu {

	public static function init() : void {
		if ( ! Loomi_Settings::get( 'hide_menus_enabled' ) ) {
			return;
		}
		add_action( 'admin_menu', [ __CLASS__, 'hide_menus' ], 999 );
	}

	public static function hide_menus() : void {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$to_hide = (array) Loomi_Settings::get( 'hidden_menus', [] );
		if ( empty( $to_hide ) ) {
			return;
		}

		foreach ( $to_hide as $slug ) {
			if ( in_array( $slug, Loomi_Settings::BLACKLISTED_MENUS, true ) ) {
				continue;
			}
			if ( ! array_key_exists( $slug, Loomi_Settings::HIDEABLE_MENUS ) ) {
				continue;
			}
			remove_menu_page( $slug );
		}
	}
}
