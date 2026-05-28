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

		// Theme class no <body>
		add_filter( 'admin_body_class', [ __CLASS__, 'filter_admin_body_class' ] );
	}

	public static function filter_admin_body_class( $classes ) {
		$theme = (string) Settings_Repository::get( 'loomi_theme', 'dark' );
		if ( ! in_array( $theme, Settings_Repository::THEME_VALUES, true ) ) {
			$theme = 'dark';
		}
		return $classes . ' loomi-theme-' . $theme;
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
		wp_enqueue_style(
			'loomi-studio-admin',
			LOOMI_STUDIO_URL . 'assets/admin.css',
			[],
			Plugin::version()
		);
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

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab ) : ?>
					<a href="#<?php echo esc_attr( $tab->slug() ); ?>" data-tab-target="<?php echo esc_attr( $tab->slug() ); ?>" class="nav-tab loomi-tab-link <?php echo $active === $tab->slug() ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab->label() ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

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

				$('.loomi-tab-link').on('click', function (e) {
					e.preventDefault();
					var target = $(this).data('tab-target');
					$('.loomi-tab-link').removeClass('nav-tab-active');
					$(this).addClass('nav-tab-active');
					$('.loomi-studio-tab').hide();
					$('.loomi-studio-tab[data-tab="' + target + '"]').show();
					syncDashboardClass(target);
				});
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
