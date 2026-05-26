<?php

class WordfenceCheckTest extends Loomi_TestCase {

	public function test_state_absent_when_file_missing() : void {
		// Wordfence not installed in test env
		if ( file_exists( WP_PLUGIN_DIR . '/' . Plugin::WORDFENCE_FILE ) ) {
			self::markTestSkipped( 'Wordfence is installed; cannot test absent state.' );
		}
		self::assertSame( 'absent', Loomi_Wordfence_Check::get_state() );
	}

	public function test_notice_renders_for_admin_when_absent() : void {
		if ( file_exists( WP_PLUGIN_DIR . '/' . Plugin::WORDFENCE_FILE ) ) {
			self::markTestSkipped( 'Wordfence is installed.' );
		}
		$this->login_as( 'administrator' );

		ob_start();
		Loomi_Wordfence_Check::render_notice();
		$out = ob_get_clean();

		self::assertStringContainsString( 'notice-error', $out );
		self::assertStringContainsString( 'Instalar Wordfence agora', $out );
		self::assertStringNotContainsString( 'is-dismissible', $out );
	}

	public function test_notice_hidden_for_loomi_client() : void {
		Loomi_Role::create();
		$user_id = self::factory()->user->create( [ 'role' => 'loomi_client' ] );
		wp_set_current_user( $user_id );

		ob_start();
		Loomi_Wordfence_Check::render_notice();
		$out = ob_get_clean();

		self::assertEmpty( trim( $out ) );

		Loomi_Role::remove();
	}

	public function test_install_button_hidden_without_install_plugins_cap() : void {
		if ( file_exists( WP_PLUGIN_DIR . '/' . Plugin::WORDFENCE_FILE ) ) {
			self::markTestSkipped( 'Wordfence is installed.' );
		}
		$user_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		// Editor has activate_plugins? Actually no — only admin has both.
		// To test the no-install-plugins-but-has-activate-plugins case, fabricate caps
		$user = get_user_by( 'id', $user_id );
		$user->add_cap( 'activate_plugins' );
		wp_set_current_user( $user_id );

		ob_start();
		Loomi_Wordfence_Check::render_notice();
		$out = ob_get_clean();

		self::assertStringContainsString( 'Solicite ao administrador', $out );
		self::assertStringNotContainsString( 'Instalar Wordfence agora', $out );
	}
}
