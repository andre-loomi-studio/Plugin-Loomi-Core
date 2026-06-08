<?php
/**
 * Schema per-post metabox: registration, render (via partial includes),
 * save flow (delegates sanitization to Loomi_Schema_Sanitizer), and assets
 * enqueueing for the post/page edit screens.
 *
 * No business logic — orchestration only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Schema_Metabox implements Loomi_Module {

	const ERROR_TRANSIENT_PREFIX = 'loomi_schema_errors_';
	const ERROR_TRANSIENT_TTL    = MINUTE_IN_SECONDS;

	public static function register() : void {
		add_action( 'add_meta_boxes',        [ __CLASS__, 'add_metabox' ] );
		add_action( 'save_post',             [ __CLASS__, 'save_metabox' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_notices',         [ __CLASS__, 'render_save_errors' ] );
	}

	public static function add_metabox() : void {
		foreach ( [ 'post', 'page' ] as $screen ) {
			add_meta_box(
				'loomi-schema',
				__( 'Schema desta página', 'loomi-studio-setup' ),
				[ __CLASS__, 'render_metabox' ],
				$screen,
				'normal',
				'default'
			);
		}
	}

	public static function render_metabox( WP_Post $post ) : void {
		wp_nonce_field( Loomi_Schema::NONCE, Loomi_Schema::NONCE );

		$type      = self::resolve_type( $post->ID );
		$data      = (array) get_post_meta( $post->ID, Loomi_Schema::META_DATA, true );
		$global    = (array) Settings_Repository::get( 'loomi_schema_global', [] );
		$permalink = ( get_post_status( $post ) !== 'auto-draft' ) ? get_permalink( $post ) : '';

		include LOOMI_STUDIO_DIR . 'includes/settings/views/schema-metabox.php';
	}

	private static function resolve_type( int $post_id ) : string {
		$type = (string) get_post_meta( $post_id, Loomi_Schema::META_TYPE, true );
		return $type === '' ? Loomi_Schema::TYPE_NONE : $type;
	}

	public static function save_metabox( int $post_id, WP_Post $post ) : void {
		if ( ! self::should_process_save( $post_id ) ) {
			return;
		}

		$type = self::read_posted_type();
		if ( $type === Loomi_Schema::TYPE_NONE ) {
			delete_post_meta( $post_id, Loomi_Schema::META_TYPE );
			delete_post_meta( $post_id, Loomi_Schema::META_DATA );
			return;
		}

		$raw_data  = self::read_posted_data();
		$sanitized = Loomi_Schema_Sanitizer::sanitize_for_type( $type, $raw_data );

		if ( is_wp_error( $sanitized ) ) {
			self::store_errors( $post_id, $sanitized->get_error_messages() );
			return;
		}

		update_post_meta( $post_id, Loomi_Schema::META_TYPE, $type );
		update_post_meta( $post_id, Loomi_Schema::META_DATA, $sanitized );
	}

	private static function should_process_save( int $post_id ) : bool {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}
		if ( ! isset( $_POST[ Loomi_Schema::NONCE ] ) ) {
			return false;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ Loomi_Schema::NONCE ] ) );
		if ( ! wp_verify_nonce( $nonce, Loomi_Schema::NONCE ) ) {
			return false;
		}
		return current_user_can( 'edit_post', $post_id );
	}

	private static function read_posted_type() : string {
		$raw = isset( $_POST['loomi_schema_type'] )
			? sanitize_key( wp_unslash( $_POST['loomi_schema_type'] ) )
			: Loomi_Schema::TYPE_NONE;
		return array_key_exists( $raw, Loomi_Schema::types() ) ? $raw : Loomi_Schema::TYPE_NONE;
	}

	private static function read_posted_data() : array {
		if ( ! isset( $_POST['loomi_schema'] ) || ! is_array( $_POST['loomi_schema'] ) ) {
			return [];
		}
		return wp_unslash( $_POST['loomi_schema'] );
	}

	private static function store_errors( int $post_id, array $messages ) : void {
		set_transient( self::ERROR_TRANSIENT_PREFIX . $post_id, $messages, self::ERROR_TRANSIENT_TTL );
	}

	public static function render_save_errors() : void {
		global $post;
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$key    = self::ERROR_TRANSIENT_PREFIX . $post->ID;
		$errors = get_transient( $key );
		if ( ! $errors || ! is_array( $errors ) ) {
			return;
		}
		delete_transient( $key );
		foreach ( $errors as $msg ) {
			printf(
				'<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
				esc_html__( 'Schema', 'loomi-studio-setup' ),
				esc_html( $msg )
			);
		}
	}

	public static function enqueue_assets( string $hook ) : void {
		if ( ! self::is_post_edit_screen( $hook ) ) {
			return;
		}
		wp_enqueue_style(
			'loomi-schema-admin',
			LOOMI_STUDIO_URL . 'assets/schema-admin.css',
			[],
			Plugin::version()
		);
		wp_enqueue_script(
			'loomi-schema-metabox',
			LOOMI_STUDIO_URL . 'assets/schema-metabox.js',
			[],
			Plugin::version(),
			true
		);
		wp_localize_script( 'loomi-schema-metabox', 'LoomiSchemaMetabox', [
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'previewNonce'  => wp_create_nonce( Loomi_Schema_Post_Preview_Handler::ACTION ),
			'postId'        => self::current_post_id(),
			'i18n'          => [
				'loading' => __( 'Carregando…', 'loomi-studio-setup' ),
			],
		] );
	}

	private static function current_post_id() : int {
		if ( isset( $_GET['post'] ) ) {
			return (int) $_GET['post'];
		}
		return isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post ? (int) $GLOBALS['post']->ID : 0;
	}

	private static function is_post_edit_screen( string $hook ) : bool {
		return in_array( $hook, [ 'post.php', 'post-new.php' ], true );
	}
}
