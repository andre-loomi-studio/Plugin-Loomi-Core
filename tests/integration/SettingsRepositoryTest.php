<?php

class SettingsRepositoryTest extends Loomi_TestCase {

	public function test_defaults_returned_when_option_missing() : void {
		delete_option( Plugin::OPTION_KEY );
		Settings_Repository::clear_cache();

		$all = Settings_Repository::all();
		self::assertSame( 'studio-access', $all['login_slug'] );
		self::assertTrue( $all['login_slug_enabled'] );
		self::assertTrue( $all['client_role_enabled'] );
		self::assertFalse( $all['custom_login_enabled'] );
		self::assertTrue( $all['hide_admin_endpoint'] );
		self::assertTrue( $all['anti_spam_enabled'] );
		self::assertTrue( $all['anti_spam_honeypot'] );
		self::assertTrue( $all['anti_spam_time_check'] );
		self::assertTrue( $all['anti_spam_comment_lockdown'] );
		self::assertTrue( $all['anti_spam_akismet_autoconfig'] );
	}

	public function test_hide_admin_endpoint_default_is_true() : void {
		delete_option( Plugin::OPTION_KEY );
		Settings_Repository::clear_cache();
		self::assertTrue( Settings_Repository::get_bool( 'hide_admin_endpoint' ) );
	}

	public function test_get_returns_individual_field() : void {
		$this->set_settings( [ 'login_slug' => 'minha-porta' ] );
		self::assertSame( 'minha-porta', Settings_Repository::get( 'login_slug' ) );
	}

	public function test_get_bool_coerces_string_false() : void {
		// Regression: wp option patch update stores literal string "false"
		update_option( Plugin::OPTION_KEY, array_merge(
			Settings_Repository::defaults(),
			[ 'login_slug_enabled' => 'false' ]
		) );
		Settings_Repository::clear_cache();
		self::assertFalse( Settings_Repository::get_bool( 'login_slug_enabled' ) );
	}

	public function test_get_bool_coerces_string_zero() : void {
		update_option( Plugin::OPTION_KEY, array_merge(
			Settings_Repository::defaults(),
			[ 'custom_login_enabled' => '0' ]
		) );
		Settings_Repository::clear_cache();
		self::assertFalse( Settings_Repository::get_bool( 'custom_login_enabled' ) );
	}

	public function test_get_bool_returns_true_for_real_bool() : void {
		$this->set_settings( [ 'client_role_enabled' => true ] );
		self::assertTrue( Settings_Repository::get_bool( 'client_role_enabled' ) );
	}

	public function test_clear_cache_forces_reload() : void {
		$this->set_settings( [ 'login_slug' => 'first' ] );
		Settings_Repository::all();

		update_option( Plugin::OPTION_KEY, array_merge(
			Settings_Repository::defaults(),
			[ 'login_slug' => 'second' ]
		) );

		// Without clearing, cache still has 'first'
		self::assertSame( 'first', Settings_Repository::get( 'login_slug' ) );

		Settings_Repository::clear_cache();
		self::assertSame( 'second', Settings_Repository::get( 'login_slug' ) );
	}

	public function test_hidden_menus_default_includes_all_hideable() : void {
		delete_option( Plugin::OPTION_KEY );
		Settings_Repository::clear_cache();

		$hidden = Settings_Repository::get( 'hidden_menus' );
		self::assertCount( count( Settings_Repository::HIDEABLE_MENUS ), $hidden );
		self::assertContains( 'edit-comments.php', $hidden );
		self::assertContains( 'tools.php', $hidden );
	}

	public function test_core_hideable_menus_has_5_entries() : void {
		self::assertCount( 5, Settings_Repository::HIDEABLE_MENUS );
		self::assertArrayHasKey( 'edit.php', Settings_Repository::HIDEABLE_MENUS );
		self::assertArrayHasKey( 'edit.php?post_type=page', Settings_Repository::HIDEABLE_MENUS );
		self::assertArrayHasKey( 'edit-comments.php', Settings_Repository::HIDEABLE_MENUS );
		self::assertArrayHasKey( 'upload.php', Settings_Repository::HIDEABLE_MENUS );
		self::assertArrayHasKey( 'tools.php', Settings_Repository::HIDEABLE_MENUS );
	}

	public function test_redundant_core_slugs_removed() : void {
		// WP já esconde esses por capability — não devem estar na lista
		self::assertArrayNotHasKey( 'themes.php',  Settings_Repository::HIDEABLE_MENUS );
		self::assertArrayNotHasKey( 'plugins.php', Settings_Repository::HIDEABLE_MENUS );
		self::assertArrayNotHasKey( 'users.php',   Settings_Repository::HIDEABLE_MENUS );
	}

	public function test_hideable_menus_returns_core_when_no_cpts() : void {
		Settings_Repository::clear_cache();
		$result = Settings_Repository::hideable_menus();
		self::assertCount( 5, $result );
	}

	public function test_hideable_menus_includes_registered_cpt() : void {
		register_post_type( 'mock_cpt_repo', [
			'public'       => true,
			'show_ui'      => true,
			'show_in_menu' => true,
			'labels'       => [ 'name' => 'Mock CPTs Repo', 'menu_name' => 'Mock Repo' ],
		] );
		Settings_Repository::clear_cache();

		$result = Settings_Repository::hideable_menus();
		self::assertArrayHasKey( 'edit.php?post_type=mock_cpt_repo', $result );
		self::assertSame( 'Mock Repo', $result['edit.php?post_type=mock_cpt_repo'] );

		unregister_post_type( 'mock_cpt_repo' );
		Settings_Repository::clear_cache();
	}

	public function test_hideable_menus_excludes_builtin_cpts() : void {
		Settings_Repository::clear_cache();
		$result = Settings_Repository::hideable_menus();
		// `post` (builtin) já está coberto por `edit.php`; não deve aparecer separado
		self::assertArrayNotHasKey( 'edit.php?post_type=post', $result );
	}

	public function test_clear_cache_resets_hideable_memo() : void {
		Settings_Repository::clear_cache();
		Settings_Repository::hideable_menus(); // popula cache

		register_post_type( 'mock_late', [
			'public' => true, 'show_ui' => true, 'show_in_menu' => true,
			'labels' => [ 'name' => 'Late', 'menu_name' => 'Late' ],
		] );

		// Sem clear, cache não vê o novo CPT
		$cached = Settings_Repository::hideable_menus();
		self::assertArrayNotHasKey( 'edit.php?post_type=mock_late', $cached );

		Settings_Repository::clear_cache();
		$refreshed = Settings_Repository::hideable_menus();
		self::assertArrayHasKey( 'edit.php?post_type=mock_late', $refreshed );

		unregister_post_type( 'mock_late' );
		Settings_Repository::clear_cache();
	}
}
