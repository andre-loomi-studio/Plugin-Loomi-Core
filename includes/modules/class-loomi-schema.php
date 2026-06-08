<?php
/**
 * Schema feature facade.
 *
 * Holds public constants and the @type→label map, plus orchestrates the
 * registration of the focused sub-modules (Renderer, Metabox, Preview_Handler).
 * Business logic lives in:
 *   - Loomi_Schema_Builder        — JSON-LD construction
 *   - Loomi_Schema_Sanitizer      — input sanitization
 *   - Loomi_Schema_Renderer       — wp_head output
 *   - Loomi_Schema_Metabox        — per-post UI + save flow
 *   - Loomi_Schema_Preview_Handler— AJAX preview endpoint
 *
 * Backward-compat shims: build_local_business/service/faq_page/custom are
 * preserved as thin delegators to Loomi_Schema_Builder so external test
 * fixtures and filters continue working without changes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Schema implements Loomi_Module {

	const META_TYPE = '_loomi_schema_type';
	const META_DATA = '_loomi_schema_data';
	const NONCE     = 'loomi_schema_metabox';

	const TYPE_NONE           = 'none';
	const TYPE_LOCAL_BUSINESS = 'local_business';
	const TYPE_SERVICE        = 'service';
	const TYPE_FAQ_PAGE       = 'faq_page';
	const TYPE_PRODUCT        = 'product';
	const TYPE_CUSTOM_JSON    = 'custom_json';

	public static function types() : array {
		return [
			self::TYPE_NONE           => __( 'Nenhum',               'loomi-studio-setup' ),
			self::TYPE_LOCAL_BUSINESS => __( 'LocalBusiness',        'loomi-studio-setup' ),
			self::TYPE_SERVICE        => __( 'Service',              'loomi-studio-setup' ),
			self::TYPE_FAQ_PAGE       => __( 'FAQPage',              'loomi-studio-setup' ),
			self::TYPE_PRODUCT        => __( 'Product',              'loomi-studio-setup' ),
			self::TYPE_CUSTOM_JSON    => __( 'JSON-LD customizado',  'loomi-studio-setup' ),
		];
	}

	public static function register() : void {
		Loomi_Schema_Renderer::register();
		Loomi_Schema_Metabox::register();
		Loomi_Schema_Preview_Handler::register();
		Loomi_Schema_Post_Preview_Handler::register();
		Loomi_Schema_Auto::register();
	}

	/* ------------------------------------------------------------------
	 * Backward-compat builder delegators. External tests and filters call
	 * these directly; keep them stable.
	 * ---------------------------------------------------------------- */

	public static function build_local_business( array $global, array $overrides = [] ) : array {
		return Loomi_Schema_Builder::build_local_business( $global, $overrides );
	}

	public static function build_service( array $data, array $global ) : array {
		return Loomi_Schema_Builder::build_service( $data, $global );
	}

	public static function build_faq_page( array $data ) : array {
		return Loomi_Schema_Builder::build_faq_page( $data );
	}

	public static function build_custom( string $json ) : array {
		return Loomi_Schema_Builder::build_custom( $json );
	}

	public static function build_product( array $data ) : array {
		return Loomi_Schema_Product_Builder::build( $data );
	}
}
