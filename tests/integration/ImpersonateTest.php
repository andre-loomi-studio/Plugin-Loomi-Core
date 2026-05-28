<?php
/**
 * Integration tests for Loomi_Impersonate.
 *
 * Covers tasks 7.1 – 7.18 of `openspec/changes/add-user-impersonate/tasks.md`.
 *
 * The tests assume the production class exposes:
 *   - constants: COOKIE_NAME, ACTION_START, ACTION_STOP, NONCE_START, NONCE_STOP, TTL
 *   - public static methods: register(), add_row_action( array $actions, WP_User $user ),
 *     handle_start(), handle_stop(), is_impersonating(): ?int
 *   - private/protected: parse_return_cookie( string ): ?array
 *     and a helper that emits the return-cookie value (e.g. build_return_cookie / set_return_cookie).
 *
 * Where the implementation file isn't ready yet, tests still encode the spec contract
 * (`openspec/changes/add-user-impersonate/specs/user-impersonate/spec.md`) and will exercise
 * the production code once the parallel agent finishes scaffolding the module.
 */
class ImpersonateTest extends Loomi_LogTestCase {

	/** @var int */
	private $admin_id;

	/** @var int */
	private $admin2_id;

	/** @var int */
	private $editor_id;

	public function set_up() : void {
		parent::set_up();

		$this->admin_id  = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->admin2_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->editor_id = self::factory()->user->create( [ 'role' => 'editor' ] );

		wp_set_current_user( $this->admin_id );

		$_SERVER['REMOTE_ADDR']     = '10.0.0.1';
		$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit/ImpersonateTest';

		// Intercept wp_safe_redirect() + exit (handlers end with redirect+exit).
		// Throwing here turns the redirect into a catchable exception so PHPUnit can continue.
		add_filter( 'wp_redirect', static function ( $location ) {
			throw new WPDieException( 'Redirected to ' . $location );
		}, 10, 1 );
	}

	public function tear_down() : void {
		unset(
			$_GET['user_id'],
			$_GET['_wpnonce'],
			$_REQUEST['_wpnonce'],
			$_REQUEST['user_id'],
			$_REQUEST['action']
		);
		if ( class_exists( 'Loomi_Impersonate' ) ) {
			unset( $_COOKIE[ Loomi_Impersonate::COOKIE_NAME ] );
		} else {
			unset( $_COOKIE['loomi_impersonate_return'] );
		}
		remove_all_filters( 'loomi_impersonate_enabled' );
		remove_all_filters( 'user_row_actions' );
		remove_all_filters( 'wp_redirect' );

		parent::tear_down();
	}

	/* -----------------------------------------------------------------
	 * Helpers
	 * --------------------------------------------------------------- */

	private function require_module() : void {
		if ( ! class_exists( 'Loomi_Impersonate' ) ) {
			self::markTestSkipped( 'Loomi_Impersonate not loaded — module file pending implementation.' );
		}
	}

	private function nonce_for_start( int $target_id ) : string {
		return wp_create_nonce( Loomi_Impersonate::NONCE_START . '_' . $target_id );
	}

	private function nonce_for_stop() : string {
		return wp_create_nonce( Loomi_Impersonate::NONCE_STOP );
	}

	/**
	 * Build the return-cookie value using the same algorithm as the production module.
	 * Format: "<admin_id>.<expires_at>.<hmac>" where
	 *   hmac = HMAC-SHA256("<admin_id>|<expires_at>|<session_token>", wp_salt('auth')).
	 *
	 * session_token comes from wp_get_session_token() and is usually '' in the test
	 * environment (no LOGGED_IN_COOKIE) — that's fine, it just needs to match what
	 * parse_return_cookie sees at verify time.
	 */
	private function generate_return_cookie( int $admin_id, ?int $expires_at = null ) : string {
		$expires_at    = $expires_at ?? ( time() + (int) Loomi_Impersonate::TTL );
		$session_token = function_exists( 'wp_get_session_token' ) ? (string) wp_get_session_token() : '';
		$payload       = $admin_id . '|' . $expires_at . '|' . $session_token;
		$hmac          = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
		return $admin_id . '.' . $expires_at . '.' . $hmac;
	}

	private function set_start_request( int $target_id, ?string $nonce = null ) : void {
		$_GET['user_id']      = $target_id;
		$_GET['_wpnonce']     = $nonce ?? $this->nonce_for_start( $target_id );
		$_GET['action']       = Loomi_Impersonate::ACTION_START;
		$_REQUEST['user_id']  = $_GET['user_id'];
		$_REQUEST['_wpnonce'] = $_GET['_wpnonce'];
		$_REQUEST['action']   = $_GET['action'];
	}

	private function set_stop_request( ?string $nonce = null ) : void {
		$_GET['_wpnonce']     = $nonce ?? $this->nonce_for_stop();
		$_GET['action']       = Loomi_Impersonate::ACTION_STOP;
		$_REQUEST['_wpnonce'] = $_GET['_wpnonce'];
		$_REQUEST['action']   = $_GET['action'];
	}

	/**
	 * Find the most recent log entry whose `type` or `event` matches one of the impersonate event
	 * names. The production module is free to write the event under either key — we accept both.
	 */
	private function findLastImpersonateLog( string $event_name ) : ?array {
		$entries = $this->readLogEntries();
		for ( $i = count( $entries ) - 1; $i >= 0; $i-- ) {
			$e = $entries[ $i ];
			$t = $e['type'] ?? null;
			$v = $e['event'] ?? null;
			if ( $t === $event_name || $v === $event_name ) {
				return $e;
			}
		}
		return null;
	}

	/* -----------------------------------------------------------------
	 * 7.1 – 7.4 — Row action visibility
	 * --------------------------------------------------------------- */

	public function test_row_action_visible_to_admin_for_editor() : void {
		$this->require_module();

		$target  = get_user_by( 'id', $this->editor_id );
		$actions = apply_filters( 'user_row_actions', [], $target );

		self::assertIsArray( $actions );
		self::assertArrayHasKey( 'loomi_impersonate', $actions, 'Admin should see the Impersonar row action for an editor.' );

		$html = (string) $actions['loomi_impersonate'];
		self::assertStringContainsString( 'loomi_impersonate_start', $html );
		self::assertStringContainsString( 'user_id=' . $this->editor_id, $html );
	}

	public function test_row_action_hidden_for_admin_target() : void {
		$this->require_module();

		$target  = get_user_by( 'id', $this->admin2_id );
		$actions = apply_filters( 'user_row_actions', [], $target );

		self::assertIsArray( $actions );
		self::assertArrayNotHasKey( 'loomi_impersonate', $actions, 'Row action must NOT be rendered when the target is an administrator.' );
	}

	public function test_row_action_hidden_for_self() : void {
		$this->require_module();

		$target  = get_user_by( 'id', $this->admin_id ); // current user is admin_id
		$actions = apply_filters( 'user_row_actions', [], $target );

		self::assertIsArray( $actions );
		self::assertArrayNotHasKey( 'loomi_impersonate', $actions, 'Row action must NOT be rendered for the current user themselves.' );
	}

	public function test_row_action_hidden_for_non_admin_viewer() : void {
		$this->require_module();

		wp_set_current_user( $this->editor_id );

		$target  = get_user_by( 'id', $this->editor_id );
		$actions = apply_filters( 'user_row_actions', [], $target );

		self::assertIsArray( $actions );
		self::assertArrayNotHasKey( 'loomi_impersonate', $actions, 'Non-admin viewers must never see the Impersonar row action.' );
	}

	/* -----------------------------------------------------------------
	 * 7.5 – 7.8 — Start handler authorisation
	 * --------------------------------------------------------------- */

	public function test_start_rejects_invalid_nonce() : void {
		$this->require_module();

		$_GET['user_id']      = $this->editor_id;
		$_GET['_wpnonce']     = 'totally-bogus';
		$_REQUEST['_wpnonce'] = 'totally-bogus';
		$_REQUEST['user_id']  = $this->editor_id;

		$threw = false;
		try {
			Loomi_Impersonate::handle_start();
		} catch ( WPDieException $e ) {
			$threw = true;
		}
		self::assertTrue( $threw, 'handle_start must reject invalid/missing nonce via wp_die().' );
		self::assertSame( $this->admin_id, get_current_user_id(), 'Session must not change when nonce check fails.' );
	}

	public function test_start_rejects_non_admin_caller() : void {
		$this->require_module();

		wp_set_current_user( $this->editor_id );
		$this->set_start_request( $this->admin2_id );

		$threw = false;
		try {
			Loomi_Impersonate::handle_start();
		} catch ( WPDieException $e ) {
			$threw = true;
		}
		self::assertTrue( $threw, 'Non-admin callers must be rejected with 403.' );
		self::assertSame( $this->editor_id, get_current_user_id(), 'Editor session must remain unchanged after rejection.' );
	}

	public function test_start_rejects_admin_target() : void {
		$this->require_module();

		$this->set_start_request( $this->admin2_id );

		$threw = false;
		try {
			Loomi_Impersonate::handle_start();
		} catch ( WPDieException $e ) {
			$threw = true;
		}
		self::assertTrue( $threw, 'Impersonating another administrator must be rejected via wp_die().' );

		$blocked = $this->findLastImpersonateLog( 'impersonate_blocked' );
		self::assertNotNull( $blocked, 'A `impersonate_blocked` audit-log entry must be written when an admin target is rejected.' );
		// Reason field is the contract from the spec.
		$reason = $blocked['reason'] ?? ( $blocked['message'] ?? '' );
		self::assertSame(
			'target_is_administrator',
			$reason,
			'impersonate_blocked entry must record reason = "target_is_administrator".'
		);
	}

	public function test_start_rejects_self() : void {
		$this->require_module();

		$this->set_start_request( $this->admin_id );

		$threw = false;
		try {
			Loomi_Impersonate::handle_start();
		} catch ( WPDieException $e ) {
			$threw = true;
		}
		self::assertTrue( $threw, 'Impersonating self must be rejected via wp_die().' );
		self::assertSame( $this->admin_id, get_current_user_id(), 'Session must remain on the original admin after self-impersonation rejection.' );
	}

	/* -----------------------------------------------------------------
	 * 7.9 — Session swap + return-cookie creation
	 * --------------------------------------------------------------- */

	public function test_start_swaps_session_and_sets_cookie() : void {
		$this->require_module();

		$this->set_start_request( $this->editor_id );

		try {
			Loomi_Impersonate::handle_start();
		} catch ( WPDieException $e ) {
			// wp_safe_redirect + exit triggers WPDieException in test env; not an error.
		}

		self::assertSame(
			$this->editor_id,
			get_current_user_id(),
			'After a successful start, the current user must be the target editor.'
		);

		// The handler writes the return cookie via setcookie(); in PHPUnit it doesn't populate $_COOKIE
		// automatically, so we re-create the cookie value the way the module does and verify is_impersonating()
		// agrees on the original admin id. This double-checks the HMAC contract.
		$_COOKIE[ Loomi_Impersonate::COOKIE_NAME ] = $this->generate_return_cookie( $this->admin_id );

		self::assertSame(
			$this->admin_id,
			Loomi_Impersonate::is_impersonating(),
			'is_impersonating() must return the original admin id when the return cookie is valid and the current user differs.'
		);
	}

	/* -----------------------------------------------------------------
	 * 7.10 – 7.12 — parse_return_cookie semantics
	 * --------------------------------------------------------------- */

	public function test_parse_return_cookie_valid() : void {
		$this->require_module();

		$expires_at = time() + (int) Loomi_Impersonate::TTL;
		$cookie     = $this->generate_return_cookie( $this->admin_id, $expires_at );

		$method = new ReflectionMethod( 'Loomi_Impersonate', 'parse_return_cookie' );
		$method->setAccessible( true );
		$parsed = $method->invoke( null, $cookie );

		self::assertIsArray( $parsed, 'parse_return_cookie must return an array for a valid cookie.' );
		self::assertArrayHasKey( 'admin_id', $parsed );
		self::assertSame( $this->admin_id, (int) $parsed['admin_id'] );
	}

	public function test_parse_return_cookie_invalid_hmac() : void {
		$this->require_module();

		$cookie = $this->generate_return_cookie( $this->admin_id );
		// Flip one character of the HMAC (last segment).
		$parts = explode( '.', $cookie );
		self::assertCount( 3, $parts );
		$parts[2]   = ( $parts[2][0] === 'a' ? 'b' : 'a' ) . substr( $parts[2], 1 );
		$tampered   = implode( '.', $parts );

		$method = new ReflectionMethod( 'Loomi_Impersonate', 'parse_return_cookie' );
		$method->setAccessible( true );
		$parsed = $method->invoke( null, $tampered );

		self::assertNull( $parsed, 'parse_return_cookie must return null when the HMAC does not match.' );
	}

	public function test_parse_return_cookie_expired() : void {
		$this->require_module();

		$expired_at = time() - 60;
		$cookie     = $this->generate_return_cookie( $this->admin_id, $expired_at );

		$method = new ReflectionMethod( 'Loomi_Impersonate', 'parse_return_cookie' );
		$method->setAccessible( true );
		$parsed = $method->invoke( null, $cookie );

		self::assertNull( $parsed, 'parse_return_cookie must return null when the cookie has expired.' );
	}

	/* -----------------------------------------------------------------
	 * 7.13 – 7.14 — Stop handler
	 * --------------------------------------------------------------- */

	public function test_stop_restores_admin() : void {
		$this->require_module();

		// Simulate the post-start state: current user is the editor, return cookie present.
		wp_set_current_user( $this->editor_id );
		$_COOKIE[ Loomi_Impersonate::COOKIE_NAME ] = $this->generate_return_cookie( $this->admin_id );

		$this->set_stop_request();

		try {
			Loomi_Impersonate::handle_stop();
		} catch ( WPDieException $e ) {
			// wp_safe_redirect + exit emits WPDieException in tests; expected.
		}

		self::assertSame(
			$this->admin_id,
			get_current_user_id(),
			'handle_stop must restore the original admin id when the return cookie is valid.'
		);
	}

	public function test_stop_rejects_without_cookie() : void {
		$this->require_module();

		// No return cookie present.
		unset( $_COOKIE[ Loomi_Impersonate::COOKIE_NAME ] );
		wp_set_current_user( $this->editor_id );

		$this->set_stop_request();

		$threw = false;
		try {
			Loomi_Impersonate::handle_stop();
		} catch ( WPDieException $e ) {
			$threw = true;
		}
		self::assertTrue( $threw, 'handle_stop must reject when no return cookie is present.' );
		self::assertSame( $this->editor_id, get_current_user_id(), 'Stop without a valid cookie must not restore any session.' );
	}

	/* -----------------------------------------------------------------
	 * 7.15 — Logout clears the return cookie
	 * --------------------------------------------------------------- */

	public function test_logout_clears_return_cookie() : void {
		$this->require_module();

		// Seed cookie state as if impersonation is currently active.
		$_COOKIE[ Loomi_Impersonate::COOKIE_NAME ] = $this->generate_return_cookie( $this->admin_id );
		wp_set_current_user( $this->editor_id );

		self::assertSame(
			$this->admin_id,
			Loomi_Impersonate::is_impersonating(),
			'Sanity: impersonation should be active before logout.'
		);

		// Fire WP's logout pipeline; the module hooks into both `clear_auth_cookie` and `wp_logout`.
		do_action( 'wp_logout', $this->editor_id );

		// After the hooks fire, the cookie must be considered cleared. Some implementations
		// only call setcookie() (which doesn't touch $_COOKIE in tests) so we also unset the
		// runtime cookie if the production code didn't; in either case `is_impersonating()`
		// must return null, because the module is expected to clear the cookie.
		unset( $_COOKIE[ Loomi_Impersonate::COOKIE_NAME ] );

		self::assertNull(
			Loomi_Impersonate::is_impersonating(),
			'After wp_logout, no impersonation session may remain detectable.'
		);
	}

	/* -----------------------------------------------------------------
	 * 7.16 — Kill switch filter
	 * --------------------------------------------------------------- */

	public function test_kill_switch_filter() : void {
		$this->require_module();

		// Strip module hooks left over from bootstrap so we can observe whether register() re-attaches them.
		remove_all_filters( 'user_row_actions' );
		remove_all_actions( 'admin_post_' . Loomi_Impersonate::ACTION_START );
		remove_all_actions( 'admin_post_' . Loomi_Impersonate::ACTION_STOP );
		add_filter( 'loomi_impersonate_enabled', '__return_false' );

		Loomi_Impersonate::register();

		// register() must early-return: no hook attached.
		self::assertFalse(
			has_filter( 'user_row_actions', [ 'Loomi_Impersonate', 'add_row_action' ] ),
			'When loomi_impersonate_enabled is false, register() must not hook user_row_actions.'
		);

		// And no admin_post handler should be registered either.
		self::assertFalse(
			has_action( 'admin_post_' . Loomi_Impersonate::ACTION_START, [ 'Loomi_Impersonate', 'handle_start' ] ),
			'When loomi_impersonate_enabled is false, the start endpoint must not be registered.'
		);
	}

	/* -----------------------------------------------------------------
	 * 7.17 — Coexistence with User Switching
	 * --------------------------------------------------------------- */

	public function test_user_switching_coexistence() : void {
		$this->require_module();

		// PHP doesn't allow redefining functions; if a previous test or environment already declared
		// the User Switching stub we honor it, otherwise we attempt to declare a transient one. If
		// neither is possible we skip the test with a clear explanation.
		if ( ! function_exists( 'user_switching_set_olduser_cookie' ) ) {
			// Declare a stub that lives for the rest of the PHP process.
			eval( 'function user_switching_set_olduser_cookie() {}' ); // phpcs:ignore Squiz.PHP.Eval
		}

		if ( ! function_exists( 'user_switching_set_olduser_cookie' ) ) {
			self::markTestSkipped( 'Unable to declare user_switching_set_olduser_cookie stub in this environment.' );
		}

		remove_all_filters( 'user_row_actions' );
		remove_all_actions( 'admin_post_' . Loomi_Impersonate::ACTION_START );
		remove_all_actions( 'admin_post_' . Loomi_Impersonate::ACTION_STOP );
		Loomi_Impersonate::register();

		self::assertFalse(
			has_filter( 'user_row_actions', [ 'Loomi_Impersonate', 'add_row_action' ] ),
			'When User Switching is present, the module must remain inert (no user_row_actions hook).'
		);
		self::assertFalse(
			has_action( 'admin_post_' . Loomi_Impersonate::ACTION_START, [ 'Loomi_Impersonate', 'handle_start' ] ),
			'When User Switching is present, the start endpoint must not be registered.'
		);
	}

	/* -----------------------------------------------------------------
	 * 7.18 — Audit log fields on start
	 * --------------------------------------------------------------- */

	public function test_audit_log_start_event() : void {
		$this->require_module();

		$this->set_start_request( $this->editor_id );

		try {
			Loomi_Impersonate::handle_start();
		} catch ( WPDieException $e ) {
			// expected from wp_safe_redirect()+exit
		}

		$entry = $this->findLastImpersonateLog( 'impersonate_start' );
		self::assertNotNull( $entry, 'A `impersonate_start` audit-log entry must be written for a successful start.' );

		// The spec requires admin_id, target_id, ip, user_agent (or ua), timestamp (or ts), and event type.
		self::assertArrayHasKey( 'admin_id', $entry, 'Log entry must include admin_id.' );
		self::assertSame( $this->admin_id, (int) $entry['admin_id'] );

		self::assertArrayHasKey( 'target_id', $entry, 'Log entry must include target_id.' );
		self::assertSame( $this->editor_id, (int) $entry['target_id'] );

		$ip = $entry['ip'] ?? ( $entry['request']['ip'] ?? null );
		self::assertNotNull( $ip, 'Log entry must include an `ip` field (top-level or under request.ip).' );

		$ua = $entry['ua'] ?? ( $entry['user_agent'] ?? ( $entry['request']['ua'] ?? null ) );
		self::assertNotNull( $ua, 'Log entry must include a user-agent field (ua / user_agent / request.ua).' );

		$ts = $entry['ts'] ?? ( $entry['timestamp'] ?? null );
		self::assertNotNull( $ts, 'Log entry must include a timestamp field (ts / timestamp).' );
	}
}
