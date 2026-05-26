<?php

class RoleTest extends Loomi_TestCase {

	public function set_up() : void {
		parent::set_up();
		Loomi_Role::create();
	}

	public function tear_down() : void {
		Loomi_Role::remove();
		parent::tear_down();
	}

	public function test_role_created() : void {
		self::assertNotNull( get_role( 'loomi_client' ) );
	}

	public function test_role_has_read_capability() : void {
		$role = get_role( 'loomi_client' );
		self::assertTrue( ! empty( $role->capabilities['read'] ) );
	}

	public function test_role_inherits_editor_baseline_caps() : void {
		$role = get_role( 'loomi_client' );
		self::assertTrue( ! empty( $role->capabilities['edit_posts'] ) );
		self::assertTrue( ! empty( $role->capabilities['edit_pages'] ) );
		self::assertTrue( ! empty( $role->capabilities['publish_posts'] ) );
	}

	public function test_role_lacks_all_forbidden_caps() : void {
		$role = get_role( 'loomi_client' );
		foreach ( Loomi_Role::FORBIDDEN_CAPS as $cap ) {
			self::assertArrayNotHasKey( $cap, $role->capabilities, "Cap $cap should not be present" );
		}
	}

	public function test_role_hidden_from_editable_roles_when_toggle_off() : void {
		$this->set_settings( [ 'client_role_enabled' => false ] );
		$roles    = wp_roles()->get_names();
		$filtered = apply_filters( 'editable_roles', $roles );
		self::assertArrayNotHasKey( 'loomi_client', $filtered );
	}

	public function test_role_visible_in_editable_roles_when_toggle_on() : void {
		$this->set_settings( [ 'client_role_enabled' => true ] );
		$roles    = wp_roles()->get_names();
		$filtered = apply_filters( 'editable_roles', $roles );
		self::assertArrayHasKey( 'loomi_client', $filtered );
	}

	public function test_remove_reassigns_users_to_subscriber() : void {
		$user_id = self::factory()->user->create( [ 'role' => 'loomi_client' ] );
		Loomi_Role::remove();

		$user = get_userdata( $user_id );
		self::assertContains( 'subscriber', $user->roles );
		self::assertNotContains( 'loomi_client', $user->roles );
		self::assertNull( get_role( 'loomi_client' ) );
	}
}
