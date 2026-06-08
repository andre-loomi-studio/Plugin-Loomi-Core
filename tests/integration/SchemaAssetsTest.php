<?php
/**
 * Assets contract for the schema feature: file existence + enqueue timing.
 *
 * Enforces that JS/CSS files exist on disk and are enqueued on the right
 * screens (post edit + plugin settings page) and NOT on unrelated admin pages.
 */

class SchemaAssetsTest extends WP_UnitTestCase {

	public function test_schema_metabox_js_file_exists() : void {
		self::assertFileExists( LOOMI_STUDIO_DIR . 'assets/schema-metabox.js' );
	}

	public function test_schema_tab_js_file_exists() : void {
		self::assertFileExists( LOOMI_STUDIO_DIR . 'assets/schema-tab.js' );
	}

	public function test_schema_admin_css_file_exists() : void {
		self::assertFileExists( LOOMI_STUDIO_DIR . 'assets/schema-admin.css' );
	}

	public function test_metabox_js_enqueued_on_post_edit() : void {
		Loomi_Schema_Metabox::enqueue_assets( 'post.php' );
		self::assertTrue( wp_script_is( 'loomi-schema-metabox', 'enqueued' ),
			'schema-metabox.js must be enqueued on post.php' );
		self::assertTrue( wp_style_is( 'loomi-schema-admin', 'enqueued' ),
			'schema-admin.css must be enqueued on post.php' );

		wp_dequeue_script( 'loomi-schema-metabox' );
		wp_dequeue_style( 'loomi-schema-admin' );
	}

	public function test_metabox_js_enqueued_on_post_new() : void {
		Loomi_Schema_Metabox::enqueue_assets( 'post-new.php' );
		self::assertTrue( wp_script_is( 'loomi-schema-metabox', 'enqueued' ),
			'schema-metabox.js must be enqueued on post-new.php' );

		wp_dequeue_script( 'loomi-schema-metabox' );
		wp_dequeue_style( 'loomi-schema-admin' );
	}

	public function test_metabox_js_not_enqueued_on_dashboard() : void {
		Loomi_Schema_Metabox::enqueue_assets( 'index.php' );
		self::assertFalse( wp_script_is( 'loomi-schema-metabox', 'enqueued' ),
			'schema-metabox.js must NOT enqueue on the dashboard (index.php)' );
		self::assertFalse( wp_style_is( 'loomi-schema-admin', 'enqueued' ),
			'schema-admin.css must NOT enqueue on the dashboard either' );
	}
}
