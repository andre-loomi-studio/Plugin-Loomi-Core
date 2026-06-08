<?php
/**
 * Orchestrator for auto-generated schemas (Article, BreadcrumbList, Product).
 *
 * Self-disables when a conflicting SEO plugin is detected. Per-type toggles
 * gate individual emissions when no conflict is present.
 *
 * Hooks wp_head at priority 100 (after Loomi_Schema_Renderer at 99) so any
 * manual per-post schema comes first in the head.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Schema_Auto implements Loomi_Module {

	const HOOK_PRIORITY = 100;

	public static function register() : void {
		add_action( 'wp_head', [ __CLASS__, 'output' ], self::HOOK_PRIORITY );
	}

	public static function output() : void {
		// LocalBusiness na homepage é independente do conflict detector: Rank Math
		// Free não cobre LocalBusiness completo, então emitimos sempre que o toggle
		// estiver ON e for a home — antes do gate de is_enabled().
		if ( self::should_emit_local_business_home() ) {
			$lb = self::collect_local_business_home();
			if ( $lb ) {
				self::emit_node( $lb );
			}
		}
		if ( ! self::is_enabled() ) {
			return;
		}
		foreach ( self::collect_nodes() as $node ) {
			self::emit_node( $node );
		}
	}

	private static function should_emit_local_business_home() : bool {
		if ( ! Settings_Repository::get_bool( 'loomi_schema_localbusiness_home' ) ) {
			return false;
		}
		return function_exists( 'is_front_page' ) && is_front_page();
	}

	private static function collect_local_business_home() : ?array {
		$global = (array) Settings_Repository::get( 'loomi_schema_global', [] );
		$node   = Loomi_Schema_Builder::build_local_business( $global );
		return $node ? $node : null;
	}

	private static function is_enabled() : bool {
		$default = ! Loomi_Schema_Conflict_Detector::any_active();
		return (bool) apply_filters( 'loomi_schema_auto_enabled', $default );
	}

	/** @return array[] */
	private static function collect_nodes() : array {
		$nodes = [];
		$post  = self::current_post();
		if ( ! $post ) {
			return $nodes;
		}

		if ( self::should_emit_article( $post ) ) {
			$nodes[] = Loomi_Schema_Article_Builder::build( $post );
		}
		if ( self::should_emit_breadcrumb( $post ) ) {
			$nodes[] = Loomi_Schema_Breadcrumb_Builder::build( $post );
		}
		if ( self::should_emit_product( $post ) ) {
			$nodes[] = Loomi_Schema_Product_Builder::build( [] );
		}
		return array_filter( $nodes );
	}

	private static function current_post() : ?WP_Post {
		if ( ! function_exists( 'is_singular' ) || ! is_singular() ) {
			return null;
		}
		$post = get_post( get_queried_object_id() );
		return $post instanceof WP_Post ? $post : null;
	}

	private static function should_emit_article( WP_Post $post ) : bool {
		if ( ! Settings_Repository::get_bool( 'loomi_schema_auto_article' ) ) {
			return false;
		}
		return $post->post_type === 'post';
	}

	private static function should_emit_breadcrumb( WP_Post $post ) : bool {
		if ( ! Settings_Repository::get_bool( 'loomi_schema_auto_breadcrumb' ) ) {
			return false;
		}
		return ! ( function_exists( 'is_front_page' ) && is_front_page() );
	}

	private static function should_emit_product( WP_Post $post ) : bool {
		if ( ! Settings_Repository::get_bool( 'loomi_schema_auto_product' ) ) {
			return false;
		}
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}
		return $post->post_type === 'product';
	}

	private static function emit_node( array $node ) : void {
		echo "\n<script type=\"application/ld+json\">"
			. wp_json_encode( $node, JSON_UNESCAPED_UNICODE )
			. "</script>\n";
	}
}
