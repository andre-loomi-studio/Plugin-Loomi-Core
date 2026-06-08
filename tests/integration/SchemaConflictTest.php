<?php
/**
 * Tests for Loomi_Schema_Conflict_Detector — detection of active SEO plugins
 * that would conflict with auto schemas.
 *
 * Note: WP_Customize_Manager-style constants and globals can't be undefined
 * between tests, so each detection test uses an in-test global override
 * (eval / function definition) and verifies that detect() picks it up.
 */

class SchemaConflictTest extends WP_UnitTestCase {

	public function tear_down() : void {
		remove_all_filters( 'loomi_schema_conflict_detected' );
		parent::tear_down();
	}

	public function test_no_seo_plugin_returns_empty() : void {
		$detected = Loomi_Schema_Conflict_Detector::detect();
		self::assertIsArray( $detected );
		self::assertFalse( Loomi_Schema_Conflict_Detector::any_active(),
			'any_active() must be false when no SEO plugin is present in the test env' );
	}

	public function test_filter_can_inject_custom_conflict() : void {
		add_filter( 'loomi_schema_conflict_detected', static function ( $list ) {
			$list[] = 'custom_seo_plugin';
			return $list;
		} );
		$detected = Loomi_Schema_Conflict_Detector::detect();
		self::assertContains( 'custom_seo_plugin', $detected );
		self::assertTrue( Loomi_Schema_Conflict_Detector::any_active() );
	}

	public function test_labels_map_contains_all_known_slugs() : void {
		$labels = Loomi_Schema_Conflict_Detector::labels();
		self::assertArrayHasKey( Loomi_Schema_Conflict_Detector::SLUG_YOAST,     $labels );
		self::assertArrayHasKey( Loomi_Schema_Conflict_Detector::SLUG_RANK_MATH, $labels );
		self::assertArrayHasKey( Loomi_Schema_Conflict_Detector::SLUG_AIOSEO,    $labels );
		self::assertArrayHasKey( Loomi_Schema_Conflict_Detector::SLUG_SEOPRESS,  $labels );
	}

	public function test_detected_labels_text_is_empty_when_no_conflict() : void {
		self::assertSame( '', Loomi_Schema_Conflict_Detector::detected_labels_text() );
	}

	public function test_detected_labels_text_joins_with_comma() : void {
		add_filter( 'loomi_schema_conflict_detected', static function () {
			return [ Loomi_Schema_Conflict_Detector::SLUG_YOAST, Loomi_Schema_Conflict_Detector::SLUG_RANK_MATH ];
		} );
		$text = Loomi_Schema_Conflict_Detector::detected_labels_text();
		self::assertStringContainsString( 'Yoast SEO', $text );
		self::assertStringContainsString( 'Rank Math', $text );
		self::assertStringContainsString( ', ', $text );
	}
}
