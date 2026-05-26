<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tab_Hide_Menus implements Loomi_Settings_Tab {

	public function slug() : string {
		return 'hide-menus';
	}

	public function label() : string {
		return __( 'Esconder Menus', 'loomi-studio-setup' );
	}

	public function render( array $s ) : void {
		$core_menus = Settings_Repository::HIDEABLE_MENUS;
		$all_menus  = Settings_Repository::hideable_menus();
		$cpt_menus  = array_diff_key( $all_menus, $core_menus );

		// Ordenar CPTs alfabeticamente pelo label.
		asort( $cpt_menus, SORT_NATURAL | SORT_FLAG_CASE );

		$hidden = (array) ( $s['hidden_menus'] ?? [] );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Ativar ocultação', 'loomi-studio-setup' ); ?></th>
				<td>
					<?php Loomi_UI::toggle(
						'hide_menus_enabled',
						(bool) $s['hide_menus_enabled'],
						esc_html__( 'Esconder menus selecionados para usuários sem permissão de administrador.', 'loomi-studio-setup' )
					); ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Menus a esconder', 'loomi-studio-setup' ); ?></th>
				<td>
					<div class="notice notice-info inline" style="margin: 0 0 12px; padding: 8px 12px;">
						<p style="margin: 0;">
							<?php esc_html_e( 'Esta lista contém apenas menus que usuários sem permissão de administrador (editores, clientes Loomi) normalmente veem. O WordPress já esconde automaticamente Plugins, Aparência, Usuários e Configurações para usuários sem a capability correspondente.', 'loomi-studio-setup' ); ?>
						</p>
					</div>

					<fieldset>
						<legend><?php esc_html_e( 'WordPress', 'loomi-studio-setup' ); ?></legend>
						<div class="loomi-toggle-stack">
							<?php foreach ( $core_menus as $slug => $label ) : ?>
								<?php Loomi_UI::array_toggle(
									'hidden_menus',
									$slug,
									in_array( $slug, $hidden, true ),
									esc_html( $label ) . ' <code>' . esc_html( $slug ) . '</code>'
								); ?>
							<?php endforeach; ?>
						</div>
					</fieldset>

					<fieldset>
						<legend><?php esc_html_e( 'Custom Post Types', 'loomi-studio-setup' ); ?></legend>
						<?php if ( empty( $cpt_menus ) ) : ?>
							<p class="loomi-empty-state">
								<?php esc_html_e( 'Nenhum Custom Post Type encontrado neste site.', 'loomi-studio-setup' ); ?>
							</p>
						<?php else : ?>
							<div class="loomi-toggle-stack">
								<?php foreach ( $cpt_menus as $slug => $label ) : ?>
									<?php Loomi_UI::array_toggle(
										'hidden_menus',
										$slug,
										in_array( $slug, $hidden, true ),
										esc_html( $label ) . ' <code>' . esc_html( $slug ) . '</code>'
									); ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</fieldset>

					<p class="description"><?php esc_html_e( 'Dashboard e o menu de Configurações nunca podem ser escondidos.', 'loomi-studio-setup' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}
}
