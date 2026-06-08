<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tab_Maps implements Loomi_Settings_Tab {

	public function slug() : string {
		return 'maps';
	}

	public function label() : string {
		return __( 'Maps', 'loomi-studio-setup' );
	}

	public function render( array $s ) : void {
		$opt_key = Plugin::OPTION_KEY;
		$prefix  = $opt_key . '[loomi_maps]';

		$m = isset( $s['loomi_maps'] ) && is_array( $s['loomi_maps'] ) ? $s['loomi_maps'] : [];

		$query     = isset( $m['query'] ) ? (string) $m['query'] : '';
		$embed_src = isset( $m['embed_src'] ) ? (string) $m['embed_src'] : '';
		$zoom      = isset( $m['zoom'] ) ? (int) $m['zoom'] : 16;
		$height    = isset( $m['height'] ) ? (int) $m['height'] : 400;
		$lazy      = array_key_exists( 'lazy', $m ) ? (bool) $m['lazy'] : true;
		$title     = isset( $m['title'] ) ? (string) $m['title'] : '';
		$autoinj   = ! empty( $s['loomi_maps_autoinject_home'] );
		?>
		<p class="description">
			<?php esc_html_e( 'Embed do Google Maps em iframe lazy-load, sem necessidade de API key. Use o shortcode [loomi_map] em qualquer página/post ou ative o auto-injetar na homepage.', 'loomi-studio-setup' ); ?>
		</p>

		<h2><?php esc_html_e( 'Configuração do mapa', 'loomi-studio-setup' ); ?></h2>

		<table class="form-table" role="presentation">

			<tr>
				<th scope="row"><label for="loomi-maps-query"><?php esc_html_e( 'Localização (texto)', 'loomi-studio-setup' ); ?></label></th>
				<td>
					<input type="text" id="loomi-maps-query" class="large-text"
						name="<?php echo esc_attr( $prefix ); ?>[query]"
						value="<?php echo esc_attr( $query ); ?>"
						placeholder="Rua João de Deus, 40, Venda Nova, 2700-489 Amadora, Portugal" />
					<p class="description">
						<?php esc_html_e( 'Endereço completo ou nome do estabelecimento. Quanto mais específico, melhor o pin.', 'loomi-studio-setup' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="loomi-maps-embed-src"><?php esc_html_e( 'URL oficial de embed (opcional)', 'loomi-studio-setup' ); ?></label></th>
				<td>
					<input type="text" id="loomi-maps-embed-src" class="large-text code"
						name="<?php echo esc_attr( $prefix ); ?>[embed_src]"
						value="<?php echo esc_attr( $embed_src ); ?>"
						placeholder="https://www.google.com/maps/embed?pb=..." />
					<p class="description">
						<?php
						printf(
							/* translators: %s: link de instruções */
							esc_html__( 'Cole aqui o URL gerado em Google Maps → Partilhar → Incorporar mapa (recomendado). Quando preenchido, tem prioridade sobre o campo de Localização. %s.', 'loomi-studio-setup' ),
							'<a href="https://support.google.com/maps/answer/144361" target="_blank" rel="noopener">' . esc_html__( 'Como obter', 'loomi-studio-setup' ) . '</a>'
						);
						?>
					</p>
					<p class="description">
						<?php esc_html_e( 'Aceitos: URL inteiro do src= ou o iframe completo (extraímos o src). Aceita apenas hosts maps.google.com / www.google.com / maps.googleapis.com em HTTPS.', 'loomi-studio-setup' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="loomi-maps-zoom"><?php esc_html_e( 'Zoom', 'loomi-studio-setup' ); ?></label></th>
				<td>
					<input type="number" id="loomi-maps-zoom" class="small-text"
						name="<?php echo esc_attr( $prefix ); ?>[zoom]"
						value="<?php echo esc_attr( $zoom ); ?>"
						min="<?php echo esc_attr( (string) Loomi_Maps::ZOOM_MIN ); ?>"
						max="<?php echo esc_attr( (string) Loomi_Maps::ZOOM_MAX ); ?>" />
					<span class="loomi-zoom-presets">
						<button type="button" class="button loomi-zoom-preset" data-zoom="13"><?php esc_html_e( 'Cidade (13)', 'loomi-studio-setup' ); ?></button>
						<button type="button" class="button loomi-zoom-preset" data-zoom="15"><?php esc_html_e( 'Bairro (15)', 'loomi-studio-setup' ); ?></button>
						<button type="button" class="button loomi-zoom-preset" data-zoom="17"><?php esc_html_e( 'Endereço (17)', 'loomi-studio-setup' ); ?></button>
					</span>
					<p class="description">
						<?php esc_html_e( 'Entre 1 (mundo) e 21 (rua). Sugestão: 16 para endereço único, 14 para uma cidade.', 'loomi-studio-setup' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="loomi-maps-height"><?php esc_html_e( 'Altura (px)', 'loomi-studio-setup' ); ?></label></th>
				<td>
					<input type="number" id="loomi-maps-height" class="small-text"
						name="<?php echo esc_attr( $prefix ); ?>[height]"
						value="<?php echo esc_attr( $height ); ?>"
						min="<?php echo esc_attr( (string) Loomi_Maps::HEIGHT_MIN ); ?>"
						max="<?php echo esc_attr( (string) Loomi_Maps::HEIGHT_MAX ); ?>" />
					<p class="description"><?php esc_html_e( 'Largura é sempre 100% (responsiva).', 'loomi-studio-setup' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><label for="loomi-maps-title"><?php esc_html_e( 'Título acessível', 'loomi-studio-setup' ); ?></label></th>
				<td>
					<input type="text" id="loomi-maps-title" class="regular-text"
						name="<?php echo esc_attr( $prefix ); ?>[title]"
						value="<?php echo esc_attr( $title ); ?>"
						placeholder="<?php esc_attr_e( 'Localização', 'loomi-studio-setup' ); ?>" />
					<p class="description"><?php esc_html_e( 'Atributo title do iframe (importante para leitores de ecrã).', 'loomi-studio-setup' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Lazy-load', 'loomi-studio-setup' ); ?></th>
				<td>
					<label>
						<input type="checkbox"
							name="<?php echo esc_attr( $prefix ); ?>[lazy]"
							value="1"
							<?php checked( $lazy, true ); ?> />
						<?php esc_html_e( 'Carregar o mapa só quando o usuário scrollar até ele (loading="lazy")', 'loomi-studio-setup' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Recomendado para mapas below-the-fold. Desative se o mapa estiver no hero (acima da dobra), porque lazy atrasaria o LCP.', 'loomi-studio-setup' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Auto-injeção', 'loomi-studio-setup' ); ?></h2>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Homepage', 'loomi-studio-setup' ); ?></th>
				<td>
					<label>
						<input type="checkbox"
							name="<?php echo esc_attr( $opt_key ); ?>[loomi_maps_autoinject_home]"
							value="1"
							<?php checked( $autoinj, true ); ?> />
						<?php esc_html_e( 'Injetar o mapa automaticamente no rodapé da homepage', 'loomi-studio-setup' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Quando ativado, o mapa aparece logo antes do </body> da home (via wp_footer). Para posicionamento controlado em outras páginas/posições, use o shortcode [loomi_map] em qualquer lugar do conteúdo.', 'loomi-studio-setup' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Shortcode', 'loomi-studio-setup' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Para colocar o mapa em qualquer lugar (página ou post), use:', 'loomi-studio-setup' ); ?>
		</p>
		<p><code>[loomi_map]</code> &mdash; <?php esc_html_e( 'usa a config global acima', 'loomi-studio-setup' ); ?></p>
		<p><code>[loomi_map zoom="14" height="350"]</code> &mdash; <?php esc_html_e( 'override de zoom e altura', 'loomi-studio-setup' ); ?></p>
		<p><code>[loomi_map q="Lisboa, Portugal"]</code> &mdash; <?php esc_html_e( 'query customizada (ex: outra cidade em landing geo)', 'loomi-studio-setup' ); ?></p>
		<p><code>[loomi_map lazy="0"]</code> &mdash; <?php esc_html_e( 'desativa lazy-load para esta instância', 'loomi-studio-setup' ); ?></p>
		<?php
	}
}
