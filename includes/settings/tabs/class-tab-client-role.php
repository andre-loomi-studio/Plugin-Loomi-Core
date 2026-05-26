<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tab_Client_Role implements Loomi_Settings_Tab {

	public function slug() : string {
		return 'client-role';
	}

	public function label() : string {
		return __( 'Role Cliente', 'loomi-studio-setup' );
	}

	public function render( array $s ) : void {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Ativar role Cliente Loomi', 'loomi-studio-setup' ); ?></th>
				<td>
					<?php Loomi_UI::toggle(
						'client_role_enabled',
						(bool) $s['client_role_enabled'],
						esc_html__( 'Mostrar a role "Cliente Loomi" na lista de papéis ao criar/editar usuários.', 'loomi-studio-setup' )
					); ?>
					<p class="description"><?php esc_html_e( 'A role existe enquanto o plugin estiver ativo. Desativar este toggle apenas a esconde do dropdown.', 'loomi-studio-setup' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}
}
