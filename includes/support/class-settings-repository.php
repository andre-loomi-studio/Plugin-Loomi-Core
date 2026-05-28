<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings_Repository {

	// Apenas menus que editor/loomi_client realmente vê na sidebar.
	// WP já esconde automaticamente Plugins/Themes/Users/Settings via capability gating
	// (activate_plugins / switch_themes / list_users / manage_options) — então não fazem
	// sentido aqui: o admin desmarcaria sem nenhum efeito prático.
	const HIDEABLE_MENUS = [
		'edit.php'                => 'Posts',
		'edit.php?post_type=page' => 'Páginas',
		'edit-comments.php'       => 'Comentários',
		'upload.php'              => 'Mídia',
		'tools.php'               => 'Ferramentas',
	];

	const BLACKLISTED_MENUS = [
		'index.php',
		'options-general.php',
	];

	const RESERVED_SLUGS = [ 'wp-admin', 'wp-login', 'admin', 'login', 'wp-content', 'wp-includes' ];

	const THEME_VALUES = [ 'dark', 'light', 'auto' ];

	const BOOL_FIELDS = [
		'custom_login_enabled',
		'login_slug_enabled',
		'hide_menus_enabled',
		'client_role_enabled',
		'hide_admin_endpoint',
		'anti_spam_enabled',
		'anti_spam_honeypot',
		'anti_spam_time_check',
		'anti_spam_comment_lockdown',
		'anti_spam_akismet_autoconfig',
	];

	private static $cache          = null;
	private static $hideable_cache = null;

	public static function defaults() : array {
		return [
			'custom_login_enabled'  => false,
			'custom_login_bg_color' => '#000000',
			'custom_login_logo_id'  => 0,
			'login_slug_enabled'    => true,
			'login_slug'            => 'studio-access',
			'hide_admin_endpoint'   => true,
			'hide_menus_enabled'    => false,
			'hidden_menus'          => array_keys( self::HIDEABLE_MENUS ),
			'client_role_enabled'   => true,
			'anti_spam_enabled'           => true,
			'anti_spam_honeypot'          => true,
			'anti_spam_time_check'        => true,
			'anti_spam_comment_lockdown'  => true,
			'anti_spam_akismet_autoconfig'=> true,
			'loomi_theme'                 => 'dark',
			'loomi_schema_global'         => [],
		];
	}

	public static function all() : array {
		if ( self::$cache === null ) {
			$stored = get_option( Plugin::OPTION_KEY, [] );
			$merged = is_array( $stored ) ? array_merge( self::defaults(), $stored ) : self::defaults();
			foreach ( self::BOOL_FIELDS as $field ) {
				$merged[ $field ] = filter_var( $merged[ $field ], FILTER_VALIDATE_BOOLEAN );
			}
			self::$cache = $merged;
		}
		return self::$cache;
	}

	public static function get( string $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	public static function get_bool( string $key ) : bool {
		return (bool) filter_var( self::get( $key, false ), FILTER_VALIDATE_BOOLEAN );
	}

	public static function clear_cache() : void {
		self::$cache          = null;
		self::$hideable_cache = null;
	}

	/**
	 * Lista dinâmica de menus que podem ser escondidos: 5 core hardcoded + CPTs públicos
	 * registrados no site (descobertos via get_post_types). Memoizada por-request.
	 */
	public static function hideable_menus() : array {
		if ( self::$hideable_cache !== null ) {
			return self::$hideable_cache;
		}

		$menus = self::HIDEABLE_MENUS;

		if ( function_exists( 'get_post_types' ) ) {
			$cpts = get_post_types(
				[ 'show_ui' => true, 'show_in_menu' => true, '_builtin' => false ],
				'objects'
			);
			foreach ( $cpts as $cpt ) {
				$slug = 'edit.php?post_type=' . $cpt->name;
				if ( isset( $menus[ $slug ] ) ) {
					continue; // core takes precedence
				}
				$label = $cpt->labels->menu_name ?? $cpt->labels->name ?? $cpt->name;
				$menus[ $slug ] = $label;
			}
		}

		self::$hideable_cache = $menus;
		return $menus;
	}
}
