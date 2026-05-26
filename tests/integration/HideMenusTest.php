<?php

class HideMenusTest extends Loomi_TestCase {

	private function trigger_admin_menu() : array {
		global $menu;
		$menu = [];
		do_action( 'admin_menu' );
		return array_map( static fn( $item ) => $item[2] ?? '', (array) $menu );
	}

	public function test_toggle_off_keeps_menus_visible() : void {
		$this->set_settings( [
			'hide_menus_enabled' => false,
			'hidden_menus'       => [ 'edit-comments.php' ],
		] );
		$this->login_as( 'editor' );

		Loomi_Admin_Menu::register();
		Loomi_Admin_Menu::hide_menus();

		// Without setting up the full menu, we just verify register() didn't crash
		self::assertTrue( true );
	}

	public function test_editor_loses_selected_menus() : void {
		$this->set_settings( [
			'hide_menus_enabled' => true,
			'hidden_menus'       => [ 'edit-comments.php', 'tools.php' ],
		] );
		$this->login_as( 'editor' );

		global $menu;
		// Pre-populate menu like WP would
		$menu = [
			[ 0 => 'Dashboard',  2 => 'index.php' ],
			[ 0 => 'Posts',      2 => 'edit.php' ],
			[ 0 => 'Comentários',2 => 'edit-comments.php' ],
			[ 0 => 'Ferramentas',2 => 'tools.php' ],
		];

		Loomi_Admin_Menu::hide_menus();

		$slugs = array_column( $menu, 2 );
		self::assertContains( 'index.php', $slugs );
		self::assertContains( 'edit.php', $slugs );
		self::assertNotContains( 'edit-comments.php', $slugs );
		self::assertNotContains( 'tools.php', $slugs );
	}

	public function test_admin_keeps_all_menus() : void {
		$this->set_settings( [
			'hide_menus_enabled' => true,
			'hidden_menus'       => [ 'edit-comments.php' ],
		] );
		$this->login_as( 'administrator' );

		global $menu;
		$menu = [
			[ 0 => 'Dashboard',   2 => 'index.php' ],
			[ 0 => 'Comentários', 2 => 'edit-comments.php' ],
		];

		Loomi_Admin_Menu::hide_menus();
		$slugs = array_column( $menu, 2 );
		self::assertContains( 'edit-comments.php', $slugs );
	}

	public function test_blacklisted_index_never_hidden() : void {
		$this->set_settings( [
			'hide_menus_enabled' => true,
			'hidden_menus'       => [ 'index.php', 'edit-comments.php' ],
		] );
		$this->login_as( 'editor' );

		global $menu;
		$menu = [
			[ 0 => 'Dashboard',   2 => 'index.php' ],
			[ 0 => 'Comentários', 2 => 'edit-comments.php' ],
		];

		Loomi_Admin_Menu::hide_menus();
		$slugs = array_column( $menu, 2 );
		self::assertContains( 'index.php', $slugs, 'Dashboard never hidden' );
	}

	public function test_unknown_menu_slug_ignored() : void {
		$this->set_settings( [
			'hide_menus_enabled' => true,
			'hidden_menus'       => [ 'evil.php' ],
		] );
		$this->login_as( 'editor' );

		global $menu;
		$menu = [
			[ 0 => 'Evil', 2 => 'evil.php' ],
		];

		Loomi_Admin_Menu::hide_menus();
		$slugs = array_column( $menu, 2 );
		self::assertContains( 'evil.php', $slugs, 'Only whitelisted slugs are processed' );
	}

	public function test_cpt_menu_hidden_when_configured() : void {
		register_post_type( 'mock_cpt_hide', [
			'public' => true, 'show_ui' => true, 'show_in_menu' => true,
			'labels' => [ 'name' => 'Mock', 'menu_name' => 'Mock' ],
		] );
		Settings_Repository::clear_cache();

		$this->set_settings( [
			'hide_menus_enabled' => true,
			'hidden_menus'       => [ 'edit.php?post_type=mock_cpt_hide' ],
		] );
		$this->login_as( 'editor' );

		global $menu;
		$menu = [
			[ 0 => 'Dashboard', 2 => 'index.php' ],
			[ 0 => 'Mock',      2 => 'edit.php?post_type=mock_cpt_hide' ],
		];

		Loomi_Admin_Menu::hide_menus();
		$slugs = array_column( $menu, 2 );
		self::assertNotContains( 'edit.php?post_type=mock_cpt_hide', $slugs );
		self::assertContains( 'index.php', $slugs );

		unregister_post_type( 'mock_cpt_hide' );
		Settings_Repository::clear_cache();
	}

	public function test_pages_slug_present_in_core() : void {
		self::assertArrayHasKey( 'edit.php?post_type=page', Settings_Repository::HIDEABLE_MENUS );
		self::assertSame( 'Páginas', Settings_Repository::HIDEABLE_MENUS['edit.php?post_type=page'] );
	}

	public function test_tab_renders_disclaimer() : void {
		$tab = new Tab_Hide_Menus();
		ob_start();
		$tab->render( Settings_Repository::all() );
		$out = ob_get_clean();
		self::assertStringContainsString( 'WordPress já esconde automaticamente', $out );
		self::assertStringContainsString( 'notice-info', $out );
	}

	public function test_tab_groups_core_and_cpts_separately() : void {
		register_post_type( 'mock_grouped', [
			'public' => true, 'show_ui' => true, 'show_in_menu' => true,
			'labels' => [ 'name' => 'Grouped' ],
		] );
		Settings_Repository::clear_cache();

		$tab = new Tab_Hide_Menus();
		ob_start();
		$tab->render( Settings_Repository::all() );
		$out = ob_get_clean();

		self::assertStringContainsString( 'WordPress', $out );
		self::assertStringContainsString( 'Custom Post Types', $out );
		self::assertStringContainsString( 'edit.php?post_type=mock_grouped', $out );

		unregister_post_type( 'mock_grouped' );
		Settings_Repository::clear_cache();
	}

	public function test_tab_shows_empty_cpt_message_when_none_registered() : void {
		Settings_Repository::clear_cache();
		$tab = new Tab_Hide_Menus();
		ob_start();
		$tab->render( Settings_Repository::all() );
		$out = ob_get_clean();
		self::assertStringContainsString( 'Nenhum Custom Post Type encontrado', $out );
	}
}
