<?php
/**
 * Integration tests for the admin Dark/Light/Auto theme toggle.
 *
 * Covers:
 *   - REST endpoint persistence + permission/validation
 *   - Body class filters (admin_body_class + login_body_class + default fallback)
 *   - CSS invariants (no body.wp-admin coupling, symmetric tokens, no hardcoded hex leak)
 *   - Dashboard render iterates THEME_VALUES
 *   - admin_body_class filter registered
 */

class AdminThemeTest extends WP_UnitTestCase {

	public function set_up() : void {
		parent::set_up();
		delete_option( Plugin::OPTION_KEY );
		Settings_Repository::clear_cache();
	}

	private function set_theme( string $value ) : void {
		$opts = get_option( Plugin::OPTION_KEY, [] );
		if ( ! is_array( $opts ) ) {
			$opts = [];
		}
		$opts['loomi_theme'] = $value;
		update_option( Plugin::OPTION_KEY, $opts );
		Settings_Repository::clear_cache();
	}

	private function read_css( string $file ) : string {
		$path = LOOMI_STUDIO_DIR . 'assets/' . $file;
		$content = file_get_contents( $path );
		self::assertIsString( $content, "Could not read assets/{$file}" );
		return $content;
	}

	/* -----------------------------------------------------------------
	 * REST endpoint
	 * --------------------------------------------------------------- */

	public function test_rest_endpoint_persists_valid_theme() : void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$request  = new WP_REST_Request( 'POST', '/loomi/v1/theme' );
		$request->set_param( 'theme', 'light' );
		$response = rest_do_request( $request );

		self::assertSame( 200, $response->get_status() );
		self::assertSame( 'light', Settings_Repository::get( 'loomi_theme' ) );
	}

	public function test_rest_endpoint_rejects_invalid_theme() : void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$this->set_theme( 'dark' );

		$request  = new WP_REST_Request( 'POST', '/loomi/v1/theme' );
		$request->set_param( 'theme', 'neon' );
		$response = rest_do_request( $request );

		self::assertSame( 400, $response->get_status() );
		self::assertSame( 'dark', Settings_Repository::get( 'loomi_theme' ) );
	}

	public function test_rest_endpoint_forbidden_without_cap() : void {
		$subscriber = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber );
		$this->set_theme( 'dark' );

		$request  = new WP_REST_Request( 'POST', '/loomi/v1/theme' );
		$request->set_param( 'theme', 'light' );
		$response = rest_do_request( $request );

		self::assertContains( (int) $response->get_status(), [ 401, 403 ] );
		self::assertSame( 'dark', Settings_Repository::get( 'loomi_theme' ) );
	}

	/* -----------------------------------------------------------------
	 * Body class
	 * --------------------------------------------------------------- */

	public function test_body_class_reflects_stored_theme() : void {
		$this->set_theme( 'light' );
		$out = Loomi_Settings_Page::filter_admin_body_class( 'wp-admin' );
		self::assertStringContainsString( 'loomi-theme-light', $out );
	}

	public function test_body_class_default_is_dark() : void {
		delete_option( Plugin::OPTION_KEY );
		Settings_Repository::clear_cache();
		$out = Loomi_Settings_Page::filter_admin_body_class( '' );
		self::assertStringContainsString( 'loomi-theme-dark', $out );
	}

	public function test_body_class_invalid_stored_falls_back_to_dark() : void {
		// Direct DB tampering — bypass sanitizer.
		update_option( Plugin::OPTION_KEY, array_merge(
			Settings_Repository::defaults(),
			[ 'loomi_theme' => 'sepia' ]
		) );
		Settings_Repository::clear_cache();
		$out = Loomi_Settings_Page::filter_admin_body_class( '' );
		self::assertStringContainsString( 'loomi-theme-dark', $out );
		self::assertStringNotContainsString( 'loomi-theme-sepia', $out );
	}

	public function test_login_body_class_includes_theme() : void {
		$this->set_theme( 'light' );
		$out = Loomi_Settings_Page::filter_login_body_class( [] );
		self::assertIsArray( $out );
		self::assertContains( 'loomi-theme-light', $out );
	}

	public function test_login_body_class_accepts_existing_classes() : void {
		$this->set_theme( 'auto' );
		$out = Loomi_Settings_Page::filter_login_body_class( [ 'login', 'wp-core-ui' ] );
		self::assertContains( 'login', $out );
		self::assertContains( 'wp-core-ui', $out );
		self::assertContains( 'loomi-theme-auto', $out );
	}

	/* -----------------------------------------------------------------
	 * CSS invariants
	 * --------------------------------------------------------------- */

	public function test_css_has_no_wp_admin_dark_coupling() : void {
		$css = $this->read_css( 'admin-global.css' );
		// Strip /* ... */ comments so prose mentioning these selectors isn't a false positive.
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );

		// Find every selector list that opens a `{` block defining --loomi-g-* tokens,
		// and assert none of those selector lists couples body.wp-admin with body.loomi-theme-*.
		if ( preg_match_all( '/([^{}]+)\{([^}]*--loomi-g-[^}]*)\}/', $css, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$selector_list = $match[1];
				$has_wp_admin   = (bool) preg_match( '/\bbody\.wp-admin\b/', $selector_list );
				$has_theme_cls  = (bool) preg_match( '/\bbody\.loomi-theme-(?:dark|light|auto)\b/', $selector_list );
				if ( $has_wp_admin && $has_theme_cls ) {
					self::fail( "body.wp-admin must not be grouped with loomi-theme-* in token-defining selector lists. Offending selector: " . trim( $selector_list ) );
				}
			}
		}

		// Test passed if no offending selector list was found.
		self::assertTrue( true );
	}

	public function test_css_symmetric_token_blocks() : void {
		$css = $this->read_css( 'admin-global.css' );

		// Extract custom properties from each theme block.
		$dark_tokens  = $this->extract_tokens( $css, '/body\.loomi-theme-dark,\s*body\.loomi-theme-auto\s*\{([^}]+)\}/s' );
		$light_tokens = $this->extract_tokens( $css, '/body\.loomi-theme-light\s*\{([^}]+)\}/s' );
		$auto_tokens  = $this->extract_tokens( $css, '/@media\s*\(prefers-color-scheme:\s*light\)\s*\{\s*body\.loomi-theme-auto\s*\{([^}]+)\}\s*\}/s' );

		self::assertNotEmpty( $dark_tokens, 'Failed to parse dark token block' );
		self::assertNotEmpty( $light_tokens, 'Failed to parse light token block' );
		self::assertNotEmpty( $auto_tokens, 'Failed to parse auto-light token block' );

		sort( $dark_tokens );
		sort( $light_tokens );
		sort( $auto_tokens );

		self::assertSame( $dark_tokens, $light_tokens,
			'Dark and Light theme blocks must define the same custom properties' );
		self::assertSame( $light_tokens, $auto_tokens,
			'Light and auto-light theme blocks must define the same custom properties' );
	}

	public function test_css_no_hardcoded_dark_hex_outside_token_blocks() : void {
		$css = $this->read_css( 'admin-global.css' );

		// Strip the dark token definition block (where #1a1a1a, #262626 etc. are SUPPOSED to live).
		$stripped = preg_replace(
			'/body\.loomi-theme-dark,\s*body\.loomi-theme-auto\s*\{[^}]+\}/s',
			'',
			$css
		);
		// Also strip the @media auto-light block (where dark colors would NOT appear anyway).
		$stripped = preg_replace(
			'/@media\s*\(prefers-color-scheme:[^)]+\)\s*\{[^}]+\}/s',
			'',
			$stripped
		);

		// Now scan: dark palette colors must not appear in selectors that match every theme.
		// Acceptable inside rgba(...) shadows, but raw #1a1a1a / #262626 / #0d0d0d should be gone.
		foreach ( [ '#1a1a1a', '#262626', '#0d0d0d', '#131313' ] as $hex ) {
			// Allow these inside text color rules for WP notices (.notice color: #1a1a1a) which
			// have a fixed light background regardless of theme — they're not a leak.
			$lines = explode( "\n", $stripped );
			foreach ( $lines as $i => $line ) {
				if ( stripos( $line, $hex ) === false ) continue;
				if ( stripos( $line, 'color:' ) !== false && stripos( $line, 'background' ) === false ) continue;
				self::fail( "Hardcoded {$hex} leaks outside theme-scoped blocks at line " . ( $i + 1 ) . ": " . trim( $line ) );
			}
		}
	}

	private function extract_tokens( string $css, string $pattern ) : array {
		if ( ! preg_match( $pattern, $css, $matches ) ) {
			return [];
		}
		$block = $matches[1];
		preg_match_all( '/(--loomi-[a-z0-9-]+)\s*:/i', $block, $names );
		return $names[1] ?? [];
	}

	/* -----------------------------------------------------------------
	 * Dashboard tab renders one radio per THEME_VALUES entry
	 * --------------------------------------------------------------- */

	public function test_dashboard_renders_one_radio_per_theme_value() : void {
		$tab = new Tab_Dashboard();
		ob_start();
		$tab->render( Settings_Repository::all() );
		$html = (string) ob_get_clean();

		$count = preg_match_all(
			'/<input[^>]+type="radio"[^>]+name="' . preg_quote( Plugin::OPTION_KEY, '/' ) . '\[loomi_theme\]"/i',
			$html
		);

		self::assertSame(
			count( Settings_Repository::THEME_VALUES ),
			$count,
			'Dashboard tab must render one radio per Settings_Repository::THEME_VALUES entry'
		);

		foreach ( Settings_Repository::THEME_VALUES as $value ) {
			self::assertStringContainsString( 'value="' . $value . '"', $html,
				"Missing radio for theme value '{$value}'" );
		}
	}

	/* -----------------------------------------------------------------
	 * Filter registration
	 * --------------------------------------------------------------- */

	public function test_admin_body_class_filter_is_registered() : void {
		self::assertNotFalse(
			has_filter( 'admin_body_class', [ 'Loomi_Settings_Page', 'filter_admin_body_class' ] ),
			'Loomi_Settings_Page::filter_admin_body_class must be registered on admin_body_class'
		);
		self::assertNotFalse(
			has_filter( 'login_body_class', [ 'Loomi_Settings_Page', 'filter_login_body_class' ] ),
			'Loomi_Settings_Page::filter_login_body_class must be registered on login_body_class'
		);
	}
}
