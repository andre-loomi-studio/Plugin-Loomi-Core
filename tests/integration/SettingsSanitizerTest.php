<?php

class SettingsSanitizerTest extends Loomi_TestCase {

	public function test_valid_hex_color_accepted() : void {
		$out = Settings_Sanitizer::sanitize( [ 'custom_login_bg_color' => '#ff0044' ] );
		self::assertSame( '#ff0044', $out['custom_login_bg_color'] );
	}

	public function test_invalid_color_keeps_previous_value() : void {
		$this->set_settings( [ 'custom_login_bg_color' => '#abcdef' ] );
		$out = Settings_Sanitizer::sanitize( [ 'custom_login_bg_color' => 'red; background:url(evil)' ] );
		self::assertSame( '#abcdef', $out['custom_login_bg_color'] );
	}

	public function test_reserved_slug_rejected() : void {
		$this->set_settings( [ 'login_slug' => 'studio-access' ] );
		$out = Settings_Sanitizer::sanitize( [ 'login_slug' => 'wp-admin' ] );
		self::assertSame( 'studio-access', $out['login_slug'] );
	}

	public function test_slug_with_spaces_sanitized() : void {
		$out = Settings_Sanitizer::sanitize( [ 'login_slug' => 'Studio Access' ] );
		self::assertSame( 'studio-access', $out['login_slug'] );
	}

	public function test_unknown_menu_slug_filtered() : void {
		$out = Settings_Sanitizer::sanitize( [
			'hidden_menus' => [ 'edit-comments.php', 'evil.php', 'tools.php' ],
		] );
		self::assertContains( 'edit-comments.php', $out['hidden_menus'] );
		self::assertContains( 'tools.php', $out['hidden_menus'] );
		self::assertNotContains( 'evil.php', $out['hidden_menus'] );
	}

	public function test_blacklisted_menu_slug_filtered() : void {
		$out = Settings_Sanitizer::sanitize( [
			'hidden_menus' => [ 'index.php', 'options-general.php', 'tools.php' ],
		] );
		self::assertNotContains( 'index.php', $out['hidden_menus'] );
		self::assertNotContains( 'options-general.php', $out['hidden_menus'] );
		self::assertContains( 'tools.php', $out['hidden_menus'] );
	}

	public function test_boolean_toggles_coerced() : void {
		$out = Settings_Sanitizer::sanitize( [
			'custom_login_enabled' => '1',
			'login_slug_enabled'   => '',
			'hide_menus_enabled'   => 'on',
			'client_role_enabled'  => null,
		] );
		self::assertTrue( $out['custom_login_enabled'] );
		self::assertFalse( $out['login_slug_enabled'] );
		self::assertTrue( $out['hide_menus_enabled'] );
		self::assertFalse( $out['client_role_enabled'] );
	}

	public function test_attachment_id_parsed_as_int() : void {
		$out = Settings_Sanitizer::sanitize( [ 'custom_login_logo_id' => '42' ] );
		self::assertSame( 42, $out['custom_login_logo_id'] );
	}

	public function test_registered_cpt_slug_accepted() : void {
		register_post_type( 'mock_cpt_san', [
			'public' => true, 'show_ui' => true, 'show_in_menu' => true,
			'labels' => [ 'name' => 'Mock' ],
		] );
		Settings_Repository::clear_cache();

		$out = Settings_Sanitizer::sanitize( [
			'hidden_menus' => [ 'edit.php?post_type=mock_cpt_san', 'tools.php' ],
		] );
		self::assertContains( 'edit.php?post_type=mock_cpt_san', $out['hidden_menus'] );
		self::assertContains( 'tools.php', $out['hidden_menus'] );

		unregister_post_type( 'mock_cpt_san' );
		Settings_Repository::clear_cache();
	}

	public function test_unregistered_cpt_slug_filtered() : void {
		Settings_Repository::clear_cache();
		$out = Settings_Sanitizer::sanitize( [
			'hidden_menus' => [ 'edit.php?post_type=does_not_exist', 'tools.php' ],
		] );
		self::assertNotContains( 'edit.php?post_type=does_not_exist', $out['hidden_menus'] );
		self::assertContains( 'tools.php', $out['hidden_menus'] );
	}
}
