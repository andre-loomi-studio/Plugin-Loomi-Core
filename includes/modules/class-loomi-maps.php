<?php
/**
 * Google Maps embed (iframe lazy-load).
 *
 * Render via shortcode [loomi_map] OR auto-inject on the homepage.
 * Config in Settings → Loomi Studio → Maps.
 *
 * Não requer API key (usa o embed público maps.google.com/maps?q=...&output=embed).
 * Para sinal SEO local melhor, recomenda-se substituir a query padrão pelo
 * iframe oficial (?pb=...) gerado em "Partilhar > Incorporar mapa" no Google Maps —
 * o shortcode aceita o parâmetro `src` pra colar o URL completo.
 *
 * Shortcode:
 *   [loomi_map]                            -> usa config global
 *   [loomi_map zoom="14" height="350"]     -> override inline
 *   [loomi_map q="Lisboa, Portugal"]       -> query custom
 *   [loomi_map src="https://www.google.com/maps/embed?pb=..."]  -> embed oficial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Maps implements Loomi_Module {

	const SHORTCODE         = 'loomi_map';
	const AUTOINJECT_HOOK   = 'wp_footer';
	const ZOOM_MIN          = 1;
	const ZOOM_MAX          = 21;
	const HEIGHT_MIN        = 100;
	const HEIGHT_MAX        = 1200;
	const EMBED_BASE_URL    = 'https://maps.google.com/maps';
	const OFFICIAL_HOST     = 'www.google.com';
	const OFFICIAL_PATH     = '/maps/embed';

	public static function register() : void {
		add_shortcode( self::SHORTCODE, [ __CLASS__, 'render_shortcode' ] );

		if ( Settings_Repository::get_bool( 'loomi_maps_autoinject_home' ) ) {
			add_action( self::AUTOINJECT_HOOK, [ __CLASS__, 'maybe_autoinject' ], 20 );
		}
	}

	/**
	 * Renderiza o iframe a partir do shortcode.
	 *
	 * @param array<string,mixed>|string $atts Atributos do shortcode.
	 */
	public static function render_shortcode( $atts ) : string {
		$atts = shortcode_atts(
			[
				'q'      => '',
				'src'    => '',
				'zoom'   => '',
				'height' => '',
				'lazy'   => '',
			],
			is_array( $atts ) ? $atts : [],
			self::SHORTCODE
		);

		$config = self::config();

		// Resolve src: 1º shortcode src, 2º config src oficial (?pb=...), 3º monta a partir de query
		$src = '';
		if ( $atts['src'] !== '' ) {
			$src = self::sanitize_embed_url( (string) $atts['src'] );
		} elseif ( ! empty( $config['embed_src'] ) ) {
			$src = $config['embed_src'];
		} else {
			$query = $atts['q'] !== '' ? (string) $atts['q'] : (string) $config['query'];
			$zoom  = $atts['zoom'] !== '' ? (int) $atts['zoom'] : (int) $config['zoom'];
			if ( $query === '' ) {
				return ''; // nada configurado → não renderiza
			}
			$src = add_query_arg(
				[
					'q'      => $query,
					'z'      => self::clamp_zoom( $zoom ),
					'output' => 'embed',
				],
				self::EMBED_BASE_URL
			);
		}

		if ( $src === '' ) {
			return '';
		}

		$height = $atts['height'] !== '' ? (int) $atts['height'] : (int) $config['height'];
		$height = self::clamp_height( $height );

		$lazy = $atts['lazy'] !== ''
			? rest_sanitize_boolean( $atts['lazy'] )
			: (bool) $config['lazy'];

		$title = (string) $config['title'];

		return self::build_iframe( $src, $height, $lazy, $title );
	}

	/**
	 * Auto-inject silencioso no rodapé da home — só usado quando o usuário
	 * habilita o toggle e ainda não tem o shortcode em nenhum lugar.
	 */
	public static function maybe_autoinject() : void {
		if ( is_admin() ) {
			return;
		}
		if ( ! function_exists( 'is_front_page' ) || ! is_front_page() ) {
			return;
		}
		echo "\n" . self::render_shortcode( [] ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- build_iframe escapa internamente.
	}

	/**
	 * Configuração efetiva (defaults + overrides do Settings).
	 *
	 * @return array{query:string,embed_src:string,zoom:int,height:int,lazy:bool,title:string}
	 */
	private static function config() : array {
		$stored = (array) Settings_Repository::get( 'loomi_maps', [] );
		return [
			'query'     => isset( $stored['query'] )     ? (string) $stored['query']     : '',
			'embed_src' => isset( $stored['embed_src'] ) ? (string) $stored['embed_src'] : '',
			'zoom'      => isset( $stored['zoom'] )      ? self::clamp_zoom( (int) $stored['zoom'] )      : 16,
			'height'    => isset( $stored['height'] )    ? self::clamp_height( (int) $stored['height'] )  : 400,
			'lazy'      => array_key_exists( 'lazy', $stored ) ? (bool) $stored['lazy'] : true,
			'title'     => isset( $stored['title'] ) && $stored['title'] !== ''
				? (string) $stored['title']
				: __( 'Localização', 'loomi-studio-setup' ),
		];
	}

	/**
	 * Aceita só URLs do Google Maps embed (host fixo, scheme https).
	 * Retorna '' se inválido.
	 */
	public static function sanitize_embed_url( string $url ) : string {
		$url = trim( $url );
		if ( $url === '' ) {
			return '';
		}
		// Extrai a URL do src="..." se o usuário colou o iframe inteiro
		if ( preg_match( '/src=["\']([^"\']+)["\']/i', $url, $m ) ) {
			$url = $m[1];
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		if ( $parts['scheme'] !== 'https' ) {
			return '';
		}
		$host = strtolower( (string) $parts['host'] );
		$allowed_hosts = [ self::OFFICIAL_HOST, 'maps.google.com', 'maps.googleapis.com' ];
		if ( ! in_array( $host, $allowed_hosts, true ) ) {
			return '';
		}
		return esc_url_raw( $url );
	}

	public static function clamp_zoom( int $z ) : int {
		if ( $z < self::ZOOM_MIN ) return self::ZOOM_MIN;
		if ( $z > self::ZOOM_MAX ) return self::ZOOM_MAX;
		return $z;
	}

	public static function clamp_height( int $h ) : int {
		if ( $h < self::HEIGHT_MIN ) return self::HEIGHT_MIN;
		if ( $h > self::HEIGHT_MAX ) return self::HEIGHT_MAX;
		return $h;
	}

	private static function build_iframe( string $src, int $height, bool $lazy, string $title ) : string {
		$loading = $lazy ? 'lazy' : 'eager';
		return sprintf(
			'<iframe src="%s" width="100%%" height="%d" style="border:0;" loading="%s" allowfullscreen referrerpolicy="no-referrer-when-downgrade" title="%s"></iframe>',
			esc_url( $src ),
			(int) $height,
			esc_attr( $loading ),
			esc_attr( $title )
		);
	}
}
