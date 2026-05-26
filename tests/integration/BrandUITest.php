<?php

class BrandUITest extends Loomi_TestCase {

	public function test_loomi_header_renders_in_settings_page() : void {
		$this->login_as( 'administrator' );
		set_current_screen( 'settings_page_loomi-studio-setup' );

		ob_start();
		Loomi_Settings_Page::render();
		$out = ob_get_clean();

		self::assertStringContainsString( 'class="loomi-header"', $out );
		self::assertStringContainsString( 'class="loomi-logo"', $out );
		self::assertStringContainsString( 'class="loomi-brand"', $out );
		self::assertStringContainsString( 'Studio Setup', $out );
		self::assertStringContainsString( 'class="loomi-version"', $out );
	}

	public function test_admin_css_enqueued_on_plugin_page_only() : void {
		// Limpar enqueues anteriores
		global $wp_styles;
		$wp_styles = new WP_Styles();

		// Página alheia: NÃO deve carregar
		Loomi_Settings_Page::enqueue_assets( 'dashboard' );
		self::assertFalse( wp_style_is( 'loomi-studio-admin', 'enqueued' ) );

		// Página do plugin: deve carregar
		Loomi_Settings_Page::enqueue_assets( 'settings_page_loomi-studio-setup' );
		self::assertTrue( wp_style_is( 'loomi-studio-admin', 'enqueued' ) );
	}

	public function test_css_file_has_brand_variables() : void {
		$css_path = dirname( __DIR__, 2 ) . '/assets/admin.css';
		self::assertFileExists( $css_path );

		$css = file_get_contents( $css_path );
		self::assertStringContainsString( '--loomi-black: #000000', $css );
		self::assertStringContainsString( '--loomi-yellow: #FBD603', $css );
		self::assertStringContainsString( '--loomi-white: #ffffff', $css );
	}

	public function test_css_is_scoped_under_wrapper() : void {
		$css_path = dirname( __DIR__, 2 ) . '/assets/admin.css';
		$css      = file_get_contents( $css_path );

		// Remover comentários, variable block (que é o único bloco sem prefixo wrapper)
		// Variable block é `.loomi-studio-wrap { --... }` — já tem o prefixo.
		// Validar que NÃO existe um seletor global tipo "body {", "input {", ".button {" no início de uma linha.
		$global_leaks = preg_match_all( '/^(body|input|button|\.button|\.nav-tab|table)\s*\{/m', $css );
		self::assertSame( 0, $global_leaks, 'CSS should not have global selectors outside .loomi-studio-wrap scope' );
	}

	public function test_apply_brand_button_removed_from_login_tab() : void {
		$tab = new Tab_Login();
		ob_start();
		$tab->render( Settings_Repository::all() );
		$out = ob_get_clean();

		// Botão "Aplicar branding Loomi" foi removido — UI mais limpa
		self::assertStringNotContainsString( 'loomi-apply-brand', $out );
		self::assertStringNotContainsString( 'Aplicar branding Loomi', $out );
	}

	public function test_settings_page_wrapper_class_present() : void {
		$this->login_as( 'administrator' );
		set_current_screen( 'settings_page_loomi-studio-setup' );

		ob_start();
		Loomi_Settings_Page::render();
		$out = ob_get_clean();

		self::assertStringContainsString( 'class="wrap loomi-studio-wrap"', $out );
	}
}
