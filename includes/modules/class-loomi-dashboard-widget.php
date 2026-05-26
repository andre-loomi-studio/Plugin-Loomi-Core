<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_Dashboard_Widget implements Loomi_Module {

	const WIDGET_ID = 'loomi_welcome_widget';

	public static function register() : void {
		add_action( 'wp_dashboard_setup', [ __CLASS__, 'cleanup_and_add_widget' ], 999 );
		// Esconde o welcome_panel default do WP
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
	}

	public static function cleanup_and_add_widget() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Remove default dashboard widgets pra dar espaço ao nosso layout Loomi
		remove_meta_box( 'dashboard_right_now',     'dashboard', 'normal' );
		remove_meta_box( 'dashboard_activity',      'dashboard', 'normal' );
		remove_meta_box( 'dashboard_quick_press',   'dashboard', 'side' );
		remove_meta_box( 'dashboard_primary',       'dashboard', 'side' );
		remove_meta_box( 'dashboard_site_health',   'dashboard', 'normal' );
		remove_meta_box( 'dashboard_php_nag',       'dashboard', 'normal' );

		wp_add_dashboard_widget(
			self::WIDGET_ID,
			'Loomi Studio',
			[ __CLASS__, 'render' ]
		);

		// Move o widget pro topo
		global $wp_meta_boxes;
		if ( isset( $wp_meta_boxes['dashboard']['normal']['core'][ self::WIDGET_ID ] ) ) {
			$ours = [ self::WIDGET_ID => $wp_meta_boxes['dashboard']['normal']['core'][ self::WIDGET_ID ] ];
			unset( $wp_meta_boxes['dashboard']['normal']['core'][ self::WIDGET_ID ] );
			$wp_meta_boxes['dashboard']['normal']['core'] = array_merge( $ours, $wp_meta_boxes['dashboard']['normal']['core'] );
		}
	}

	public static function render() : void {
		// Stats WP (uso real do site, não configurações do plugin)
		$posts_count    = (int) wp_count_posts( 'post' )->publish;
		$pages_count    = (int) wp_count_posts( 'page' )->publish;
		$comments_total = (int) get_comments( [ 'count' => true, 'status' => 'approve' ] );
		$plugins_active = count( (array) get_option( 'active_plugins', [] ) );
		$plugins_total  = count( (array) get_plugins() );

		$stats = [
			[
				'label' => __( 'Posts', 'loomi-studio-setup' ),
				'value' => $posts_count,
				'icon'  => 'login',
				'url'   => admin_url( 'edit.php' ),
			],
			[
				'label' => __( 'Páginas', 'loomi-studio-setup' ),
				'value' => $pages_count,
				'icon'  => 'puzzle',
				'url'   => admin_url( 'edit.php?post_type=page' ),
			],
			[
				'label' => __( 'Comentários', 'loomi-studio-setup' ),
				'value' => $comments_total,
				'icon'  => 'mail',
				'url'   => admin_url( 'edit-comments.php' ),
			],
			[
				'label' => __( 'Plugins', 'loomi-studio-setup' ),
				'value' => sprintf( '%d/%d', $plugins_active, $plugins_total ),
				'icon'  => 'shield',
				'url'   => admin_url( 'plugins.php' ),
			],
		];

		$settings_url = admin_url( 'options-general.php?page=loomi-studio-setup' );
		?>
		<div class="loomi-welcome-widget">

			<div class="loomi-welcome-widget__hero">
				<div class="loomi-welcome-widget__brand">
					<svg class="loomi-welcome-widget__logo" viewBox="0 0 100 24" xmlns="http://www.w3.org/2000/svg" aria-label="Loomi">
						<text x="0" y="20" font-family="system-ui, -apple-system, sans-serif" font-weight="700" font-size="22" fill="currentColor" letter-spacing="-0.5">loomi</text>
						<circle cx="68" cy="6" r="3.5" fill="#FBD603" class="loomi-pulse"/>
					</svg>
					<span class="loomi-welcome-widget__product"><?php esc_html_e( 'Studio Setup', 'loomi-studio-setup' ); ?></span>
				</div>
				<p class="loomi-welcome-widget__tagline">
					<?php esc_html_e( 'Configuração padronizada Loomi pra este site WordPress.', 'loomi-studio-setup' ); ?>
				</p>
			</div>

			<div class="loomi-welcome-widget__stats">
				<?php foreach ( $stats as $i => $stat ) : ?>
					<a href="<?php echo esc_url( $stat['url'] ); ?>" class="loomi-welcome-widget__stat" style="animation-delay: <?php echo 0.06 * ( $i + 1 ); ?>s">
						<span class="loomi-welcome-widget__stat-icon" aria-hidden="true"><?php echo Loomi_UI::icon( $stat['icon'], 18 ); /* phpcs:ignore WordPress.Security.EscapeOutput */ ?></span>
						<span class="loomi-welcome-widget__stat-number"><?php echo esc_html( (string) $stat['value'] ); ?></span>
						<span class="loomi-welcome-widget__stat-label"><?php echo esc_html( $stat['label'] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="loomi-welcome-widget__actions">
				<a href="<?php echo esc_url( $settings_url ); ?>" class="loomi-welcome-widget__cta">
					<?php esc_html_e( 'Abrir Loomi Studio', 'loomi-studio-setup' ); ?>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<line x1="5" y1="12" x2="19" y2="12"/>
						<polyline points="12 5 19 12 12 19"/>
					</svg>
				</a>
				<a href="mailto:dev@loomi.studio" class="loomi-welcome-widget__link">
					dev@loomi.studio
				</a>
				<span class="loomi-welcome-widget__version">v<?php echo esc_html( Plugin::version() ); ?> · WP <?php echo esc_html( get_bloginfo( 'version' ) ); ?></span>
			</div>

		</div>
		<?php
	}
}
