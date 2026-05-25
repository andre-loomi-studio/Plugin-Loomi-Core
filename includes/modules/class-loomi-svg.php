<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_SVG {

	const ALLOWED_TAGS = [
		'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
		'text', 'tspan', 'defs', 'use', 'title', 'desc', 'linearGradient',
		'radialGradient', 'stop', 'mask', 'clipPath', 'pattern', 'filter', 'feGaussianBlur',
		'feOffset', 'feMerge', 'feMergeNode', 'feColorMatrix', 'symbol', 'marker',
	];

	const ALLOWED_ATTRS = [
		'd', 'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-linecap',
		'stroke-linejoin', 'stroke-dasharray', 'stroke-opacity', 'stroke-miterlimit',
		'transform', 'viewBox', 'width', 'height', 'x', 'y', 'cx', 'cy', 'r', 'rx', 'ry',
		'x1', 'y1', 'x2', 'y2', 'points', 'opacity', 'class', 'id', 'style', 'offset',
		'stop-color', 'stop-opacity', 'gradientUnits', 'gradientTransform', 'spreadMethod',
		'patternUnits', 'patternTransform', 'clip-path', 'clip-rule', 'mask', 'filter',
		'preserveAspectRatio', 'xmlns', 'version', 'font-family', 'font-size', 'font-weight',
		'text-anchor', 'dominant-baseline', 'in', 'in2', 'result', 'stdDeviation', 'mode',
		'values', 'type', 'orient', 'markerWidth', 'markerHeight', 'refX', 'refY',
	];

	public static function init() : void {
		add_filter( 'upload_mimes', [ __CLASS__, 'allow_svg_mime' ], 99 );
		add_filter( 'wp_check_filetype_and_ext', [ __CLASS__, 'check_filetype' ], 10, 4 );
		add_filter( 'wp_handle_upload_prefilter', [ __CLASS__, 'sanitize_on_upload' ] );
		add_filter( 'wp_prepare_attachment_for_js', [ __CLASS__, 'fix_preview' ], 10, 3 );
	}

	public static function allow_svg_mime( $mimes ) {
		if ( ! is_array( $mimes ) ) {
			$mimes = [];
		}
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';
		return $mimes;
	}

	public static function check_filetype( $data, $file, $filename, $mimes ) {
		if ( ! empty( $data['ext'] ) && ! empty( $data['type'] ) ) {
			return $data;
		}

		$wp_filetype = wp_check_filetype( $filename, $mimes );
		$ext         = $wp_filetype['ext'] ?? '';
		$type        = $wp_filetype['type'] ?? '';

		if ( in_array( strtolower( $ext ), [ 'svg', 'svgz' ], true ) ) {
			$data['ext']             = $ext;
			$data['type']            = 'image/svg+xml';
			$data['proper_filename'] = $filename;
		}

		return $data;
	}

	public static function sanitize_on_upload( $file ) {
		if ( empty( $file['tmp_name'] ) || empty( $file['name'] ) ) {
			return $file;
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( $ext !== 'svg' ) {
			return $file;
		}

		$contents = @file_get_contents( $file['tmp_name'] );
		if ( $contents === false || $contents === '' ) {
			$file['error'] = __( 'Não foi possível ler o arquivo SVG.', 'loomi-studio-setup' );
			return $file;
		}

		$sanitized = self::sanitize( $contents );
		if ( $sanitized === null ) {
			$file['error'] = __( 'Arquivo SVG inválido ou malformado.', 'loomi-studio-setup' );
			return $file;
		}

		$written = @file_put_contents( $file['tmp_name'], $sanitized );
		if ( $written === false ) {
			$file['error'] = __( 'Não foi possível salvar o SVG sanitizado.', 'loomi-studio-setup' );
		}

		return $file;
	}

	public static function sanitize( string $svg ) : ?string {
		$svg = preg_replace( '/<\?xml-stylesheet[^>]*>/i', '', $svg );

		libxml_use_internal_errors( true );
		if ( PHP_VERSION_ID < 80000 && function_exists( 'libxml_disable_entity_loader' ) ) {
			libxml_disable_entity_loader( true );
		}

		$dom                     = new DOMDocument();
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput       = false;

		// NOTE: explicitly NO LIBXML_NOENT — keeps entity substitution off (XXE / billion-laughs safe).
		$loaded = $dom->loadXML( $svg, LIBXML_NONET );
		libxml_clear_errors();

		if ( ! $loaded ) {
			return null;
		}

		// Remove DOCTYPE node entirely (defense vs internal-subset entity declarations).
		foreach ( iterator_to_array( $dom->childNodes ) as $child ) {
			if ( $child->nodeType === XML_DOCUMENT_TYPE_NODE ) {
				$dom->removeChild( $child );
			}
		}

		$root = $dom->documentElement;
		if ( ! $root || strtolower( $root->nodeName ) !== 'svg' ) {
			return null;
		}

		self::scrub_node( $root );

		return $dom->saveXML();
	}

	private static function scrub_node( DOMElement $node ) : void {
		$tag = strtolower( $node->nodeName );

		if ( ! in_array( $tag, self::ALLOWED_TAGS, true ) ) {
			$node->parentNode->removeChild( $node );
			return;
		}

		$attrs_to_remove = [];
		foreach ( iterator_to_array( $node->attributes ) as $attr ) {
			$name = strtolower( $attr->nodeName );

			if ( preg_match( '/^on[a-z]+$/i', $name ) ) {
				$attrs_to_remove[] = $attr->nodeName;
				continue;
			}

			if ( in_array( $name, [ 'href', 'xlink:href' ], true ) ) {
				$value = trim( strtolower( $attr->nodeValue ) );
				if ( strpos( $value, 'javascript:' ) === 0 ) {
					$attrs_to_remove[] = $attr->nodeName;
					continue;
				}
				// Block all data: URIs except non-SVG images. data:image/svg+xml can carry script when rendered via <use>.
				if ( strpos( $value, 'data:' ) === 0 ) {
					$is_safe_image = strpos( $value, 'data:image/' ) === 0
						&& strpos( $value, 'data:image/svg' ) !== 0;
					if ( ! $is_safe_image ) {
						$attrs_to_remove[] = $attr->nodeName;
						continue;
					}
				}
			}

			$base_name = preg_replace( '/^[a-z]+:/', '', $name );
			if ( ! in_array( $name, self::ALLOWED_ATTRS, true ) && ! in_array( $base_name, self::ALLOWED_ATTRS, true ) ) {
				if ( strpos( $name, 'xmlns' ) === 0 ) {
					continue;
				}
				$attrs_to_remove[] = $attr->nodeName;
			}
		}

		foreach ( $attrs_to_remove as $attr_name ) {
			$node->removeAttribute( $attr_name );
		}

		foreach ( iterator_to_array( $node->childNodes ) as $child ) {
			if ( $child instanceof DOMElement ) {
				self::scrub_node( $child );
			} elseif ( $child->nodeType === XML_PI_NODE ) {
				$node->removeChild( $child );
			}
		}
	}

	public static function fix_preview( $response, $attachment, $meta ) {
		if ( ! is_array( $response ) ) {
			return $response;
		}
		if ( ( $response['mime'] ?? '' ) !== 'image/svg+xml' ) {
			return $response;
		}

		$url = $response['url'] ?? wp_get_attachment_url( $attachment->ID );
		if ( ! $url ) {
			return $response;
		}

		$response['sizes'] = [
			'full'      => [
				'url'         => $url,
				'width'       => $response['width'] ?? 150,
				'height'      => $response['height'] ?? 150,
				'orientation' => 'portrait',
			],
			'thumbnail' => [
				'url'         => $url,
				'width'       => $response['width'] ?? 150,
				'height'      => $response['height'] ?? 150,
				'orientation' => 'portrait',
			],
		];
		$response['image'] = [ 'src' => $url ];
		$response['icon']  = $url;

		return $response;
	}
}
