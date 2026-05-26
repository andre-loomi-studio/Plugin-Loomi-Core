<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tab_Dashboard implements Loomi_Settings_Tab {

	public function slug() : string {
		return 'dashboard';
	}

	public function label() : string {
		return __( 'Dashboard', 'loomi-studio-setup' );
	}

	public function render( array $s ) : void {
		$features = [
			[
				'name'    => __( 'Custom Login', 'loomi-studio-setup' ),
				'enabled' => ! empty( $s['custom_login_enabled'] ),
				'tab'     => 'login',
				'icon'    => 'login',
				'desc'    => __( 'Tela de login com cor e logo personalizados.', 'loomi-studio-setup' ),
			],
			[
				'name'    => __( 'Login Slug', 'loomi-studio-setup' ),
				'enabled' => ! empty( $s['login_slug_enabled'] ),
				'tab'     => 'login-slug',
				'icon'    => 'lock',
				'desc'    => sprintf( __( 'URL secreta de login: /%s/', 'loomi-studio-setup' ), esc_html( $s['login_slug'] ?? 'studio-access' ) ),
			],
			[
				'name'    => __( 'Hardening /wp-admin/', 'loomi-studio-setup' ),
				'enabled' => ! empty( $s['hide_admin_endpoint'] ),
				'tab'     => 'login-slug',
				'icon'    => 'shield',
				'desc'    => __( 'Bloqueia /wp-admin/ para anônimos (404).', 'loomi-studio-setup' ),
			],
			[
				'name'    => __( 'Esconder Menus', 'loomi-studio-setup' ),
				'enabled' => ! empty( $s['hide_menus_enabled'] ),
				'tab'     => 'hide-menus',
				'icon'    => 'eye-off',
				'desc'    => sprintf( _n( '%d menu oculto.', '%d menus ocultos.', count( $s['hidden_menus'] ?? [] ), 'loomi-studio-setup' ), count( $s['hidden_menus'] ?? [] ) ),
			],
			[
				'name'    => __( 'Role Cliente', 'loomi-studio-setup' ),
				'enabled' => ! empty( $s['client_role_enabled'] ),
				'tab'     => 'client-role',
				'icon'    => 'user',
				'desc'    => __( 'Role "loomi_client" visível no dropdown.', 'loomi-studio-setup' ),
			],
			[
				'name'    => __( 'Anti-Spam', 'loomi-studio-setup' ),
				'enabled' => ! empty( $s['anti_spam_enabled'] ),
				'tab'     => 'anti-spam',
				'icon'    => 'shield-off',
				'desc'    => __( 'Honeypot + time check + comment lockdown.', 'loomi-studio-setup' ),
			],
		];

		$wordfence_state = class_exists( 'Loomi_Wordfence_Check' ) ? Loomi_Wordfence_Check::get_state() : 'absent';

		// Detecta ACF (free ou Pro) — guarda o file path da variante instalada pra usar no link de ativação.
		$acf_free_file = 'advanced-custom-fields/acf.php';
		$acf_pro_file  = 'advanced-custom-fields-pro/acf.php';
		$acf_active = function_exists( 'is_plugin_active' ) && (
			is_plugin_active( $acf_free_file ) || is_plugin_active( $acf_pro_file )
		);
		if ( file_exists( WP_PLUGIN_DIR . '/' . $acf_pro_file ) ) {
			$acf_installed_file = $acf_pro_file;
		} elseif ( file_exists( WP_PLUGIN_DIR . '/' . $acf_free_file ) ) {
			$acf_installed_file = $acf_free_file;
		} else {
			$acf_installed_file = '';
		}

		if ( $acf_active ) {
			$acf_state = 'active';
		} elseif ( $acf_installed_file !== '' ) {
			$acf_state = 'installed_inactive';
		} else {
			$acf_state = 'absent';
		}

		$current_theme = (string) ( $s['loomi_theme'] ?? 'dark' );
		?>

		<div class="loomi-dashboard">

			<div class="loomi-theme-toggle" role="radiogroup" aria-label="<?php esc_attr_e( 'Tema do painel', 'loomi-studio-setup' ); ?>">
				<span class="loomi-theme-toggle__label"><?php esc_html_e( 'Tema', 'loomi-studio-setup' ); ?></span>
				<div class="loomi-segmented">
					<?php
					$options = [
						'dark'  => __( 'Dark', 'loomi-studio-setup' ),
						'light' => __( 'Light', 'loomi-studio-setup' ),
						'auto'  => __( 'Auto', 'loomi-studio-setup' ),
					];
					foreach ( $options as $value => $label ) :
						?>
						<label class="loomi-segmented__option <?php echo $current_theme === $value ? 'is-active' : ''; ?>">
							<input type="radio" name="<?php echo esc_attr( Plugin::OPTION_KEY ); ?>[loomi_theme]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $current_theme, $value ); ?> />
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="loomi-welcome">
				<h2 class="loomi-welcome-title"><?php esc_html_e( 'Bem-vindo ao Loomi Studio Setup', 'loomi-studio-setup' ); ?></h2>
				<p class="loomi-welcome-text">
					<?php esc_html_e( 'Pacote interno de ajustes WordPress da Loomi: padroniza configuração, segurança e identidade de todos os sites da agência. Use as abas acima para configurar cada módulo.', 'loomi-studio-setup' ); ?>
				</p>
			</div>

			<div class="loomi-section-title"><?php esc_html_e( 'Status das funcionalidades', 'loomi-studio-setup' ); ?></div>

			<div class="loomi-status-grid">
				<?php foreach ( $features as $feature ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $feature['tab'] ) ); ?>" class="loomi-status-card loomi-status-card--<?php echo $feature['enabled'] ? 'on' : 'off'; ?>">
						<div class="loomi-status-card__head">
							<span class="loomi-status-card__icon" aria-hidden="true"><?php echo Loomi_UI::icon( $feature['icon'], 22 ); /* phpcs:ignore WordPress.Security.EscapeOutput */ ?></span>
							<span class="loomi-status-card__badge">
								<?php echo $feature['enabled']
									? '<span class="loomi-dot loomi-dot--on"></span>' . esc_html__( 'Ativo', 'loomi-studio-setup' )
									: '<span class="loomi-dot loomi-dot--off"></span>' . esc_html__( 'Inativo', 'loomi-studio-setup' );
								?>
							</span>
						</div>
						<h3 class="loomi-status-card__name"><?php echo esc_html( $feature['name'] ); ?></h3>
						<p class="loomi-status-card__desc"><?php echo esc_html( $feature['desc'] ); ?></p>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="loomi-section-title"><?php esc_html_e( 'Dependências', 'loomi-studio-setup' ); ?></div>

			<div class="loomi-deps">
				<?php
				$deps = [
					[
						'name'        => 'Wordfence Security',
						'icon'        => 'shield',
						'state'       => $wordfence_state,
						'desc'        => __( 'Firewall + rate-limit + IP block. Recomendado em todos os sites Loomi.', 'loomi-studio-setup' ),
						'wp_slug'     => 'wordfence',
						'plugin_file' => Plugin::WORDFENCE_FILE,
					],
					[
						'name'        => 'Advanced Custom Fields',
						'icon'        => 'puzzle',
						'state'       => $acf_state,
						'desc'        => __( 'Campos customizados pra editores Loomi. Free ou Pro — ambas detectadas.', 'loomi-studio-setup' ),
						'wp_slug'     => 'advanced-custom-fields',
						'plugin_file' => $acf_installed_file !== '' ? $acf_installed_file : $acf_free_file,
					],
				];
				foreach ( $deps as $dep ) :
					$action_label = '';
					$action_url   = '';
					$action_icon  = '';
					if ( $dep['state'] === 'absent' && current_user_can( 'install_plugins' ) ) {
						$action_label = __( 'Instalar', 'loomi-studio-setup' );
						$action_icon  = 'plus';
						$action_url   = wp_nonce_url(
							self_admin_url( 'update.php?action=install-plugin&plugin=' . $dep['wp_slug'] ),
							'install-plugin_' . $dep['wp_slug']
						);
					} elseif ( $dep['state'] === 'installed_inactive' && current_user_can( 'activate_plugins' ) ) {
						$action_label = __( 'Ativar', 'loomi-studio-setup' );
						$action_icon  = 'check';
						$action_url   = wp_nonce_url(
							self_admin_url( 'plugins.php?action=activate&plugin=' . $dep['plugin_file'] ),
							'activate-plugin_' . $dep['plugin_file']
						);
					}
					?>
					<div class="loomi-dep">
						<div class="loomi-dep__head">
							<span class="loomi-dep__icon" aria-hidden="true"><?php echo Loomi_UI::icon( $dep['icon'], 18 ); /* phpcs:ignore WordPress.Security.EscapeOutput */ ?></span>
							<strong><?php echo esc_html( $dep['name'] ); ?></strong>
							<?php if ( $dep['state'] === 'active' ) : ?>
								<span class="loomi-pill loomi-pill--on">
									<?php echo Loomi_UI::icon( 'check', 12 ); /* phpcs:ignore WordPress.Security.EscapeOutput */ ?>
									<?php esc_html_e( 'Ativo', 'loomi-studio-setup' ); ?>
								</span>
							<?php elseif ( $dep['state'] === 'installed_inactive' ) : ?>
								<span class="loomi-pill loomi-pill--warn">
									<?php echo Loomi_UI::icon( 'alert', 12 ); /* phpcs:ignore WordPress.Security.EscapeOutput */ ?>
									<?php esc_html_e( 'Instalado, inativo', 'loomi-studio-setup' ); ?>
								</span>
							<?php else : ?>
								<span class="loomi-pill loomi-pill--off">
									<?php echo Loomi_UI::icon( 'x', 12 ); /* phpcs:ignore WordPress.Security.EscapeOutput */ ?>
									<?php esc_html_e( 'Não instalado', 'loomi-studio-setup' ); ?>
								</span>
							<?php endif; ?>
						</div>
						<p class="loomi-dep__desc"><?php echo esc_html( $dep['desc'] ); ?></p>
						<?php if ( $action_url !== '' ) : ?>
							<div class="loomi-dep__actions">
								<a href="<?php echo esc_url( $action_url ); ?>" class="loomi-dep__action button button-small">
									<?php echo Loomi_UI::icon( $action_icon, 14 ); /* phpcs:ignore WordPress.Security.EscapeOutput */ ?>
									<?php echo esc_html( $action_label ); ?>
								</a>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="loomi-support">
				<div class="loomi-support__inner">
					<div>
						<h3 class="loomi-support__title"><?php esc_html_e( 'Suporte', 'loomi-studio-setup' ); ?></h3>
						<p class="loomi-support__text">
							<?php esc_html_e( 'Dúvida, bug, sugestão? Fale com o time de desenvolvimento da Loomi.', 'loomi-studio-setup' ); ?>
						</p>
					</div>
					<div class="loomi-support__actions">
						<a href="mailto:dev@loomi.studio" class="loomi-support__btn">
							<?php echo Loomi_UI::icon( 'mail', 16 ); /* phpcs:ignore WordPress.Security.EscapeOutput */ ?>
							dev@loomi.studio
						</a>
						<a href="https://loomi.studio" target="_blank" rel="noopener" class="loomi-support__btn loomi-support__btn--ghost">
							<?php echo Loomi_UI::icon( 'globe', 16 ); /* phpcs:ignore WordPress.Security.EscapeOutput */ ?>
							loomi.studio
						</a>
					</div>
				</div>
			</div>

			<div class="loomi-footer-meta">
				<span><?php esc_html_e( 'Versão', 'loomi-studio-setup' ); ?> <?php echo esc_html( Plugin::version() ); ?></span>
				<span>·</span>
				<span>WP <?php echo esc_html( get_bloginfo( 'version' ) ); ?></span>
				<span>·</span>
				<span>PHP <?php echo esc_html( PHP_VERSION ); ?></span>
			</div>

		</div>
		<?php
	}
}
