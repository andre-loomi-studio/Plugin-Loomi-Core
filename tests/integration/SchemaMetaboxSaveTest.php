<?php
/**
 * Tests for Loomi_Schema_Metabox::save_metabox — round-trip save flow
 * per @type, including error transient storage and TYPE_NONE meta deletion.
 */

class SchemaMetaboxSaveTest extends WP_UnitTestCase {

	private int $admin_id;
	private int $post_id;

	public function set_up() : void {
		parent::set_up();
		$this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_id );
		$this->post_id = self::factory()->post->create( [ 'post_author' => $this->admin_id ] );
	}

	public function tear_down() : void {
		unset( $_POST[ Loomi_Schema::NONCE ], $_POST['loomi_schema_type'], $_POST['loomi_schema'] );
		parent::tear_down();
	}

	private function set_save_post_data( string $type, array $data ) : void {
		$_POST[ Loomi_Schema::NONCE ]      = wp_create_nonce( Loomi_Schema::NONCE );
		$_POST['loomi_schema_type']        = $type;
		$_POST['loomi_schema']             = $data;
	}

	public function test_save_local_business_persists_meta() : void {
		$this->set_save_post_data( Loomi_Schema::TYPE_LOCAL_BUSINESS, [
			'name'            => 'Acme',
			'addressLocality' => 'São Paulo',
		] );

		Loomi_Schema_Metabox::save_metabox( $this->post_id, get_post( $this->post_id ) );

		self::assertSame( Loomi_Schema::TYPE_LOCAL_BUSINESS,
			get_post_meta( $this->post_id, Loomi_Schema::META_TYPE, true ) );
		$data = get_post_meta( $this->post_id, Loomi_Schema::META_DATA, true );
		self::assertSame( 'Acme', $data['name'] );
		self::assertSame( 'São Paulo', $data['addressLocality'] );
	}

	public function test_save_type_none_deletes_metas() : void {
		// Pre-populate with valid data so we can confirm deletion.
		update_post_meta( $this->post_id, Loomi_Schema::META_TYPE, Loomi_Schema::TYPE_FAQ_PAGE );
		update_post_meta( $this->post_id, Loomi_Schema::META_DATA, [ 'faq' => [ [ 'question' => 'Q', 'answer' => 'A' ] ] ] );

		$this->set_save_post_data( Loomi_Schema::TYPE_NONE, [] );
		Loomi_Schema_Metabox::save_metabox( $this->post_id, get_post( $this->post_id ) );

		self::assertSame( '', get_post_meta( $this->post_id, Loomi_Schema::META_TYPE, true ) );
		self::assertSame( '', get_post_meta( $this->post_id, Loomi_Schema::META_DATA, true ) );
	}

	public function test_save_invalid_data_stores_error_transient() : void {
		// LocalBusiness latitude out of range.
		$this->set_save_post_data( Loomi_Schema::TYPE_LOCAL_BUSINESS, [
			'latitude' => '999',
		] );

		Loomi_Schema_Metabox::save_metabox( $this->post_id, get_post( $this->post_id ) );

		$errors = get_transient( 'loomi_schema_errors_' . $this->post_id );
		self::assertIsArray( $errors );
		self::assertNotEmpty( $errors );
		// No persistence happened.
		self::assertSame( '', get_post_meta( $this->post_id, Loomi_Schema::META_TYPE, true ) );
	}

	public function test_save_skipped_without_nonce() : void {
		$_POST['loomi_schema_type'] = Loomi_Schema::TYPE_FAQ_PAGE;
		$_POST['loomi_schema']      = [ 'faq' => [ [ 'question' => 'Q', 'answer' => 'A' ] ] ];
		// Note: no nonce set.

		Loomi_Schema_Metabox::save_metabox( $this->post_id, get_post( $this->post_id ) );

		self::assertSame( '', get_post_meta( $this->post_id, Loomi_Schema::META_TYPE, true ),
			'Save must short-circuit without nonce — no meta written' );
	}

	public function test_save_faq_round_trip() : void {
		$this->set_save_post_data( Loomi_Schema::TYPE_FAQ_PAGE, [
			'faq' => [
				[ 'question' => 'Qual o prazo?', 'answer' => '15 dias.' ],
			],
		] );

		Loomi_Schema_Metabox::save_metabox( $this->post_id, get_post( $this->post_id ) );

		$type = get_post_meta( $this->post_id, Loomi_Schema::META_TYPE, true );
		$data = get_post_meta( $this->post_id, Loomi_Schema::META_DATA, true );
		self::assertSame( Loomi_Schema::TYPE_FAQ_PAGE, $type );
		self::assertCount( 1, $data['faq'] );
		self::assertSame( 'Qual o prazo?', $data['faq'][0]['question'] );
	}
}
