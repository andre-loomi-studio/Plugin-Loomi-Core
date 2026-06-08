<?php
/**
 * Architectural invariants for the schema feature.
 *
 * Each test enforces a rule from openspec/config.yaml (code quality / senior
 * reviewer rigor). If a future change violates the rule, the matching test
 * fails — pulling the engineer's attention before bad code ships.
 */

class SchemaArchitectureTest extends WP_UnitTestCase {

	private const SCHEMA_CLASSES = [
		'Loomi_Schema_Builder',
		'Loomi_Schema_Sanitizer',
		'Loomi_Schema_Renderer',
		'Loomi_Schema_Metabox',
		'Loomi_Schema_Preview_Handler',
	];

	private const SCHEMA_CLASS_FILES = [
		'includes/modules/schema/class-schema-builder.php',
		'includes/modules/schema/class-schema-sanitizer.php',
		'includes/modules/schema/class-schema-renderer.php',
		'includes/modules/schema/class-schema-metabox.php',
		'includes/modules/schema/class-schema-preview-handler.php',
	];

	private const VIEW_FILES = [
		'includes/settings/views/schema-metabox.php',
		'includes/settings/views/schema/metabox/view-metabox-type-selector.php',
		'includes/settings/views/schema/metabox/view-metabox-section-local-business.php',
		'includes/settings/views/schema/metabox/view-metabox-section-service.php',
		'includes/settings/views/schema/metabox/view-metabox-section-faq.php',
		'includes/settings/views/schema/metabox/view-metabox-section-product.php',
		'includes/settings/views/schema/metabox/view-metabox-section-custom-json.php',
		'includes/settings/views/schema/metabox/view-metabox-rich-results-test.php',
		'includes/settings/views/schema/view-schema-tab-auto-schemas.php',
	];

	public function test_loomi_schema_is_thin_facade() : void {
		$file  = LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-schema.php';
		$lines = file( $file, FILE_SKIP_EMPTY_LINES );
		self::assertLessThan( 200, count( $lines ), 'Loomi_Schema must remain a thin facade (< 200 LOC). Current: ' . count( $lines ) );

		$reflection = new ReflectionClass( 'Loomi_Schema' );
		$own_public = array_filter(
			$reflection->getMethods( ReflectionMethod::IS_PUBLIC ),
			fn( $m ) => $m->getDeclaringClass()->getName() === 'Loomi_Schema'
		);
		self::assertLessThanOrEqual( 7, count( $own_public ),
			'Loomi_Schema must have ≤ 7 public methods (currently delegators + types() + register()).' );
	}

	public function test_each_schema_class_under_size_limit() : void {
		foreach ( self::SCHEMA_CLASS_FILES as $relative ) {
			$path = LOOMI_STUDIO_DIR . $relative;
			self::assertFileExists( $path, "Missing file: $relative" );
			$lines = file( $path, FILE_SKIP_EMPTY_LINES );
			self::assertLessThan( 260, count( $lines ),
				"$relative exceeds 260 LOC (current: " . count( $lines ) . '). Split it further.' );
		}
	}

	public function test_schema_classes_exist() : void {
		foreach ( self::SCHEMA_CLASSES as $class ) {
			self::assertTrue( class_exists( $class ), "Missing schema class: $class" );
		}
	}

	public function test_no_inline_script_in_schema_views() : void {
		foreach ( self::VIEW_FILES as $relative ) {
			$path = LOOMI_STUDIO_DIR . $relative;
			$content = file_get_contents( $path );
			self::assertSame( 0, preg_match( '/<script[^>]*>/', $content ),
				"Inline <script> found in $relative — extract to assets/" );
		}
	}

	public function test_no_inline_style_block_in_schema_views() : void {
		foreach ( self::VIEW_FILES as $relative ) {
			$path = LOOMI_STUDIO_DIR . $relative;
			$content = file_get_contents( $path );
			self::assertSame( 0, preg_match( '/<style[^>]*>/', $content ),
				"<style> block found in $relative — extract to assets/schema-admin.css" );
		}
	}

	public function test_no_inline_script_in_schema_tab() : void {
		$path = LOOMI_STUDIO_DIR . 'includes/settings/tabs/class-tab-schema.php';
		$content = file_get_contents( $path );
		self::assertSame( 0, preg_match( '/<script[^>]*>/', $content ),
			'Inline <script> found in class-tab-schema.php — extract to assets/schema-tab.js' );
		self::assertSame( 0, preg_match( '/<style[^>]*>/', $content ),
			'<style> block found in class-tab-schema.php — extract to assets/schema-admin.css' );
	}

	public function test_loomi_schema_register_delegates_to_submodules() : void {
		// Calling register() must wire each sub-module's hook.
		Loomi_Schema::register();

		self::assertNotFalse( has_action( 'wp_head', [ 'Loomi_Schema_Renderer', 'output' ] ),
			'Schema_Renderer must hook wp_head' );
		self::assertNotFalse( has_action( 'add_meta_boxes', [ 'Loomi_Schema_Metabox', 'add_metabox' ] ),
			'Schema_Metabox must hook add_meta_boxes' );
		self::assertNotFalse( has_action( 'wp_ajax_loomi_schema_preview', [ 'Loomi_Schema_Preview_Handler', 'handle' ] ),
			'Schema_Preview_Handler must hook wp_ajax_loomi_schema_preview' );
	}
}
