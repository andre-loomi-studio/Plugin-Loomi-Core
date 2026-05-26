<?php

class ThemeToggleTest extends Loomi_TestCase {

	public function test_default_theme_is_dark() : void {
		delete_option( Plugin::OPTION_KEY );
		Settings_Repository::clear_cache();
		self::assertSame( 'dark', Settings_Repository::get( 'loomi_theme' ) );
	}

	public function test_each_valid_value_persisted() : void {
		foreach ( [ 'dark', 'light', 'auto' ] as $theme ) {
			$this->set_settings( [ 'loomi_theme' => $theme ] );
			self::assertSame( $theme, Settings_Repository::get( 'loomi_theme' ) );
		}
	}

	public function test_invalid_value_rejected() : void {
		$this->set_settings( [ 'loomi_theme' => 'dark' ] );
		$out = Settings_Sanitizer::sanitize( [ 'loomi_theme' => 'rainbow' ] );
		self::assertSame( 'dark', $out['loomi_theme'] );
	}

	public function test_each_valid_value_accepted_by_sanitizer() : void {
		foreach ( [ 'dark', 'light', 'auto' ] as $theme ) {
			$out = Settings_Sanitizer::sanitize( [ 'loomi_theme' => $theme ] );
			self::assertSame( $theme, $out['loomi_theme'] );
		}
	}

	public function test_admin_body_class_includes_theme() : void {
		$this->set_settings( [ 'loomi_theme' => 'light' ] );
		$result = Loomi_Settings_Page::filter_admin_body_class( 'foo bar' );
		self::assertStringContainsString( 'loomi-theme-light', $result );

		$this->set_settings( [ 'loomi_theme' => 'dark' ] );
		$result = Loomi_Settings_Page::filter_admin_body_class( 'foo bar' );
		self::assertStringContainsString( 'loomi-theme-dark', $result );

		$this->set_settings( [ 'loomi_theme' => 'auto' ] );
		$result = Loomi_Settings_Page::filter_admin_body_class( 'foo bar' );
		self::assertStringContainsString( 'loomi-theme-auto', $result );
	}

	public function test_invalid_stored_theme_falls_back_to_dark_in_body_class() : void {
		// Force invalid value via direct DB write
		$opts = Settings_Repository::all();
		$opts['loomi_theme'] = 'invalid_value';
		update_option( Plugin::OPTION_KEY, $opts );
		Settings_Repository::clear_cache();

		$result = Loomi_Settings_Page::filter_admin_body_class( '' );
		self::assertStringContainsString( 'loomi-theme-dark', $result );
	}

	public function test_tab_dashboard_renders_3_radios() : void {
		$tab = new Tab_Dashboard();
		ob_start();
		$tab->render( Settings_Repository::all() );
		$out = ob_get_clean();

		self::assertStringContainsString( 'value="dark"', $out );
		self::assertStringContainsString( 'value="light"', $out );
		self::assertStringContainsString( 'value="auto"', $out );
		self::assertStringContainsString( 'loomi-segmented', $out );
	}
}
