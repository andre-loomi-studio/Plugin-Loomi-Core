<?php

abstract class Loomi_TestCase extends WP_UnitTestCase {

	public function set_up() : void {
		parent::set_up();
		Settings_Repository::clear_cache();
		delete_option( Plugin::OPTION_KEY );
	}

	protected function set_settings( array $overrides ) : void {
		$defaults = Settings_Repository::defaults();
		update_option( Plugin::OPTION_KEY, array_merge( $defaults, $overrides ) );
		Settings_Repository::clear_cache();
	}

	protected function login_as( string $role ) : int {
		$user_id = self::factory()->user->create( [ 'role' => $role ] );
		wp_set_current_user( $user_id );
		return $user_id;
	}
}
