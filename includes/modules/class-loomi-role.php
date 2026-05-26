<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Role implements Loomi_Module {

	const ROLE_SLUG = 'loomi_client';

	const FORBIDDEN_CAPS = [
		'edit_theme_options', 'manage_options', 'list_users', 'edit_users', 'delete_users',
		'create_users', 'promote_users', 'install_plugins', 'activate_plugins', 'edit_plugins',
		'update_plugins', 'delete_plugins', 'switch_themes', 'install_themes', 'edit_themes',
		'update_themes', 'delete_themes', 'update_core', 'export', 'import', 'manage_links',
		'edit_files', 'unfiltered_html',
	];

	public static function register() : void {
		add_filter( 'editable_roles', [ __CLASS__, 'maybe_hide_role' ] );
	}

	public static function create() : void {
		$editor = get_role( 'editor' );
		if ( ! $editor ) return;

		$caps = $editor->capabilities;
		foreach ( self::FORBIDDEN_CAPS as $cap ) {
			unset( $caps[ $cap ] );
		}
		$caps['read'] = true;

		if ( get_role( self::ROLE_SLUG ) ) {
			remove_role( self::ROLE_SLUG );
		}
		add_role( self::ROLE_SLUG, __( 'Cliente Loomi', 'loomi-studio-setup' ), $caps );
	}

	public static function remove() : void {
		$users = get_users( [ 'role' => self::ROLE_SLUG, 'fields' => [ 'ID' ] ] );
		foreach ( $users as $user ) {
			$u = new WP_User( $user->ID );
			$u->remove_role( self::ROLE_SLUG );
			$u->add_role( 'subscriber' );
		}
		if ( get_role( self::ROLE_SLUG ) ) {
			remove_role( self::ROLE_SLUG );
		}
	}

	public static function maybe_hide_role( $roles ) {
		if ( Settings_Repository::get_bool( 'client_role_enabled' ) ) {
			return $roles;
		}
		if ( is_array( $roles ) && isset( $roles[ self::ROLE_SLUG ] ) ) {
			unset( $roles[ self::ROLE_SLUG ] );
		}
		return $roles;
	}
}
