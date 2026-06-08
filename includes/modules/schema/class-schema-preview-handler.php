<?php
/**
 * AJAX handler for the Schema tab live preview. Reads unsaved form state from
 * $_POST, casts each field minimally, builds a LocalBusiness JSON-LD via
 * Loomi_Schema_Builder, returns it as JSON. Nothing is persisted.
 *
 * Endpoint: wp_ajax_loomi_schema_preview (admin-ajax.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Schema_Preview_Handler implements Loomi_Module {

	const ACTION = 'loomi_schema_preview';

	public static function register() : void {
		add_action( 'wp_ajax_' . self::ACTION, [ __CLASS__, 'handle' ] );
	}

	public static function handle() : void {
		check_admin_referer( self::ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden', 'loomi-studio-setup' ) ], 403 );
		}

		$raw = isset( $_POST['loomi_schema_global'] ) && is_array( $_POST['loomi_schema_global'] )
			? wp_unslash( $_POST['loomi_schema_global'] )
			: [];

		$payload = self::cast_global_payload( $raw );
		$jsonld  = Loomi_Schema_Builder::build_local_business( $payload, [] );

		wp_send_json_success( [ 'jsonld' => $jsonld ] );
	}

	/**
	 * Minimal sanitizer/caster for preview-time payload. Mirrors the relevant
	 * bits of Settings_Sanitizer without persisting anything.
	 */
	private static function cast_global_payload( array $raw ) : array {
		$out = [];

		self::cast_top_level_strings( $out, $raw );
		self::cast_address( $out, $raw );
		self::cast_geo( $out, $raw );
		self::cast_opening_hours( $out, $raw );
		self::cast_string_lists( $out, $raw );
		self::cast_identifier( $out, $raw );

		return $out;
	}

	private static function cast_top_level_strings( array &$out, array $raw ) : void {
		foreach ( [ 'name', 'alternateName', 'description', 'telephone', 'email', 'priceRange' ] as $f ) {
			if ( isset( $raw[ $f ] ) ) {
				$out[ $f ] = sanitize_text_field( (string) $raw[ $f ] );
			}
		}
	}

	private static function cast_address( array &$out, array $raw ) : void {
		if ( ! isset( $raw['address'] ) || ! is_array( $raw['address'] ) ) {
			return;
		}
		$out['address'] = array_map( 'sanitize_text_field', array_map( 'strval', $raw['address'] ) );
	}

	private static function cast_geo( array &$out, array $raw ) : void {
		if ( ! isset( $raw['geo'] ) || ! is_array( $raw['geo'] ) ) {
			return;
		}
		$lat = self::comma_float( $raw['geo']['latitude'] ?? '' );
		$lon = self::comma_float( $raw['geo']['longitude'] ?? '' );
		if ( $lat !== null && $lon !== null ) {
			$out['geo'] = [ 'latitude' => $lat, 'longitude' => $lon ];
		}
	}

	private static function comma_float( $value ) : ?float {
		$value = str_replace( ',', '.', (string) $value );
		return $value !== '' ? (float) $value : null;
	}

	private static function cast_opening_hours( array &$out, array $raw ) : void {
		if ( ! isset( $raw['openingHours'] ) || ! is_array( $raw['openingHours'] ) ) {
			return;
		}
		$out['openingHours'] = array_values( array_map( [ __CLASS__, 'cast_hours_row' ], $raw['openingHours'] ) );
	}

	private static function cast_hours_row( $row ) : array {
		if ( ! is_array( $row ) ) {
			return [ 'days' => [], 'opens' => '', 'closes' => '' ];
		}
		return [
			'days'   => isset( $row['days'] ) ? array_map( 'sanitize_text_field', array_map( 'strval', (array) $row['days'] ) ) : [],
			'opens'  => isset( $row['opens'] ) ? sanitize_text_field( (string) $row['opens'] ) : '',
			'closes' => isset( $row['closes'] ) ? sanitize_text_field( (string) $row['closes'] ) : '',
		];
	}

	private static function cast_string_lists( array &$out, array $raw ) : void {
		if ( isset( $raw['areaServed'] ) ) {
			$out['areaServed'] = self::lines_or_array( $raw['areaServed'], 'sanitize_text_field' );
		}
		if ( isset( $raw['sameAs'] ) ) {
			$out['sameAs'] = self::lines_or_array( $raw['sameAs'], 'esc_url_raw' );
		}
	}

	private static function lines_or_array( $input, callable $cleaner ) : array {
		$list = is_string( $input )
			? array_filter( array_map( 'trim', explode( "\n", $input ) ) )
			: (array) $input;
		return array_values( array_filter( array_map( $cleaner, array_map( 'strval', $list ) ) ) );
	}

	private static function cast_identifier( array &$out, array $raw ) : void {
		if ( ! isset( $raw['identifier'] ) || ! is_array( $raw['identifier'] ) ) {
			return;
		}
		$out['identifier'] = array_map( 'sanitize_text_field', array_map( 'strval', $raw['identifier'] ) );
	}
}
