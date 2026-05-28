<?php
/**
 * Integration tests for the Loomi_GTM module.
 *
 * Cover container ID validation in Settings_Sanitizer and the wp_head /
 * wp_body_open output produced by Loomi_GTM.
 */

class GtmTest extends WP_UnitTestCase {

	public function set_up() : void {
		parent::set_up();
		delete_option( Plugin::OPTION_KEY );
		Settings_Repository::clear_cache();
		global $wp_settings_errors;
		$wp_settings_errors = [];
	}

	public function tear_down() : void {
		remove_all_filters( 'loomi_gtm_enabled' );
		remove_all_filters( 'loomi_gtm_data_layer_init' );
		parent::tear_down();
	}

	private function set_id( string $id ) : void {
		$opts = get_option( Plugin::OPTION_KEY, [] );
		if ( ! is_array( $opts ) ) {
			$opts = [];
		}
		$opts['loomi_gtm_id'] = $id;
		update_option( Plugin::OPTION_KEY, $opts );
		Settings_Repository::clear_cache();
	}

	private function capture( string $action ) : string {
		ob_start();
		do_action( $action );
		return (string) ob_get_clean();
	}

	/* -----------------------------------------------------------------
	 * Default + persistence
	 * --------------------------------------------------------------- */

	public function test_default_id_is_empty_string() : void {
		self::assertSame( '', Settings_Repository::get( 'loomi_gtm_id', null ) );
	}

	/* -----------------------------------------------------------------
	 * Sanitizer
	 * --------------------------------------------------------------- */

	public function test_lowercase_id_is_uppercased() : void {
		$out = Settings_Sanitizer::sanitize( [ 'loomi_gtm_id' => 'gtm-abcd123' ] );
		self::assertSame( 'GTM-ABCD123', $out['loomi_gtm_id'] );
	}

	public function test_whitespace_is_trimmed() : void {
		$out = Settings_Sanitizer::sanitize( [ 'loomi_gtm_id' => '  GTM-ABCD123  ' ] );
		self::assertSame( 'GTM-ABCD123', $out['loomi_gtm_id'] );
	}

	public function test_invalid_id_keeps_previous() : void {
		update_option( Plugin::OPTION_KEY, array_merge(
			Settings_Repository::defaults(),
			[ 'loomi_gtm_id' => 'GTM-PREV123' ]
		) );

		$out = Settings_Sanitizer::sanitize( [ 'loomi_gtm_id' => 'UA-12345' ] );
		self::assertSame( 'GTM-PREV123', $out['loomi_gtm_id'] );

		$errors = get_settings_errors( Plugin::OPTION_KEY );
		$codes  = array_column( $errors, 'code' );
		self::assertContains( 'loomi_invalid_gtm_id', $codes );
	}

	public function test_empty_id_clears_value() : void {
		update_option( Plugin::OPTION_KEY, array_merge(
			Settings_Repository::defaults(),
			[ 'loomi_gtm_id' => 'GTM-PREV123' ]
		) );

		$out = Settings_Sanitizer::sanitize( [ 'loomi_gtm_id' => '' ] );
		self::assertSame( '', $out['loomi_gtm_id'] );

		$errors = get_settings_errors( Plugin::OPTION_KEY );
		$codes  = array_column( $errors, 'code' );
		self::assertNotContains( 'loomi_invalid_gtm_id', $codes );
	}

	public function test_length_boundaries() : void {
		// 4 chars after prefix → valid.
		$out = Settings_Sanitizer::sanitize( [ 'loomi_gtm_id' => 'GTM-A1B2' ] );
		self::assertSame( 'GTM-A1B2', $out['loomi_gtm_id'] );

		// 3 chars → invalid → previous kept (empty here, never set).
		$out = Settings_Sanitizer::sanitize( [ 'loomi_gtm_id' => 'GTM-ABC' ] );
		self::assertSame( '', $out['loomi_gtm_id'] );

		// 10 chars → valid.
		$out = Settings_Sanitizer::sanitize( [ 'loomi_gtm_id' => 'GTM-ABCDEFGHIJ' ] );
		self::assertSame( 'GTM-ABCDEFGHIJ', $out['loomi_gtm_id'] );

		// 11 chars → invalid.
		$out = Settings_Sanitizer::sanitize( [ 'loomi_gtm_id' => 'GTM-ABCDEFGHIJK' ] );
		self::assertSame( '', $out['loomi_gtm_id'] );
	}

	/* -----------------------------------------------------------------
	 * Head output
	 * --------------------------------------------------------------- */

	public function test_head_output_contains_id_when_set() : void {
		$this->set_id( 'GTM-ABCD123' );
		$out = $this->capture( 'wp_head' );

		self::assertStringContainsString( 'GTM-ABCD123', $out );
		self::assertStringContainsString( 'googletagmanager.com/gtm.js', $out );
		self::assertStringContainsString( '<!-- Google Tag Manager -->', $out );
	}

	public function test_head_output_empty_when_id_empty() : void {
		$this->set_id( '' );
		$out = $this->capture( 'wp_head' );

		self::assertStringNotContainsString( 'googletagmanager.com', $out );
		self::assertStringNotContainsString( '<!-- Google Tag Manager -->', $out );
	}

	/* -----------------------------------------------------------------
	 * Body output
	 * --------------------------------------------------------------- */

	public function test_body_output_contains_iframe_when_set() : void {
		$this->set_id( 'GTM-ABCD123' );
		$out = $this->capture( 'wp_body_open' );

		self::assertStringContainsString( 'ns.html?id=GTM-ABCD123', $out );
		self::assertStringContainsString( '<!-- Google Tag Manager (noscript) -->', $out );
	}

	public function test_body_output_empty_when_id_empty() : void {
		$this->set_id( '' );
		$out = $this->capture( 'wp_body_open' );

		self::assertStringNotContainsString( 'googletagmanager.com', $out );
	}

	/* -----------------------------------------------------------------
	 * Output scope
	 * --------------------------------------------------------------- */

	public function test_admin_context_skips_output() : void {
		$this->set_id( 'GTM-ABCD123' );
		set_current_screen( 'edit-post' ); // sets is_admin() = true

		ob_start();
		Loomi_GTM::output_head();
		$out = (string) ob_get_clean();

		self::assertStringNotContainsString( 'googletagmanager.com', $out );

		set_current_screen( 'front' );
	}

	public function test_filter_disables_output() : void {
		$this->set_id( 'GTM-ABCD123' );
		add_filter( 'loomi_gtm_enabled', '__return_false' );
		$out = $this->capture( 'wp_head' );

		self::assertStringNotContainsString( 'googletagmanager.com', $out );
	}
}
