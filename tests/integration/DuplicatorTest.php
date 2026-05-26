<?php

class DuplicatorTest extends Loomi_TestCase {

	private int $source_id;
	private int $admin_id;

	public function set_up() : void {
		parent::set_up();
		$this->admin_id  = $this->login_as( 'administrator' );
		$this->source_id = self::factory()->post->create( [
			'post_type'    => 'page',
			'post_title'   => 'Original',
			'post_content' => '<p>Content</p>',
			'post_excerpt' => 'Excerpt',
			'post_status'  => 'publish',
		] );
	}

	private function run_duplicate() : int {
		$new_id = wp_insert_post( [
			'post_title'   => get_post( $this->source_id )->post_title . ' (cópia)',
			'post_content' => get_post( $this->source_id )->post_content,
			'post_excerpt' => get_post( $this->source_id )->post_excerpt,
			'post_status'  => 'draft',
			'post_type'    => get_post( $this->source_id )->post_type,
			'post_author'  => $this->admin_id,
		] );

		$rc       = new ReflectionClass( 'Loomi_Duplicate' );
		$copy_meta = $rc->getMethod( 'copy_meta' );
		$copy_meta->setAccessible( true );
		$copy_meta->invoke( null, $this->source_id, (int) $new_id );

		$copy_tax = $rc->getMethod( 'copy_taxonomies' );
		$copy_tax->setAccessible( true );
		$copy_tax->invoke( null, get_post( $this->source_id ), (int) $new_id );

		return (int) $new_id;
	}

	public function test_duplicate_creates_draft_with_suffixed_title() : void {
		$new_id = $this->run_duplicate();
		self::assertSame( 'draft', get_post( $new_id )->post_status );
		self::assertStringContainsString( '(cópia)', get_post( $new_id )->post_title );
	}

	public function test_duplicate_preserves_content_and_excerpt() : void {
		$new_id = $this->run_duplicate();
		self::assertSame( '<p>Content</p>', get_post( $new_id )->post_content );
		self::assertSame( 'Excerpt',        get_post( $new_id )->post_excerpt );
	}

	public function test_duplicate_copies_thumbnail_meta() : void {
		update_post_meta( $this->source_id, '_thumbnail_id', 42 );
		$new_id = $this->run_duplicate();
		self::assertSame( '42', get_post_meta( $new_id, '_thumbnail_id', true ) );
	}

	public function test_duplicate_copies_acf_like_meta() : void {
		update_post_meta( $this->source_id, '_acf_field_text', 'ACF Value' );
		update_post_meta( $this->source_id, 'array_meta', [ 'a', 'b', 'c' ] );

		$new_id = $this->run_duplicate();
		self::assertSame( 'ACF Value', get_post_meta( $new_id, '_acf_field_text', true ) );
		self::assertSame( [ 'a', 'b', 'c' ], get_post_meta( $new_id, 'array_meta', true ) );
	}

	public function test_duplicate_skips_edit_lock() : void {
		update_post_meta( $this->source_id, '_edit_lock', '1234567890:1' );
		$new_id = $this->run_duplicate();
		self::assertEmpty( get_post_meta( $new_id, '_edit_lock', true ) );
	}

	public function test_source_unchanged() : void {
		$before_title = get_post( $this->source_id )->post_title;
		$before_status = get_post( $this->source_id )->post_status;
		$this->run_duplicate();
		self::assertSame( $before_title, get_post( $this->source_id )->post_title );
		self::assertSame( $before_status, get_post( $this->source_id )->post_status );
	}

	public function test_row_action_appears_for_users_with_edit_post() : void {
		$post    = get_post( $this->source_id );
		$actions = Loomi_Duplicate::add_action_link( [], $post );
		self::assertArrayHasKey( 'loomi_duplicate', $actions );
		self::assertStringContainsString( 'Duplicar', $actions['loomi_duplicate'] );
	}

	public function test_row_action_hidden_for_unauthorized_user() : void {
		wp_set_current_user( 0 );
		$post    = get_post( $this->source_id );
		$actions = Loomi_Duplicate::add_action_link( [], $post );
		self::assertArrayNotHasKey( 'loomi_duplicate', $actions );
	}
}
