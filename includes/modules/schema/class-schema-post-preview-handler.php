<?php
/**
 * AJAX handler for the per-post schema preview in the metabox.
 *
 * Distinct from Loomi_Schema_Preview_Handler (which serves the global Schema
 * tab LocalBusiness preview) so each has a single responsibility.
 *
 * Builds a graph of:
 *   - The manual @type currently selected in the metabox form (unsaved)
 *   - Auto schemas applicable to that post (Article / Breadcrumb / Product),
 *     gated by SEO plugin conflict detection and per-type toggles.
 *
 * Endpoint: wp_ajax_loomi_schema_post_preview
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Schema_Post_Preview_Handler implements Loomi_Module {

	const ACTION = 'loomi_schema_post_preview';

	public static function register() : void {
		add_action( 'wp_ajax_' . self::ACTION, [ __CLASS__, 'handle' ] );
	}

	public static function handle() : void {
		check_admin_referer( self::ACTION );

		$post_id = self::read_post_id();
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden', 'loomi-studio-setup' ) ], 403 );
		}

		$graph = self::build_graph( $post_id );
		wp_send_json_success( [ 'jsonld' => $graph ] );
	}

	private static function read_post_id() : int {
		return isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	}

	private static function read_type() : string {
		$type = isset( $_POST['loomi_schema_type'] )
			? sanitize_key( wp_unslash( $_POST['loomi_schema_type'] ) )
			: Loomi_Schema::TYPE_NONE;
		return array_key_exists( $type, Loomi_Schema::types() ) ? $type : Loomi_Schema::TYPE_NONE;
	}

	private static function read_data() : array {
		if ( ! isset( $_POST['loomi_schema'] ) || ! is_array( $_POST['loomi_schema'] ) ) {
			return [];
		}
		return wp_unslash( $_POST['loomi_schema'] );
	}

	private static function build_graph( int $post_id ) : array {
		$graph  = [];
		$manual = self::build_manual_node();
		if ( ! empty( $manual ) ) {
			$graph[] = $manual;
		}
		foreach ( self::build_auto_nodes( $post_id ) as $node ) {
			$graph[] = $node;
		}
		return $graph;
	}

	private static function build_manual_node() : array {
		$type = self::read_type();
		if ( $type === Loomi_Schema::TYPE_NONE ) {
			return [];
		}
		$sanitized = Loomi_Schema_Sanitizer::sanitize_for_type( $type, self::read_data() );
		if ( is_wp_error( $sanitized ) ) {
			return [ 'error' => $sanitized->get_error_message() ];
		}
		$global = (array) Settings_Repository::get( 'loomi_schema_global', [] );
		return Loomi_Schema_Builder::build_for_type( $type, $sanitized, $global );
	}

	private static function build_auto_nodes( int $post_id ) : array {
		if ( Loomi_Schema_Conflict_Detector::any_active() ) {
			return [];
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return [];
		}
		return array_filter( [
			self::maybe_article( $post ),
			self::maybe_breadcrumb( $post ),
			self::maybe_product( $post ),
		] );
	}

	private static function maybe_article( WP_Post $post ) : array {
		if ( $post->post_type !== 'post' ) {
			return [];
		}
		if ( ! Settings_Repository::get_bool( 'loomi_schema_auto_article' ) ) {
			return [];
		}
		return Loomi_Schema_Article_Builder::build( $post );
	}

	private static function maybe_breadcrumb( WP_Post $post ) : array {
		if ( ! Settings_Repository::get_bool( 'loomi_schema_auto_breadcrumb' ) ) {
			return [];
		}
		return Loomi_Schema_Breadcrumb_Builder::build( $post );
	}

	private static function maybe_product( WP_Post $post ) : array {
		if ( $post->post_type !== 'product' ) {
			return [];
		}
		if ( ! class_exists( 'WooCommerce' ) ) {
			return [];
		}
		if ( ! Settings_Repository::get_bool( 'loomi_schema_auto_product' ) ) {
			return [];
		}
		return Loomi_Schema_Product_Builder::build( [] );
	}
}
