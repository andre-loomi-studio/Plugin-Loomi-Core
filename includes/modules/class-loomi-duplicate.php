<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Duplicate {

	const ACTION = 'loomi_duplicate_post';

	public static function init() : void {
		add_filter( 'post_row_actions', [ __CLASS__, 'add_action_link' ], 10, 2 );
		add_filter( 'page_row_actions', [ __CLASS__, 'add_action_link' ], 10, 2 );
		add_action( 'admin_action_' . self::ACTION, [ __CLASS__, 'handle' ] );
		add_action( 'admin_notices', [ __CLASS__, 'maybe_render_notice' ] );
	}

	public static function add_action_link( $actions, $post ) {
		if ( ! $post instanceof WP_Post ) {
			return $actions;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		if ( ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin.php?action=' . self::ACTION . '&post=' . $post->ID ),
			self::ACTION . '_' . $post->ID,
			'_wpnonce'
		);

		$actions['loomi_duplicate'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $url ),
			esc_attr( sprintf( __( 'Duplicar "%s"', 'loomi-studio-setup' ), $post->post_title ) ),
			esc_html__( 'Duplicar', 'loomi-studio-setup' )
		);

		return $actions;
	}

	public static function handle() : void {
		$source_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $source_id ) {
			wp_die( esc_html__( 'Post inválido.', 'loomi-studio-setup' ) );
		}

		check_admin_referer( self::ACTION . '_' . $source_id );

		if ( ! current_user_can( 'edit_post', $source_id ) ) {
			wp_die( esc_html__( 'Permissão negada.', 'loomi-studio-setup' ) );
		}

		$source = get_post( $source_id );
		if ( ! $source ) {
			wp_die( esc_html__( 'Post não encontrado.', 'loomi-studio-setup' ) );
		}

		$new_id = wp_insert_post( [
			'post_title'     => $source->post_title . ' ' . __( '(cópia)', 'loomi-studio-setup' ),
			'post_content'   => $source->post_content,
			'post_excerpt'   => $source->post_excerpt,
			'post_status'    => 'draft',
			'post_type'      => $source->post_type,
			'post_author'    => get_current_user_id(),
			'post_parent'    => $source->post_parent,
			'menu_order'     => $source->menu_order,
			'comment_status' => $source->comment_status,
			'ping_status'    => $source->ping_status,
		], true );

		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		self::copy_meta( $source_id, (int) $new_id );
		self::copy_taxonomies( $source, (int) $new_id );

		$redirect = add_query_arg(
			[
				'post_type'         => $source->post_type === 'post' ? false : $source->post_type,
				'loomi_duplicated'  => 1,
			],
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	private static function copy_meta( int $source_id, int $new_id ) : void {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
				$source_id
			)
		);
		if ( ! $rows ) {
			return;
		}
		foreach ( $rows as $row ) {
			if ( $row->meta_key === '_edit_lock' || $row->meta_key === '_edit_last' ) {
				continue;
			}
			// Pass raw serialized string; WP slashes/unserializes on read. Avoids object instantiation
			// (POP-chain injection vector) that maybe_unserialize() would trigger here.
			$wpdb->insert(
				$wpdb->postmeta,
				[
					'post_id'    => $new_id,
					'meta_key'   => $row->meta_key,
					'meta_value' => $row->meta_value,
				]
			);
		}
	}

	private static function copy_taxonomies( WP_Post $source, int $new_id ) : void {
		$taxes = get_object_taxonomies( $source->post_type );
		foreach ( $taxes as $tax ) {
			$terms = wp_get_object_terms( $source->ID, $tax, [ 'fields' => 'ids' ] );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			wp_set_object_terms( $new_id, array_map( 'intval', $terms ), $tax );
		}
	}

	public static function maybe_render_notice() : void {
		if ( empty( $_GET['loomi_duplicated'] ) ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Item duplicado com sucesso.', 'loomi-studio-setup' ) . '</p></div>';
	}
}
