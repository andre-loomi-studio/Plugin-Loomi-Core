<?php
/**
 * Detects active SEO plugins that emit their own Schema.org JSON-LD so the
 * Loomi auto-schemas can self-disable and avoid duplicate markup (which
 * Google Rich Results flags as an error).
 *
 * Static helper. No hooks of its own. Filter `loomi_schema_conflict_detected`
 * lets other plugins extend the list.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Schema_Conflict_Detector {

	const SLUG_YOAST     = 'yoast';
	const SLUG_RANK_MATH = 'rank_math';
	const SLUG_AIOSEO    = 'aioseo';
	const SLUG_SEOPRESS  = 'seopress';

	/** @return string[] */
	public static function detect() : array {
		$found = array_filter( [
			self::SLUG_YOAST     => self::is_yoast_active(),
			self::SLUG_RANK_MATH => self::is_rank_math_active(),
			self::SLUG_AIOSEO    => self::is_aioseo_active(),
			self::SLUG_SEOPRESS  => self::is_seopress_active(),
		] );
		return (array) apply_filters( 'loomi_schema_conflict_detected', array_keys( $found ) );
	}

	public static function any_active() : bool {
		return ! empty( self::detect() );
	}

	private static function is_yoast_active() : bool {
		return defined( 'WPSEO_VERSION' ) || function_exists( 'wpseo_init' );
	}

	private static function is_rank_math_active() : bool {
		return class_exists( 'RankMath' );
	}

	private static function is_aioseo_active() : bool {
		return function_exists( 'aioseo' );
	}

	private static function is_seopress_active() : bool {
		return function_exists( 'seopress_init' );
	}

	/** @return array<string,string> */
	public static function labels() : array {
		return [
			self::SLUG_YOAST     => __( 'Yoast SEO', 'loomi-studio-setup' ),
			self::SLUG_RANK_MATH => __( 'Rank Math', 'loomi-studio-setup' ),
			self::SLUG_AIOSEO    => __( 'All in One SEO', 'loomi-studio-setup' ),
			self::SLUG_SEOPRESS  => __( 'SEOPress', 'loomi-studio-setup' ),
		];
	}

	public static function detected_labels_text() : string {
		$labels = self::labels();
		$names  = array_map(
			static fn( $slug ) => $labels[ $slug ] ?? $slug,
			self::detect()
		);
		return implode( ', ', $names );
	}
}
