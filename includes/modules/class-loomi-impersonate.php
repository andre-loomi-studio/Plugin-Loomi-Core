<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Impersonate implements Loomi_Module {

	const COOKIE_NAME  = 'loomi_impersonate_return';
	const ACTION_START = 'loomi_impersonate_start';
	const ACTION_STOP  = 'loomi_impersonate_stop';
	const NONCE_START  = 'loomi_impersonate_start';
	const NONCE_STOP   = 'loomi_impersonate_stop';
	const TTL          = 7200;

	public static function register() : void {
		if ( ! apply_filters( 'loomi_impersonate_enabled', true ) ) {
			return;
		}

		if ( function_exists( 'user_switching_set_olduser_cookie' ) ) {
			add_action( 'admin_notices', [ __CLASS__, 'render_user_switching_notice' ] );
			return;
		}

		add_filter( 'user_row_actions',                                [ __CLASS__, 'add_row_action' ],     10, 2 );
		add_action( 'edit_user_profile',                               [ __CLASS__, 'render_profile_link' ] );
		add_action( 'admin_post_' . self::ACTION_START,                [ __CLASS__, 'handle_start' ] );
		add_action( 'admin_post_' . self::ACTION_STOP,                 [ __CLASS__, 'handle_stop' ] );
		add_action( 'admin_notices',                                   [ __CLASS__, 'render_banner' ] );
		add_action( 'admin_bar_menu',                                  [ __CLASS__, 'add_admin_bar_node' ], 80 );
		add_action( 'wp_logout',                                       [ __CLASS__, 'clear_return_cookie' ] );
		add_action( 'clear_auth_cookie',                               [ __CLASS__, 'clear_return_cookie' ] );
	}

	public static function render_user_switching_notice() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p>'
			. esc_html__( 'Loomi Impersonate está inativo porque o plugin User Switching está ativo. A funcionalidade de troca de usuário é delegada a ele.', 'loomi-studio-setup' )
			. '</p></div>';
	}

	public static function add_row_action( array $actions, $user ) : array {
		if ( ! ( $user instanceof WP_User ) ) {
			return $actions;
		}
		if ( ! self::can_impersonate_target( $user ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION_START . '&user_id=' . $user->ID ),
			self::NONCE_START . '_' . $user->ID
		);

		$actions['loomi_impersonate'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $url ),
			esc_attr( sprintf( __( 'Impersonar %s', 'loomi-studio-setup' ), $user->display_name ) ),
			esc_html__( 'Impersonar', 'loomi-studio-setup' )
		);
		return $actions;
	}

	public static function render_profile_link( $user ) : void {
		if ( ! ( $user instanceof WP_User ) ) {
			return;
		}
		if ( ! self::can_impersonate_target( $user ) ) {
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION_START . '&user_id=' . $user->ID ),
			self::NONCE_START . '_' . $user->ID
		);

		echo '<table class="form-table"><tr>';
		echo '<th>' . esc_html__( 'Impersonate', 'loomi-studio-setup' ) . '</th>';
		echo '<td><a class="button" href="' . esc_url( $url ) . '">'
			. esc_html__( 'Impersonar este usuário', 'loomi-studio-setup' )
			. '</a></td>';
		echo '</tr></table>';
	}

	public static function handle_start() : void {
		$target_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;

		check_admin_referer( self::NONCE_START . '_' . $target_id );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', '', [ 'response' => 403 ] );
		}

		if ( $target_id <= 0 ) {
			wp_die( esc_html__( 'Usuário-alvo inválido.', 'loomi-studio-setup' ), '', [ 'response' => 400 ] );
		}

		$target = get_user_by( 'id', $target_id );
		if ( ! $target instanceof WP_User ) {
			wp_die( '', '', [ 'response' => 403 ] );
		}

		$admin_id = get_current_user_id();

		if ( $target->ID === $admin_id ) {
			wp_die( esc_html__( 'Cannot impersonate yourself.', 'loomi-studio-setup' ), '', [ 'response' => 400 ] );
		}

		if ( in_array( 'administrator', (array) $target->roles, true ) ) {
			self::log_event( 'impersonate_blocked', [
				'admin_id'  => $admin_id,
				'target_id' => $target->ID,
				'reason'    => 'target_is_administrator',
			] );
			wp_die( esc_html__( 'Cannot impersonate another administrator.', 'loomi-studio-setup' ), '', [ 'response' => 403 ] );
		}

		self::set_return_cookie( $admin_id );

		wp_clear_auth_cookie();
		wp_set_current_user( $target->ID );
		wp_set_auth_cookie( $target->ID, false );

		self::log_event( 'impersonate_start', [
			'admin_id'  => $admin_id,
			'target_id' => $target->ID,
		] );

		wp_safe_redirect( admin_url() );
		exit;
	}

	public static function handle_stop() : void {
		check_admin_referer( self::NONCE_STOP );

		$raw    = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? (string) $_COOKIE[ self::COOKIE_NAME ] : '';
		$parsed = self::parse_return_cookie( $raw );
		if ( $parsed === null ) {
			wp_die( '', '', [ 'response' => 403 ] );
		}

		$admin_id         = (int) $parsed['admin_id'];
		$target_id_before = get_current_user_id();

		wp_clear_auth_cookie();
		wp_set_current_user( $admin_id );
		wp_set_auth_cookie( $admin_id, false );

		self::clear_return_cookie();

		self::log_event( 'impersonate_stop', [
			'admin_id'  => $admin_id,
			'target_id' => $target_id_before,
		] );

		wp_safe_redirect( admin_url() );
		exit;
	}

	public static function is_impersonating() : ?int {
		$raw = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? (string) $_COOKIE[ self::COOKIE_NAME ] : '';
		if ( $raw === '' ) {
			return null;
		}
		$parsed = self::parse_return_cookie( $raw );
		if ( $parsed === null ) {
			return null;
		}
		$admin_id = (int) $parsed['admin_id'];
		if ( $admin_id === get_current_user_id() ) {
			return null;
		}
		return $admin_id;
	}

	public static function render_banner() : void {
		$admin_id = self::is_impersonating();
		if ( $admin_id === null ) {
			return;
		}

		$current = wp_get_current_user();
		$admin   = get_user_by( 'id', $admin_id );
		if ( ! $current || ! $current->ID || ! $admin instanceof WP_User ) {
			return;
		}

		$role = isset( $current->roles[0] ) ? (string) $current->roles[0] : '';

		$stop_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION_STOP ),
			self::NONCE_STOP
		);

		echo '<div class="notice notice-warning loomi-impersonate-banner" style="border-left-color:#d63638;">';
		echo '<p>';
		echo wp_kses(
			sprintf(
				/* translators: 1: target display name, 2: target role */
				__( 'Você está impersonando <strong>%1$s</strong> (%2$s).', 'loomi-studio-setup' ),
				esc_html( $current->display_name ),
				esc_html( $role )
			),
			[ 'strong' => [] ]
		);
		echo ' <a class="button button-primary" href="' . esc_url( $stop_url ) . '">'
			. esc_html( sprintf( __( 'Voltar como %s', 'loomi-studio-setup' ), $admin->display_name ) )
			. '</a>';
		echo '</p>';
		echo '</div>';
	}

	public static function add_admin_bar_node( $bar ) : void {
		if ( ! ( $bar instanceof WP_Admin_Bar ) ) {
			return;
		}
		if ( ! is_admin_bar_showing() ) {
			return;
		}
		$admin_id = self::is_impersonating();
		if ( $admin_id === null ) {
			return;
		}
		$admin = get_user_by( 'id', $admin_id );
		if ( ! $admin instanceof WP_User ) {
			return;
		}

		$stop_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION_STOP ),
			self::NONCE_STOP
		);

		$bar->add_node( [
			'id'    => 'loomi-impersonate-back',
			'title' => '<span style="color:#ff4444">' . esc_html( sprintf( __( 'Voltando para %s', 'loomi-studio-setup' ), $admin->display_name ) ) . '</span>',
			'href'  => esc_url( $stop_url ),
		] );
	}

	public static function clear_return_cookie() : void {
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_NAME,
				'',
				time() - HOUR_IN_SECONDS,
				COOKIEPATH,
				COOKIE_DOMAIN,
				is_ssl(),
				true
			);
		}
		unset( $_COOKIE[ self::COOKIE_NAME ] );
	}

	private static function can_impersonate_target( WP_User $user ) : bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( $user->ID === get_current_user_id() ) {
			return false;
		}
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return false;
		}
		return true;
	}

	private static function set_return_cookie( int $admin_id ) : void {
		$expires_at = time() + self::TTL;
		// Session token amarra o cookie à sessão de login atual do operador: logout/login do admin
		// regenera o token e invalida cookies emitidos previamente, mesmo que ainda dentro do TTL.
		$session_token = function_exists( 'wp_get_session_token' ) ? (string) wp_get_session_token() : '';
		$payload    = $admin_id . '|' . $expires_at . '|' . $session_token;
		// wp_salt('auth') é portável e troca junto com AUTH_KEY — não depende da constante estar definida em runtime.
		$hmac  = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
		$value = $admin_id . '.' . $expires_at . '.' . $hmac;

		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_NAME,
				$value,
				$expires_at,
				COOKIEPATH,
				COOKIE_DOMAIN,
				is_ssl(),
				true
			);
		}
		$_COOKIE[ self::COOKIE_NAME ] = $value;
	}

	private static function parse_return_cookie( string $raw ) : ?array {
		if ( $raw === '' ) {
			return null;
		}
		$parts = explode( '.', $raw, 3 );
		if ( count( $parts ) !== 3 ) {
			return null;
		}
		list( $admin_id_raw, $expires_at_raw, $hmac ) = $parts;
		$admin_id   = absint( $admin_id_raw );
		$expires_at = absint( $expires_at_raw );

		if ( $admin_id === 0 || $expires_at < time() ) {
			return null;
		}

		$session_token = function_exists( 'wp_get_session_token' ) ? (string) wp_get_session_token() : '';
		$payload  = $admin_id . '|' . $expires_at . '|' . $session_token;
		$computed = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
		if ( ! hash_equals( $computed, (string) $hmac ) ) {
			self::log_event( 'impersonate.stop.invalid_cookie', [
				'admin_id'   => $admin_id,
				'expires_at' => $expires_at,
			] );
			return null;
		}

		return [
			'admin_id'   => $admin_id,
			'expires_at' => $expires_at,
		];
	}

	private static function log_event( string $event, array $context ) : void {
		$request = class_exists( 'Loomi_Log_Context' )
			? Loomi_Log_Context::request()
			: [
				'uri'    => isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '',
				'method' => isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : 'CLI',
				'ua'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '',
				'ip'     => isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '',
				'xff'    => '',
			];

		$ts = class_exists( 'Loomi_Log_Context' )
			? Loomi_Log_Context::timestamp()
			: gmdate( 'c' );

		$env = class_exists( 'Loomi_Log_Context' )
			? Loomi_Log_Context::env()
			: [ 'plugin' => '', 'wp' => '', 'php' => PHP_VERSION ];

		$user = class_exists( 'Loomi_Log_Context' )
			? Loomi_Log_Context::user()
			: [ 'id' => get_current_user_id() ?: null, 'login' => '', 'role' => '' ];

		$entry = [
			'ts'        => $ts,
			'type'      => 'impersonate',
			'event'     => $event,
			'admin_id'  => isset( $context['admin_id'] ) ? (int) $context['admin_id'] : null,
			'target_id' => isset( $context['target_id'] ) ? (int) $context['target_id'] : null,
			'reason'    => isset( $context['reason'] ) ? (string) $context['reason'] : null,
			'ip'        => (string) ( $request['ip'] ?? '' ),
			'ua'        => (string) ( $request['ua'] ?? '' ),
			'request'   => $request,
			'user'      => $user,
			'env'       => $env,
		];

		if ( ! class_exists( 'Loomi_Log_Writer' ) ) {
			return;
		}

		$json = wp_json_encode( $entry );
		if ( $json === false ) {
			return;
		}
		Loomi_Log_Writer::append_line( $json );
	}
}
