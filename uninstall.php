<?php
/**
 * Uninstall handler — removes role, option, transient.
 *
 * Runs only when the user clicks "Delete" in the plugins screen,
 * never on deactivation.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/modules/class-loomi-role.php';

Loomi_Role::remove();

delete_option( 'loomi_studio_setup_settings' );
delete_transient( 'loomi_update_check' );
