<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tab_Anti_Spam implements Loomi_Settings_Tab {

	public function slug() : string {
		return 'anti-spam';
	}

	public function label() : string {
		return __( 'Anti-Spam', 'loomi-studio-setup' );
	}

	public function render( array $s ) : void {
		?>
		<div class="notice notice-info inline" style="margin: 0 0 12px; padding: 8px 12px;">
			<p style="margin: 0;">
				<?php esc_html_e( 'Proteção zero-config contra bots. Não exige reCAPTCHA, Akismet ou credencial externa. Combinado com Wordfence cobre >95% do spam genérico em forms nativos do WordPress (login, registro, comentários).', 'loomi-studio-setup' ); ?>
			</p>
		</div>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Ativar proteção anti-spam', 'loomi-studio-setup' ); ?></th>
				<td>
					<?php Loomi_UI::toggle(
						'anti_spam_enabled',
						! empty( $s['anti_spam_enabled'] ),
						esc_html__( 'Kill switch geral. Desligar desativa todas as técnicas abaixo.', 'loomi-studio-setup' )
					); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Técnicas', 'loomi-studio-setup' ); ?></th>
				<td>
					<div class="loomi-toggle-stack">
						<?php Loomi_UI::toggle(
							'anti_spam_honeypot',
							! empty( $s['anti_spam_honeypot'] ),
							'<strong>' . esc_html__( 'Honeypot', 'loomi-studio-setup' ) . '</strong> <span class="loomi-toggle-desc">— ' . esc_html__( 'Campo invisível em login, registro e comentários. Bot dumb preenche → rejeitamos.', 'loomi-studio-setup' ) . '</span>'
						); ?>
						<?php Loomi_UI::toggle(
							'anti_spam_time_check',
							! empty( $s['anti_spam_time_check'] ),
							'<strong>' . esc_html__( 'Time check', 'loomi-studio-setup' ) . '</strong> <span class="loomi-toggle-desc">— ' . esc_html__( 'Rejeita submissões em menos de 2 segundos do carregamento do form.', 'loomi-studio-setup' ) . '</span>'
						); ?>
						<?php Loomi_UI::toggle(
							'anti_spam_comment_lockdown',
							! empty( $s['anti_spam_comment_lockdown'] ),
							'<strong>' . esc_html__( 'Comment lockdown', 'loomi-studio-setup' ) . '</strong> <span class="loomi-toggle-desc">— ' . esc_html__( 'Desabilita pingback/trackback e força hold-for-moderation.', 'loomi-studio-setup' ) . '</span>'
						); ?>
					</div>
				</td>
			</tr>
		</table>
		<?php
	}
}
