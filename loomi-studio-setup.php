<?php
/**
 * Plugin Name:       Loomi Studio Setup
 * Plugin URI:        https://loomi.studio
 * Description:       Pacote de ajustes recorrentes para sites Loomi: upload de SVG, custom login, slug de login, ocultação de menus, role de cliente, duplicação de posts/páginas e auto-update centralizado.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  wordfence
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

define( 'LOOMI_STUDIO_VERSION', '1.0.0' );
define( 'LOOMI_STUDIO_FILE', __FILE__ );
define( 'LOOMI_STUDIO_DIR', plugin_dir_path( __FILE__ ) );
define( 'LOOMI_STUDIO_URL', plugin_dir_url( __FILE__ ) );
define( 'LOOMI_STUDIO_BASENAME', plugin_basename( __FILE__ ) );

if ( ! defined( 'LOOMI_STUDIO_UPDATE_SERVER' ) ) {
	define( 'LOOMI_STUDIO_UPDATE_SERVER', 'https://updates.loomi.studio/loomi-studio-setup.json' );
}

require_once LOOMI_STUDIO_DIR . 'includes/class-loomi-settings.php';
require_once LOOMI_STUDIO_DIR . 'includes/class-loomi-updater.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-svg.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-login.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-admin-menu.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-role.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-duplicate.php';
require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-wordfence-check.php';

add_action( 'plugins_loaded', static function () {
	load_plugin_textdomain( 'loomi-studio-setup', false, dirname( LOOMI_STUDIO_BASENAME ) . '/languages' );

	Loomi_Settings::init();
	Loomi_SVG::init();
	Loomi_Login::init();
	Loomi_Admin_Menu::init();
	Loomi_Role::init();
	Loomi_Duplicate::init();
	if ( is_admin() ) {
		Loomi_Wordfence_Check::init();
	}

	if ( defined( 'LOOMI_STUDIO_UPDATE_SERVER' ) && LOOMI_STUDIO_UPDATE_SERVER ) {
		Loomi_Updater::init();
	}
} );

register_activation_hook( __FILE__, static function () {
	Loomi_Role::create();
	if ( ! get_option( 'loomi_studio_setup_settings' ) ) {
		add_option( 'loomi_studio_setup_settings', Loomi_Settings::defaults(), '', 'yes' );
	}
} );

register_deactivation_hook( __FILE__, static function () {
	flush_rewrite_rules( false );
} );
