<?php
/**
 * Google Tag Manager (web container) installer.
 *
 * Snippets verificados byte-exato contra a documentação oficial:
 *   https://support.google.com/tagmanager/answer/14847097
 * Verificado em: 2026-05.
 *
 * O snippet head precisa estar o mais alto possível no <head>; o noscript
 * iframe deve ficar imediatamente após o opening <body>. Prioridade 1 nos
 * dois hooks coloca os snippets antes de praticamente qualquer outro plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_GTM implements Loomi_Module {

	const HEAD_PRIORITY = 1;
	const BODY_PRIORITY = 1;

	public static function register() : void {
		add_action( 'wp_head',       [ __CLASS__, 'output_head' ], self::HEAD_PRIORITY );
		add_action( 'wp_body_open',  [ __CLASS__, 'output_body' ], self::BODY_PRIORITY );
	}

	private static function should_output() : bool {
		if ( is_admin() ) {
			return false;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return false;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return false;
		}
		if ( ! apply_filters( 'loomi_gtm_enabled', true ) ) {
			return false;
		}
		$id = (string) Settings_Repository::get( 'loomi_gtm_id', '' );
		return $id !== '';
	}

	public static function output_head() : void {
		if ( ! self::should_output() ) {
			return;
		}
		$id = (string) Settings_Repository::get( 'loomi_gtm_id', '' );

		$layer_items = apply_filters( 'loomi_gtm_data_layer_init', [] );
		if ( is_array( $layer_items ) && ! empty( $layer_items ) ) {
			echo "\n<script>window.dataLayer = window.dataLayer || [];";
			foreach ( $layer_items as $item ) {
				echo 'window.dataLayer.push(' . wp_json_encode( $item ) . ');';
			}
			echo "</script>\n";
		}

		$snippet = <<<'HTML'
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-XXXXXX');</script>
<!-- End Google Tag Manager -->
HTML;

		echo "\n" . str_replace( 'GTM-XXXXXX', esc_js( $id ), $snippet ) . "\n";
	}

	public static function output_body() : void {
		if ( ! self::should_output() ) {
			return;
		}
		$id = (string) Settings_Repository::get( 'loomi_gtm_id', '' );

		$snippet = <<<'HTML'
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXXXX"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
HTML;

		echo "\n" . str_replace( 'GTM-XXXXXX', esc_attr( $id ), $snippet ) . "\n";
	}
}
