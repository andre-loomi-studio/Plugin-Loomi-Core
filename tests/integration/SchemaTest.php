<?php
/**
 * Unit tests for the Loomi_Schema JSON-LD builders.
 *
 * The four public builders are pure functions over their inputs and
 * `home_url()` (stabilised by WP_UnitTestCase). No DB / option access required.
 */

class SchemaTest extends WP_UnitTestCase {

	/* -----------------------------------------------------------------
	 * Helpers
	 * --------------------------------------------------------------- */

	private function full_global() : array {
		return [
			'name'          => 'Loomi Studio',
			'alternateName' => 'Loomi',
			'description'   => 'Estúdio de design e tecnologia.',
			'telephone'     => '+55 11 99999-0000',
			'email'         => 'oi@loomi.test',
			'priceRange'    => '$$',
			'address'       => [
				'streetAddress'   => 'Rua das Flores, 123',
				'addressLocality' => 'São Paulo',
				'addressRegion'   => 'SP',
				'postalCode'      => '01000-000',
				'addressCountry'  => 'BR',
			],
		];
	}

	/* -----------------------------------------------------------------
	 * build_local_business
	 * --------------------------------------------------------------- */

	public function test_local_business_with_global_only() : void {
		$out = Loomi_Schema::build_local_business( $this->full_global() );

		self::assertSame( 'https://schema.org', $out['@context'] );
		self::assertSame( [ 'LocalBusiness' ], $out['@type'] );
		self::assertSame( home_url( '/' ) . '#localbusiness', $out['@id'] );
		self::assertSame( home_url( '/' ), $out['url'] );

		self::assertSame( 'Loomi Studio', $out['name'] );
		self::assertSame( 'Loomi', $out['alternateName'] );
		self::assertSame( 'Estúdio de design e tecnologia.', $out['description'] );
		self::assertSame( '+55 11 99999-0000', $out['telephone'] );
		self::assertSame( 'oi@loomi.test', $out['email'] );
		self::assertSame( '$$', $out['priceRange'] );

		self::assertArrayHasKey( 'address', $out );
		self::assertSame( 'PostalAddress', $out['address']['@type'] );
		self::assertSame( 'Rua das Flores, 123', $out['address']['streetAddress'] );
		self::assertSame( 'São Paulo', $out['address']['addressLocality'] );
		self::assertSame( 'SP', $out['address']['addressRegion'] );
		self::assertSame( '01000-000', $out['address']['postalCode'] );
		self::assertSame( 'BR', $out['address']['addressCountry'] );
	}

	public function test_local_business_override_takes_precedence() : void {
		$overrides = [
			'name'            => 'Loomi Filial Centro',
			'addressLocality' => 'Campinas',
		];
		$out = Loomi_Schema::build_local_business( $this->full_global(), $overrides );

		self::assertSame( 'Loomi Filial Centro', $out['name'] );
		self::assertSame( 'Campinas', $out['address']['addressLocality'] );
		// Other address fields preserved from global.
		self::assertSame( 'Rua das Flores, 123', $out['address']['streetAddress'] );
		self::assertSame( 'SP', $out['address']['addressRegion'] );
		self::assertSame( '01000-000', $out['address']['postalCode'] );
		self::assertSame( 'BR', $out['address']['addressCountry'] );
		// Other top-level global fields preserved.
		self::assertSame( '+55 11 99999-0000', $out['telephone'] );
	}

	public function test_local_business_with_geo() : void {
		$global         = $this->full_global();
		$global['geo']  = [ 'latitude' => -23.5505, 'longitude' => -46.6333 ];
		$out            = Loomi_Schema::build_local_business( $global );

		self::assertArrayHasKey( 'geo', $out );
		self::assertSame( 'GeoCoordinates', $out['geo']['@type'] );
		self::assertSame( -23.5505, $out['geo']['latitude'] );
		self::assertSame( -46.6333, $out['geo']['longitude'] );
	}

	public function test_local_business_without_geo() : void {
		$out = Loomi_Schema::build_local_business( $this->full_global() );
		self::assertArrayNotHasKey( 'geo', $out );
	}

	public function test_local_business_with_opening_hours() : void {
		$global                  = $this->full_global();
		$global['openingHours']  = [
			[
				'days'   => [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' ],
				'opens'  => '09:00',
				'closes' => '17:00',
			],
		];
		$out = Loomi_Schema::build_local_business( $global );

		self::assertArrayHasKey( 'openingHoursSpecification', $out );
		self::assertCount( 1, $out['openingHoursSpecification'] );
		self::assertSame( 'OpeningHoursSpecification', $out['openingHoursSpecification'][0]['@type'] );
		self::assertCount( 5, $out['openingHoursSpecification'][0]['dayOfWeek'] );
		self::assertSame( '09:00', $out['openingHoursSpecification'][0]['opens'] );
		self::assertSame( '17:00', $out['openingHoursSpecification'][0]['closes'] );
	}

	public function test_local_business_skips_incomplete_hours_rows() : void {
		$global                  = $this->full_global();
		$global['openingHours']  = [
			// Incomplete — no opens.
			[ 'days' => [ 'Saturday' ], 'opens' => '', 'closes' => '13:00' ],
			// Valid.
			[ 'days' => [ 'Sunday' ], 'opens' => '10:00', 'closes' => '14:00' ],
		];
		$out = Loomi_Schema::build_local_business( $global );

		self::assertCount( 1, $out['openingHoursSpecification'] );
		self::assertSame( [ 'Sunday' ], $out['openingHoursSpecification'][0]['dayOfWeek'] );
	}

	public function test_local_business_area_served_and_same_as() : void {
		$global                = $this->full_global();
		$global['areaServed']  = [ 'São Paulo', 'Campinas' ];
		$global['sameAs']      = [ 'https://instagram.com/loomi', 'https://linkedin.com/company/loomi' ];
		$out = Loomi_Schema::build_local_business( $global );

		self::assertArrayHasKey( 'areaServed', $out );
		self::assertCount( 2, $out['areaServed'] );
		self::assertSame( 'City', $out['areaServed'][0]['@type'] );
		self::assertSame( 'São Paulo', $out['areaServed'][0]['name'] );
		self::assertSame( 'Campinas', $out['areaServed'][1]['name'] );

		self::assertSame(
			[ 'https://instagram.com/loomi', 'https://linkedin.com/company/loomi' ],
			$out['sameAs']
		);
	}

	public function test_local_business_identifier_present() : void {
		$global                = $this->full_global();
		$global['identifier']  = [ 'propertyID' => 'CNPJ', 'value' => '00.000.000/0001-00' ];
		$out = Loomi_Schema::build_local_business( $global );

		self::assertArrayHasKey( 'identifier', $out );
		self::assertSame( 'PropertyValue', $out['identifier']['@type'] );
		self::assertSame( 'CNPJ', $out['identifier']['propertyID'] );
		self::assertSame( '00.000.000/0001-00', $out['identifier']['value'] );
	}

	public function test_local_business_identifier_partial_skipped() : void {
		$global                = $this->full_global();
		$global['identifier']  = [ 'propertyID' => 'CNPJ', 'value' => '' ];
		$out = Loomi_Schema::build_local_business( $global );

		self::assertArrayNotHasKey( 'identifier', $out );
	}

	/* -----------------------------------------------------------------
	 * build_service
	 * --------------------------------------------------------------- */

	public function test_service_basic() : void {
		$data = [
			'serviceType' => 'Identidade Visual',
			'description' => 'Criação completa de identidade visual.',
			'areaServed'  => [ 'São Paulo', 'Rio de Janeiro' ],
		];
		$out = Loomi_Schema::build_service( $data, $this->full_global() );

		self::assertSame( 'https://schema.org', $out['@context'] );
		self::assertSame( 'Service', $out['@type'] );
		self::assertSame( 'Identidade Visual', $out['serviceType'] );
		self::assertSame( 'Criação completa de identidade visual.', $out['description'] );

		self::assertArrayHasKey( 'provider', $out );
		self::assertSame( home_url( '/' ) . '#localbusiness', $out['provider']['@id'] );

		self::assertArrayHasKey( 'areaServed', $out );
		self::assertCount( 2, $out['areaServed'] );
		self::assertSame( 'City', $out['areaServed'][0]['@type'] );
		self::assertSame( 'São Paulo', $out['areaServed'][0]['name'] );
		self::assertSame( 'Rio de Janeiro', $out['areaServed'][1]['name'] );
	}

	public function test_service_requires_provider_id() : void {
		// Even with no data at all, provider.@id must be present and well-formed.
		$out = Loomi_Schema::build_service( [], [] );

		self::assertArrayHasKey( 'provider', $out );
		self::assertSame( home_url( '/' ) . '#localbusiness', $out['provider']['@id'] );

		// And with full data — same invariant.
		$out2 = Loomi_Schema::build_service(
			[ 'serviceType' => 'Branding', 'description' => 'x', 'areaServed' => [ 'SP' ] ],
			$this->full_global()
		);
		self::assertSame( home_url( '/' ) . '#localbusiness', $out2['provider']['@id'] );
	}

	/* -----------------------------------------------------------------
	 * build_faq_page
	 * --------------------------------------------------------------- */

	public function test_faq_page_with_two_pairs() : void {
		$out = Loomi_Schema::build_faq_page( [
			'faq' => [
				[ 'question' => 'Qual o prazo?', 'answer' => 'Em média 15 dias úteis.' ],
				[ 'question' => 'Como pago?',    'answer' => 'Pix ou cartão.' ],
			],
		] );

		self::assertSame( 'https://schema.org', $out['@context'] );
		self::assertSame( 'FAQPage', $out['@type'] );
		self::assertArrayHasKey( 'mainEntity', $out );
		self::assertCount( 2, $out['mainEntity'] );

		self::assertSame( 'Question', $out['mainEntity'][0]['@type'] );
		self::assertSame( 'Qual o prazo?', $out['mainEntity'][0]['name'] );
		self::assertSame( 'Answer', $out['mainEntity'][0]['acceptedAnswer']['@type'] );
		self::assertSame( 'Em média 15 dias úteis.', $out['mainEntity'][0]['acceptedAnswer']['text'] );

		self::assertSame( 'Como pago?', $out['mainEntity'][1]['name'] );
		self::assertSame( 'Pix ou cartão.', $out['mainEntity'][1]['acceptedAnswer']['text'] );
	}

	public function test_faq_page_skips_empty_pairs() : void {
		$out = Loomi_Schema::build_faq_page( [
			'faq' => [
				[ 'question' => 'Q1', 'answer' => 'A1' ],
				[ 'question' => 'Q2', 'answer' => '' ],   // invalid — skipped
				[ 'question' => 'Q3', 'answer' => 'A3' ],
			],
		] );

		self::assertCount( 2, $out['mainEntity'] );
		self::assertSame( 'Q1', $out['mainEntity'][0]['name'] );
		self::assertSame( 'Q3', $out['mainEntity'][1]['name'] );
	}

	public function test_faq_page_returns_empty_when_no_valid_pair() : void {
		self::assertSame( [], Loomi_Schema::build_faq_page( [] ) );
		self::assertSame( [], Loomi_Schema::build_faq_page( [ 'faq' => [] ] ) );
		self::assertSame( [], Loomi_Schema::build_faq_page( [
			'faq' => [ [ 'question' => '', 'answer' => '' ] ],
		] ) );
	}

	/* -----------------------------------------------------------------
	 * build_custom
	 * --------------------------------------------------------------- */

	public function test_custom_valid_json() : void {
		$json = '{"@context":"https://schema.org","@type":"Organization","name":"Loomi"}';
		$out  = Loomi_Schema::build_custom( $json );

		self::assertSame( 'https://schema.org', $out['@context'] );
		self::assertSame( 'Organization', $out['@type'] );
		self::assertSame( 'Loomi', $out['name'] );
	}

	public function test_custom_invalid_json() : void {
		self::assertSame( [], Loomi_Schema::build_custom( '{not valid json' ) );
		self::assertSame( [], Loomi_Schema::build_custom( '{"unterminated":' ) );
	}

	public function test_custom_empty_string() : void {
		self::assertSame( [], Loomi_Schema::build_custom( '' ) );
		self::assertSame( [], Loomi_Schema::build_custom( '   ' ) );
	}

	/* -----------------------------------------------------------------
	 * Sanitizer — lat/lon comma normalization (pt-PT locale workaround)
	 * --------------------------------------------------------------- */

	public function test_global_latitude_with_comma_is_normalized() : void {
		delete_option( Plugin::OPTION_KEY );
		Settings_Repository::clear_cache();

		$sanitized = Settings_Sanitizer::sanitize( [
			'loomi_schema_global' => [
				'geo' => [ 'latitude' => '38,76283' ],
			],
		] );
		update_option( Plugin::OPTION_KEY, $sanitized );

		$stored = get_option( Plugin::OPTION_KEY );
		self::assertSame( 38.76283, $stored['loomi_schema_global']['geo']['latitude'] );
	}

	public function test_global_longitude_with_comma_is_normalized() : void {
		delete_option( Plugin::OPTION_KEY );
		Settings_Repository::clear_cache();

		$sanitized = Settings_Sanitizer::sanitize( [
			'loomi_schema_global' => [
				'geo' => [ 'longitude' => '-9,22571' ],
			],
		] );
		update_option( Plugin::OPTION_KEY, $sanitized );

		$stored = get_option( Plugin::OPTION_KEY );
		self::assertSame( -9.22571, $stored['loomi_schema_global']['geo']['longitude'] );
	}
}
