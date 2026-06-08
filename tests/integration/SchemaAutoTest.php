<?php
/**
 * Tests for Loomi_Schema_Auto orchestrator + the per-type builders
 * (Article, Breadcrumb). Covers conflict gating, per-type toggles, and
 * the filter override hook.
 */

class SchemaAutoTest extends WP_UnitTestCase {

	public function tear_down() : void {
		remove_all_filters( 'loomi_schema_conflict_detected' );
		remove_all_filters( 'loomi_schema_auto_enabled' );
		parent::tear_down();
	}

	private function set_toggle( string $key, bool $value ) : void {
		$opts = get_option( Plugin::OPTION_KEY, [] );
		if ( ! is_array( $opts ) ) { $opts = []; }
		$opts[ $key ] = $value;
		update_option( Plugin::OPTION_KEY, $opts );
		Settings_Repository::clear_cache();
	}

	private function capture_wp_head() : string {
		ob_start();
		do_action( 'wp_head' );
		return (string) ob_get_clean();
	}

	/* -----------------------------------------------------------------
	 * Article builder
	 * --------------------------------------------------------------- */

	public function test_article_builder_returns_expected_shape() : void {
		$author_id = self::factory()->user->create( [ 'display_name' => 'Jane Doe' ] );
		$post_id   = self::factory()->post->create( [
			'post_title'   => 'Hello World',
			'post_content' => 'Body here',
			'post_excerpt' => 'A short excerpt',
			'post_author'  => $author_id,
		] );
		$post = get_post( $post_id );

		$node = Loomi_Schema_Article_Builder::build( $post );

		self::assertSame( 'https://schema.org', $node['@context'] );
		self::assertSame( 'Article', $node['@type'] );
		self::assertSame( 'Hello World', $node['headline'] );
		self::assertSame( 'A short excerpt', $node['description'] );
		self::assertArrayHasKey( 'datePublished', $node );
		self::assertArrayHasKey( 'dateModified', $node );
		self::assertArrayHasKey( 'mainEntityOfPage', $node );
		self::assertArrayHasKey( 'author', $node );
		self::assertSame( 'Jane Doe', $node['author']['name'] );
		self::assertArrayHasKey( 'publisher', $node );
	}

	public function test_article_builder_filter_can_mutate() : void {
		$post_id = self::factory()->post->create();
		add_filter( 'loomi_schema_auto_article', static function ( $node ) {
			$node['custom'] = 'value';
			return $node;
		} );

		$node = Loomi_Schema_Article_Builder::build( get_post( $post_id ) );

		self::assertSame( 'value', $node['custom'] );
	}

	/* -----------------------------------------------------------------
	 * Breadcrumb builder
	 * --------------------------------------------------------------- */

	public function test_breadcrumb_builder_includes_home_and_post() : void {
		$post_id = self::factory()->post->create( [ 'post_title' => 'Single Post' ] );
		$node    = Loomi_Schema_Breadcrumb_Builder::build( get_post( $post_id ) );

		self::assertSame( 'BreadcrumbList', $node['@type'] );
		$items = $node['itemListElement'];
		self::assertCount( 2, $items, 'Top-level post → 2 items (home + self)' );
		self::assertSame( 1, $items[0]['position'] );
		self::assertSame( home_url( '/' ), $items[0]['item'] );
		self::assertSame( 2, $items[1]['position'] );
		self::assertSame( 'Single Post', $items[1]['name'] );
	}

	public function test_breadcrumb_includes_ancestors() : void {
		$parent_id = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Parent' ] );
		$child_id  = self::factory()->post->create( [ 'post_type' => 'page', 'post_title' => 'Child', 'post_parent' => $parent_id ] );

		$node = Loomi_Schema_Breadcrumb_Builder::build( get_post( $child_id ) );

		self::assertCount( 3, $node['itemListElement'], 'home → parent → child' );
		self::assertSame( 'Parent', $node['itemListElement'][1]['name'] );
		self::assertSame( 'Child',  $node['itemListElement'][2]['name'] );
	}

	/* -----------------------------------------------------------------
	 * Auto orchestrator
	 * --------------------------------------------------------------- */

	public function test_auto_disabled_when_conflict_detected() : void {
		add_filter( 'loomi_schema_conflict_detected', static fn() => [ Loomi_Schema_Conflict_Detector::SLUG_YOAST ] );
		$this->set_toggle( 'loomi_schema_auto_article', true );

		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$out = $this->capture_wp_head();

		self::assertStringNotContainsString( '"@type":"Article"', $out,
			'Auto Article must NOT emit when an SEO plugin is detected' );
	}

	public function test_auto_filter_override_forces_on_despite_conflict() : void {
		add_filter( 'loomi_schema_conflict_detected', static fn() => [ Loomi_Schema_Conflict_Detector::SLUG_YOAST ] );
		add_filter( 'loomi_schema_auto_enabled', '__return_true' );
		$this->set_toggle( 'loomi_schema_auto_article', true );

		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$out = $this->capture_wp_head();

		self::assertStringContainsString( '"@type":"Article"', $out,
			'Filter override must allow auto schemas even with conflict' );
	}

	public function test_auto_article_toggle_off_suppresses() : void {
		$this->set_toggle( 'loomi_schema_auto_article', false );

		$post_id = self::factory()->post->create();
		$this->go_to( get_permalink( $post_id ) );

		$out = $this->capture_wp_head();

		self::assertStringNotContainsString( '"@type":"Article"', $out );
	}

	public function test_auto_article_emits_with_default_toggle() : void {
		// Defaults: auto_article=true, no conflict in test env.
		Settings_Repository::clear_cache();

		$post_id = self::factory()->post->create( [ 'post_title' => 'Auto Test' ] );
		$this->go_to( get_permalink( $post_id ) );

		$out = $this->capture_wp_head();

		self::assertStringContainsString( '"@type":"Article"', $out );
		self::assertStringContainsString( 'Auto Test', $out );
	}

	public function test_auto_breadcrumb_emits_on_non_home() : void {
		Settings_Repository::clear_cache();

		$post_id = self::factory()->post->create( [ 'post_title' => 'Some Post' ] );
		$this->go_to( get_permalink( $post_id ) );

		$out = $this->capture_wp_head();

		self::assertStringContainsString( '"@type":"BreadcrumbList"', $out );
	}
}
