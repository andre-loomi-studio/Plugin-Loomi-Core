<?php

class LoginUrlsTest extends Loomi_TestCase {

	public function set_up() : void {
		parent::set_up();
		$this->set_settings( [ 'login_slug' => 'studio-access' ] );
	}

	public function test_build_no_args_returns_slug_url() : void {
		$url = Login_URLs::build();
		self::assertStringContainsString( '/studio-access/', $url );
	}

	public function test_build_with_action() : void {
		$url = Login_URLs::build( 'logout' );
		self::assertStringContainsString( 'action=logout', $url );
	}

	public function test_build_with_redirect_to_encodes_value() : void {
		$url = Login_URLs::build( '', [ 'redirect_to' => 'https://example.com/admin' ] );
		self::assertStringContainsString( 'redirect_to=', $url );
		self::assertStringContainsString( 'https%3A%2F%2Fexample.com%2Fadmin', $url );
	}

	public function test_build_with_reauth_flag() : void {
		$url = Login_URLs::build( '', [ 'reauth' => '1' ] );
		self::assertStringContainsString( 'reauth=1', $url );
	}

	public function test_build_filters_null_and_empty_extras() : void {
		$url = Login_URLs::build( '', [ 'foo' => null, 'bar' => '', 'baz' => false, 'qux' => 'kept' ] );
		self::assertStringNotContainsString( 'foo=', $url );
		self::assertStringNotContainsString( 'bar=', $url );
		self::assertStringNotContainsString( 'baz=', $url );
		self::assertStringContainsString( 'qux=kept', $url );
	}

	public function test_build_empty_slug_falls_back_to_default() : void {
		$this->set_settings( [ 'login_slug' => '' ] );
		$url = Login_URLs::build();
		self::assertStringContainsString( '/studio-access/', $url );
	}

	public function test_filter_login_url_uses_slug() : void {
		$url = Loomi_Login::filter_login_url( wp_login_url(), 'https://example.com', false );
		self::assertStringContainsString( '/studio-access/', $url );
		self::assertStringContainsString( 'redirect_to=', $url );
	}
}
