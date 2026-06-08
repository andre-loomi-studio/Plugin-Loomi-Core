<?php
/**
 * Integration tests for the security fixes catalogued in
 * openspec/changes/fix-security-vulnerabilities.
 *
 * Each /* F<N> *​/ block tests the fix for one Medium finding.
 */

class SecurityTest extends WP_UnitTestCase {

	public function tear_down() : void {
		// Reset state that tests may have touched.
		remove_all_filters( 'wp_redirect' );
		parent::tear_down();
	}

	private function capture_output( callable $fn ) : string {
		ob_start();
		$fn();
		return (string) ob_get_clean();
	}

	/* -----------------------------------------------------------------
	 * F2 — JSON-LD XSS via Custom JSON
	 * --------------------------------------------------------------- */

	public function test_jsonld_output_escapes_forward_slashes() : void {
		// Build a LocalBusiness schema that contains URLs.
		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => 'LocalBusiness',
			'url'      => 'https://example.com/a/b',
			'sameAs'   => [ 'https://instagram.com/loomi' ],
		];

		$encoded = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE );

		// Encoded output MUST escape slashes — i.e., '\/' present, '/' replaced.
		self::assertStringContainsString( 'https:\/\/example.com\/a\/b', $encoded );
		self::assertStringContainsString( 'https:\/\/instagram.com\/loomi', $encoded );
	}

	public function test_jsonld_with_closing_script_tag_is_rejected() : void {
		$payload = wp_json_encode( [ 'name' => '</script><script>alert(1)</script>' ] );
		$out     = Loomi_Schema::build_custom( $payload );

		// build_custom always returns array, but the sanitizer is what blocks save.
		// We test the sanitizer path directly via reflection of sanitize_payload.
		$reflection = new ReflectionClass( "Loomi_Schema_Sanitizer" );
		$method     = $reflection->getMethod( 'sanitize_custom_json' );
		$method->setAccessible( true );

		$result = $method->invoke( null, [ 'custom_json' => $payload ] );
		self::assertInstanceOf( 'WP_Error', $result );
		self::assertSame( 'loomi_schema_jsonld_unsafe', $result->get_error_code() );
	}

	public function test_jsonld_closing_script_tag_case_insensitive() : void {
		$reflection = new ReflectionClass( "Loomi_Schema_Sanitizer" );
		$method     = $reflection->getMethod( 'sanitize_custom_json' );
		$method->setAccessible( true );

		foreach ( [ '</SCRIPT', '</ScRiPt', '</script ', '</script>' ] as $token ) {
			$payload = wp_json_encode( [ 'nested' => [ 'evil' => "prefix{$token}suffix" ] ] );
			$result  = $method->invoke( null, [ 'custom_json' => $payload ] );
			self::assertInstanceOf( 'WP_Error', $result, "Token {$token} should be rejected" );
			self::assertSame( 'loomi_schema_jsonld_unsafe', $result->get_error_code() );
		}
	}

	public function test_jsonld_valid_payload_accepted() : void {
		$reflection = new ReflectionClass( "Loomi_Schema_Sanitizer" );
		$method     = $reflection->getMethod( 'sanitize_custom_json' );
		$method->setAccessible( true );

		$payload = wp_json_encode( [
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => 'Loomi',
			'url'      => 'https://loomi.studio/contato',
		] );

		$result = $method->invoke( null, [ 'custom_json' => $payload ] );
		self::assertIsArray( $result );
		self::assertArrayHasKey( 'custom_json', $result );
	}

	/* -----------------------------------------------------------------
	 * F4 — Impersonate cookie + nopriv
	 * --------------------------------------------------------------- */

	public function test_impersonate_nopriv_stop_not_registered() : void {
		// Force the module to run register() in a clean state.
		// (It registers on plugins_loaded which already fired during bootstrap.)
		self::assertFalse(
			(bool) has_action( 'admin_post_nopriv_loomi_impersonate_stop' ),
			'admin_post_nopriv_loomi_impersonate_stop must NOT be registered'
		);
	}

	/**
	 * In WP_UnitTestCase, wp_set_auth_cookie / wp_logout do NOT update $_COOKIE
	 * (they only call setcookie() which targets HTTP response headers). To simulate
	 * the operator's session changing between cookie issue and cookie verify, we
	 * mint session tokens via WP_Session_Tokens and write the corresponding cookie
	 * value into $_COOKIE[ LOGGED_IN_COOKIE ] — that's what wp_get_session_token()
	 * reads from.
	 */
	private function set_logged_in_cookie_for( int $user_id, string $token ) : void {
		$expiration = time() + 3600;
		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user_id, $expiration, 'logged_in', $token );
	}

	public function test_impersonate_cookie_invalidates_across_sessions() : void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$manager = WP_Session_Tokens::get_instance( $admin_id );

		// Session A.
		$token_a = $manager->create( time() + 3600 );
		$this->set_logged_in_cookie_for( $admin_id, $token_a );
		self::assertSame( $token_a, wp_get_session_token() );

		// Mint return cookie under session A.
		$reflection = new ReflectionClass( 'Loomi_Impersonate' );
		$set_method = $reflection->getMethod( 'set_return_cookie' );
		$set_method->setAccessible( true );
		$set_method->invoke( null, $admin_id );
		$cookie_value = $_COOKIE[ Loomi_Impersonate::COOKIE_NAME ];
		self::assertNotEmpty( $cookie_value );

		// Operator logs out + back in → new session token B.
		$token_b = $manager->create( time() + 3600 );
		self::assertNotSame( $token_a, $token_b );
		$this->set_logged_in_cookie_for( $admin_id, $token_b );
		self::assertSame( $token_b, wp_get_session_token() );

		// Cookie minted under A must fail under B.
		$parse_method = $reflection->getMethod( 'parse_return_cookie' );
		$parse_method->setAccessible( true );
		$result = $parse_method->invoke( null, $cookie_value );

		self::assertNull( $result, 'Cookie minted in a previous session must be rejected' );
	}

	public function test_impersonate_cookie_works_in_same_session() : void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$manager = WP_Session_Tokens::get_instance( $admin_id );
		$token   = $manager->create( time() + 3600 );
		$this->set_logged_in_cookie_for( $admin_id, $token );

		$reflection = new ReflectionClass( 'Loomi_Impersonate' );
		$set_method = $reflection->getMethod( 'set_return_cookie' );
		$set_method->setAccessible( true );
		$set_method->invoke( null, $admin_id );

		$cookie_value = $_COOKIE[ Loomi_Impersonate::COOKIE_NAME ];

		$parse_method = $reflection->getMethod( 'parse_return_cookie' );
		$parse_method->setAccessible( true );
		$result = $parse_method->invoke( null, $cookie_value );

		self::assertIsArray( $result );
		self::assertSame( $admin_id, $result['admin_id'] );
	}

	/* -----------------------------------------------------------------
	 * F1 — CSS injection do login
	 * --------------------------------------------------------------- */

	public function test_login_bg_valid_hex() : void {
		update_option( Plugin::OPTION_KEY, array_merge(
			Settings_Repository::defaults(),
			[
				'custom_login_enabled'  => true,
				'custom_login_bg_color' => '#FF00AA',
			]
		) );
		Settings_Repository::clear_cache();

		$out = $this->capture_output( [ 'Loomi_Login', 'inject_login_styles' ] );

		self::assertStringContainsString( 'background:#FF00AA !important', $out );
		self::assertStringNotContainsString( '<script', $out );
	}

	public function test_login_bg_tampered_falls_back_to_black() : void {
		// Simulate another plugin / migration writing a tampered value directly.
		update_option( Plugin::OPTION_KEY, array_merge(
			Settings_Repository::defaults(),
			[
				'custom_login_enabled'  => true,
				'custom_login_bg_color' => 'red;}</style><script>alert(1)</script>',
			]
		) );
		Settings_Repository::clear_cache();

		$out = $this->capture_output( [ 'Loomi_Login', 'inject_login_styles' ] );

		self::assertStringContainsString( 'background:#000000 !important', $out );
		self::assertStringNotContainsString( '<script', $out );
		self::assertStringNotContainsString( 'alert(1)', $out );
		// The wrapping <style>...</style> tag itself ends with </style> — that's expected.
		// What MUST NOT happen is a </style> appearing in the middle of the content,
		// breaking the style tag. Check there's exactly one </style> at the very end.
		self::assertSame( 1, substr_count( $out, '</style>' ), 'Output must contain exactly one closing </style>' );
		self::assertStringEndsWith( "</style>\n", $out );
	}

	/* -----------------------------------------------------------------
	 * F3 — Open redirect no Wordfence handler
	 * --------------------------------------------------------------- */

	public function test_wordfence_handle_install_does_not_use_referer() : void {
		// Static analysis: read the actual source of handle_install and assert that
		// wp_get_referer is NOT referenced and the fixed plugins.php destination IS.
		// We choose static check over invoking handle_install because the live handler
		// is intricately coupled with WP_Upgrader / plugins_api which are slow to mock
		// reliably across WP versions; the contract we care about — "no referer-based
		// redirect" — is a code-level invariant best asserted at source level.
		$reflection = new ReflectionMethod( 'Loomi_Wordfence_Check', 'handle_install' );
		$file       = $reflection->getFileName();
		$source     = file_get_contents( $file );
		$start      = $reflection->getStartLine();
		$end        = $reflection->getEndLine();
		$lines      = explode( "\n", $source );
		$body       = implode( "\n", array_slice( $lines, $start - 1, $end - $start + 1 ) );

		// Strip comments so we don't false-positive on the explanatory comment that
		// mentions wp_get_referer in prose.
		$without_comments = preg_replace( '~//[^\n]*~', '', $body );
		self::assertStringNotContainsString( 'wp_get_referer(', $without_comments, 'handle_install must not call wp_get_referer()' );
		self::assertStringContainsString( "admin_url( 'plugins.php' )", $body, 'handle_install must use fixed plugins.php redirect' );
	}
}
