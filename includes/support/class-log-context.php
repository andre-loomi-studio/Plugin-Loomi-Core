<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Log_Context {

	const UA_MAX_LENGTH = 512;

	public static function user() : array {
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() && function_exists( 'wp_get_current_user' ) ) {
			$u = wp_get_current_user();
			if ( $u && $u->ID ) {
				return [
					'id'    => (int) $u->ID,
					'login' => (string) $u->user_login,
					'role'  => isset( $u->roles[0] ) ? (string) $u->roles[0] : 'unknown',
				];
			}
		}
		return [
			'id'    => null,
			'login' => '',
			'role'  => 'guest',
		];
	}

	public static function request() : array {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( function_exists( 'esc_url_raw' ) ) {
			$uri = esc_url_raw( $uri );
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'CLI';
		// whitelist common methods to avoid garbage
		if ( ! preg_match( '/^[A-Z]{3,10}$/', $method ) ) {
			$method = 'CLI';
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		if ( strlen( $ua ) > self::UA_MAX_LENGTH ) {
			$ua = substr( $ua, 0, self::UA_MAX_LENGTH );
		}

		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		$xff = '';
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$first = explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] )[0];
			$xff   = trim( $first );
		}

		return [
			'uri'    => $uri,
			'method' => $method,
			'ua'     => $ua,
			'ip'     => $ip,
			'xff'    => $xff,
		];
	}

	public static function env() : array {
		return [
			'plugin' => Plugin::version(),
			'wp'     => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '',
			'php'    => PHP_VERSION,
		];
	}

	public static function timestamp() : string {
		if ( function_exists( 'current_time' ) ) {
			return (string) current_time( 'c' );
		}
		return gmdate( 'c' );
	}
}
