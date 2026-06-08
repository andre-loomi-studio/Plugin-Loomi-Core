<?php
/**
 * Builds Schema.org Product JSON-LD from manual data and/or WooCommerce.
 *
 * Single responsibility: produce a valid Product node. WC auto-fill and
 * manual overrides are split into distinct private methods. The merger
 * lets manual fields take precedence over WC defaults.
 *
 * Filter: `loomi_schema_product_data` ($node, $data) — last chance to mutate.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Schema_Product_Builder {

	public static function build( array $data ) : array {
		$node = [
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
		];

		if ( self::is_wc_product_context() ) {
			$node = self::fill_from_woocommerce( $node );
		}
		$node = self::apply_manual_overrides( $node, $data );

		return (array) apply_filters( 'loomi_schema_product_data', $node, $data );
	}

	private static function is_wc_product_context() : bool {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}
		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}
		if ( ! function_exists( 'get_queried_object_id' ) ) {
			return false;
		}
		return get_post_type( (int) get_queried_object_id() ) === 'product';
	}

	private static function fill_from_woocommerce( array $node ) : array {
		$product = wc_get_product( (int) get_queried_object_id() );
		if ( ! $product ) {
			return $node;
		}
		return self::apply_wc_fields( $node, $product );
	}

	private static function apply_wc_fields( array $node, $product ) : array {
		$node['name']        = (string) $product->get_name();
		$desc = (string) ( $product->get_short_description() ?: $product->get_description() );
		$node['description'] = wp_strip_all_tags( $desc );

		self::maybe_set( $node, 'sku', (string) $product->get_sku() );
		self::maybe_set_image( $node, (int) $product->get_image_id() );
		self::maybe_set_offers( $node, $product );
		self::maybe_set_rating( $node, $product );

		return $node;
	}

	private static function maybe_set( array &$node, string $key, string $value ) : void {
		if ( $value !== '' ) {
			$node[ $key ] = $value;
		}
	}

	private static function maybe_set_image( array &$node, int $image_id ) : void {
		if ( ! $image_id ) {
			return;
		}
		$url = wp_get_attachment_url( $image_id );
		if ( $url ) {
			$node['image'] = $url;
		}
	}

	private static function maybe_set_offers( array &$node, $product ) : void {
		$price = (string) $product->get_price();
		if ( $price === '' ) {
			return;
		}
		$node['offers'] = [
			'@type'         => 'Offer',
			'price'         => (float) $price,
			'priceCurrency' => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
			'availability'  => 'https://schema.org/' . ( $product->is_in_stock() ? 'InStock' : 'OutOfStock' ),
			'url'           => get_permalink( (int) get_queried_object_id() ),
		];
	}

	private static function maybe_set_rating( array &$node, $product ) : void {
		if ( ! method_exists( $product, 'get_average_rating' ) ) {
			return;
		}
		$rating = (float) $product->get_average_rating();
		$count  = (int) $product->get_review_count();
		if ( $count <= 0 || $rating <= 0 ) {
			return;
		}
		$node['aggregateRating'] = [
			'@type'       => 'AggregateRating',
			'ratingValue' => $rating,
			'reviewCount' => $count,
		];
	}

	private static function apply_manual_overrides( array $node, array $data ) : array {
		foreach ( [ 'name', 'sku', 'description' ] as $field ) {
			if ( ! empty( $data[ $field ] ) ) {
				$node[ $field ] = (string) $data[ $field ];
			}
		}
		if ( ! empty( $data['brand'] ) ) {
			$node['brand'] = [ '@type' => 'Brand', 'name' => (string) $data['brand'] ];
		}
		return self::merge_offers( $node, $data );
	}

	private static function merge_offers( array $node, array $data ) : array {
		$has_offer_field = isset( $data['price'], $data['priceCurrency'], $data['availability'] )
			|| ! empty( $data['price'] ) || ! empty( $data['priceCurrency'] ) || ! empty( $data['availability'] );
		if ( ! $has_offer_field ) {
			return $node;
		}
		$offers = isset( $node['offers'] ) && is_array( $node['offers'] ) ? $node['offers'] : [ '@type' => 'Offer' ];
		if ( isset( $data['price'] ) && $data['price'] !== '' ) {
			$offers['price'] = (float) $data['price'];
		}
		if ( ! empty( $data['priceCurrency'] ) ) {
			$offers['priceCurrency'] = (string) $data['priceCurrency'];
		}
		if ( ! empty( $data['availability'] ) ) {
			$offers['availability'] = 'https://schema.org/' . (string) $data['availability'];
		}
		$node['offers'] = $offers;
		return $node;
	}
}
