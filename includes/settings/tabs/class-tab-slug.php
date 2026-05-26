<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tab_Slug implements Loomi_Settings_Tab {

	public function slug() : string {
		return 'login-slug';
	}

	public function label() : string {
		return __( 'Login Slug', 'loomi-studio-setup' );
	}

	public function render( array $s ) : void {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Ativar slug customizada', 'loomi-studio-setup' ); ?></th>
				<td>
					<?php Loomi_UI::toggle(
						'login_slug_enabled',
						(bool) $s['login_slug_enabled'],
						esc_html__( 'Bloqueia o acesso direto a /wp-login.php para visitantes não logados.', 'loomi-studio-setup' )
					); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="loomi-login-slug"><?php esc_html_e( 'Slug de login', 'loomi-studio-setup' ); ?></label></th>
				<td>
					<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code>
					<input type="text" id="loomi-login-slug" name="<?php echo esc_attr( Plugin::OPTION_KEY ); ?>[login_slug]" value="<?php echo esc_attr( $s['login_slug'] ); ?>" class="regular-text" />
					<p class="description"><?php esc_html_e( 'Default: studio-access. Caracteres inválidos serão removidos; slugs reservadas (wp-admin, login, etc.) são rejeitadas.', 'loomi-studio-setup' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Esconder /wp-admin/', 'loomi-studio-setup' ); ?></th>
				<td>
					<?php Loomi_UI::toggle(
						'hide_admin_endpoint',
						! empty( $s['hide_admin_endpoint'] ),
						esc_html__( 'Bloquear /wp-admin/ para visitantes não autenticados (recomendado).', 'loomi-studio-setup' )
					); ?>
					<p class="description">
						<?php esc_html_e( 'Quando ativo, requests não autenticadas a /wp-admin/ retornam 404 em vez de redirecionar para a slug customizada. Isso evita que scanners descubram a slug inspecionando o header Location.', 'loomi-studio-setup' ); ?>
						<br>
						<strong><?php esc_html_e( 'Trade-off:', 'loomi-studio-setup' ); ?></strong>
						<?php esc_html_e( 'admin precisa lembrar do slug — digitar /wp-admin/ no browser também levará a 404. Caso fique trancado, defina LOOMI_STUDIO_DISABLE_HARDENING = true em wp-config.php para desativar temporariamente.', 'loomi-studio-setup' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}
}
