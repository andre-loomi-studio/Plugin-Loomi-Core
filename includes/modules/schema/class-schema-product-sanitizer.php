<?php
/**
 * Sanitizes per-post Product schema input.
 *
 * Separated from Loomi_Schema_Sanitizer because Product has its own validation
 * concerns (WC-conditional required fields, availability whitelist, currency
 * code format, price as non-negative float). Returns clean array or WP_Error.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Schema_Product_Sanitizer {

	const ALLOWED_AVAILABILITY = [
		'InStock',
		'OutOfStock',
		'PreOrder',
		'Discontinued',
		'LimitedAvailability',
	];

	/**
	 * @return array|WP_Error
	 */
	public static function sanitize( array $raw ) {
		$wc_active = class_exists( 'WooCommerce' );

		$name_or_error = self::extract_name( $raw, $wc_active );
		if ( is_wp_error( $name_or_error ) ) {
			return $name_or_error;
		}
		$price_or_error = self::extract_price( $raw, $wc_active );
		if ( is_wp_error( $price_or_error ) ) {
			return $price_or_error;
		}
		$availability_or_error = self::extract_availability( $raw );
		if ( is_wp_error( $availability_or_error ) ) {
			return $availability_or_error;
		}

		return self::build_output( $raw, $name_or_error, $price_or_error, $availability_or_error );
	}

	/** @return string|WP_Error  empty string OK only when WC active */
	private static function extract_name( array $raw, bool $wc_active ) {
		$name = isset( $raw['name'] ) ? sanitize_text_field( (string) $raw['name'] ) : '';
		if ( $name === '' && ! $wc_active ) {
			return new WP_Error(
				'loomi_schema_product_name',
				__( 'Nome do produto é obrigatório quando WooCommerce não está ativo.', 'loomi-studio-setup' )
			);
		}
		return $name;
	}

	/** @return float|null|WP_Error  null means "no price provided" */
	private static function extract_price( array $raw, bool $wc_active ) {
		if ( ! isset( $raw['price'] ) || $raw['price'] === '' ) {
			if ( ! $wc_active ) {
				return new WP_Error(
					'loomi_schema_product_price_required',
					__( 'Preço é obrigatório quando WooCommerce não está ativo.', 'loomi-studio-setup' )
				);
			}
			return null;
		}
		$price = (float) str_replace( ',', '.', (string) $raw['price'] );
		if ( $price < 0 ) {
			return new WP_Error(
				'loomi_schema_product_price',
				__( 'Preço inválido (não pode ser negativo).', 'loomi-studio-setup' )
			);
		}
		return $price;
	}

	/** @return string|WP_Error  empty string means "no availability provided" */
	private static function extract_availability( array $raw ) {
		if ( empty( $raw['availability'] ) ) {
			return '';
		}
		if ( ! in_array( $raw['availability'], self::ALLOWED_AVAILABILITY, true ) ) {
			return new WP_Error(
				'loomi_schema_product_availability',
				__( 'Disponibilidade inválida — use InStock, OutOfStock, PreOrder, etc.', 'loomi-studio-setup' )
			);
		}
		return (string) $raw['availability'];
	}

	private static function build_output( array $raw, string $name, $price, string $availability ) : array {
		$out = [];
		if ( $name !== '' ) {
			$out['name'] = $name;
		}
		foreach ( [ 'sku', 'brand', 'priceCurrency' ] as $field ) {
			if ( isset( $raw[ $field ] ) ) {
				$clean = sanitize_text_field( (string) $raw[ $field ] );
				if ( $clean !== '' ) {
					$out[ $field ] = $clean;
				}
			}
		}
		if ( isset( $raw['description'] ) ) {
			$desc = sanitize_textarea_field( (string) $raw['description'] );
			if ( $desc !== '' ) {
				$out['description'] = $desc;
			}
		}
		if ( $price !== null ) {
			$out['price'] = $price;
		}
		if ( $availability !== '' ) {
			$out['availability'] = $availability;
		}
		return $out;
	}
}
