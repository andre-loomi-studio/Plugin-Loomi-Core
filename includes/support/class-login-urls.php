<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Login_URLs {

	/**
	 * Constrói a URL de login.
	 *
	 * Em sites com pretty permalinks (`permalink_structure` populado), usa o formato
	 * limpo `/<slug>/?action=...`. Em sites com permalinks Plain (sem mod_rewrite
	 * / AllowOverride disponível — comum em hosting compartilhado), cai pra
	 * `/?loomi_login=<slug>&action=...`, garantindo que a slug funcione mesmo
	 * sem regras de rewrite no servidor.
	 */
	public static function build( string $action = '', array $extra = [] ) : string {
		$slug = trim( (string) Settings_Repository::get( 'login_slug', 'studio-access' ), '/' );
		if ( $slug === '' ) {
			$slug = 'studio-access';
		}

		$has_pretty_permalinks = (string) get_option( 'permalink_structure', '' ) !== '';
		if ( $has_pretty_permalinks ) {
			$url = home_url( '/' . $slug . '/' );
		} else {
			$url = add_query_arg( 'loomi_login', $slug, home_url( '/' ) );
		}

		if ( $action !== '' ) {
			$url = add_query_arg( 'action', $action, $url );
		}

		foreach ( $extra as $key => $value ) {
			if ( $value === null || $value === '' || $value === false ) {
				continue;
			}
			$url = add_query_arg( $key, rawurlencode( (string) $value ), $url );
		}

		return $url;
	}
}
