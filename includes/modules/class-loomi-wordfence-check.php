<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Wordfence_Check implements Loomi_Module {

	const ACTION       = 'loomi_install_wordfence';
	const NONCE_ACTION = 'loomi_install_wordfence';

	public static function register() : void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_notices', [ __CLASS__, 'render_notice' ] );
		add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'handle_install' ] );
	}

	public static function get_state() : string {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( is_plugin_active( Plugin::WORDFENCE_FILE ) ) {
			return 'active';
		}
		if ( file_exists( WP_PLUGIN_DIR . '/' . Plugin::WORDFENCE_FILE ) ) {
			return 'installed_inactive';
		}
		return 'absent';
	}

	public static function render_notice() : void {
		self::render_status_notice();

		if ( ! current_user_can( 'activate_plugins' ) ) return;

		// Não renderizar no Dashboard (index.php) — tela hero Loomi não deve ter notice WP por cima.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->base, [ 'dashboard', 'dashboard-network' ], true ) ) {
			return;
		}

		$state = self::get_state();
		if ( $state === 'active' ) return;

		$can_install = current_user_can( 'install_plugins' );
		$action_url  = esc_url( admin_url( 'admin-post.php' ) );

		if ( $state === 'absent' ) {
			$headline = esc_html__( 'Wordfence não está instalado.', 'loomi-studio-setup' );
			$detail   = esc_html__( 'O Loomi Studio Setup exige que o plugin Wordfence Security esteja ativo neste site.', 'loomi-studio-setup' );
			$button   = $can_install ? esc_html__( 'Instalar Wordfence agora', 'loomi-studio-setup' ) : '';
			$fallback = ! $can_install ? esc_html__( 'Solicite ao administrador do site a instalação do Wordfence.', 'loomi-studio-setup' ) : '';
		} else {
			$headline = esc_html__( 'Wordfence está instalado mas não está ativo.', 'loomi-studio-setup' );
			$detail   = esc_html__( 'O Loomi Studio Setup exige que o plugin Wordfence Security esteja ativo.', 'loomi-studio-setup' );
			$button   = esc_html__( 'Ativar Wordfence', 'loomi-studio-setup' );
			$fallback = '';
		}

		echo '<div class="notice notice-error"><p><strong>' . $headline . '</strong> ' . $detail . '</p>';

		if ( $button !== '' ) {
			echo '<p><form action="' . $action_url . '" method="post" style="display:inline;">';
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
			echo wp_nonce_field( self::NONCE_ACTION, '_wpnonce', true, false ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<button type="submit" class="button button-primary">' . $button . '</button>';
			echo '</form></p>';
		} elseif ( $fallback !== '' ) {
			echo '<p><em>' . $fallback . '</em></p>';
		}
		echo '</div>';
	}

	private static function render_status_notice() : void {
		if ( empty( $_GET['loomi_wf_status'] ) ) return;
		$status = sanitize_key( wp_unslash( $_GET['loomi_wf_status'] ) );

		if ( $status === 'ok' || $status === 'activated' ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Wordfence ativo. Obrigado!', 'loomi-studio-setup' ) . '</p></div>';
			return;
		}
		if ( $status === 'error' ) {
			$msg = isset( $_GET['loomi_wf_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['loomi_wf_msg'] ) ) : __( 'Erro desconhecido.', 'loomi-studio-setup' );
			echo '<div class="notice notice-error is-dismissible"><p>'
				. esc_html__( 'Falha ao instalar/ativar Wordfence:', 'loomi-studio-setup' ) . ' '
				. esc_html( $msg ) . '</p></div>';
		}
	}

	public static function handle_install() : void {
		check_admin_referer( self::NONCE_ACTION );

		$state    = self::get_state();
		// Redirect fixo: wp_safe_redirect já bloqueia host externo, mas usar wp_get_referer()
		// permitia ao atacante empurrar admin para paths arbitrários do mesmo host via Referer.
		$redirect = admin_url( 'plugins.php' );

		$required_cap = $state === 'absent' ? 'install_plugins' : 'activate_plugins';
		if ( ! current_user_can( $required_cap ) ) {
			wp_die( esc_html__( 'Permissão negada.', 'loomi-studio-setup' ), '', [ 'response' => 403 ] );
		}

		if ( $state === 'absent' ) {
			$installed = self::install_wordfence();
			if ( is_wp_error( $installed ) ) {
				self::redirect_with_status( $redirect, 'error', $installed->get_error_message() );
				return;
			}
		}

		$activated = activate_plugin( Plugin::WORDFENCE_FILE, '', false, true );
		if ( is_wp_error( $activated ) ) {
			self::redirect_with_status( $redirect, 'error', $activated->get_error_message() );
			return;
		}

		$status = $state === 'absent' ? 'ok' : 'activated';
		self::redirect_with_status( $redirect, $status );
	}

	private static function install_wordfence() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$api = plugins_api( 'plugin_information', [
			'slug'   => 'wordfence',
			'fields' => [ 'sections' => false ],
		] );
		if ( is_wp_error( $api ) ) return $api;
		if ( empty( $api->download_link ) ) {
			return new WP_Error( 'loomi_no_download_link', __( 'wp.org não retornou link de download.', 'loomi-studio-setup' ) );
		}

		$skin     = new WP_Ajax_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) return $result;
		if ( is_wp_error( $skin->result ) ) return $skin->result;
		if ( $result === false ) {
			$errors = $skin->get_errors();
			return ( $errors && $errors->has_errors() )
				? $errors
				: new WP_Error( 'loomi_install_failed', __( 'Falha na instalação.', 'loomi-studio-setup' ) );
		}
		return true;
	}

	private static function redirect_with_status( string $referer, string $status, string $message = '' ) : void {
		$args = [ 'loomi_wf_status' => $status ];
		if ( $message !== '' ) {
			$args['loomi_wf_msg'] = rawurlencode( $message );
		}
		wp_safe_redirect( add_query_arg( $args, $referer ) );
		exit;
	}
}
