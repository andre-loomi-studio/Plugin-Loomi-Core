<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Settings_Page implements Loomi_Module {

	const PAGE_HOOK = 'settings_page_loomi-studio-setup';

	public static function register() : void {
		add_action( 'admin_menu', [ __CLASS__, 'register_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_setting' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_global_brand' ] );

		// Cleanup admin WP defaults (W logo + footer text)
		add_action( 'wp_before_admin_bar_render', [ __CLASS__, 'remove_wp_logo' ] );
		add_filter( 'admin_footer_text', [ __CLASS__, 'admin_footer_text' ], 99 );
		add_filter( 'update_footer', [ __CLASS__, 'update_footer' ], 99 );

		// Theme class no <body> — admin standard pages, wp-login.php and Customizer chrome.
		add_filter( 'admin_body_class', [ __CLASS__, 'filter_admin_body_class' ] );
		add_filter( 'login_body_class', [ __CLASS__, 'filter_login_body_class' ] );
		add_action( 'customize_controls_print_footer_scripts', [ __CLASS__, 'inject_customizer_body_class' ] );

		// REST endpoint for instant theme persistence (no Save Changes click required).
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_route' ] );
	}

	/**
	 * Resolve the body class for the current stored theme. Falls back to 'loomi-theme-dark'
	 * when the option is missing or contains an unrecognized value. Single source of truth
	 * shared by the admin/login/customizer filters.
	 */
	private static function resolve_theme_class() : string {
		$theme = (string) Settings_Repository::get( 'loomi_theme', 'dark' );
		if ( ! in_array( $theme, Settings_Repository::THEME_VALUES, true ) ) {
			$theme = 'dark';
		}
		return 'loomi-theme-' . $theme;
	}

	public static function filter_admin_body_class( $classes ) {
		return $classes . ' ' . self::resolve_theme_class();
	}

	public static function filter_login_body_class( $classes ) {
		if ( ! is_array( $classes ) ) {
			$classes = [];
		}
		$classes[] = self::resolve_theme_class();
		return $classes;
	}

	/**
	 * Inject the theme class on the Customizer chrome body. The standard admin_body_class
	 * filter does not reliably apply on customize.php, so we do it via inline JS in the
	 * controls footer — runs once on load.
	 */
	public static function inject_customizer_body_class() : void {
		$class = self::resolve_theme_class();
		?>
		<script>
			(function () {
				if ( document.body && ! document.body.classList.contains(<?php echo wp_json_encode( $class ); ?>) ) {
					document.body.classList.add(<?php echo wp_json_encode( $class ); ?>);
				}
			})();
		</script>
		<?php
	}

	/**
	 * Register the REST route POST /loomi/v1/theme used by the dashboard toggle.
	 * Permission: manage_options. Param validation against Settings_Repository::THEME_VALUES.
	 */
	public static function register_rest_route() : void {
		register_rest_route(
			'loomi/v1',
			'/theme',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'handle_set_theme' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => [
					'theme' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static function ( $value ) {
							return in_array( (string) $value, Settings_Repository::THEME_VALUES, true );
						},
					],
				],
			]
		);
	}

	public static function handle_set_theme( WP_REST_Request $req ) {
		$theme = (string) $req->get_param( 'theme' );
		$opts  = get_option( Plugin::OPTION_KEY, [] );
		if ( ! is_array( $opts ) ) {
			$opts = [];
		}
		$opts['loomi_theme'] = $theme;
		update_option( Plugin::OPTION_KEY, $opts );
		Settings_Repository::clear_cache();
		return [ 'theme' => $theme ];
	}

	public static function remove_wp_logo() : void {
		global $wp_admin_bar;
		if ( $wp_admin_bar instanceof WP_Admin_Bar ) {
			$wp_admin_bar->remove_node( 'wp-logo' );
		}
	}

	public static function admin_footer_text( $text ) {
		return '';
	}

	public static function update_footer( $text ) {
		return '';
	}

	/**
	 * Enqueue brand CSS globally (every admin page) — sidebar, buttons, focus colors etc.
	 */
	public static function enqueue_global_brand() : void {
		wp_enqueue_style(
			'loomi-studio-admin-global',
			LOOMI_STUDIO_URL . 'assets/admin-global.css',
			[],
			Plugin::version()
		);
	}

	/**
	 * @return Loomi_Settings_Tab[]
	 */
	public static function tabs() : array {
		return [
			new Tab_Dashboard(),
			new Tab_Login(),
			new Tab_Slug(),
			new Tab_Hide_Menus(),
			new Tab_Client_Role(),
			new Tab_Anti_Spam(),
			new Tab_Schema(),
			new Tab_GTM(),
			new Tab_Maps(),
			new Tab_Logs(),
		];
	}

	public static function register_page() : void {
		add_options_page(
			__( 'Loomi Studio Setup', 'loomi-studio-setup' ),
			__( 'Loomi Studio', 'loomi-studio-setup' ),
			'manage_options',
			Plugin::SETTINGS_PAGE,
			[ __CLASS__, 'render' ]
		);
	}

	public static function register_setting() : void {
		register_setting(
			Plugin::SETTINGS_GROUP,
			Plugin::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ 'Settings_Sanitizer', 'sanitize' ],
				'default'           => Settings_Repository::defaults(),
			]
		);
	}

	public static function enqueue_assets( string $hook ) : void {
		if ( $hook !== self::PAGE_HOOK ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		// wp-api-fetch ships the REST nonce automatically — needed by the theme toggle JS.
		wp_enqueue_script( 'wp-api-fetch' );
		wp_enqueue_style(
			'loomi-studio-admin',
			LOOMI_STUDIO_URL . 'assets/admin.css',
			[],
			Plugin::version()
		);
		self::enqueue_schema_tab_assets();
		self::enqueue_maps_tab_assets();
	}

	/**
	 * Enqueue the Maps settings tab JS. Conditional on the active tab being
	 * 'maps' (read from $_GET['tab']) since the script only acts on Maps tab
	 * UI controls.
	 */
	private static function enqueue_maps_tab_assets() : void {
		$active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( $active !== 'maps' ) {
			return;
		}
		wp_enqueue_script(
			'loomi-maps-tab',
			LOOMI_STUDIO_URL . 'assets/maps-tab.js',
			[],
			Plugin::version(),
			true
		);
	}

	/**
	 * Enqueue the Schema settings tab assets (JS + CSS) only on the plugin
	 * settings page. Localizes runtime config (nonce, AJAX URL, option prefix,
	 * i18n strings) via wp_localize_script — no PHP interpolation in JS.
	 */
	private static function enqueue_schema_tab_assets() : void {
		wp_enqueue_style(
			'loomi-schema-admin',
			LOOMI_STUDIO_URL . 'assets/schema-admin.css',
			[],
			Plugin::version()
		);
		wp_enqueue_script(
			'loomi-schema-tab',
			LOOMI_STUDIO_URL . 'assets/schema-tab.js',
			[],
			Plugin::version(),
			true
		);
		wp_localize_script( 'loomi-schema-tab', 'LoomiSchemaTab', [
			'nonce'        => wp_create_nonce( 'loomi_schema_preview' ),
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'optionPrefix' => Plugin::OPTION_KEY . '[loomi_schema_global]',
			'i18n'         => [
				'loading'     => __( 'Carregando…', 'loomi-studio-setup' ),
				'formMissing' => __( 'Erro: formulário não encontrado.', 'loomi-studio-setup' ),
			],
		] );
	}

	public static function render() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = Settings_Repository::all();
		$tabs     = self::tabs();
		$active   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $tabs[0]->slug();
		$found    = false;
		foreach ( $tabs as $tab ) {
			if ( $tab->slug() === $active ) { $found = true; break; }
		}
		if ( ! $found ) {
			$active = $tabs[0]->slug();
		}
		?>
		<div class="wrap loomi-studio-wrap">
			<h1><?php esc_html_e( 'Loomi Studio Setup', 'loomi-studio-setup' ); ?></h1>

			<div class="loomi-header">
				<div class="loomi-brand">
					<svg class="loomi-logo" viewBox="0 0 100 24" xmlns="http://www.w3.org/2000/svg" aria-label="Loomi">
						<text x="0" y="20" font-family="system-ui, -apple-system, sans-serif" font-weight="700" font-size="22" fill="currentColor" letter-spacing="-0.5">loomi</text>
						<circle cx="68" cy="6" r="3" fill="#FBD603"/>
					</svg>
					<span class="loomi-divider" aria-hidden="true"></span>
					<span class="loomi-product"><?php esc_html_e( 'Studio Setup', 'loomi-studio-setup' ); ?></span>
				</div>
				<span class="loomi-version">v<?php echo esc_html( Plugin::version() ); ?></span>
			</div>

			<div class="loomi-tabs-scroll" data-loomi-tabs-scroll>
				<button type="button" class="loomi-tabs-arrow loomi-tabs-arrow--left" aria-label="<?php esc_attr_e( 'Rolar abas para a esquerda', 'loomi-studio-setup' ); ?>" tabindex="-1" hidden>&lsaquo;</button>
				<nav class="nav-tab-wrapper">
					<?php foreach ( $tabs as $tab ) : ?>
						<a href="#<?php echo esc_attr( $tab->slug() ); ?>" data-tab-target="<?php echo esc_attr( $tab->slug() ); ?>" class="nav-tab loomi-tab-link <?php echo $active === $tab->slug() ? 'nav-tab-active' : ''; ?>">
							<?php echo esc_html( $tab->label() ); ?>
						</a>
					<?php endforeach; ?>
				</nav>
				<button type="button" class="loomi-tabs-arrow loomi-tabs-arrow--right" aria-label="<?php esc_attr_e( 'Rolar abas para a direita', 'loomi-studio-setup' ); ?>" tabindex="-1" hidden>&rsaquo;</button>
			</div>

			<form method="post" action="options.php" class="loomi-studio-form">
				<?php settings_fields( Plugin::SETTINGS_GROUP ); ?>
				<?php foreach ( $tabs as $tab ) : ?>
					<div class="loomi-studio-tab" data-tab="<?php echo esc_attr( $tab->slug() ); ?>" style="<?php echo $active === $tab->slug() ? '' : 'display:none;'; ?>">
						<?php $tab->render( $settings ); ?>
					</div>
				<?php endforeach; ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<script>
		(function ($) {
			$(function () {
				function syncDashboardClass(target) {
					if (target === 'dashboard') {
						$('.loomi-studio-wrap').addClass('loomi-tab-dashboard');
					} else {
						$('.loomi-studio-wrap').removeClass('loomi-tab-dashboard');
					}
				}
				// Initial state
				var activeNow = $('.loomi-tab-link.nav-tab-active').data('tab-target');
				if (activeNow) syncDashboardClass(activeNow);

				// Theme toggle: optimistic body class swap + REST persistence.
				// Works on any tab — does not require Save Changes (the form submit button
				// is hidden on the Dashboard tab where the toggle lives).
				var themeRadioSelector = 'input[name="<?php echo esc_js( Plugin::OPTION_KEY ); ?>[loomi_theme]"]';
				$(document).on('change', themeRadioSelector, function () {
					var value = String(this.value || '').trim();
					if (!/^(dark|light|auto)$/.test(value)) return;
					document.body.classList.remove('loomi-theme-dark', 'loomi-theme-light', 'loomi-theme-auto');
					document.body.classList.add('loomi-theme-' + value);
					// Sync `.is-active` on segmented option labels.
					$('.loomi-segmented__option').removeClass('is-active');
					$(this).closest('.loomi-segmented__option').addClass('is-active');
					if (window.wp && wp.apiFetch) {
						wp.apiFetch({
							path: '/loomi/v1/theme',
							method: 'POST',
							data: { theme: value }
						}).catch(function (err) {
							if (window.console) console.warn('[loomi] theme persist failed', err);
						});
					}
				});

				$('.loomi-tab-link').on('click', function (e) {
					e.preventDefault();
					var target = $(this).data('tab-target');
					$('.loomi-tab-link').removeClass('nav-tab-active');
					$(this).addClass('nav-tab-active');
					$('.loomi-studio-tab').hide();
					$('.loomi-studio-tab[data-tab="' + target + '"]').show();
					syncDashboardClass(target);
					// When user clicks a partially-visible tab, scroll it into view.
					var el = this;
					if (el && el.scrollIntoView) {
						el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
					}
				});

				// Horizontal scroll + arrows for the tab nav (responsive overflow handling).
				(function () {
					var container = document.querySelector('[data-loomi-tabs-scroll]');
					if (!container) return;
					var nav   = container.querySelector('.nav-tab-wrapper');
					var left  = container.querySelector('.loomi-tabs-arrow--left');
					var right = container.querySelector('.loomi-tabs-arrow--right');
					if (!nav || !left || !right) return;

					function updateArrows() {
						var overflow = nav.scrollWidth > nav.clientWidth + 1;
						if (!overflow) {
							left.hidden = true;
							right.hidden = true;
							container.classList.remove('is-overflowing');
							return;
						}
						container.classList.add('is-overflowing');
						var atStart = nav.scrollLeft <= 1;
						var atEnd   = nav.scrollLeft + nav.clientWidth >= nav.scrollWidth - 1;
						left.hidden  = atStart;
						right.hidden = atEnd;
					}

					function scrollBy(delta) {
						nav.scrollBy({ left: delta, behavior: 'smooth' });
					}

					left.addEventListener('click', function () { scrollBy(-Math.max(200, nav.clientWidth * 0.6)); });
					right.addEventListener('click', function () { scrollBy(Math.max(200, nav.clientWidth * 0.6)); });

					nav.addEventListener('scroll', updateArrows, { passive: true });
					window.addEventListener('resize', updateArrows);

					// Keyboard: ArrowLeft/ArrowRight when focus is in the tab strip.
					nav.addEventListener('keydown', function (e) {
						if (e.key === 'ArrowRight') { e.preventDefault(); scrollBy(Math.max(200, nav.clientWidth * 0.6)); }
						else if (e.key === 'ArrowLeft') { e.preventDefault(); scrollBy(-Math.max(200, nav.clientWidth * 0.6)); }
					});

					// On load, ensure the active tab is visible.
					var active = nav.querySelector('.nav-tab-active');
					if (active && active.scrollIntoView) {
						active.scrollIntoView({ block: 'nearest', inline: 'nearest' });
					}

					updateArrows();
				})();
				if ($.fn.wpColorPicker) {
					$('#loomi-bg-color').wpColorPicker();
				}
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
}
