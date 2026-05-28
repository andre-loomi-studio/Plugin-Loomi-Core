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

		$prev_schema = isset( $previous['loomi_schema_global'] ) && is_array( $previous['loomi_schema_global'] )
			? $previous['loomi_schema_global']
			: [];

		if ( isset( $input['loomi_schema_global'] ) && is_array( $input['loomi_schema_global'] ) ) {
			$in_sg  = $input['loomi_schema_global'];
			$out_sg = [];

			foreach ( [ 'name', 'alternateName', 'telephone', 'priceRange' ] as $f ) {
				if ( isset( $in_sg[ $f ] ) ) {
					$out_sg[ $f ] = sanitize_text_field( (string) $in_sg[ $f ] );
				}
			}

			if ( isset( $in_sg['description'] ) ) {
				$out_sg['description'] = sanitize_textarea_field( (string) $in_sg['description'] );
			}

			if ( isset( $in_sg['email'] ) ) {
				$raw_email = (string) $in_sg['email'];
				if ( $raw_email === '' ) {
					$out_sg['email'] = '';
				} else {
					$clean_email = sanitize_email( $raw_email );
					if ( $clean_email && is_email( $clean_email ) ) {
						$out_sg['email'] = $clean_email;
					} else {
						$out_sg['email'] = $prev_schema['email'] ?? '';
						add_settings_error(
							Plugin::OPTION_KEY,
							'loomi_schema_email',
							__( 'E-mail do schema inválido — valor anterior mantido.', 'loomi-studio-setup' )
						);
					}
				}
			}

			if ( isset( $in_sg['address'] ) && is_array( $in_sg['address'] ) ) {
				$addr_in  = $in_sg['address'];
				$addr_out = [];
				foreach ( [ 'streetAddress', 'addressLocality', 'addressRegion', 'postalCode' ] as $af ) {
					if ( isset( $addr_in[ $af ] ) ) {
						$addr_out[ $af ] = sanitize_text_field( (string) $addr_in[ $af ] );
					}
				}
				// Country: aceita seleção do <select> (código ISO direto) ou "other" + campo livre `addressCountryOther`.
				// Persistimos apenas `addressCountry` (o `addressCountryOther` é transiente do form).
				if ( isset( $addr_in['addressCountry'] ) ) {
					$country = (string) $addr_in['addressCountry'];
					if ( $country === 'other' ) {
						$country = isset( $addr_in['addressCountryOther'] ) ? (string) $addr_in['addressCountryOther'] : '';
					}
					$country = sanitize_text_field( $country );
					$addr_out['addressCountry'] = $country !== '' ? strtoupper( substr( $country, 0, 2 ) ) : '';
				}
				$out_sg['address'] = $addr_out;
			}

			if ( isset( $in_sg['geo'] ) && is_array( $in_sg['geo'] ) ) {
				$geo_in  = $in_sg['geo'];
				$geo_out = [];
				$prev_geo = isset( $prev_schema['geo'] ) && is_array( $prev_schema['geo'] ) ? $prev_schema['geo'] : [];

				if ( isset( $geo_in['latitude'] ) && $geo_in['latitude'] !== '' ) {
					$lat = (float) str_replace( ',', '.', (string) $geo_in['latitude'] );
					if ( $lat >= -90.0 && $lat <= 90.0 ) {
						$geo_out['latitude'] = $lat;
					} else {
						if ( isset( $prev_geo['latitude'] ) ) {
							$geo_out['latitude'] = $prev_geo['latitude'];
						}
						add_settings_error(
							Plugin::OPTION_KEY,
							'loomi_schema_lat',
							__( 'Latitude fora do intervalo [-90, 90] — valor anterior mantido.', 'loomi-studio-setup' )
						);
					}
				}

				if ( isset( $geo_in['longitude'] ) && $geo_in['longitude'] !== '' ) {
					$lng = (float) str_replace( ',', '.', (string) $geo_in['longitude'] );
					if ( $lng >= -180.0 && $lng <= 180.0 ) {
						$geo_out['longitude'] = $lng;
					} else {
						if ( isset( $prev_geo['longitude'] ) ) {
							$geo_out['longitude'] = $prev_geo['longitude'];
						}
						add_settings_error(
							Plugin::OPTION_KEY,
							'loomi_schema_lng',
							__( 'Longitude fora do intervalo [-180, 180] — valor anterior mantido.', 'loomi-studio-setup' )
						);
					}
				}

				$out_sg['geo'] = $geo_out;
			}

			if ( isset( $in_sg['openingHours'] ) && is_array( $in_sg['openingHours'] ) ) {
				$allowed_days = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
				$hours_out    = [];
				foreach ( $in_sg['openingHours'] as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$days_raw = isset( $row['days'] ) && is_array( $row['days'] ) ? $row['days'] : [];
					$days     = array_values( array_intersect( array_map( 'strval', $days_raw ), $allowed_days ) );
					$opens    = isset( $row['opens'] ) ? (string) $row['opens'] : '';
					$closes   = isset( $row['closes'] ) ? (string) $row['closes'] : '';

					if ( empty( $days ) && $opens === '' && $closes === '' ) {
						continue;
					}

					if ( ! preg_match( '/^\d{2}:\d{2}$/', $opens ) ) {
						$opens = '';
					}
					if ( ! preg_match( '/^\d{2}:\d{2}$/', $closes ) ) {
						$closes = '';
					}

					$hours_out[] = [
						'days'   => $days,
						'opens'  => $opens,
						'closes' => $closes,
					];
				}
				$out_sg['openingHours'] = $hours_out;
			}

			if ( isset( $in_sg['areaServed'] ) ) {
				$area_raw = is_array( $in_sg['areaServed'] )
					? $in_sg['areaServed']
					: explode( "\n", (string) $in_sg['areaServed'] );
				$area_out = [];
				foreach ( $area_raw as $line ) {
					$line = sanitize_text_field( trim( (string) $line ) );
					if ( $line !== '' ) {
						$area_out[] = $line;
					}
				}
				$out_sg['areaServed'] = $area_out;
			}

			if ( isset( $in_sg['sameAs'] ) ) {
				$same_raw = is_array( $in_sg['sameAs'] )
					? $in_sg['sameAs']
					: explode( "\n", (string) $in_sg['sameAs'] );
				$same_out = [];
				foreach ( $same_raw as $line ) {
					$line = trim( (string) $line );
					if ( $line === '' ) {
						continue;
					}
					$url = esc_url_raw( $line );
					if ( $url !== '' ) {
						$same_out[] = $url;
					}
				}
				$out_sg['sameAs'] = $same_out;
			}

			if ( isset( $in_sg['identifier'] ) && is_array( $in_sg['identifier'] ) ) {
				$id_in  = $in_sg['identifier'];
				$id_out = [];
				foreach ( [ 'propertyID', 'value' ] as $idf ) {
					if ( isset( $id_in[ $idf ] ) ) {
						$id_out[ $idf ] = sanitize_text_field( (string) $id_in[ $idf ] );
					}
				}
				$out_sg['identifier'] = $id_out;
			}

			$out['loomi_schema_global'] = $out_sg;
		} else {
			$out['loomi_schema_global'] = $prev_schema;
		}

		Settings_Repository::clear_cache();
		return $out;
	}
}
