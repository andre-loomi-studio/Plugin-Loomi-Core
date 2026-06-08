<?php
/**
 * Emits the per-post JSON-LD <script> on the public frontend via wp_head.
 * Single responsibility: read post meta + global settings → delegate build to
 * Loomi_Schema_Builder → echo the <script> block. No business logic in here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Schema_Renderer implements Loomi_Module {

	const HOOK_PRIORITY = 99;

	public static function register() : void {
		add_action( 'wp_head', [ __CLASS__, 'output' ], self::HOOK_PRIORITY );
	}

	public static function output() : void {
		if ( ! self::should_emit() ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		$type    = (string) get_post_meta( $post_id, Loomi_Schema::META_TYPE, true );
		$data    = (array) get_post_meta( $post_id, Loomi_Schema::META_DATA, true );
		$global  = (array) Settings_Repository::get( 'loomi_schema_global', [] );

		$schema = Loomi_Schema_Builder::build_for_type( $type, $data, $global );
		if ( empty( $schema ) ) {
			return;
		}

		echo "\n<script type=\"application/ld+json\">"
			. wp_json_encode( $schema, JSON_UNESCAPED_UNICODE )
			. "</script>\n";
	}

	private static function should_emit() : bool {
		if ( ! apply_filters( 'loomi_schema_enabled', true ) ) {
			return false;
		}
		if ( ! is_singular() ) {
			return false;
		}
		$post_id = (int) get_queried_object_id();
		if ( ! $post_id ) {
			return false;
		}
		$type = (string) get_post_meta( $post_id, Loomi_Schema::META_TYPE, true );
		return $type !== '' && $type !== Loomi_Schema::TYPE_NONE;
	}
}
