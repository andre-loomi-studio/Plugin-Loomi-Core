<?php
/**
 * Tests for the Product schema type — manual standalone path (WC inactive)
 * and the sanitizer/builder behavior. WC-active path requires a WC stub
 * which is out of scope for this suite.
 */

class SchemaProductTest extends WP_UnitTestCase {

	public function tear_down() : void {
		remove_all_filters( 'loomi_schema_product_data' );
		parent::tear_down();
	}

	/* -----------------------------------------------------------------
	 * Product Builder — manual path
	 * --------------------------------------------------------------- */

	public function test_product_builder_manual_basic() : void {
		$node = Loomi_Schema_Product_Builder::build( [
			'name'          => 'Widget',
			'sku'           => 'WIDGET-1',
			'price'         => '49.90',
			'priceCurrency' => 'BRL',
			'availability'  => 'InStock',
		] );

		self::assertSame( 'Product', $node['@type'] );
		self::assertSame( 'Widget', $node['name'] );
		self::assertSame( 'WIDGET-1', $node['sku'] );
		self::assertSame( 49.90, $node['offers']['price'] );
		self::assertSame( 'BRL', $node['offers']['priceCurrency'] );
		self::assertSame( 'https://schema.org/InStock', $node['offers']['availability'] );
	}

	public function test_product_builder_attaches_brand_object() : void {
		$node = Loomi_Schema_Product_Builder::build( [
			'name'  => 'X',
			'brand' => 'AcmeCorp',
			'price' => '10',
		] );

		self::assertSame( [ '@type' => 'Brand', 'name' => 'AcmeCorp' ], $node['brand'] );
	}

	public function test_product_filter_can_mutate_node() : void {
		add_filter( 'loomi_schema_product_data', static function ( $node ) {
			$node['extra'] = 'value';
			return $node;
		} );

		$node = Loomi_Schema_Product_Builder::build( [ 'name' => 'X', 'price' => '10' ] );

		self::assertSame( 'value', $node['extra'] );
	}

	/* -----------------------------------------------------------------
	 * Product Sanitizer — manual path (WC inactive in this test env)
	 * --------------------------------------------------------------- */

	public function test_sanitizer_requires_name_when_wc_inactive() : void {
		if ( class_exists( 'WooCommerce' ) ) {
			self::markTestSkipped( 'WC is active — manual name requirement does not apply.' );
		}
		$result = Loomi_Schema_Product_Sanitizer::sanitize( [ 'price' => '10' ] );
		self::assertInstanceOf( 'WP_Error', $result );
		self::assertSame( 'loomi_schema_product_name', $result->get_error_code() );
	}

	public function test_sanitizer_requires_price_when_wc_inactive() : void {
		if ( class_exists( 'WooCommerce' ) ) {
			self::markTestSkipped( 'WC is active — manual price requirement does not apply.' );
		}
		$result = Loomi_Schema_Product_Sanitizer::sanitize( [ 'name' => 'Widget' ] );
		self::assertInstanceOf( 'WP_Error', $result );
		self::assertSame( 'loomi_schema_product_price_required', $result->get_error_code() );
	}

	public function test_sanitizer_rejects_invalid_availability() : void {
		$result = Loomi_Schema_Product_Sanitizer::sanitize( [
			'name'         => 'Widget',
			'price'        => '10',
			'availability' => 'maybe',
		] );
		self::assertInstanceOf( 'WP_Error', $result );
		self::assertSame( 'loomi_schema_product_availability', $result->get_error_code() );
	}

	public function test_sanitizer_rejects_negative_price() : void {
		$result = Loomi_Schema_Product_Sanitizer::sanitize( [
			'name'  => 'Widget',
			'price' => '-5',
		] );
		self::assertInstanceOf( 'WP_Error', $result );
		self::assertSame( 'loomi_schema_product_price', $result->get_error_code() );
	}

	public function test_sanitizer_accepts_valid_minimal_input() : void {
		$result = Loomi_Schema_Product_Sanitizer::sanitize( [
			'name'  => 'Widget',
			'price' => '49.90',
		] );
		self::assertIsArray( $result );
		self::assertSame( 'Widget', $result['name'] );
		self::assertSame( 49.90, $result['price'] );
	}

	public function test_sanitizer_accepts_comma_decimal_in_price() : void {
		$result = Loomi_Schema_Product_Sanitizer::sanitize( [
			'name'  => 'Widget',
			'price' => '49,90',
		] );
		self::assertIsArray( $result );
		self::assertSame( 49.90, $result['price'] );
	}

	/* -----------------------------------------------------------------
	 * Custom JSON anti-script wrap (regression alongside Product)
	 * --------------------------------------------------------------- */

	public function test_custom_json_with_script_wrap_rejected() : void {
		$reflection = new ReflectionClass( 'Loomi_Schema_Sanitizer' );
		$method     = $reflection->getMethod( 'sanitize_custom_json' );
		$method->setAccessible( true );

		$result = $method->invoke( null, [ 'custom_json' => '<script>{"@type":"Article"}</script>' ] );
		self::assertInstanceOf( 'WP_Error', $result );
		self::assertSame( 'loomi_schema_jsonld_wrap_script', $result->get_error_code() );
	}
}
