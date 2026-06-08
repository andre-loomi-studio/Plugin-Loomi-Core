<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tab_Login implements Loomi_Settings_Tab {

	public function slug() : string {
		return 'login';
	}

	public function label() : string {
		return __( 'Custom Login', 'loomi-studio-setup' );
	}

	public function render( array $s ) : void {
		$logo_url = $s['custom_login_logo_id'] ? wp_get_attachment_url( (int) $s['custom_login_logo_id'] ) : '';

		$customize_url = add_query_arg(
			[
				'url'                => rawurlencode( wp_login_url() ),
				'autofocus[section]' => 'loomi_login',
				'return'             => rawurlencode( admin_url( 'options-general.php?page=' . Plugin::SETTINGS_PAGE . '&tab=login' ) ),
			],
			admin_url( 'customize.php' )
		);
		?>
		<div class="loomi-customize-login-cta" style="margin: 0 0 24px; padding: 20px 24px; background: var(--loomi-bg-elevated); border: 1px solid var(--loomi-gray-200); border-radius: var(--loomi-radius);">
			<h3 style="margin: 0 0 6px; font-size: 15px; font-weight: 600;">
				<?php esc_html_e( 'Personalizar com preview ao vivo', 'loomi-studio-setup' ); ?>
			</h3>
			<p class="description" style="margin: 0 0 12px;">
				<?php esc_html_e( 'Abre o Customizer do WordPress com a tela de login no painel à direita. Ajuste cor de fundo, logo e dimensões e veja a mudança em tempo real antes de publicar.', 'loomi-studio-setup' ); ?>
			</p>
			<a href="<?php echo esc_url( $customize_url ); ?>" class="button button-primary">
				<?php esc_html_e( 'Personalizar tela de login →', 'loomi-studio-setup' ); ?>
			</a>
			<p class="description" style="margin: 12px 0 0; font-size: 12px;">
				<?php esc_html_e( 'Como você está logado, o iframe vai mostrar a mensagem "você já está conectado". A cor de fundo e o logo aparecem corretamente em volta — suficiente para previewar.', 'loomi-studio-setup' ); ?>
			</p>
		</div>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Ativar Custom Login', 'loomi-studio-setup' ); ?></th>
				<td>
					<?php Loomi_UI::toggle(
						'custom_login_enabled',
						(bool) $s['custom_login_enabled'],
						esc_html__( 'Aplicar cor de fundo e logo customizados na tela de login.', 'loomi-studio-setup' )
					); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="loomi-bg-color"><?php esc_html_e( 'Cor de fundo', 'loomi-studio-setup' ); ?></label></th>
				<td>
					<input type="text" id="loomi-bg-color" name="<?php echo esc_attr( Plugin::OPTION_KEY ); ?>[custom_login_bg_color]" value="<?php echo esc_attr( $s['custom_login_bg_color'] ); ?>" class="loomi-color-field" />
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
						<input type="hidden" id="loomi-logo-id" name="<?php echo esc_attr( Plugin::OPTION_KEY ); ?>[custom_login_logo_id]" value="<?php echo esc_attr( $s['custom_login_logo_id'] ); ?>" />
						<button type="button" class="button" id="loomi-logo-select"><?php esc_html_e( 'Selecionar logo', 'loomi-studio-setup' ); ?></button>
						<button type="button" class="button" id="loomi-logo-clear" <?php echo $logo_url ? '' : 'hidden'; ?>><?php esc_html_e( 'Remover', 'loomi-studio-setup' ); ?></button>
					</div>
				</td>
			</tr>
		</table>
		<?php
	}
}
