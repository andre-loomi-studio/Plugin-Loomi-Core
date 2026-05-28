<?php
/**
 * Plugin Name:       Loomi Studio Setup
 * Plugin URI:        https://loomi.studio
 * Description:       Pacote de ajustes recorrentes para sites Loomi: upload de SVG, custom login, slug de login, ocultação de menus, role de cliente, duplicação de posts/páginas e auto-update centralizado.
 * Version:           1.2.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Loomi
 * Author URI:        https://loomi.studio
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       loomi-studio-setup
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LOOMI_STUDIO_VERSION', '1.2.1' );
define( 'LOOMI_STUDIO_FILE', __FILE__ );
define( 'LOOMI_STUDIO_DIR', plugin_dir_path( __FILE__ ) );
define( 'LOOMI_STUDIO_URL', plugin_dir_url( __FILE__ ) );
define( 'LOOMI_STUDIO_BASENAME', plugin_basename( __FILE__ ) );

if ( ! defined( 'LOOMI_STUDIO_UPDATE_SERVER' ) ) {
	define( 'LOOMI_STUDIO_UPDATE_SERVER', 'https://updates.loomi.studio/loomi-studio-setup.json' );
}

// Core
require_once LOOMI_STUDIO_DIR . 'includes/class-plugin.php';
require_once LOOMI_STUDIO_DIR . 'includes/contracts/interface-module.php';
require_once LOOMI_STUDIO_DIR . 'includes/contracts/interface-settings-tab.php';

// Support
require_once LOOMI_STUDIO_DIR . 'includes/support/class-settings-repository.php';
require_once LOOMI_STUDIO_DIR . 'includes/support/class-settings-sanitizer.php';
require_once LOOMI_STUDIO_DIR . 'includes/support/class-login-urls.php';
require_once LOOMI_STUDIO_DIR . 'includes/support/class-loomi-ui.php';
require_once LOOMI_STUDIO_DIR . 'includes/support/class-log-writer.php';
require_once LOOMI_STUDIO_DIR . 'includes/support/class-log-context.php';

// Settings UI
require_once LOOMI_STUDIO_DIR . 'includes/settings/tabs/class-tab-dashboard.php';
require_once LOOMI_STUDIO_DIR . 'includes/settings/tabs/class-tab-login.php';
require_once LOOMI_STUDIO_DIR . 'includes/settings/tabs/class-tab-slug.php';
require_once LOOMI_STUDIO_DIR . 'includes/settings/tabs/class-tab-hide-menus.php';
require_once LOOMI_STUDIO_DIR . 'includes/settings/tabs/class-tab-client-role.php';
require_once LOOMI_STUDIO_DIR . 'includes/settings/tabs/class-tab-anti-spam.php';
require_once LOOMI_STUDIO_DIR . 'includes/settings/tabs/class-tab-schema.php';
require_once LOOMI_STUDIO_DIR . 'includes/settings/class-settings-page.php';

// Modules
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-critical-logger.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-svg.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-login.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-admin-menu.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-role.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-duplicate.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-anti-spam.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-wordfence-check.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-dashboard-widget.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-schema.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-impersonate.php';
require_once LOOMI_STUDIO_DIR . 'includes/class-loomi-updater.php';

// Legacy alias
require_once LOOMI_STUDIO_DIR . 'includes/class-loomi-settings.php';

add_action( 'plugins_loaded', static function () {
	load_plugin_textdomain( Plugin::TEXT_DOMAIN, false, dirname( Plugin::basename() ) . '/languages' );

	// Critical logger registers BEFORE other modules so exceptions thrown by them get captured.
	Loomi_Critical_Logger::register();

	$modules = [
		Loomi_SVG::class,
		Loomi_Login::class,
		Loomi_Admin_Menu::class,
		Loomi_Role::class,
		Loomi_Duplicate::class,
		Loomi_Anti_Spam::class,
		Loomi_Wordfence_Check::class,
		Loomi_Dashboard_Widget::class,
		Loomi_Schema::class,
		Loomi_Impersonate::class,
		Loomi_Settings_Page::class,
	];
	foreach ( $modules as $module ) {
		$module::register();
	}

	if ( defined( 'LOOMI_STUDIO_UPDATE_SERVER' ) && LOOMI_STUDIO_UPDATE_SERVER ) {
		Loomi_Updater::register();
	}
} );

register_activation_hook( __FILE__, static function () {
	Loomi_Critical_Logger::install();
	Loomi_Role::create();
	if ( ! get_option( Plugin::OPTION_KEY ) ) {
		add_option( Plugin::OPTION_KEY, Settings_Repository::defaults(), '', 'yes' );
	}
	Loomi_Anti_Spam::apply_comment_lockdown();
} );

register_deactivation_hook( __FILE__, static function () {
	flush_rewrite_rules( false );
} );
