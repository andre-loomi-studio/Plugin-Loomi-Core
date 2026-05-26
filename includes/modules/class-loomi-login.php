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
			// Hook em wp_loaded (priority 1) — depois de plugins_loaded + setup_theme
			// + init + theme/block-templates carregados, MAS antes de admin.php
			// chamar admin_init/auth_redirect (que faria 302 vazando a slug).
			// Precisamos do tema carregado pra renderizar o template 404 corretamente
			// em block themes (template-canvas.php depende disso).
			add_action( 'wp_loaded', [ __CLASS__, 'gate_admin_endpoint' ], 1 );

			add_filter( 'login_url',         [ __CLASS__, 'filter_login_url' ], 10, 3 );
			add_filter( 'logout_url',        [ __CLASS__, 'filter_logout_url' ], 10, 2 );
			add_filter( 'logout_redirect',   [ __CLASS__, 'filter_logout_redirect' ], 10, 3 );
			add_filter( 'lostpassword_url',  [ __CLASS__, 'filter_lostpassword_url' ], 10, 2 );
			add_filter( 'register_url',      [ __CLASS__, 'filter_register_url' ] );

			// site_url('wp-login.php', ...) é usado pelo WP core pra montar o action
			// do <form> de login. Sem reescrever, POST de login vai pra /wp-login.php
			// e em caso de falha o browser fica parado nessa URL → refresh = 404.
			add_filter( 'site_url', [ __CLASS__, 'filter_site_url' ], 10, 4 );
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

		// Match 1 — pretty permalink (requer mod_rewrite + AllowOverride / regras vhost): /<slug>/
		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$request_path = trim( rawurldecode( $request_path ), '/' );

		// Match 2 — query-string fallback (sempre funciona, mesmo em hosting compartilhado sem AllowOverride): /?loomi_login=<slug>
		$query_slug = isset( $_GET['loomi_login'] ) ? trim( (string) wp_unslash( $_GET['loomi_login'] ), '/' ) : '';

		if ( $request_path !== $slug && $query_slug !== $slug ) return;

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

		// Estratégia: em vez de renderizar o template do tema inline dentro do
		// contexto admin (onde block-library CSS per-block não enqueia direito
		// → layout quebrado), redirecionamos pra uma URL inexistente no frontend.
		// WP processa como 404 normal, template renderiza com TODOS os estilos.
		// O path do redirect é genérico (não vaza a slug de login).
		if ( ! headers_sent() ) {
			nocache_headers();
			wp_safe_redirect( home_url( '/loomi-not-found' ), 302 );
			exit;
		}

		// Fallback se headers já foram enviados — minimal 404 inline (sem template).
		self::strip_admin_context();
		self::render_not_found();
	}

	/**
	 * Limpa contexto admin do output do 404 quando o gate dispara de /wp-admin/.
	 * Sem isso, o template do tema herda classes/CSS de admin (admin-bar bump 32px,
	 * body class admin-bar, scripts admin enqueueados) que quebram o layout do 404.
	 */
	private static function strip_admin_context() : void {
		add_filter( 'show_admin_bar', '__return_false' );
		remove_action( 'wp_head', '_admin_bar_bump_cb' );
		remove_action( 'wp_body_open', 'wp_admin_bar_render', 0 );
		remove_action( 'wp_footer', 'wp_admin_bar_render', 1000 );

		// Body classes admin foram adicionadas em init (antes do wp_loaded onde rodamos).
		// Removemos via filtro que dispara no get_body_class durante o render.
		add_filter( 'body_class', static function ( $classes ) {
			return array_values( array_diff(
				(array) $classes,
				[ 'admin-bar', 'no-customize-support', 'wp-admin', 'branch-6', 'branch-6-7', 'version-6-7-2' ]
			) );
		} );

		// Em contexto admin, WP usa modo "on-demand" pra block library CSS — cada
		// block enqueia seu próprio style quando renderizado. Mas o pré-render que
		// detecta blocks no template não roda em admin → wp_head() é chamado antes
		// dos blocks serem analisados → CSS per-block falta no <head> → layout
		// quebrado. Forçamos modo bundled (block-library.css inteiro) que sempre
		// enqueia via wp_enqueue_scripts.
		add_filter( 'should_load_separate_core_block_assets', '__return_false' );

		// Suppress admin-specific scripts/styles enqueued in admin context.
		add_action( 'wp_print_scripts', static function () {
			global $wp_scripts;
			if ( $wp_scripts instanceof WP_Scripts ) {
				$wp_scripts->dequeue( [ 'admin-bar', 'customize-support', 'customize-base' ] );
			}
		}, 1 );
		add_action( 'wp_print_styles', static function () {
			global $wp_styles;
			if ( $wp_styles instanceof WP_Styles ) {
				$wp_styles->dequeue( [ 'admin-bar', 'dashicons' ] );
			}
		}, 1 );

		// Garante que wp_enqueue_scripts foi disparado pelo menos uma vez antes
		// do wp_head — em admin context o WP pode pular dependendo do hook order.
		add_action( 'wp_head', static function () {
			if ( ! did_action( 'wp_enqueue_scripts' ) ) {
				do_action( 'wp_enqueue_scripts' );
			}
		}, 0 );
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

		// Block themes (WP 6.x+) usam template-canvas.php que depende da máquina de
		// block templates inicializada. Quando o gate dispara em `init` priority 1
		// (antes de wp_loaded), o canvas renderiza só o shell vazio → tela branca.
		// Só usamos o template do tema quando wp_loaded já rodou (gate_wp_login em
		// login_init é seguro; gate_admin_endpoint em init priority 1 não é).
		if ( did_action( 'wp_loaded' ) ) {
			$template = get_query_template( '404' );
			if ( $template && file_exists( $template ) ) {
				include $template;
				exit;
			}
		}

		// Fallback minimal 404 — sem dependência do tema. Sempre renderiza HTML
		// visível (não em branco) com branding Loomi consistente. Usado quando
		// o gate dispara cedo demais pro tema estar pronto.
		self::render_minimal_404();
		exit;
	}

	private static function render_minimal_404() : void {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
		}
		$title = esc_html__( 'Página não encontrada', 'loomi-studio-setup' );
		$msg   = esc_html__( 'A página solicitada não existe neste site.', 'loomi-studio-setup' );
		$home  = esc_url( home_url( '/' ) );
		$back  = esc_html__( 'Voltar para o início', 'loomi-studio-setup' );
		?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<meta name="robots" content="noindex,nofollow" />
<title>404 — <?php echo $title; ?></title>
<style>
html,body{height:100%;margin:0;background:#0a0a0a;color:#fff;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;}
.wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;text-align:center;}
.card{max-width:480px;}
.code{font-size:64px;margin:0 0 8px;font-weight:700;letter-spacing:-2px;line-height:1;}
.code::after{content:"";display:inline-block;width:10px;height:10px;background:#FBD603;border-radius:50%;vertical-align:super;margin-left:6px;box-shadow:0 0 0 4px rgba(251,214,3,0.18);}
h1{font-size:18px;margin:0 0 12px;font-weight:600;letter-spacing:-0.3px;}
p{margin:0 0 24px;color:rgba(255,255,255,0.6);font-size:14px;line-height:1.55;}
a{color:#FBD603;text-decoration:none;font-weight:600;font-size:13px;border-bottom:1px solid currentColor;padding-bottom:2px;}
a:hover{color:#fff;}
</style>
</head>
<body>
<div class="wrap"><div class="card">
<div class="code">404</div>
<h1><?php echo $title; ?></h1>
<p><?php echo $msg; ?></p>
<a href="<?php echo $home; ?>"><?php echo $back; ?></a>
</div></div>
</body>
</html>
		<?php
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

	/**
	 * Reescreve qualquer site_url('wp-login.php', ...) para a slug URL.
	 *
	 * Necessário porque o WP core usa `site_url( 'wp-login.php', 'login_post' )`
	 * pra montar o `action` do <form> de login — sem reescrever, POST de login
	 * pousa em /wp-login.php e em caso de falha o browser fica parado lá
	 * (refresh = 404).
	 */
	public static function filter_site_url( $url, $path, $scheme, $blog_id ) {
		if ( ! is_string( $path ) || $path === '' ) {
			return $url;
		}
		$normalized = ltrim( $path, '/' );
		if ( strpos( $normalized, 'wp-login.php' ) !== 0 ) {
			return $url;
		}

		$action = '';
		$extra  = [];

		$parts = explode( '?', $normalized, 2 );
		if ( isset( $parts[1] ) ) {
			$query = [];
			parse_str( $parts[1], $query );
			if ( isset( $query['action'] ) ) {
				$action = (string) $query['action'];
				unset( $query['action'] );
			}
			$extra = $query;
		}

		return Login_URLs::build( $action, $extra );
	}
}
