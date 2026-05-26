<?php

class SvgSanitizerTest extends Loomi_TestCase {

	public function test_clean_svg_passes_through() : void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="red"/></svg>';
		$out = Loomi_SVG::sanitize( $svg );
		self::assertNotNull( $out );
		self::assertStringContainsString( '<circle', $out );
	}

	public function test_script_tag_removed() : void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><circle r="10"/></svg>';
		$out = Loomi_SVG::sanitize( $svg );
		self::assertNotNull( $out );
		self::assertStringNotContainsString( '<script', $out );
		self::assertStringNotContainsString( 'alert(1)', $out );
	}

	public function test_onload_attribute_removed() : void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><circle r="10"/></svg>';
		$out = Loomi_SVG::sanitize( $svg );
		self::assertNotNull( $out );
		self::assertStringNotContainsString( 'onload', $out );
	}

	public function test_javascript_href_removed() : void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href="javascript:alert(1)"><circle r="10"/></a></svg>';
		$out = Loomi_SVG::sanitize( $svg );
		self::assertNotNull( $out );
		self::assertStringNotContainsString( 'javascript:', $out );
	}

	public function test_style_with_url_javascript_removed() : void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><style>*{background:url(javascript:alert(1))}</style><circle r="10"/></svg>';
		$out = Loomi_SVG::sanitize( $svg );
		self::assertNotNull( $out );
		self::assertStringNotContainsString( '<style', $out );
		self::assertStringNotContainsString( 'javascript:', $out );
	}

	public function test_xxe_neutered() : void {
		$svg = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&xxe;</svg>';
		$out = Loomi_SVG::sanitize( $svg );
		// Either rejected (null) or entity left literal (not expanded)
		if ( $out !== null ) {
			self::assertStringNotContainsString( 'root:', $out );
			self::assertStringNotContainsString( '<!DOCTYPE', $out );
		}
		self::assertTrue( true ); // both outcomes are safe
	}

	public function test_billion_laughs_blocked() : void {
		$svg = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY lol "lol"><!ENTITY lol2 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">]><svg xmlns="http://www.w3.org/2000/svg">&lol2;</svg>';
		$start = microtime( true );
		$out   = Loomi_SVG::sanitize( $svg );
		$dur   = microtime( true ) - $start;
		self::assertLessThan( 1.0, $dur, 'Sanitize should not blow up on billion-laughs' );
		if ( $out !== null ) {
			self::assertStringNotContainsString( '<!DOCTYPE', $out );
		}
	}

	public function test_foreign_object_removed() : void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></body></foreignObject></svg>';
		$out = Loomi_SVG::sanitize( $svg );
		self::assertNotNull( $out );
		self::assertStringNotContainsString( 'foreignObject', $out );
		self::assertStringNotContainsString( '<script', $out );
	}

	public function test_data_svg_href_removed() : void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="data:image/svg+xml;base64,PHN2Zz4="/></svg>';
		$out = Loomi_SVG::sanitize( $svg );
		self::assertNotNull( $out );
		self::assertStringNotContainsString( 'data:image/svg', $out );
	}

	public function test_malformed_xml_rejected() : void {
		$svg = '<svg broken xml<<<';
		$out = Loomi_SVG::sanitize( $svg );
		self::assertNull( $out );
	}

	public function test_upload_prefilter_rejects_malformed() : void {
		$tmp = tempnam( sys_get_temp_dir(), 'svg' );
		file_put_contents( $tmp, '<svg broken' );
		$file = [
			'name'     => 'test.svg',
			'tmp_name' => $tmp,
			'type'     => 'image/svg+xml',
			'error'    => 0,
			'size'     => 12,
		];
		$result = Loomi_SVG::sanitize_on_upload( $file );
		self::assertNotEmpty( $result['error'] );
		@unlink( $tmp );
	}
}
