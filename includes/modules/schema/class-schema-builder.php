<?php
/**
 * Builds Schema.org JSON-LD arrays from sanitized data.
 *
 * Single responsibility: input → JSON-LD structure. No I/O, no WP hooks, no
 * sanitization. Stateless static methods so consumers (Renderer, AJAX preview,
 * external test fixtures) can call without instantiation.
 *
 * Public dispatcher: build_for_type( $type, $data, $global ).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Schema_Builder {

	public static function build_for_type( string $type, array $data, array $global ) : array {
		switch ( $type ) {
			case Loomi_Schema::TYPE_LOCAL_BUSINESS:
				return self::build_local_business( $global, $data );
			case Loomi_Schema::TYPE_SERVICE:
				return self::build_service( $data, $global );
			case Loomi_Schema::TYPE_FAQ_PAGE:
				return self::build_faq_page( $data );
			case Loomi_Schema::TYPE_PRODUCT:
				return Loomi_Schema_Product_Builder::build( $data );
			case Loomi_Schema::TYPE_CUSTOM_JSON:
				return self::build_custom( $data['custom_json'] ?? '' );
		}
		return [];
	}

	public static function build_local_business( array $global, array $overrides = [] ) : array {
		$id   = home_url( '/' ) . '#localbusiness';
		$out  = [
			'@context' => 'https://schema.org',
			'@type'    => [ 'LocalBusiness' ],
			'@id'      => $id,
			'url'      => home_url( '/' ),
		];

		$name = $overrides['name'] ?? ( $global['name'] ?? '' );
		if ( $name !== '' ) {
			$out['name'] = $name;
		}

		self::merge_top_level_strings( $out, $global );
		self::attach_address( $out, $global, $overrides );
		self::attach_geo( $out, $global, $overrides );
		self::attach_opening_hours( $out, $global );
		self::attach_area_served( $out, $global );
		self::attach_same_as( $out, $global );
		self::attach_identifier( $out, $global );

		return $out;
	}

	private static function merge_top_level_strings( array &$out, array $global ) : void {
		foreach ( [ 'alternateName', 'description', 'telephone', 'email', 'priceRange' ] as $field ) {
			if ( ! empty( $global[ $field ] ) ) {
				$out[ $field ] = $global[ $field ];
			}
		}
	}

	private static function attach_address( array &$out, array $global, array $overrides ) : void {
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
	}

	private static function attach_geo( array &$out, array $global, array $overrides ) : void {
		$gg  = (array) ( $global['geo'] ?? [] );
		$lat = isset( $overrides['latitude'] ) ? (float) $overrides['latitude'] : ( $gg['latitude'] ?? null );
		$lon = isset( $overrides['longitude'] ) ? (float) $overrides['longitude'] : ( $gg['longitude'] ?? null );
		if ( $lat === null || $lon === null ) {
			return;
		}
		$out['geo'] = [
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) $lat,
			'longitude' => (float) $lon,
		];
	}

	private static function attach_opening_hours( array &$out, array $global ) : void {
		if ( empty( $global['openingHours'] ) || ! is_array( $global['openingHours'] ) ) {
			return;
		}
		$specs = [];
		foreach ( $global['openingHours'] as $row ) {
			if ( empty( $row['days'] ) || empty( $row['opens'] ) || empty( $row['closes'] ) ) {
				continue;
			}
			$specs[] = [
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => array_values( (array) $row['days'] ),
				'opens'     => (string) $row['opens'],
				'closes'    => (string) $row['closes'],
			];
		}
		if ( $specs ) {
			$out['openingHoursSpecification'] = $specs;
		}
	}

	private static function attach_area_served( array &$out, array $global ) : void {
		if ( empty( $global['areaServed'] ) || ! is_array( $global['areaServed'] ) ) {
			return;
		}
		$out['areaServed'] = array_map(
			static fn( $c ) => [ '@type' => 'City', 'name' => (string) $c ],
			array_values( $global['areaServed'] )
		);
	}

	private static function attach_same_as( array &$out, array $global ) : void {
		if ( empty( $global['sameAs'] ) || ! is_array( $global['sameAs'] ) ) {
			return;
		}
		$out['sameAs'] = array_values( $global['sameAs'] );
	}

	private static function attach_identifier( array &$out, array $global ) : void {
		if ( empty( $global['identifier']['propertyID'] ) || empty( $global['identifier']['value'] ) ) {
			return;
		}
		$out['identifier'] = [
			'@type'      => 'PropertyValue',
			'propertyID' => (string) $global['identifier']['propertyID'],
			'value'      => (string) $global['identifier']['value'],
		];
	}

	public static function build_service( array $data, array $global ) : array {
		$out = [
			'@context'    => 'https://schema.org',
			'@type'       => 'Service',
			'serviceType' => $data['serviceType'] ?? '',
			'provider'    => [ '@id' => home_url( '/' ) . '#localbusiness' ],
		];
		if ( ! empty( $data['description'] ) ) {
			$out['description'] = $data['description'];
		}
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
			$a = (string) ( $row['answer'] ?? '' );
			if ( $q === '' || $a === '' ) {
				continue;
			}
			$main[] = [
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $a ],
			];
		}
		if ( ! $main ) {
			return [];
		}
		return [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $main,
		];
	}

	public static function build_custom( string $json ) : array {
		if ( trim( $json ) === '' ) {
			return [];
		}
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : [];
	}
}
