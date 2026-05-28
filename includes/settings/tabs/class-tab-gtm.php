<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tab_GTM implements Loomi_Settings_Tab {

	public function slug() : string {
		return 'gtm';
	}

	public function label() : string {
		return __( 'Tag Manager', 'loomi-studio-setup' );
	}

	public function render( array $s ) : void {
		$opt_key = Plugin::OPTION_KEY;
		$id      = isset( $s['loomi_gtm_id'] ) ? (string) $s['loomi_gtm_id'] : '';
		?>
		<p class="description">
			<?php esc_html_e( 'Cole o ID do container do Google Tag Manager. Os snippets oficiais (head + noscript body) são injetados automaticamente em todas as páginas públicas do site.', 'loomi-studio-setup' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="loomi-gtm-id"><?php esc_html_e( 'ID do Container', 'loomi-studio-setup' ); ?></label></th>
				<td>
					<input
						type="text"
						id="loomi-gtm-id"
						class="regular-text code"
						name="<?php echo esc_attr( $opt_key ); ?>[loomi_gtm_id]"
						value="<?php echo esc_attr( $id ); ?>"
						placeholder="GTM-XXXXXX"
						pattern="^GTM-[A-Za-z0-9]{4,10}$"
						maxlength="14"
						autocomplete="off"
						spellcheck="false"
					/>
					<p class="description">
						<?php
						printf(
							/* translators: %s: link para o Tag Manager */
							esc_html__( 'Encontre o ID em %s (formato: GTM-XXXXXX). Deixe vazio para desativar a injeção.', 'loomi-studio-setup' ),
							'<a href="https://tagmanager.google.com/" target="_blank" rel="noopener">tagmanager.google.com</a>'
						);
						?>
					</p>
					<p class="description">
						<strong><?php esc_html_e( 'Atenção:', 'loomi-studio-setup' ); ?></strong>
						<?php esc_html_e( 'desative qualquer outro plugin ou snippet de GTM no tema antes de salvar, ou o container será carregado duas vezes e os dados de analytics duplicarão.', 'loomi-studio-setup' ); ?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Se o site usa Content Security Policy estrito (sem unsafe-inline e sem nonce), o snippet inline será bloqueado pelo navegador. Configure um nonce CSP ou ajuste a política.', 'loomi-studio-setup' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}
}
