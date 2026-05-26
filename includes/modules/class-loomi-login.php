<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Login implements Loomi_Module {

	const ALLOWED_LOGIN_ACTIONS = [ 'logout', 'lostpassword', 'retrievepassword', 'rp', 'resetpass', 'postpass', 'register' ];

	public static function register() : void {
		if ( Settings_Repository::get_bool( 'custom_login_enabled' ) ) {
			add_action( 'login_enqueue_scripts', [ __CLASS__, 'inject_login_styles' ] );
			add_filter( 'login_headerurl', [ __CLASS__, 'login_logo_url' ] );
			add_filter( 'login_headertext', [ __CLASS__, 'login_logo_title' ] );
		}

		if ( Settings_Repository::get_bool( 'login_slug_enabled' ) ) {
			add_action( 'init', [ __CLASS__, 'maybe_serve_login' ], 1 );
			add_action( 'login_init', [ __CLASS__, 'gate_wp_login' ], 1 );

			// Bloqueia leak da slug via /wp-admin/.
			// Hook em init priority 1 dispara durante wp-settings.php, ANTES do
			// wp-admin/admin.php chamar auth_redirect() (que faria 302 vazando a slug).
			add_action( 'init', [ __CLASS__, 'gate_admin_endpoint' ], 1 );

			add_filter( 'login_url',         [ __CLASS__, 'filter_login_url' ], 10, 3 );
			add_filter( 'logout_url',        [ __CLASS__, 'filter_logout_url' ], 10, 2 );
			add_filter( 'logout_redirect',   [ __CLASS__, 'filter_logout_redirect' ], 10, 3 );
			add_filter( 'lostpassword_url',  [ __CLASS__, 'filter_lostpassword_url' ], 10, 2 );
			add_filter( 'register_url',      [ __CLASS__, 'filter_register_url' ] );
		}
	}

	public static function inject_login_styles() : void {
		$bg       = Settings_Repository::get( 'custom_login_bg_color', '#000000' );
		$logo_id  = (int) Settings_Repository::get( 'custom_login_logo_id', 0 );
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

	public static function maybe_serve_login() : void {
		$slug = trim( (string) Settings_Repository::get( 'login_slug', 'studio-access' ), '/' );
		if ( $slug === '' ) return;

		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$request_path = trim( rawurldecode( $request_path ), '/' );

		if ( $request_path !== $slug ) return;

		if ( ! defined( 'LOOMI_LOGIN_ROUTE' ) ) {
			define( 'LOOMI_LOGIN_ROUTE', true );
		}
		require ABSPATH . 'wp-login.php';
		exit;
	}

	/**
	 * Bloqueia /wp-admin/ pra visitantes não autenticados retornando 404 (em vez do
	 * 302 padrão que vazaria a slug custom no header Location).
	 *
	 * Disparada via add_action('init', ..., 1) — durante wp-settings.php, ANTES do
	 * wp-admin/admin.php chamar auth_redirect().
	 */
	public static function gate_admin_endpoint() : void {
		// Escape hatch via constante (admin trancado pode definir em wp-config.php).
		if ( defined( 'LOOMI_STUDIO_DISABLE_HARDENING' ) && LOOMI_STUDIO_DISABLE_HARDENING ) {
			return;
		}
		if ( ! Settings_Repository::get_bool( 'hide_admin_endpoint' ) ) {
			return;
		}
		// Apenas em contexto wp-admin (WP_ADMIN constante).
		if ( ! is_admin() ) {
			return;
		}
		if ( is_user_logged_in() ) {
			return;
		}
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		self::render_not_found();
	}

	public static function gate_wp_login() : void {
		if ( defined( 'LOOMI_LOGIN_ROUTE' ) ) return;
		if ( is_user_logged_in() ) return;

		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( in_array( $action, self::ALLOWED_LOGIN_ACTIONS, true ) ) return;

		if ( ! empty( $_SERVER['REQUEST_METHOD'] ) && strtoupper( $_SERVER['REQUEST_METHOD'] ) === 'POST' ) return;

		self::render_not_found();
	}

	public static function render_not_found() : void {
		status_header( 404 );
		nocache_headers();

		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->set_404();
		}

		// get_query_template() resolves theme hierarchy: returns the classic 404.php for
		// classic themes OR template-canvas.php for block themes (which then renders the
		// theme's 404 block template). Funciona pra ambos os tipos de tema.
		$template = get_query_template( '404' );
		if ( $template && file_exists( $template ) ) {
			include $template;
			exit;
		}

		// Last-resort fallback: minimal wp_die when nem template clássico nem block existe.
		wp_die(
			'<p>' . esc_html__( 'A página solicitada não existe neste site.', 'loomi-studio-setup' ) . '</p>',
			esc_html__( '404 — Página não encontrada', 'loomi-studio-setup' ),
			[ 'response' => 404 ]
		);
	}

	public static function filter_login_url( $url, $redirect, $force_reauth ) {
		return Login_URLs::build( '', [ 'redirect_to' => $redirect, 'reauth' => $force_reauth ? '1' : null ] );
	}

	public static function filter_logout_url( $url, $redirect ) {
		return wp_nonce_url( Login_URLs::build( 'logout', [ 'redirect_to' => $redirect ] ), 'log-out' );
	}

	public static function filter_logout_redirect( $redirect_to, $requested, $user ) {
		if ( empty( $redirect_to ) || strpos( (string) $redirect_to, 'wp-login.php' ) !== false ) {
			return Login_URLs::build( '', [ 'loggedout' => 'true' ] );
		}
		return $redirect_to;
	}

	public static function filter_lostpassword_url( $url, $redirect ) {
		return Login_URLs::build( 'lostpassword', [ 'redirect_to' => $redirect ] );
	}

	public static function filter_register_url( $url ) {
		return Login_URLs::build( 'register' );
	}
}
