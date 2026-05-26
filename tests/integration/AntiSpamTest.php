<?php

class AntiSpamTest extends Loomi_TestCase {

	public function tear_down() : void {
		unset( $_POST[ Loomi_Anti_Spam::HP_FIELD ] );
		unset( $_POST[ Loomi_Anti_Spam::TIME_FIELD ] );
		unset( $_SERVER['REQUEST_METHOD'] );
		parent::tear_down();
	}

	public function test_all_defaults_true() : void {
		delete_option( Plugin::OPTION_KEY );
		Settings_Repository::clear_cache();

		self::assertTrue( Settings_Repository::get_bool( 'anti_spam_enabled' ) );
		self::assertTrue( Settings_Repository::get_bool( 'anti_spam_honeypot' ) );
		self::assertTrue( Settings_Repository::get_bool( 'anti_spam_time_check' ) );
		self::assertTrue( Settings_Repository::get_bool( 'anti_spam_comment_lockdown' ) );
		self::assertTrue( Settings_Repository::get_bool( 'anti_spam_akismet_autoconfig' ) );
	}

	public function test_master_switch_off_disables_all() : void {
		$this->set_settings( [ 'anti_spam_enabled' => false ] );
		// is_bot_submission early-returns false because honeypot toggle is irrelevant when master off
		$_POST[ Loomi_Anti_Spam::HP_FIELD ] = 'filled-by-bot';
		// Re-evaluate: since the module wouldn't register hooks, the submission isn't blocked.
		// We test by re-registering and checking that no hooks were added.
		remove_all_filters( 'authenticate' );
		Loomi_Anti_Spam::register();
		self::assertFalse( has_filter( 'authenticate', [ 'Loomi_Anti_Spam', 'gate_authenticate' ] ) );
	}

	public function test_render_emits_honeypot_field() : void {
		$this->set_settings( [ 'anti_spam_enabled' => true, 'anti_spam_honeypot' => true, 'anti_spam_time_check' => false ] );
		ob_start();
		Loomi_Anti_Spam::render_hidden_fields();
		$out = ob_get_clean();
		self::assertStringContainsString( 'name="loomi_hp"', $out );
		self::assertStringContainsString( 'aria-hidden="true"', $out );
		self::assertStringContainsString( 'tabindex="-1"', $out );
		self::assertStringContainsString( 'left:-9999px', $out );
	}

	public function test_render_emits_time_field() : void {
		$this->set_settings( [ 'anti_spam_enabled' => true, 'anti_spam_honeypot' => false, 'anti_spam_time_check' => true ] );
		ob_start();
		Loomi_Anti_Spam::render_hidden_fields();
		$out = ob_get_clean();
		self::assertStringContainsString( 'name="loomi_t"', $out );
	}

	public function test_honeypot_filled_detected_as_bot() : void {
		$this->set_settings( [ 'anti_spam_enabled' => true, 'anti_spam_honeypot' => true, 'anti_spam_time_check' => false ] );
		$_POST[ Loomi_Anti_Spam::HP_FIELD ] = 'http://spam.example';
		self::assertTrue( Loomi_Anti_Spam::is_bot_submission() );
	}

	public function test_honeypot_empty_not_bot() : void {
		$this->set_settings( [ 'anti_spam_enabled' => true, 'anti_spam_honeypot' => true, 'anti_spam_time_check' => false ] );
		$_POST[ Loomi_Anti_Spam::HP_FIELD ] = '';
		self::assertFalse( Loomi_Anti_Spam::is_bot_submission() );
	}

	public function test_time_check_instant_submission_is_bot() : void {
		$this->set_settings( [ 'anti_spam_enabled' => true, 'anti_spam_honeypot' => false, 'anti_spam_time_check' => true ] );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST[ Loomi_Anti_Spam::TIME_FIELD ] = (string) time();
		self::assertTrue( Loomi_Anti_Spam::is_bot_submission() );
	}

	public function test_time_check_passes_after_delay() : void {
		$this->set_settings( [ 'anti_spam_enabled' => true, 'anti_spam_honeypot' => false, 'anti_spam_time_check' => true ] );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST[ Loomi_Anti_Spam::TIME_FIELD ] = (string) ( time() - 5 );
		self::assertFalse( Loomi_Anti_Spam::is_bot_submission() );
	}

	public function test_time_check_missing_field_is_bot() : void {
		$this->set_settings( [ 'anti_spam_enabled' => true, 'anti_spam_honeypot' => false, 'anti_spam_time_check' => true ] );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		// no TIME_FIELD in $_POST
		self::assertTrue( Loomi_Anti_Spam::is_bot_submission() );
	}

	public function test_gate_authenticate_returns_error_for_bot() : void {
		$this->set_settings( [ 'anti_spam_enabled' => true, 'anti_spam_honeypot' => true ] );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST[ Loomi_Anti_Spam::HP_FIELD ] = 'spam';

		$result = Loomi_Anti_Spam::gate_authenticate( null );
		self::assertInstanceOf( WP_Error::class, $result );
	}

	public function test_gate_comment_marks_spam_for_bot() : void {
		$this->set_settings( [ 'anti_spam_enabled' => true, 'anti_spam_honeypot' => true ] );
		$_POST[ Loomi_Anti_Spam::HP_FIELD ] = 'spam-content';

		$result = Loomi_Anti_Spam::gate_comment( 1, [] );
		self::assertSame( 'spam', $result );
	}

	public function test_gate_comment_passes_legitimate() : void {
		$this->set_settings( [ 'anti_spam_enabled' => true, 'anti_spam_honeypot' => true, 'anti_spam_time_check' => false ] );
		// no $_POST manipulation — honeypot empty
		$result = Loomi_Anti_Spam::gate_comment( 1, [] );
		self::assertSame( 1, $result );
	}

	public function test_xmlrpc_pingback_methods_removed() : void {
		$methods = [
			'pingback.ping' => 'callback',
			'pingback.extensions.getPingbacks' => 'callback',
			'system.listMethods' => 'callback',
		];
		$filtered = Loomi_Anti_Spam::strip_pingback_methods( $methods );
		self::assertArrayNotHasKey( 'pingback.ping', $filtered );
		self::assertArrayNotHasKey( 'pingback.extensions.getPingbacks', $filtered );
		self::assertArrayHasKey( 'system.listMethods', $filtered );
	}

	public function test_comment_lockdown_sets_options() : void {
		$this->set_settings( [ 'anti_spam_enabled' => true, 'anti_spam_comment_lockdown' => true ] );
		// Reset before
		update_option( 'default_pingback_flag', 1 );
		update_option( 'comment_moderation', 0 );

		Loomi_Anti_Spam::apply_comment_lockdown();

		self::assertSame( '0', (string) get_option( 'default_pingback_flag' ) );
		self::assertSame( 'closed', get_option( 'default_ping_status' ) );
		self::assertSame( '1', (string) get_option( 'comment_moderation' ) );
	}

	public function test_akismet_autoconfig_skipped_when_constant_undefined() : void {
		// LOOMI_AKISMET_KEY is not defined in tests
		$before = get_option( 'wordpress_api_key' );
		Loomi_Anti_Spam::configure_akismet();
		$after = get_option( 'wordpress_api_key' );
		self::assertSame( $before, $after );
	}

	public function test_tab_renders_4_toggles() : void {
		$tab = new Tab_Anti_Spam();
		ob_start();
		$tab->render( Settings_Repository::all() );
		$out = ob_get_clean();

		self::assertStringContainsString( 'anti_spam_enabled', $out );
		self::assertStringContainsString( 'anti_spam_honeypot', $out );
		self::assertStringContainsString( 'anti_spam_time_check', $out );
		self::assertStringContainsString( 'anti_spam_comment_lockdown', $out );
		// Akismet autoconfig removido da UI (continua funcional via constante LOOMI_AKISMET_KEY)
		self::assertStringNotContainsString( 'anti_spam_akismet_autoconfig', $out );
	}
}
