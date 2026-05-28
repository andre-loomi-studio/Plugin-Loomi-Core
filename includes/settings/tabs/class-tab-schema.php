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
				<td><input type="text" class="small-text" name="<?php echo esc_attr( $prefix ); ?>[priceRange]" value="<?php echo esc_attr( $g['priceRange'] ?? '' ); ?>" placeholder="€€" /></td>
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
		<pre id="loomi-schema-preview-output" class="loomi-schema-preview-output" style="display:none;"></pre>

		<script>
			(function () {
				var btn = document.getElementById('loomi-schema-preview-btn');
				var out = document.getElementById('loomi-schema-preview-output');
				if (!btn || !out) return;

				var nonce  = <?php echo wp_json_encode( wp_create_nonce( 'loomi_schema_preview' ) ); ?>;
				var url    = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
				var prefix = <?php echo wp_json_encode( $prefix ); ?>;

				btn.addEventListener('click', function () {
					out.style.display = 'block';
					out.textContent   = <?php echo wp_json_encode( __( 'Carregando…', 'loomi-studio-setup' ) ); ?>;

					var form = btn.closest('form');
					if (!form) {
						out.textContent = <?php echo wp_json_encode( __( 'Erro: formulário não encontrado.', 'loomi-studio-setup' ) ); ?>;
						return;
					}

					var fd = new FormData();
					fd.append('action', 'loomi_schema_preview');
					fd.append('_wpnonce', nonce);

					// Collect every field from this tab that lives under loomi_schema_global
					form.querySelectorAll('[name^="' + prefix + '"]').forEach(function (el) {
						if (el.disabled) return;
						if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
						// Rewrite the option-key prefix to a bare key for handle_preview()
						var translated = el.name.replace(prefix, 'loomi_schema_global');
						fd.append(translated, el.value);
					});

					fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
						.then(function (r) { return r.json(); })
						.then(function (data) {
							if (data && data.success) {
								out.textContent = JSON.stringify(data.data.jsonld, null, 2);
							} else {
								var msg = (data && data.data && data.data.message) ? data.data.message : 'desconhecido';
								out.textContent = 'Erro: ' + msg;
							}
						})
						.catch(function (e) { out.textContent = 'Erro: ' + e.message; });
				});
			})();
		</script>

		<style>
			.loomi-schema-days { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
			.loomi-schema-day { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; }
			.loomi-schema-hours__row { display: flex; gap: 16px; align-items: flex-start; flex-wrap: wrap; margin-bottom: 16px; padding: 12px; border: 1px solid var(--loomi-border, #444); border-radius: 4px; }
			.loomi-schema-hours__remove { color: #d63638; cursor: pointer; background: none; border: none; padding: 4px 8px; }
			.loomi-schema-hours__remove:hover { background: rgba(214, 54, 56, 0.1); border-radius: 4px; }
		</style>

		<script>
			(function () {
				var container = document.querySelector('.loomi-schema-hours');
				if (!container) return;
				var template  = document.getElementById('loomi-schema-hours-template');
				var addBtn    = document.querySelector('.loomi-schema-hours__add');

				function updateRemoveButtons() {
					var rows = container.querySelectorAll('.loomi-schema-hours__row');
					rows.forEach(function (row) {
						var btn = row.querySelector('.loomi-schema-hours__remove');
						if (btn) btn.style.display = rows.length > 1 ? '' : 'none';
					});
				}

				function reindex() {
					var rows = container.querySelectorAll('.loomi-schema-hours__row');
					rows.forEach(function (row, idx) {
						row.setAttribute('data-row-index', idx);
						row.querySelectorAll('[name*="[openingHours]["]').forEach(function (input) {
							input.name = input.name.replace(/\[openingHours\]\[\d+\]/, '[openingHours][' + idx + ']');
							input.name = input.name.replace(/\[openingHours\]\[__INDEX__\]/, '[openingHours][' + idx + ']');
						});
					});
				}

				if (addBtn && template) {
					addBtn.addEventListener('click', function () {
						var clone = template.content.cloneNode(true);
						// Insert before the description <p> / add button block: append to container then move add button below.
						// Simpler: insert clone before the description paragraph if present, else append.
						var desc = container.querySelector('p.description');
						if (desc) {
							container.insertBefore(clone, desc);
						} else {
							container.appendChild(clone);
						}
						reindex();
						updateRemoveButtons();
					});
				}

				container.addEventListener('click', function (e) {
					var removeBtn = e.target.closest('.loomi-schema-hours__remove');
					if (!removeBtn) return;
					var row = removeBtn.closest('.loomi-schema-hours__row');
					if (row && container.querySelectorAll('.loomi-schema-hours__row').length > 1) {
						row.remove();
						reindex();
						updateRemoveButtons();
					}
				});

				updateRemoveButtons();
			})();
		</script>

		<script>
			document.querySelectorAll('input[name*="[geo][latitude]"], input[name*="[geo][longitude]"]')
				.forEach(function (el) {
					el.addEventListener('input', function (e) {
						if (e.target.value.indexOf(',') !== -1) {
							e.target.value = e.target.value.replace(',', '.');
						}
					});
				});
		</script>

		<script>
			(function () {
				var ta = document.querySelector('textarea[name*="[sameAs]"]');
				if (!ta) return;
				var errList = document.querySelector('.loomi-sameas-errors');
				if (!errList) return;

				function validate() {
					var errors = [];
					var lines = ta.value.split('\n');
					lines.forEach(function (line, idx) {
						var trimmed = line.trim();
						if (trimmed === '') return;
						if (!/^https?:\/\/[^\s]+$/i.test(trimmed)) {
							errors.push('Linha ' + (idx + 1) + ': URL inválida (' + trimmed.substring(0, 40) + ')');
						}
					});

					if (errors.length === 0) {
						errList.style.display = 'none';
						errList.innerHTML = '';
					} else {
						errList.innerHTML = errors.map(function (e) { return '<li>' + e.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</li>'; }).join('');
						errList.style.display = 'block';
					}
				}

				ta.addEventListener('blur', validate);
				// Validate on initial load if textarea has content
				if (ta.value.trim() !== '') validate();
			})();
		</script>

		<script>
			(function () {
				var sel = document.getElementById('loomi-schema-country');
				var other = document.getElementById('loomi-schema-country-other');
				if (!sel || !other) return;
				function sync() {
					other.style.display = (sel.value === 'other') ? 'inline-block' : 'none';
				}
				sel.addEventListener('change', sync);
			})();
		</script>
		<?php
	}
}
