<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/modules/class-loomi-role.php';

Loomi_Role::remove();

delete_option( Plugin::OPTION_KEY );
delete_transient( Plugin::UPDATE_TRANSIENT );
