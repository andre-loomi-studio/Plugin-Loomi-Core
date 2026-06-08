<?php
/**
 * Builds Schema.org Article JSON-LD from a WP_Post.
 *
 * Single responsibility: WP_Post → Article node. Pure function — no hooks, no
 * I/O beyond reading post fields. Caller decides WHEN to emit.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Schema_Article_Builder {

	public static function build( WP_Post $post ) : array {
		$node = self::base_fields( $post );
		self::attach_optional( $node, $post );
		return (array) apply_filters( 'loomi_schema_auto_article', $node, $post );
	}

	private static function base_fields( WP_Post $post ) : array {
		return [
			'@context'         => 'https://schema.org',
			'@type'            => 'Article',
			'headline'         => get_the_title( $post ),
			'mainEntityOfPage' => [
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post ),
			],
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
		];
	}

	private static function attach_optional( array &$node, WP_Post $post ) : void {
		self::attach_description( $node, $post );
		self::attach_image( $node, $post );
		self::attach_author( $node, $post );
		self::attach_publisher( $node );
	}

	private static function attach_description( array &$node, WP_Post $post ) : void {
		$excerpt = wp_strip_all_tags( (string) get_the_excerpt( $post ) );
		if ( $excerpt !== '' ) {
			$node['description'] = $excerpt;
		}
	}

	private static function attach_image( array &$node, WP_Post $post ) : void {
		$thumb_id = (int) get_post_thumbnail_id( $post );
		if ( ! $thumb_id ) {
			return;
		}
		$url = wp_get_attachment_url( $thumb_id );
		if ( $url ) {
			$node['image'] = $url;
		}
	}

	private static function attach_author( array &$node, WP_Post $post ) : void {
		$author_id = (int) $post->post_author;
		if ( ! $author_id ) {
			return;
		}
		$node['author'] = [
			'@type' => 'Person',
			'name'  => get_the_author_meta( 'display_name', $author_id ),
		];
	}

	private static function attach_publisher( array &$node ) : void {
		$site_name = get_bloginfo( 'name' );
		if ( $site_name === '' ) {
			return;
		}
		$node['publisher'] = [
			'@type' => 'Organization',
			'name'  => $site_name,
		];
	}
}
