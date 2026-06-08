<?php
/**
 * Tests for Loomi_Schema_Renderer + Loomi_Schema_Auto wp_head emission:
 * hook priorities, is_singular gating, and JSON-LD script wrapping.
 */

class SchemaOutputTest extends WP_UnitTestCase {

	public function set_up() : void {
		parent::set_up();
		// Ensure handlers are registered before asserting on hooks.
		Loomi_Schema::register();
	}

	public function tear_down() : void {
		remove_all_filters( 'loomi_schema_enabled' );
		remove_all_filters( 'loomi_schema_auto_enabled' );
		remove_all_filters( 'loomi_schema_conflict_detected' );
		parent::tear_down();
	}

	public function test_renderer_hooks_wp_head_at_priority_99() : void {
		$priority = has_action( 'wp_head', [ 'Loomi_Schema_Renderer', 'output' ] );
		self::assertSame( 99, $priority,
			'Loomi_Schema_Renderer::output must be at wp_head priority 99 (just before Schema_Auto at 100).' );
	}

	public function test_auto_hooks_wp_head_at_priority_100() : void {
		$priority = has_action( 'wp_head', [ 'Loomi_Schema_Auto', 'output' ] );
		self::assertSame( 100, $priority,
			'Loomi_Schema_Auto::output must be at wp_head priority 100.' );
	}

	public function test_renderer_emits_nothing_when_not_singular() : void {
		// home query (not singular).
		$this->go_to( home_url( '/' ) );

		ob_start();
		Loomi_Schema_Renderer::output();
		$out = (string) ob_get_clean();

		self::assertSame( '', $out, 'Renderer must bail when !is_singular()' );
	}

	public function test_renderer_emits_nothing_when_type_is_none() : void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, Loomi_Schema::META_TYPE, Loomi_Schema::TYPE_NONE );
		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		Loomi_Schema_Renderer::output();
		$out = (string) ob_get_clean();

		self::assertSame( '', $out, 'Renderer must bail when stored type is none' );
	}

	public function test_renderer_emits_script_tag_for_valid_post_with_type() : void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, Loomi_Schema::META_TYPE, Loomi_Schema::TYPE_FAQ_PAGE );
		update_post_meta( $post_id, Loomi_Schema::META_DATA, [
			'faq' => [ [ 'question' => 'Q?', 'answer' => 'A.' ] ],
		] );
		$this->go_to( get_permalink( $post_id ) );

		ob_start();
		Loomi_Schema_Renderer::output();
		$out = (string) ob_get_clean();

		self::assertStringContainsString( '<script type="application/ld+json">', $out );
		self::assertStringContainsString( '"@type":"FAQPage"', $out );
		self::assertStringContainsString( '</script>', $out );
	}

	public function test_filter_can_disable_renderer_globally() : void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, Loomi_Schema::META_TYPE, Loomi_Schema::TYPE_FAQ_PAGE );
		update_post_meta( $post_id, Loomi_Schema::META_DATA, [ 'faq' => [ [ 'question' => 'Q', 'answer' => 'A' ] ] ] );
		$this->go_to( get_permalink( $post_id ) );
		add_filter( 'loomi_schema_enabled', '__return_false' );

		ob_start();
		Loomi_Schema_Renderer::output();
		$out = (string) ob_get_clean();

		self::assertSame( '', $out );
	}
}
