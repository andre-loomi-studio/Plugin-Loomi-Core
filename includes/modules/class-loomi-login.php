<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Login {

	const ALLOWED_LOGIN_ACTIONS = [ 'logout', 'lostpassword', 'retrievepassword', 'rp', 'resetpass', 'postpass', 'register' ];

	public static function init() : void {
		if ( Loomi_Settings::get( 'custom_login_enabled' ) ) {
			add_action( 'login_enqueue_scripts', [ __CLASS__, 'inject_login_styles' ] );
			add_filter( 'login_headerurl', [ __CLASS__, 'login_logo_url' ] );
			add_filter( 'login_headertext', [ __CLASS__, 'login_logo_title' ] );
		}

		if ( Loomi_Settings::get( 'login_slug_enabled' ) ) {
			add_action( 'init', [ __CLASS__, 'register_rewrite_rule' ] );
			add_action( 'login_init', [ __CLASS__, 'gate_wp_login' ], 1 );
		}

		add_action( 'update_option_' . Loomi_Settings::OPTION_KEY, [ __CLASS__, 'maybe_flush_rewrites' ], 10, 2 );
	}

	public static function inject_login_styles() : void {
		$bg       = Loomi_Settings::get( 'custom_login_bg_color', '#000000' );
		$logo_id  = (int) Loomi_Settings::get( 'custom_login_logo_id', 0 );
		$logo_url = $logo_id ? wp_get_attachment_url( $logo_id ) : '';

		$css  = 'body.login{background:' . esc_attr( $bg ) . ' !important;}';
		$css .= '#nav a,#backtoblog a,.privacy-policy-link{color:#fff !important;}';
		$css .= '.login #login_error,.login .message,.login .success{color:#1d2327;}';

		if ( $logo_url ) {
			$css .= '.login h1 a{'
				. 'background-image:url("' . esc_url( $logo_url ) . '") !important;'
				. 'width:320px !important;height:120px !important;margin-bottom:60px !important;'
				. 'background-size:contain !important;background-position:center center !important;'
				. 'background-repeat:no-repeat !important;'
				. '}';
		}

		echo "<style id=\"loomi-login\">{$css}</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function login_logo_url() : string {
		return home_url();
	}

	public static function login_logo_title() : string {
		return get_bloginfo( 'name' );
	}

	public static function register_rewrite_rule() : void {
		$slug = (string) Loomi_Settings::get( 'login_slug', 'studio-access' );
		if ( $slug === '' ) {
			return;
		}
		add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/?$', 'wp-login.php', 'top' );
	}

	public static function gate_wp_login() : void {
		if ( is_user_logged_in() ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( in_array( $action, self::ALLOWED_LOGIN_ACTIONS, true ) ) {
			return;
		}

		// POST is passed through so the login form (whose <form action> targets wp-login.php)
		// keeps working from /studio-access/. This means the slug protects against bot GET-scans
		// but not against targeted credential-stuffing POSTs — accepted trade-off; harden via
		// rate-limit/2FA at the WP/host layer if that threat applies.
		if ( ! empty( $_SERVER['REQUEST_METHOD'] ) && strtoupper( $_SERVER['REQUEST_METHOD'] ) === 'POST' ) {
			return;
		}

		$slug         = (string) Loomi_Settings::get( 'login_slug', 'studio-access' );
		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$request_path = trim( rawurldecode( $request_path ), '/' );

		if ( $slug !== '' && $request_path === trim( $slug, '/' ) ) {
			return;
		}

		status_header( 404 );
		nocache_headers();
		wp_die( '', '', [ 'response' => 404 ] );
	}

	public static function maybe_flush_rewrites( $old, $new ) : void {
		$old = is_array( $old ) ? $old : [];
		$new = is_array( $new ) ? $new : [];

		$old_slug = $old['login_slug'] ?? null;
		$new_slug = $new['login_slug'] ?? null;
		$old_on   = ! empty( $old['login_slug_enabled'] );
		$new_on   = ! empty( $new['login_slug_enabled'] );

		if ( $old_slug !== $new_slug || $old_on !== $new_on ) {
			flush_rewrite_rules( false );
		}
	}
}
