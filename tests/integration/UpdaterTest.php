<?php

class UpdaterTest extends Loomi_TestCase {

	public function set_up() : void {
		parent::set_up();
		delete_transient( Plugin::UPDATE_TRANSIENT );
	}

	private function mock_remote( $body, int $code = 200 ) : void {
		add_filter( 'pre_http_request', static function ( $pre, $args, $url ) use ( $body, $code ) {
			if ( strpos( $url, 'updates.loomi.studio' ) !== false ) {
				return [
					'response' => [ 'code' => $code ],
					'body'     => is_string( $body ) ? $body : wp_json_encode( $body ),
				];
			}
			return $pre;
		}, 10, 3 );
	}

	public function test_valid_response_injects_update() : void {
		$this->mock_remote( [
			'version'      => '9.9.9',
			'download_url' => 'https://updates.loomi.studio/loomi-studio-setup-9.9.9.zip',
			'sections'     => [ 'changelog' => '<h4>9.9.9</h4><ul><li>x</li></ul>' ],
			'tested'       => '6.7',
			'requires'     => '6.0',
			'requires_php' => '7.4',
		] );

		$transient = (object) [ 'response' => [] ];
		$result    = Loomi_Updater::inject_update( $transient );

		self::assertArrayHasKey( Plugin::basename(), $result->response );
		self::assertSame( '9.9.9', $result->response[ Plugin::basename() ]->new_version );
	}

	public function test_untrusted_package_url_rejected() : void {
		$this->mock_remote( [
			'version'      => '9.9.9',
			'download_url' => 'https://evil.com/payload.zip', // different host!
			'sections'     => [ 'changelog' => 'x' ],
		] );

		$transient = (object) [ 'response' => [] ];
		$result    = Loomi_Updater::inject_update( $transient );
		self::assertEmpty( $result->response );
	}

	public function test_malformed_json_discarded() : void {
		$this->mock_remote( 'not valid json {{{' );

		$transient = (object) [ 'response' => [] ];
		$result    = Loomi_Updater::inject_update( $transient );
		self::assertEmpty( $result->response );
	}

	public function test_missing_sections_discarded() : void {
		$this->mock_remote( [
			'version'      => '9.9.9',
			'download_url' => 'https://updates.loomi.studio/x.zip',
			// no sections key
		] );

		$transient = (object) [ 'response' => [] ];
		$result    = Loomi_Updater::inject_update( $transient );
		self::assertEmpty( $result->response );
	}

	public function test_serve_plugin_info_returns_changelog() : void {
		$this->mock_remote( [
			'version'      => '9.9.9',
			'download_url' => 'https://updates.loomi.studio/x.zip',
			'sections'     => [ 'changelog' => '<h4>9.9.9</h4>', 'description' => 'desc' ],
		] );

		$args   = (object) [ 'slug' => 'loomi-studio-setup' ];
		$result = Loomi_Updater::serve_plugin_info( false, 'plugin_information', $args );

		self::assertIsObject( $result );
		self::assertSame( 'Loomi Studio Setup', $result->name );
		self::assertSame( '9.9.9', $result->version );
		self::assertArrayHasKey( 'changelog', $result->sections );
	}

	public function test_offline_endpoint_returns_null_silently() : void {
		// Force WP_Error from wp_remote_get
		add_filter( 'pre_http_request', static function ( $pre, $args, $url ) {
			if ( strpos( $url, 'updates.loomi.studio' ) !== false ) {
				return new WP_Error( 'http_request_failed', 'connection refused' );
			}
			return $pre;
		}, 10, 3 );

		$start  = microtime( true );
		$rc     = new ReflectionClass( 'Loomi_Updater' );
		$method = $rc->getMethod( 'check_remote' );
		$method->setAccessible( true );
		$result = $method->invoke( null );
		$dur    = microtime( true ) - $start;

		self::assertNull( $result );
		self::assertLessThan( 3.5, $dur );
	}
}
