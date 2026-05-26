<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Login_URLs {

	public static function build( string $action = '', array $extra = [] ) : string {
		$slug = trim( (string) Settings_Repository::get( 'login_slug', 'studio-access' ), '/' );
		if ( $slug === '' ) {
			$slug = 'studio-access';
		}
		$url = home_url( '/' . $slug . '/' );

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
