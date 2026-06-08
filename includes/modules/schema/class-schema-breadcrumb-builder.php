<?php
/**
 * Builds Schema.org BreadcrumbList JSON-LD from a WP_Post.
 *
 * Single responsibility: traverse post ancestry → ListItem entries. Position 1
 * is always the home URL; final entry is the post itself.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Schema_Breadcrumb_Builder {

	public static function build( WP_Post $post ) : array {
		$items = self::build_items( $post );
		$node  = [
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		];
		return (array) apply_filters( 'loomi_schema_auto_breadcrumb', $node, $post );
	}

	private static function build_items( WP_Post $post ) : array {
		$items   = [ self::home_item() ];
		$position = 2;

		foreach ( self::ancestors_oldest_first( $post ) as $ancestor_id ) {
			$items[] = self::list_item( $position++, $ancestor_id );
		}
		$items[] = self::list_item( $position, $post->ID );
		return $items;
	}

	private static function home_item() : array {
		return [
			'@type'    => 'ListItem',
			'position' => 1,
			'name'     => __( 'Início', 'loomi-studio-setup' ),
			'item'     => home_url( '/' ),
		];
	}

	private static function ancestors_oldest_first( WP_Post $post ) : array {
		return array_reverse( (array) get_post_ancestors( $post ) );
	}

	private static function list_item( int $position, int $post_id ) : array {
		return [
			'@type'    => 'ListItem',
			'position' => $position,
			'name'     => get_the_title( $post_id ),
			'item'     => get_permalink( $post_id ),
		];
	}
}
