<?php

class LoginSlugRoutingTest extends Loomi_TestCase {

	public function set_up() : void {
		parent::set_up();
		$this->set_settings( [
			'login_slug_enabled' => true,
			'login_slug'         => 'studio-access',
		] );
	}

	public function test_filter_login_url_returns_slug() : void {
		$url = wp_login_url();
		self::assertStringContainsString( '/studio-access/', $url );
		self::assertStringNotContainsString( 'wp-login.php', $url );
	}

	public function test_filter_logout_url_uses_slug_and_includes_nonce() : void {
		$this->login_as( 'administrator' );
		$url = wp_logout_url();
		self::assertStringContainsString( '/studio-access/', $url );
		self::assertStringContainsString( 'action=logout', $url );
		self::assertStringContainsString( '_wpnonce=', $url );
	}

	public function test_filter_lostpassword_url_uses_slug() : void {
		$url = wp_lostpassword_url();
		self::assertStringContainsString( '/studio-access/', $url );
		self::assertStringContainsString( 'action=lostpassword', $url );
	}

	public function test_filter_register_url_uses_slug() : void {
		$url = wp_registration_url();
		self::assertStringContainsString( '/studio-access/', $url );
		self::assertStringContainsString( 'action=register', $url );
	}

	public function test_gate_skipped_when_logged_in() : void {
		$this->login_as( 'administrator' );
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_REQUEST                  = [];

		// gate_wp_login does early-return when logged in — assert no exit/die
		Loomi_Login::gate_wp_login();
		self::assertTrue( true ); // reached this line means no wp_die
	}

	public function test_gate_skipped_for_post_method() : void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_REQUEST                  = [];
		wp_set_current_user( 0 );

		Loomi_Login::gate_wp_login();
		self::assertTrue( true );
	}

	public function test_gate_skipped_for_logout_action() : void {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_REQUEST                  = [ 'action' => 'logout' ];
		wp_set_current_user( 0 );

		Loomi_Login::gate_wp_login();
		self::assertTrue( true );
	}
}
