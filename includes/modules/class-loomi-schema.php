<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Schema implements Loomi_Module {

	const META_TYPE = '_loomi_schema_type';
	const META_DATA = '_loomi_schema_data';
	const NONCE     = 'loomi_schema_metabox';

	const TYPE_NONE           = 'none';
	const TYPE_LOCAL_BUSINESS = 'local_business';
	const TYPE_SERVICE        = 'service';
	const TYPE_FAQ_PAGE       = 'faq_page';
	const TYPE_CUSTOM_JSON    = 'custom_json';

	public static function types() : array {
		return [
			self::TYPE_NONE           => __( 'Nenhum',               'loomi-studio-setup' ),
			self::TYPE_LOCAL_BUSINESS => __( 'LocalBusiness',        'loomi-studio-setup' ),
			self::TYPE_SERVICE        => __( 'Service',              'loomi-studio-setup' ),
			self::TYPE_FAQ_PAGE       => __( 'FAQPage',              'loomi-studio-setup' ),
			self::TYPE_CUSTOM_JSON    => __( 'JSON-LD customizado',  'loomi-studio-setup' ),
		];
	}

	public static function register() : void {
		add_action( 'add_meta_boxes',           [ __CLASS__, 'add_metabox' ] );
		add_action( 'save_post',                [ __CLASS__, 'save_metabox' ], 10, 2 );
		add_action( 'wp_head',                  [ __CLASS__, 'output' ], 99 );
		add_action( 'admin_notices',            [ __CLASS__, 'render_save_errors' ] );
		add_action( 'wp_ajax_loomi_schema_preview', [ __CLASS__, 'handle_preview' ] );
	}

	/**
	 * AJAX: build a JSON-LD LocalBusiness from the current (unsaved) form state.
	 *
	 * Reads $_POST['loomi_schema_global'] (already shaped like the option key),
	 * casts the minimum needed for build_local_business(), and returns the
	 * resulting array as JSON. Nothing is persisted.
	 */
	public static function handle_preview() : void {
		check_admin_referer( 'loomi_schema_preview' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden', 'loomi-studio-setup' ) ], 403 );
		}

		$raw = isset( $_POST['loomi_schema_global'] ) && is_array( $_POST['loomi_schema_global'] )
			? wp_unslash( $_POST['loomi_schema_global'] )
			: [];

		$payload = self::cast_global_payload( $raw );
		$jsonld  = self::build_local_business( $payload, [] );

		wp_send_json_success( [ 'jsonld' => $jsonld ] );
	}

	/**
	 * Minimal sanitizer/caster for preview-time payload. Mirrors the
	 * relevant bits of Settings_Sanitizer without persisting anything.
	 */
	private static function cast_global_payload( array $raw ) : array {
		$out = [];

		foreach ( [ 'name', 'alternateName', 'description', 'telephone', 'email', 'priceRange' ] as $f ) {
			if ( isset( $raw[ $f ] ) ) {
				$out[ $f ] = sanitize_text_field( (string) $raw[ $f ] );
			}
		}

		if ( isset( $raw['address'] ) && is_array( $raw['address'] ) ) {
			$out['address'] = array_map( 'sanitize_text_field', array_map( 'strval', $raw['address'] ) );
		}

		if ( isset( $raw['geo'] ) && is_array( $raw['geo'] ) ) {
			$lat_raw = isset( $raw['geo']['latitude'] ) ? str_replace( ',', '.', (string) $raw['geo']['latitude'] ) : '';
			$lon_raw = isset( $raw['geo']['longitude'] ) ? str_replace( ',', '.', (string) $raw['geo']['longitude'] ) : '';
			$lat     = $lat_raw !== '' ? (float) $lat_raw : null;
			$lon     = $lon_raw !== '' ? (float) $lon_raw : null;
			if ( $lat !== null && $lon !== null ) {
				$out['geo'] = [
					'latitude'  => $lat,
					'longitude' => $lon,
				];
			}
		}

		if ( isset( $raw['openingHours'] ) && is_array( $raw['openingHours'] ) ) {
			$out['openingHours'] = array_values( array_map( static function ( $row ) {
				if ( ! is_array( $row ) ) {
					return [ 'days' => [], 'opens' => '', 'closes' => '' ];
				}
				return [
					'days'   => isset( $row['days'] ) ? array_map( 'sanitize_text_field', array_map( 'strval', (array) $row['days'] ) ) : [],
					'opens'  => isset( $row['opens'] ) ? sanitize_text_field( (string) $row['opens'] ) : '',
					'closes' => isset( $row['closes'] ) ? sanitize_text_field( (string) $row['closes'] ) : '',
				];
			}, $raw['openingHours'] ) );
		}

		if ( isset( $raw['areaServed'] ) ) {
			$list = is_string( $raw['areaServed'] )
				? array_filter( array_map( 'trim', explode( "\n", $raw['areaServed'] ) ) )
				: (array) $raw['areaServed'];
			$out['areaServed'] = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', $list ) ) ) );
		}

		if ( isset( $raw['sameAs'] ) ) {
			$list = is_string( $raw['sameAs'] )
				? array_filter( array_map( 'trim', explode( "\n", $raw['sameAs'] ) ) )
				: (array) $raw['sameAs'];
			$out['sameAs'] = array_values( array_filter( array_map( 'esc_url_raw', array_map( 'strval', $list ) ) ) );
		}

		if ( isset( $raw['identifier'] ) && is_array( $raw['identifier'] ) ) {
			$out['identifier'] = array_map( 'sanitize_text_field', array_map( 'strval', $raw['identifier'] ) );
		}

		return $out;
	}

	public static function add_metabox() : void {
		foreach ( [ 'post', 'page' ] as $screen ) {
			add_meta_box(
				'loomi-schema',
				__( 'Schema desta página', 'loomi-studio-setup' ),
				[ __CLASS__, 'render_metabox' ],
				$screen,
				'normal',
				'default'
			);
		}
	}

	public static function render_metabox( WP_Post $post ) : void {
		wp_nonce_field( self::NONCE, self::NONCE );

		$type = (string) get_post_meta( $post->ID, self::META_TYPE, true );
		if ( $type === '' ) {
			$type = self::TYPE_NONE;
		}
		$data      = (array) get_post_meta( $post->ID, self::META_DATA, true );
		$global    = (array) Settings_Repository::get( 'loomi_schema_global', [] );
		$permalink = ( get_post_status( $post ) !== 'auto-draft' ) ? get_permalink( $post ) : '';

		include LOOMI_STUDIO_DIR . 'includes/settings/views/schema-metabox.php';
	}

	public static function save_metabox( int $post_id, WP_Post $post ) : void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( wp_is_post_revision( $post_id ) ) return;
		if ( ! isset( $_POST[ self::NONCE ] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		$raw_type = isset( $_POST['loomi_schema_type'] ) ? sanitize_key( wp_unslash( $_POST['loomi_schema_type'] ) ) : self::TYPE_NONE;
		if ( ! array_key_exists( $raw_type, self::types() ) ) {
			$raw_type = self::TYPE_NONE;
		}

		if ( $raw_type === self::TYPE_NONE ) {
			delete_post_meta( $post_id, self::META_TYPE );
			delete_post_meta( $post_id, self::META_DATA );
			return;
		}

		$raw_data    = isset( $_POST['loomi_schema'] ) && is_array( $_POST['loomi_schema'] )
			? wp_unslash( $_POST['loomi_schema'] )
			: [];
		$sanitized   = self::sanitize_payload( $raw_type, $raw_data );

		if ( is_wp_error( $sanitized ) ) {
			set_transient(
				'loomi_schema_errors_' . $post_id,
				$sanitized->get_error_messages(),
				MINUTE_IN_SECONDS
			);
			return;
		}

		update_post_meta( $post_id, self::META_TYPE, $raw_type );
		update_post_meta( $post_id, self::META_DATA, $sanitized );
	}

	private static function sanitize_payload( string $type, array $raw ) {
		switch ( $type ) {
			case self::TYPE_LOCAL_BUSINESS:
				return self::sanitize_local_business( $raw );
			case self::TYPE_SERVICE:
				return self::sanitize_service( $raw );
			case self::TYPE_FAQ_PAGE:
				return self::sanitize_faq_page( $raw );
			case self::TYPE_CUSTOM_JSON:
				return self::sanitize_custom_json( $raw );
		}
		return [];
	}

	private static function sanitize_local_business( array $raw ) {
		$out  = [];
		$name = isset( $raw['name'] ) ? sanitize_text_field( (string) $raw['name'] ) : '';
		if ( $name !== '' ) $out['name'] = $name;

		$locality = isset( $raw['addressLocality'] ) ? sanitize_text_field( (string) $raw['addressLocality'] ) : '';
		if ( $locality !== '' ) $out['addressLocality'] = $locality;

		$lat = isset( $raw['latitude'] ) && $raw['latitude'] !== '' ? (float) $raw['latitude'] : null;
		$lon = isset( $raw['longitude'] ) && $raw['longitude'] !== '' ? (float) $raw['longitude'] : null;
		if ( $lat !== null ) {
			if ( $lat < -90.0 || $lat > 90.0 ) {
				return new WP_Error( 'loomi_schema_lat', __( 'Latitude fora do intervalo [-90, 90].', 'loomi-studio-setup' ) );
			}
			$out['latitude'] = $lat;
		}
		if ( $lon !== null ) {
			if ( $lon < -180.0 || $lon > 180.0 ) {
				return new WP_Error( 'loomi_schema_lon', __( 'Longitude fora do intervalo [-180, 180].', 'loomi-studio-setup' ) );
			}
			$out['longitude'] = $lon;
		}
		return $out;
	}

	private static function sanitize_service( array $raw ) {
		$service_type = isset( $raw['serviceType'] ) ? sanitize_text_field( (string) $raw['serviceType'] ) : '';
		if ( $service_type === '' ) {
			return new WP_Error( 'loomi_schema_service_type', __( 'serviceType é obrigatório para Service.', 'loomi-studio-setup' ) );
		}
		$description = isset( $raw['description'] ) ? sanitize_textarea_field( (string) $raw['description'] ) : '';

		$area_served = [];
		if ( isset( $raw['areaServed'] ) && is_array( $raw['areaServed'] ) ) {
			foreach ( $raw['areaServed'] as $city ) {
				$city = sanitize_text_field( (string) $city );
				if ( $city !== '' ) $area_served[] = $city;
			}
		}
		return [
			'serviceType' => $service_type,
			'description' => $description,
			'areaServed'  => $area_served,
		];
	}

	private static function sanitize_faq_page( array $raw ) {
		$items = [];
		if ( isset( $raw['faq'] ) && is_array( $raw['faq'] ) ) {
			foreach ( $raw['faq'] as $row ) {
				$q = isset( $row['question'] ) ? sanitize_text_field( (string) $row['question'] ) : '';
				$a = isset( $row['answer'] )   ? sanitize_textarea_field( (string) $row['answer'] ) : '';
				if ( $q === '' && $a === '' ) continue; // skip totally empty rows
				if ( $q === '' || $a === '' ) {
					return new WP_Error( 'loomi_schema_faq', __( 'Cada par de FAQ precisa de pergunta e resposta.', 'loomi-studio-setup' ) );
				}
				$items[] = [ 'question' => $q, 'answer' => $a ];
			}
		}
		return [ 'faq' => $items ];
	}

	private static function sanitize_custom_json( array $raw ) {
		$json = isset( $raw['custom_json'] ) ? (string) $raw['custom_json'] : '';
		if ( trim( $json ) === '' ) {
			return [ 'custom_json' => '' ];
		}
		$decoded = json_decode( $json, true );
		if ( $decoded === null && json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'loomi_schema_json',
				sprintf(
					/* translators: %s: JSON error message. */
					__( 'JSON-LD inválido: %s', 'loomi-studio-setup' ),
					json_last_error_msg()
				)
			);
		}
		if ( is_array( $decoded ) && self::contains_unsafe_token( $decoded ) ) {
			return new WP_Error(
				'loomi_schema_jsonld_unsafe',
				__( 'JSON-LD contém sequência insegura "</script" — bloqueado.', 'loomi-studio-setup' )
			);
		}
		return [ 'custom_json' => $json ];
	}

	private static function contains_unsafe_token( array $data ) : bool {
		foreach ( $data as $value ) {
			if ( is_string( $value ) ) {
				if ( stripos( $value, '</script' ) !== false ) {
					return true;
				}
			} elseif ( is_array( $value ) ) {
				if ( self::contains_unsafe_token( $value ) ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function render_save_errors() : void {
		global $post;
		if ( ! $post instanceof WP_Post ) return;
		$key    = 'loomi_schema_errors_' . $post->ID;
		$errors = get_transient( $key );
		if ( ! $errors || ! is_array( $errors ) ) return;
		delete_transient( $key );
		foreach ( $errors as $msg ) {
			printf(
				'<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
				esc_html__( 'Schema', 'loomi-studio-setup' ),
				esc_html( $msg )
			);
		}
	}

	public static function output() : void {
		if ( ! apply_filters( 'loomi_schema_enabled', true ) ) return;
		if ( ! is_singular() ) return;

		$post_id = get_queried_object_id();
		if ( ! $post_id ) return;

		$type = (string) get_post_meta( $post_id, self::META_TYPE, true );
		if ( $type === '' || $type === self::TYPE_NONE ) return;

		$data   = (array) get_post_meta( $post_id, self::META_DATA, true );
		$global = (array) Settings_Repository::get( 'loomi_schema_global', [] );
		$schema = self::build( $type, $data, $global );

		if ( empty( $schema ) ) return;

		echo "\n<script type=\"application/ld+json\">"
			. wp_json_encode( $schema, JSON_UNESCAPED_UNICODE )
			. "</script>\n";
	}

	public static function build( string $type, array $data, array $global ) : array {
		switch ( $type ) {
			case self::TYPE_LOCAL_BUSINESS:
				return self::build_local_business( $global, $data );
			case self::TYPE_SERVICE:
				return self::build_service( $data, $global );
			case self::TYPE_FAQ_PAGE:
				return self::build_faq_page( $data );
			case self::TYPE_CUSTOM_JSON:
				return self::build_custom( $data['custom_json'] ?? '' );
		}
		return [];
	}

	public static function build_local_business( array $global, array $overrides = [] ) : array {
		$id = home_url( '/' ) . '#localbusiness';

		$name = $overrides['name'] ?? ( $global['name'] ?? '' );
		$out  = [
			'@context' => 'https://schema.org',
			'@type'    => [ 'LocalBusiness' ],
			'@id'      => $id,
		];

		if ( $name !== '' ) $out['name'] = $name;
		foreach ( [ 'alternateName', 'description', 'telephone', 'email', 'priceRange' ] as $field ) {
			if ( ! empty( $global[ $field ] ) ) {
				$out[ $field ] = $global[ $field ];
			}
		}
		$out['url'] = home_url( '/' );

		$address = [ '@type' => 'PostalAddress' ];
		$ga      = (array) ( $global['address'] ?? [] );
		foreach ( [ 'streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry' ] as $f ) {
			$value = $overrides[ $f ] ?? ( $ga[ $f ] ?? '' );
			if ( $value !== '' ) {
				$address[ $f ] = $value;
			}
		}
		if ( count( $address ) > 1 ) {
			$out['address'] = $address;
		}

		$gg  = (array) ( $global['geo'] ?? [] );
		$lat = isset( $overrides['latitude'] ) ? (float) $overrides['latitude'] : ( $gg['latitude'] ?? null );
		$lon = isset( $overrides['longitude'] ) ? (float) $overrides['longitude'] : ( $gg['longitude'] ?? null );
		if ( $lat !== null && $lon !== null ) {
			$out['geo'] = [
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) $lon,
			];
		}

		if ( ! empty( $global['openingHours'] ) && is_array( $global['openingHours'] ) ) {
			$specs = [];
			foreach ( $global['openingHours'] as $row ) {
				if ( empty( $row['days'] ) || empty( $row['opens'] ) || empty( $row['closes'] ) ) continue;
				$specs[] = [
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => array_values( (array) $row['days'] ),
					'opens'     => (string) $row['opens'],
					'closes'    => (string) $row['closes'],
				];
			}
			if ( $specs ) $out['openingHoursSpecification'] = $specs;
		}

		if ( ! empty( $global['areaServed'] ) && is_array( $global['areaServed'] ) ) {
			$out['areaServed'] = array_map(
				static fn( $c ) => [ '@type' => 'City', 'name' => (string) $c ],
				array_values( $global['areaServed'] )
			);
		}

		if ( ! empty( $global['sameAs'] ) && is_array( $global['sameAs'] ) ) {
			$out['sameAs'] = array_values( $global['sameAs'] );
		}

		if ( ! empty( $global['identifier']['propertyID'] ) && ! empty( $global['identifier']['value'] ) ) {
			$out['identifier'] = [
				'@type'      => 'PropertyValue',
				'propertyID' => (string) $global['identifier']['propertyID'],
				'value'      => (string) $global['identifier']['value'],
			];
		}

		return $out;
	}

	public static function build_service( array $data, array $global ) : array {
		$out = [
			'@context'    => 'https://schema.org',
			'@type'       => 'Service',
			'serviceType' => $data['serviceType'] ?? '',
			'provider'    => [ '@id' => home_url( '/' ) . '#localbusiness' ],
		];
		if ( ! empty( $data['description'] ) ) $out['description'] = $data['description'];
		if ( ! empty( $data['areaServed'] ) && is_array( $data['areaServed'] ) ) {
			$out['areaServed'] = array_map(
				static fn( $c ) => [ '@type' => 'City', 'name' => (string) $c ],
				array_values( $data['areaServed'] )
			);
		}
		return $out;
	}

	public static function build_faq_page( array $data ) : array {
		$items = (array) ( $data['faq'] ?? [] );
		$main  = [];
		foreach ( $items as $row ) {
			$q = (string) ( $row['question'] ?? '' );
			$a = (string) ( $row['answer']   ?? '' );
			if ( $q === '' || $a === '' ) continue;
			$main[] = [
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $a,
				],
			];
		}
		if ( ! $main ) return [];
		return [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $main,
		];
	}

	public static function build_custom( string $json ) : array {
		if ( trim( $json ) === '' ) return [];
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) return [];
		return $decoded;
	}
}
