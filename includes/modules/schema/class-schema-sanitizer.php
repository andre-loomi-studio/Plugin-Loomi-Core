<?php
/**
 * Sanitizes per-post schema input by @type. Returns either a clean array OR
 * a WP_Error explaining what's wrong. Single responsibility — no I/O, no
 * persistence, no rendering.
 *
 * Public dispatcher: sanitize_for_type( $type, $raw ).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Schema_Sanitizer {

	/**
	 * @return array|WP_Error
	 */
	public static function sanitize_for_type( string $type, array $raw ) {
		switch ( $type ) {
			case Loomi_Schema::TYPE_LOCAL_BUSINESS:
				return self::sanitize_local_business( $raw );
			case Loomi_Schema::TYPE_SERVICE:
				return self::sanitize_service( $raw );
			case Loomi_Schema::TYPE_FAQ_PAGE:
				return self::sanitize_faq_page( $raw );
			case Loomi_Schema::TYPE_PRODUCT:
				return Loomi_Schema_Product_Sanitizer::sanitize( $raw );
			case Loomi_Schema::TYPE_CUSTOM_JSON:
				return self::sanitize_custom_json( $raw );
		}
		return [];
	}

	/**
	 * @return array|WP_Error
	 */
	private static function sanitize_local_business( array $raw ) {
		$out = [];

		$name = isset( $raw['name'] ) ? sanitize_text_field( (string) $raw['name'] ) : '';
		if ( $name !== '' ) {
			$out['name'] = $name;
		}

		$locality = isset( $raw['addressLocality'] ) ? sanitize_text_field( (string) $raw['addressLocality'] ) : '';
		if ( $locality !== '' ) {
			$out['addressLocality'] = $locality;
		}

		$lat = self::coord_or_null( $raw['latitude'] ?? null );
		$lon = self::coord_or_null( $raw['longitude'] ?? null );
		if ( $lat !== null && ( $lat < -90.0 || $lat > 90.0 ) ) {
			return new WP_Error( 'loomi_schema_lat', __( 'Latitude fora do intervalo [-90, 90].', 'loomi-studio-setup' ) );
		}
		if ( $lon !== null && ( $lon < -180.0 || $lon > 180.0 ) ) {
			return new WP_Error( 'loomi_schema_lon', __( 'Longitude fora do intervalo [-180, 180].', 'loomi-studio-setup' ) );
		}
		if ( $lat !== null ) { $out['latitude'] = $lat; }
		if ( $lon !== null ) { $out['longitude'] = $lon; }

		return $out;
	}

	private static function coord_or_null( $value ) : ?float {
		if ( $value === null || $value === '' ) {
			return null;
		}
		return (float) $value;
	}

	/**
	 * @return array|WP_Error
	 */
	private static function sanitize_service( array $raw ) {
		$service_type = isset( $raw['serviceType'] ) ? sanitize_text_field( (string) $raw['serviceType'] ) : '';
		if ( $service_type === '' ) {
			return new WP_Error( 'loomi_schema_service_type', __( 'serviceType é obrigatório para Service.', 'loomi-studio-setup' ) );
		}

		return [
			'serviceType' => $service_type,
			'description' => isset( $raw['description'] ) ? sanitize_textarea_field( (string) $raw['description'] ) : '',
			'areaServed'  => self::sanitize_string_list( $raw['areaServed'] ?? [] ),
		];
	}

	private static function sanitize_string_list( $input ) : array {
		if ( ! is_array( $input ) ) {
			return [];
		}
		$out = [];
		foreach ( $input as $item ) {
			$clean = sanitize_text_field( (string) $item );
			if ( $clean !== '' ) {
				$out[] = $clean;
			}
		}
		return $out;
	}

	/**
	 * @return array|WP_Error
	 */
	private static function sanitize_faq_page( array $raw ) {
		$items = [];
		if ( ! isset( $raw['faq'] ) || ! is_array( $raw['faq'] ) ) {
			return [ 'faq' => $items ];
		}

		foreach ( $raw['faq'] as $row ) {
			$pair = self::sanitize_faq_row( $row );
			if ( $pair === null ) {
				continue; // totally empty — skip silently
			}
			if ( is_wp_error( $pair ) ) {
				return $pair;
			}
			$items[] = $pair;
		}
		return [ 'faq' => $items ];
	}

	/**
	 * @return array|WP_Error|null  null = empty pair, skip silently.
	 */
	private static function sanitize_faq_row( $row ) {
		$q = isset( $row['question'] ) ? sanitize_text_field( (string) $row['question'] ) : '';
		$a = isset( $row['answer'] )   ? sanitize_textarea_field( (string) $row['answer'] ) : '';
		if ( $q === '' && $a === '' ) {
			return null;
		}
		if ( $q === '' || $a === '' ) {
			return new WP_Error( 'loomi_schema_faq', __( 'Cada par de FAQ precisa de pergunta e resposta.', 'loomi-studio-setup' ) );
		}
		return [ 'question' => $q, 'answer' => $a ];
	}

	/**
	 * @return array|WP_Error
	 */
	private static function sanitize_custom_json( array $raw ) {
		$json = isset( $raw['custom_json'] ) ? (string) $raw['custom_json'] : '';
		if ( trim( $json ) === '' ) {
			return [ 'custom_json' => '' ];
		}
		// Reject the common UX mistake of pasting the full <script type="application/ld+json">...</script>
		// wrapper. The plugin already wraps; an extra <script> emits invalid HTML.
		if ( stripos( ltrim( $json ), '<script' ) === 0 ) {
			return new WP_Error(
				'loomi_schema_jsonld_wrap_script',
				__( 'O plugin já encapsula em <script type="application/ld+json">. Cole apenas o JSON puro (sem tags <script>).', 'loomi-studio-setup' )
			);
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
			if ( is_string( $value ) && stripos( $value, '</script' ) !== false ) {
				return true;
			}
			if ( is_array( $value ) && self::contains_unsafe_token( $value ) ) {
				return true;
			}
		}
		return false;
	}
}
