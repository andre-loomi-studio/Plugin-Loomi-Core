<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Anti_Spam implements Loomi_Module {

	const HP_FIELD   = 'loomi_hp';
	const TIME_FIELD = 'loomi_t';
	const MIN_DELTA  = 2;

	public static function register() : void {
		if ( ! Settings_Repository::get_bool( 'anti_spam_enabled' ) ) {
			return;
		}

		if ( Settings_Repository::get_bool( 'anti_spam_honeypot' ) || Settings_Repository::get_bool( 'anti_spam_time_check' ) ) {
			add_action( 'login_form',               [ __CLASS__, 'render_hidden_fields' ] );
			add_action( 'register_form',            [ __CLASS__, 'render_hidden_fields' ] );
			add_action( 'comment_form_after_fields',[ __CLASS__, 'render_hidden_fields' ] );

			add_filter( 'authenticate',         [ __CLASS__, 'gate_authenticate' ], 21, 1 );
			add_filter( 'pre_comment_approved', [ __CLASS__, 'gate_comment' ], 21, 2 );
			add_filter( 'registration_errors',  [ __CLASS__, 'gate_registration' ], 10, 1 );
		}

		if ( Settings_Repository::get_bool( 'anti_spam_comment_lockdown' ) ) {
			add_filter( 'xmlrpc_methods', [ __CLASS__, 'strip_pingback_methods' ] );
		}

		if ( Settings_Repository::get_bool( 'anti_spam_akismet_autoconfig' ) ) {
			add_action( 'admin_init', [ __CLASS__, 'configure_akismet' ], 99 );
		}
	}

	public static function render_hidden_fields() : void {
		$honeypot   = Settings_Repository::get_bool( 'anti_spam_honeypot' );
		$time_check = Settings_Repository::get_bool( 'anti_spam_time_check' );

		if ( ! $honeypot && ! $time_check ) {
			return;
		}

		echo '<div aria-hidden="true" style="position:absolute;left:-9999px;height:1px;width:1px;overflow:hidden;">';

		if ( $honeypot ) {
			echo '<label>'
				. esc_html__( 'Deixe em branco', 'loomi-studio-setup' )
				. '<input type="text" name="' . esc_attr( self::HP_FIELD ) . '" tabindex="-1" autocomplete="off" value="" />'
				. '</label>';
		}

		if ( $time_check ) {
			echo '<input type="hidden" name="' . esc_attr( self::TIME_FIELD ) . '" value="' . esc_attr( (string) time() ) . '" />';
		}

		echo '</div>';
	}

	/**
	 * Detecta bot: honeypot preenchido OU submit muito rápido.
	 */
	public static function is_bot_submission() : bool {
		if ( Settings_Repository::get_bool( 'anti_spam_honeypot' ) ) {
			$hp = isset( $_POST[ self::HP_FIELD ] ) ? wp_unslash( $_POST[ self::HP_FIELD ] ) : '';
			if ( $hp !== '' ) {
				return true;
			}
		}

		if ( Settings_Repository::get_bool( 'anti_spam_time_check' ) ) {
			// Time check só aplica em POSTs de form (não em fluxos onde não emitimos o campo).
			if ( ! empty( $_SERVER['REQUEST_METHOD'] ) && strtoupper( $_SERVER['REQUEST_METHOD'] ) === 'POST' ) {
				$rendered = isset( $_POST[ self::TIME_FIELD ] ) ? (int) $_POST[ self::TIME_FIELD ] : 0;
				if ( $rendered === 0 ) {
					return true; // bot strippou o campo
				}
				if ( ( time() - $rendered ) < self::MIN_DELTA ) {
					return true;
				}
			}
		}

		return false;
	}

	public static function gate_authenticate( $user ) {
		// Só interceptar requests reais de login (que sempre são POST), não chamadas internas.
		if ( empty( $_SERVER['REQUEST_METHOD'] ) || strtoupper( $_SERVER['REQUEST_METHOD'] ) !== 'POST' ) {
			return $user;
		}
		if ( self::is_bot_submission() ) {
			return new WP_Error( 'loomi_anti_spam', __( 'Tentativa de login bloqueada (anti-spam).', 'loomi-studio-setup' ) );
		}
		return $user;
	}

	public static function gate_comment( $approved, $commentdata ) {
		if ( self::is_bot_submission() ) {
			return 'spam';
		}
		return $approved;
	}

	public static function gate_registration( $errors ) {
		if ( self::is_bot_submission() ) {
			$errors->add( 'loomi_anti_spam', __( 'Registro bloqueado (anti-spam).', 'loomi-studio-setup' ) );
		}
		return $errors;
	}

	public static function strip_pingback_methods( $methods ) {
		unset( $methods['pingback.ping'] );
		unset( $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	/**
	 * Aplica o comment lockdown nas options do WP. Chamado pelo activation hook do plugin.
	 */
	public static function apply_comment_lockdown() : void {
		if ( ! Settings_Repository::get_bool( 'anti_spam_comment_lockdown' ) ) {
			return;
		}
		update_option( 'default_pingback_flag', 0 );
		update_option( 'default_ping_status',   'closed' );
		update_option( 'comment_moderation',    1 );
		update_option( 'comment_previously_approved', 0 );
	}

	public static function configure_akismet() : void {
		if ( ! defined( 'LOOMI_AKISMET_KEY' ) || ! LOOMI_AKISMET_KEY ) {
			return;
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active( 'akismet/akismet.php' ) ) {
			return;
		}
		$current = get_option( 'wordpress_api_key' );
		if ( $current !== LOOMI_AKISMET_KEY ) {
			update_option( 'wordpress_api_key', LOOMI_AKISMET_KEY );
		}
	}
}
