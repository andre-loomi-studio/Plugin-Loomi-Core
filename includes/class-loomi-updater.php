<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Updater implements Loomi_Module {

	const SLUG = 'loomi-studio-setup';

	public static function register() : void {
		add_filter( 'pre_set_site_transient_update_plugins', [ __CLASS__, 'inject_update' ] );
		add_filter( 'plugins_api', [ __CLASS__, 'serve_plugin_info' ], 10, 3 );
		add_action( 'upgrader_process_complete', [ __CLASS__, 'clear_cache_after_upgrade' ], 10, 2 );
	}

	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) return $transient;

		$remote = self::check_remote();
		if ( ! $remote || ! is_string( $remote['version'] ?? null ) ) return $transient;

		if ( ! self::is_trusted_package_url( (string) ( $remote['download_url'] ?? '' ) ) ) {
			return $transient;
		}

		if ( version_compare( $remote['version'], Plugin::version(), '<=' ) ) return $transient;

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = [];
		}

		$transient->response[ Plugin::basename() ] = (object) [
			'id'           => self::SLUG . '/' . self::SLUG . '.php',
			'slug'         => self::SLUG,
			'plugin'       => Plugin::basename(),
			'new_version'  => $remote['version'],
			'url'          => $remote['url'] ?? '',
			'package'      => $remote['download_url'],
			'tested'       => $remote['tested'] ?? '',
			'requires'     => $remote['requires'] ?? '',
			'requires_php' => $remote['requires_php'] ?? '',
		];
		return $transient;
	}

	public static function serve_plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) return $result;
		if ( empty( $args->slug ) || $args->slug !== self::SLUG ) return $result;

		$remote = self::check_remote();
		if ( ! $remote ) return $result;

		return (object) [
			'name'          => 'Loomi Studio Setup',
			'slug'          => self::SLUG,
			'version'       => $remote['version'],
			'tested'        => $remote['tested'] ?? '',
			'requires'      => $remote['requires'] ?? '',
			'requires_php'  => $remote['requires_php'] ?? '',
			'author'        => '<a href="https://loomi.studio">Loomi</a>',
			'download_link' => $remote['download_url'],
			'trunk'         => $remote['download_url'],
			'last_updated'  => $remote['last_updated'] ?? '',
			'sections'      => (array) ( $remote['sections'] ?? [] ),
		];
	}

	public static function clear_cache_after_upgrade( $upgrader, $hook_extra ) : void {
		if ( empty( $hook_extra['type'] ) || $hook_extra['type'] !== 'plugin' ) return;
		if ( empty( $hook_extra['plugins'] ) || ! is_array( $hook_extra['plugins'] ) ) return;
		if ( in_array( Plugin::basename(), $hook_extra['plugins'], true ) ) {
			delete_transient( Plugin::UPDATE_TRANSIENT );
		}
	}

	private static function is_trusted_package_url( string $url ) : bool {
		if ( $url === '' ) return false;
		if ( ! defined( 'LOOMI_STUDIO_UPDATE_SERVER' ) ) return false;

		$server_host    = wp_parse_url( LOOMI_STUDIO_UPDATE_SERVER, PHP_URL_HOST );
		$server_scheme  = wp_parse_url( LOOMI_STUDIO_UPDATE_SERVER, PHP_URL_SCHEME );
		$package_host   = wp_parse_url( $url, PHP_URL_HOST );
		$package_scheme = wp_parse_url( $url, PHP_URL_SCHEME );

		if ( ! $server_host || ! $package_host ) return false;
		return strcasecmp( $server_host, $package_host ) === 0
			&& $package_scheme === 'https'
			&& $server_scheme === 'https';
	}

	private static function check_remote() : ?array {
		if ( ! defined( 'LOOMI_STUDIO_UPDATE_SERVER' ) ) return null;

		$cached = get_transient( Plugin::UPDATE_TRANSIENT );
		if ( $cached !== false ) {
			return is_array( $cached ) && ! empty( $cached ) ? $cached : null;
		}

		$response = wp_remote_get( LOOMI_STUDIO_UPDATE_SERVER, [
			'timeout'   => Plugin::UPDATE_TIMEOUT,
			'sslverify' => true,
			'headers'   => [ 'Accept' => 'application/json' ],
		] );

		if ( is_wp_error( $response ) ) {
			set_transient( Plugin::UPDATE_TRANSIENT, [], MINUTE_IN_SECONDS * 30 );
			return null;
		}
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			set_transient( Plugin::UPDATE_TRANSIENT, [], MINUTE_IN_SECONDS * 30 );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if (
			! is_array( $body )
			|| empty( $body['version'] ) || ! is_string( $body['version'] )
			|| empty( $body['download_url'] ) || ! is_string( $body['download_url'] )
			|| empty( $body['sections'] ) || ! is_array( $body['sections'] )
		) {
			set_transient( Plugin::UPDATE_TRANSIENT, [], MINUTE_IN_SECONDS * 30 );
			return null;
		}

		set_transient( Plugin::UPDATE_TRANSIENT, $body, Plugin::UPDATE_TTL );
		return $body;
	}
}
