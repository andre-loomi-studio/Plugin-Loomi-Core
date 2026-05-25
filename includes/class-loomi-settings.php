<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Settings {

	const OPTION_KEY     = 'loomi_studio_setup_settings';
	const OPTION_GROUP   = 'loomi_studio';
	const PAGE_SLUG      = 'loomi-studio-setup';
	const PAGE_HOOK      = 'settings_page_loomi-studio-setup';

	const HIDEABLE_MENUS = [
		'edit.php'          => 'Posts',
		'edit-comments.php' => 'Comentários',
		'tools.php'         => 'Ferramentas',
		'themes.php'        => 'Aparência',
		'plugins.php'       => 'Plugins',
		'users.php'         => 'Usuários',
		'upload.php'        => 'Mídia',
	];

	const BLACKLISTED_MENUS = [
		'index.php',
		'options-general.php',
	];

	const RESERVED_SLUGS = [ 'wp-admin', 'wp-login', 'admin', 'login', 'wp-content', 'wp-includes' ];

	private static $cache = null;

	public static function init() : void {
		add_action( 'admin_menu', [ __CLASS__, 'register_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_setting' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	public static function defaults() : array {
		return [
			'custom_login_enabled'  => false,
			'custom_login_bg_color' => '#000000',
			'custom_login_logo_id'  => 0,
			'login_slug_enabled'    => true,
			'login_slug'            => 'studio-access',
			'hide_menus_enabled'    => false,
			'hidden_menus'          => array_keys( self::HIDEABLE_MENUS ),
			'client_role_enabled'   => true,
		];
	}

	public static function all() : array {
		if ( self::$cache === null ) {
			$stored = get_option( self::OPTION_KEY, [] );
			$merged = is_array( $stored ) ? array_merge( self::defaults(), $stored ) : self::defaults();

			// Coerce boolean fields. WP-CLI / REST may inject string values
			// like "false" / "0" which PHP otherwise treats as truthy.
			foreach ( [ 'custom_login_enabled', 'login_slug_enabled', 'hide_menus_enabled', 'client_role_enabled' ] as $bool_field ) {
				$merged[ $bool_field ] = filter_var( $merged[ $bool_field ], FILTER_VALIDATE_BOOLEAN );
			}

			self::$cache = $merged;
		}
		return self::$cache;
	}

	public static function clear_cache() : void {
		self::$cache = null;
	}

	public static function get( string $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	public static function register_page() : void {
		add_options_page(
			__( 'Loomi Studio Setup', 'loomi-studio-setup' ),
			__( 'Loomi Studio', 'loomi-studio-setup' ),
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function register_setting() : void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize' ],
				'default'           => self::defaults(),
			]
		);
	}

	public static function sanitize( $input ) : array {
		$defaults = self::defaults();
		$previous = get_option( self::OPTION_KEY, $defaults );
		$previous = is_array( $previous ) ? array_merge( $defaults, $previous ) : $defaults;
		$out      = $previous;

		if ( ! is_array( $input ) ) {
			return $previous;
		}

		$out['custom_login_enabled'] = ! empty( $input['custom_login_enabled'] );
		$out['login_slug_enabled']   = ! empty( $input['login_slug_enabled'] );
		$out['hide_menus_enabled']   = ! empty( $input['hide_menus_enabled'] );
		$out['client_role_enabled']  = ! empty( $input['client_role_enabled'] );

		if ( isset( $input['custom_login_bg_color'] ) ) {
			$color = sanitize_hex_color( (string) $input['custom_login_bg_color'] );
			if ( $color ) {
				$out['custom_login_bg_color'] = $color;
			} else {
				add_settings_error(
					self::OPTION_KEY,
					'loomi_invalid_color',
					__( 'Cor de fundo do login inválida — valor anterior mantido.', 'loomi-studio-setup' )
				);
			}
		}

		if ( isset( $input['custom_login_logo_id'] ) ) {
			$out['custom_login_logo_id'] = (int) $input['custom_login_logo_id'];
		}

		if ( isset( $input['login_slug'] ) ) {
			$raw  = (string) $input['login_slug'];
			$slug = sanitize_title( $raw );
			if ( $slug === '' || in_array( $slug, self::RESERVED_SLUGS, true ) ) {
				add_settings_error(
					self::OPTION_KEY,
					'loomi_invalid_slug',
					__( 'Slug de login inválida ou reservada — valor anterior mantido.', 'loomi-studio-setup' )
				);
			} else {
				$out['login_slug'] = $slug;
			}
		}

		$out['hidden_menus'] = [];
		if ( isset( $input['hidden_menus'] ) && is_array( $input['hidden_menus'] ) ) {
			foreach ( $input['hidden_menus'] as $slug ) {
				$slug = (string) $slug;
				if ( in_array( $slug, self::BLACKLISTED_MENUS, true ) ) {
					continue;
				}
				if ( array_key_exists( $slug, self::HIDEABLE_MENUS ) ) {
					$out['hidden_menus'][] = $slug;
				}
			}
			$out['hidden_menus'] = array_values( array_unique( $out['hidden_menus'] ) );
		}

		self::$cache = $out;
		return $out;
	}

	public static function enqueue_assets( string $hook ) : void {
		if ( $hook !== self::PAGE_HOOK ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_style(
			'loomi-studio-admin',
			LOOMI_STUDIO_URL . 'assets/admin.css',
			[],
			LOOMI_STUDIO_VERSION
		);
	}

	public static function render_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::all();
		$tabs     = [
			'login'       => __( 'Custom Login', 'loomi-studio-setup' ),
			'login-slug'  => __( 'Login Slug', 'loomi-studio-setup' ),
			'hide-menus'  => __( 'Esconder Menus', 'loomi-studio-setup' ),
			'client-role' => __( 'Role Cliente', 'loomi-studio-setup' ),
		];
		$active   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'login';
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'login';
		}
		?>
		<div class="wrap loomi-studio-wrap">
			<h1><?php esc_html_e( 'Loomi Studio Setup', 'loomi-studio-setup' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="#<?php echo esc_attr( $slug ); ?>" data-tab-target="<?php echo esc_attr( $slug ); ?>" class="nav-tab loomi-tab-link <?php echo $active === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php" class="loomi-studio-form">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<?php foreach ( array_keys( $tabs ) as $tab_slug ) : ?>
					<div class="loomi-studio-tab" data-tab="<?php echo esc_attr( $tab_slug ); ?>" style="<?php echo $active === $tab_slug ? '' : 'display:none;'; ?>">
						<?php self::render_tab( $tab_slug, $settings ); ?>
					</div>
				<?php endforeach; ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<script>
		(function ($) {
			$(function () {
				$('.loomi-tab-link').on('click', function (e) {
					e.preventDefault();
					var target = $(this).data('tab-target');
					$('.loomi-tab-link').removeClass('nav-tab-active');
					$(this).addClass('nav-tab-active');
					$('.loomi-studio-tab').hide();
					$('.loomi-studio-tab[data-tab="' + target + '"]').show();
				});
				if ($.fn.wpColorPicker) {
					$('#loomi-bg-color').wpColorPicker();
				}
			});
		})(jQuery);
		</script>
		<?php
	}

	private static function render_tab( string $tab, array $s ) : void {
		switch ( $tab ) {
			case 'login':
				self::render_login_tab( $s );
				break;
			case 'login-slug':
				self::render_slug_tab( $s );
				break;
			case 'hide-menus':
				self::render_menus_tab( $s );
				break;
			case 'client-role':
				self::render_role_tab( $s );
				break;
		}
	}

	private static function render_login_tab( array $s ) : void {
		$logo_url = $s['custom_login_logo_id'] ? wp_get_attachment_url( (int) $s['custom_login_logo_id'] ) : '';
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Ativar Custom Login', 'loomi-studio-setup' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_login_enabled]" value="1" <?php checked( $s['custom_login_enabled'] ); ?> />
						<?php esc_html_e( 'Aplicar cor de fundo e logo customizados na tela de login.', 'loomi-studio-setup' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="loomi-bg-color"><?php esc_html_e( 'Cor de fundo', 'loomi-studio-setup' ); ?></label></th>
				<td>
					<input type="text" id="loomi-bg-color" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_login_bg_color]" value="<?php echo esc_attr( $s['custom_login_bg_color'] ); ?>" class="loomi-color-field" />
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Logo', 'loomi-studio-setup' ); ?></th>
				<td>
					<div class="loomi-logo-picker">
						<?php if ( $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="" class="loomi-logo-preview" />
						<?php else : ?>
							<img alt="" class="loomi-logo-preview" hidden />
						<?php endif; ?>
						<input type="hidden" id="loomi-logo-id" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[custom_login_logo_id]" value="<?php echo esc_attr( $s['custom_login_logo_id'] ); ?>" />
						<button type="button" class="button" id="loomi-logo-select"><?php esc_html_e( 'Selecionar logo', 'loomi-studio-setup' ); ?></button>
						<button type="button" class="button" id="loomi-logo-clear" <?php echo $logo_url ? '' : 'hidden'; ?>><?php esc_html_e( 'Remover', 'loomi-studio-setup' ); ?></button>
					</div>
				</td>
			</tr>
		</table>
		<script>
		(function ($) {
			$(function () {
				var frame;
				$('#loomi-logo-select').on('click', function (e) {
					e.preventDefault();
					if (frame) { frame.open(); return; }
					frame = wp.media({ title: 'Selecionar logo', button: { text: 'Usar este logo' }, multiple: false });
					frame.on('select', function () {
						var att = frame.state().get('selection').first().toJSON();
						$('#loomi-logo-id').val(att.id);
						$('.loomi-logo-preview').attr('src', att.url).removeAttr('hidden');
						$('#loomi-logo-clear').removeAttr('hidden');
					});
					frame.open();
				});
				$('#loomi-logo-clear').on('click', function (e) {
					e.preventDefault();
					$('#loomi-logo-id').val(0);
					$('.loomi-logo-preview').removeAttr('src').attr('hidden', '');
					$(this).attr('hidden', '');
				});
			});
		})(jQuery);
		</script>
		<?php
	}

	private static function render_slug_tab( array $s ) : void {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Ativar slug customizada', 'loomi-studio-setup' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[login_slug_enabled]" value="1" <?php checked( $s['login_slug_enabled'] ); ?> />
						<?php esc_html_e( 'Bloqueia o acesso direto a /wp-login.php para visitantes não logados.', 'loomi-studio-setup' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="loomi-login-slug"><?php esc_html_e( 'Slug de login', 'loomi-studio-setup' ); ?></label></th>
				<td>
					<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
					<input type="text" id="loomi-login-slug" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[login_slug]" value="<?php echo esc_attr( $s['login_slug'] ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Default: studio-access. Caracteres inválidos serão removidos; slugs reservadas (wp-admin, login, etc.) são rejeitadas.', 'loomi-studio-setup' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private static function render_menus_tab( array $s ) : void {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Ativar ocultação', 'loomi-studio-setup' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[hide_menus_enabled]" value="1" <?php checked( $s['hide_menus_enabled'] ); ?> />
						<?php esc_html_e( 'Esconder menus selecionados para usuários sem permissão de administrador.', 'loomi-studio-setup' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Menus a esconder', 'loomi-studio-setup' ); ?></th>
				<td>
					<?php foreach ( self::HIDEABLE_MENUS as $slug => $label ) : ?>
						<label style="display:block; margin-bottom:4px;">
							<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[hidden_menus][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $s['hidden_menus'], true ) ); ?> />
							<?php echo esc_html( $label ); ?> <code><?php echo esc_html( $slug ); ?></code>
						</label>
					<?php endforeach; ?>
					<p class="description"><?php esc_html_e( 'Dashboard e o menu de Configurações nunca podem ser escondidos.', 'loomi-studio-setup' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private static function render_role_tab( array $s ) : void {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Ativar role Cliente Loomi', 'loomi-studio-setup' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[client_role_enabled]" value="1" <?php checked( $s['client_role_enabled'] ); ?> />
						<?php esc_html_e( 'Mostrar a role "Cliente Loomi" na lista de papéis ao criar/editar usuários.', 'loomi-studio-setup' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'A role existe enquanto o plugin estiver ativo. Desativar este toggle apenas a esconde do dropdown.', 'loomi-studio-setup' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}
}
