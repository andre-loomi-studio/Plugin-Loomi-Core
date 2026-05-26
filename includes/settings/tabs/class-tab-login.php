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
		?>
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
