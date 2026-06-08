<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tab_Schema implements Loomi_Settings_Tab {

	const DAYS = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];

	const COUNTRY_CODES = [
		'PT' => 'Portugal',
		'BR' => 'Brasil',
		'ES' => 'España',
		'US' => 'United States',
		'GB' => 'United Kingdom',
		'FR' => 'France',
		'DE' => 'Deutschland',
		'IT' => 'Italia',
		'NL' => 'Nederland',
		'BE' => 'België',
		'IE' => 'Ireland',
		'AT' => 'Österreich',
		'CH' => 'Schweiz',
		'LU' => 'Luxembourg',
		'PL' => 'Polska',
		'CZ' => 'Česko',
		'SE' => 'Sverige',
		'NO' => 'Norge',
		'DK' => 'Danmark',
		'FI' => 'Suomi',
		'GR' => 'Ελλάδα',
		'AR' => 'Argentina',
		'CL' => 'Chile',
		'MX' => 'México',
		'CO' => 'Colombia',
		'PE' => 'Perú',
		'UY' => 'Uruguay',
		'PY' => 'Paraguay',
		'BO' => 'Bolivia',
		'VE' => 'Venezuela',
		'EC' => 'Ecuador',
		'CA' => 'Canada',
		'AU' => 'Australia',
		'NZ' => 'New Zealand',
		'JP' => '日本',
		'KR' => '대한민국',
		'CN' => '中国',
		'IN' => 'India',
		'AO' => 'Angola',
		'MZ' => 'Moçambique',
		'CV' => 'Cabo Verde',
		'GW' => 'Guiné-Bissau',
		'ST' => 'São Tomé e Príncipe',
		'TL' => 'Timor-Leste',
	];

	public function slug() : string {
		return 'schema';
	}

	public function label() : string {
		return __( 'Schema', 'loomi-studio-setup' );
	}

	private function required_marker( string $tooltip = '' ) : string {
		$title = $tooltip !== '' ? $tooltip : __( 'Campo recomendado para LocalBusiness válido', 'loomi-studio-setup' );
		return ' <span class="loomi-required" title="' . esc_attr( $title ) . '">*</span>';
	}

	public function render( array $s ) : void {
		$g       = isset( $s['loomi_schema_global'] ) && is_array( $s['loomi_schema_global'] )
			? $s['loomi_schema_global']
			: [];
		$opt_key = Plugin::OPTION_KEY;
		$prefix  = $opt_key . '[loomi_schema_global]';

		$addr   = (array) ( $g['address'] ?? [] );
		$geo    = (array) ( $g['geo'] ?? [] );
		$hours  = (array) ( $g['openingHours'] ?? [] );
		$same   = (array) ( $g['sameAs'] ?? [] );
		$areas  = (array) ( $g['areaServed'] ?? [] );
		$ident  = (array) ( $g['identifier'] ?? [] );
		?>
		<p class="description">
			<?php esc_html_e( 'Dados globais do negócio usados como base do schema LocalBusiness/MedicalBusiness em todas as páginas. Defina aqui uma vez; o módulo Schema consome esses valores no frontend.', 'loomi-studio-setup' ); ?>
		</p>

		<?php include LOOMI_STUDIO_DIR . 'includes/settings/views/schema/view-schema-tab-auto-schemas.php'; ?>

		<h2><?php esc_html_e( 'Identificação', 'loomi-studio-setup' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><label><?php esc_html_e( 'Nome', 'loomi-studio-setup' ); ?><?php echo $this->required_marker(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML escapado em required_marker() ?></label></th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[name]" value="<?php echo esc_attr( $g['name'] ?? '' ); ?>" /></td>
			</tr>
			<tr><th scope="row"><label><?php esc_html_e( 'Nome alternativo', 'loomi-studio-setup' ); ?></label></th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[alternateName]" value="<?php echo esc_attr( $g['alternateName'] ?? '' ); ?>" /></td>
			</tr>
			<tr><th scope="row"><label><?php esc_html_e( 'Descrição', 'loomi-studio-setup' ); ?></label></th>
				<td><textarea class="large-text" rows="2" name="<?php echo esc_attr( $prefix ); ?>[description]"><?php echo esc_textarea( $g['description'] ?? '' ); ?></textarea></td>
			</tr>
			<tr><th scope="row"><label><?php esc_html_e( 'Telefone', 'loomi-studio-setup' ); ?><?php echo $this->required_marker(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[telephone]" value="<?php echo esc_attr( $g['telephone'] ?? '' ); ?>" placeholder="+351936271109" /></td>
			</tr>
			<tr><th scope="row"><label><?php esc_html_e( 'E-mail', 'loomi-studio-setup' ); ?></label></th>
				<td><input type="email" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[email]" value="<?php echo esc_attr( $g['email'] ?? '' ); ?>" /></td>
			</tr>
			<tr><th scope="row"><label><?php esc_html_e( 'Faixa de preço', 'loomi-studio-setup' ); ?></label></th>
				<td>
					<input type="text" class="small-text" name="<?php echo esc_attr( $prefix ); ?>[priceRange]" value="<?php echo esc_attr( $g['priceRange'] ?? '' ); ?>" placeholder="€€" />
					<span class="loomi-pricerange-presets">
						<button type="button" class="button loomi-pricerange-btn" data-price="€">€</button>
						<button type="button" class="button loomi-pricerange-btn" data-price="€€">€€</button>
						<button type="button" class="button loomi-pricerange-btn" data-price="€€€">€€€</button>
						<button type="button" class="button loomi-pricerange-btn" data-price="€€€€">€€€€</button>
					</span>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Endereço (PostalAddress)', 'loomi-studio-setup' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><label><?php esc_html_e( 'Rua', 'loomi-studio-setup' ); ?><?php echo $this->required_marker(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[address][streetAddress]" value="<?php echo esc_attr( $addr['streetAddress'] ?? '' ); ?>" /></td>
			</tr>
			<tr><th scope="row"><label><?php esc_html_e( 'Localidade', 'loomi-studio-setup' ); ?><?php echo $this->required_marker(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[address][addressLocality]" value="<?php echo esc_attr( $addr['addressLocality'] ?? '' ); ?>" /></td>
			</tr>
			<tr><th scope="row"><label><?php esc_html_e( 'Região', 'loomi-studio-setup' ); ?></label></th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[address][addressRegion]" value="<?php echo esc_attr( $addr['addressRegion'] ?? '' ); ?>" /></td>
			</tr>
			<tr><th scope="row"><label><?php esc_html_e( 'Código postal', 'loomi-studio-setup' ); ?><?php echo $this->required_marker(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[address][postalCode]" value="<?php echo esc_attr( $addr['postalCode'] ?? '' ); ?>" /></td>
			</tr>
			<?php
			$current_country = (string) ( $addr['addressCountry'] ?? '' );
			$is_known        = $current_country !== '' && array_key_exists( $current_country, self::COUNTRY_CODES );
			$is_other        = $current_country !== '' && ! $is_known;
			?>
			<tr>
				<th scope="row"><label for="loomi-schema-country"><?php esc_html_e( 'País (ISO)', 'loomi-studio-setup' ); ?><?php echo $this->required_marker(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
				<td>
					<select name="<?php echo esc_attr( $prefix ); ?>[address][addressCountry]" id="loomi-schema-country">
						<option value=""><?php esc_html_e( 'Selecione um país…', 'loomi-studio-setup' ); ?></option>
						<?php foreach ( self::COUNTRY_CODES as $code => $name ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $current_country, $code ); ?>><?php echo esc_html( $name . ' (' . $code . ')' ); ?></option>
						<?php endforeach; ?>
						<option value="other" <?php selected( $is_other, true ); ?>><?php esc_html_e( 'Outro…', 'loomi-studio-setup' ); ?></option>
					</select>
					<input type="text"
						name="<?php echo esc_attr( $prefix ); ?>[address][addressCountryOther]"
						id="loomi-schema-country-other"
						maxlength="2"
						placeholder="XX"
						value="<?php echo esc_attr( $is_other ? $current_country : '' ); ?>"
						style="display: <?php echo $is_other ? 'inline-block' : 'none'; ?>; margin-left: 8px; width: 60px;" />
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Geolocalização', 'loomi-studio-setup' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="loomi-schema-maps-url"><?php esc_html_e( 'URL do Google Maps', 'loomi-studio-setup' ); ?></label></th>
				<td>
					<input type="url" id="loomi-schema-maps-url" class="large-text code"
						placeholder="https://www.google.com/maps/place/..." />
					<button type="button" class="button" id="loomi-schema-extract-coords">
						<?php esc_html_e( 'Extrair coordenadas', 'loomi-studio-setup' ); ?>
					</button>
					<div class="loomi-extract-feedback"></div>
					<p class="description">
						<?php esc_html_e( 'Cole o URL completo do Google Maps; vamos extrair lat/lng automaticamente.', 'loomi-studio-setup' ); ?>
					</p>
				</td>
			</tr>
			<tr><th scope="row"><label><?php esc_html_e( 'Latitude', 'loomi-studio-setup' ); ?></label></th>
				<td><input type="number" step="0.000001" min="-90" max="90" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[geo][latitude]" value="<?php echo esc_attr( $geo['latitude'] ?? '' ); ?>" /></td>
			</tr>
			<tr><th scope="row"><label><?php esc_html_e( 'Longitude', 'loomi-studio-setup' ); ?></label></th>
				<td><input type="number" step="0.000001" min="-180" max="180" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[geo][longitude]" value="<?php echo esc_attr( $geo['longitude'] ?? '' ); ?>" /></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Horário de funcionamento', 'loomi-studio-setup' ); ?></h2>
		<div class="loomi-schema-hours" data-prefix="<?php echo esc_attr( $prefix . '[openingHours]' ); ?>">
			<?php
			$rows = $hours ?: [ [ 'days' => self::DAYS, 'opens' => '09:00', 'closes' => '17:00' ] ];
			foreach ( $rows as $i => $row ) :
				$days = (array) ( $row['days'] ?? [] );
				?>
				<div class="loomi-schema-hours__row" data-row-index="<?php echo (int) $i; ?>">
					<div class="loomi-schema-days">
						<?php foreach ( self::DAYS as $day ) : ?>
							<label class="loomi-schema-day">
								<input type="checkbox"
									name="<?php echo esc_attr( $prefix ); ?>[openingHours][<?php echo (int) $i; ?>][days][]"
									value="<?php echo esc_attr( $day ); ?>"
									<?php checked( in_array( $day, $days, true ), true ); ?> />
								<?php echo esc_html( $day ); ?>
							</label>
						<?php endforeach; ?>
					</div>
					<label><?php esc_html_e( 'Abre', 'loomi-studio-setup' ); ?>
						<input type="time" name="<?php echo esc_attr( $prefix ); ?>[openingHours][<?php echo (int) $i; ?>][opens]" value="<?php echo esc_attr( $row['opens'] ?? '' ); ?>" />
					</label>
					<label><?php esc_html_e( 'Fecha', 'loomi-studio-setup' ); ?>
						<input type="time" name="<?php echo esc_attr( $prefix ); ?>[openingHours][<?php echo (int) $i; ?>][closes]" value="<?php echo esc_attr( $row['closes'] ?? '' ); ?>" />
					</label>
					<button type="button" class="loomi-schema-hours__remove" aria-label="<?php esc_attr_e( 'Remover horário', 'loomi-studio-setup' ); ?>">&#10005; <?php esc_html_e( 'Remover', 'loomi-studio-setup' ); ?></button>
				</div>
			<?php endforeach; ?>
			<p class="description"><?php esc_html_e( 'Marque os dias da semana e defina o horário. Use "+ Adicionar horário" para incluir um novo grupo de dias com horários diferentes.', 'loomi-studio-setup' ); ?></p>
			<p>
				<button type="button" class="button button-secondary loomi-schema-hours__add">+ <?php esc_html_e( 'Adicionar horário', 'loomi-studio-setup' ); ?></button>
			</p>
		</div>

		<template id="loomi-schema-hours-template">
			<div class="loomi-schema-hours__row" data-row-index="__INDEX__">
				<div class="loomi-schema-days">
					<?php foreach ( self::DAYS as $day ) : ?>
						<label class="loomi-schema-day">
							<input type="checkbox"
								name="<?php echo esc_attr( $prefix ); ?>[openingHours][__INDEX__][days][]"
								value="<?php echo esc_attr( $day ); ?>" />
							<?php echo esc_html( $day ); ?>
						</label>
					<?php endforeach; ?>
				</div>
				<label><?php esc_html_e( 'Abre', 'loomi-studio-setup' ); ?>
					<input type="time" name="<?php echo esc_attr( $prefix ); ?>[openingHours][__INDEX__][opens]" />
				</label>
				<label><?php esc_html_e( 'Fecha', 'loomi-studio-setup' ); ?>
					<input type="time" name="<?php echo esc_attr( $prefix ); ?>[openingHours][__INDEX__][closes]" />
				</label>
				<button type="button" class="loomi-schema-hours__remove" aria-label="<?php esc_attr_e( 'Remover horário', 'loomi-studio-setup' ); ?>">&#10005; <?php esc_html_e( 'Remover', 'loomi-studio-setup' ); ?></button>
			</div>
		</template>

		<h2><?php esc_html_e( 'Áreas atendidas', 'loomi-studio-setup' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Uma cidade por linha (ex: Lisboa, Amadora, Oeiras).', 'loomi-studio-setup' ); ?></p>
		<textarea class="large-text code" rows="4" name="<?php echo esc_attr( $prefix ); ?>[areaServed]"><?php echo esc_textarea( implode( "\n", $areas ) ); ?></textarea>

		<h2><?php esc_html_e( 'Perfis sociais (sameAs)', 'loomi-studio-setup' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Uma URL por linha (Facebook, Instagram, LinkedIn, etc).', 'loomi-studio-setup' ); ?></p>
		<textarea class="large-text code" rows="4" name="<?php echo esc_attr( $prefix ); ?>[sameAs]"><?php echo esc_textarea( implode( "\n", $same ) ); ?></textarea>
		<ul class="loomi-sameas-errors" style="display:none;"></ul>

		<h2><?php esc_html_e( 'Identificador (opcional)', 'loomi-studio-setup' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><label><?php esc_html_e( 'Property ID', 'loomi-studio-setup' ); ?></label></th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[identifier][propertyID]" value="<?php echo esc_attr( $ident['propertyID'] ?? '' ); ?>" placeholder="Licença SS" /></td>
			</tr>
			<tr><th scope="row"><label><?php esc_html_e( 'Valor', 'loomi-studio-setup' ); ?></label></th>
				<td><input type="text" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[identifier][value]" value="<?php echo esc_attr( $ident['value'] ?? '' ); ?>" placeholder="59/2025" /></td>
			</tr>
		</table>

		<hr>
		<h2><?php esc_html_e( 'Preview JSON-LD', 'loomi-studio-setup' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Veja o JSON-LD que seria emitido com os valores atuais (sem salvar).', 'loomi-studio-setup' ); ?></p>
		<p>
			<button type="button" class="button" id="loomi-schema-preview-btn">
				<?php esc_html_e( 'Visualizar JSON-LD', 'loomi-studio-setup' ); ?>
			</button>
		</p>
		<pre id="loomi-schema-preview-output" class="loomi-schema-preview-output" hidden></pre>
		<?php
		// JS lives in assets/schema-tab.js (enqueued by Loomi_Settings_Page::enqueue_assets).
		// CSS lives in assets/schema-admin.css.
	}
}
