<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings_Sanitizer {

	public static function sanitize( $input ) : array {
		$defaults = Settings_Repository::defaults();
		$previous = get_option( Plugin::OPTION_KEY, $defaults );
		$previous = is_array( $previous ) ? array_merge( $defaults, $previous ) : $defaults;
		$out      = $previous;

		if ( ! is_array( $input ) ) {
			return $previous;
		}

		foreach ( Settings_Repository::BOOL_FIELDS as $field ) {
			$out[ $field ] = ! empty( $input[ $field ] );
		}

		if ( isset( $input['custom_login_bg_color'] ) ) {
			$color = sanitize_hex_color( (string) $input['custom_login_bg_color'] );
			if ( $color ) {
				$out['custom_login_bg_color'] = $color;
			} else {
				add_settings_error(
					Plugin::OPTION_KEY,
					'loomi_invalid_color',
					__( 'Cor de fundo do login inválida — valor anterior mantido.', 'loomi-studio-setup' )
				);
			}
		}

		if ( isset( $input['custom_login_logo_id'] ) ) {
			$out['custom_login_logo_id'] = (int) $input['custom_login_logo_id'];
		}

		if ( isset( $input['login_slug'] ) ) {
			$slug = sanitize_title( (string) $input['login_slug'] );
			if ( $slug === '' || in_array( $slug, Settings_Repository::RESERVED_SLUGS, true ) ) {
				add_settings_error(
					Plugin::OPTION_KEY,
					'loomi_invalid_slug',
					__( 'Slug de login inválida ou reservada — valor anterior mantido.', 'loomi-studio-setup' )
				);
			} else {
				$out['login_slug'] = $slug;
			}
		}

		$out['hidden_menus'] = [];
		if ( isset( $input['hidden_menus'] ) && is_array( $input['hidden_menus'] ) ) {
			$allowed = Settings_Repository::hideable_menus(); // core + CPTs dinâmicos
			foreach ( $input['hidden_menus'] as $slug ) {
				$slug = (string) $slug;
				if ( in_array( $slug, Settings_Repository::BLACKLISTED_MENUS, true ) ) {
					continue;
				}
				if ( array_key_exists( $slug, $allowed ) ) {
					$out['hidden_menus'][] = $slug;
				}
			}
			$out['hidden_menus'] = array_values( array_unique( $out['hidden_menus'] ) );
		}

		if ( isset( $input['loomi_theme'] ) ) {
			$theme = (string) $input['loomi_theme'];
			if ( in_array( $theme, Settings_Repository::THEME_VALUES, true ) ) {
				$out['loomi_theme'] = $theme;
			} else {
				add_settings_error(
					Plugin::OPTION_KEY,
					'loomi_invalid_theme',
					__( 'Tema inválido — valor anterior mantido.', 'loomi-studio-setup' )
				);
			}
		}

		Settings_Repository::clear_cache();
		return $out;
	}
}
