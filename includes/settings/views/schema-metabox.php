<?php
/**
 * View: Meta box "Schema desta página".
 *
 * Renderizado por Loomi_Schema::render_metabox().
 *
 * Variáveis disponíveis no escopo:
 *
 * @var WP_Post $post
 * @var string  $type      Um dos Loomi_Schema::TYPE_*.
 * @var array   $data      Dados salvos em _loomi_schema_data.
 * @var array   $global    Settings globais (chaves opcionais: name, address.addressLocality, geo.latitude, geo.longitude).
 * @var string  $permalink URL pública do post (vazio se auto-draft).
 *
 * O nonce já foi emitido em Loomi_Schema::render_metabox().
 */

defined( 'ABSPATH' ) || exit;

$types = Loomi_Schema::types();

// Helpers de dados saneados para os campos.
$lb_name     = isset( $data['name'] ) ? (string) $data['name'] : '';
$lb_locality = isset( $data['addressLocality'] ) ? (string) $data['addressLocality'] : '';
$lb_lat      = isset( $data['latitude'] ) ? (string) $data['latitude'] : '';
$lb_lng      = isset( $data['longitude'] ) ? (string) $data['longitude'] : '';

$svc_type        = isset( $data['serviceType'] ) ? (string) $data['serviceType'] : '';
$svc_description = isset( $data['description'] ) ? (string) $data['description'] : '';
$svc_area        = isset( $data['areaServed'] ) && is_array( $data['areaServed'] ) ? array_values( $data['areaServed'] ) : array();
if ( empty( $svc_area ) ) {
	$svc_area = array( '' );
}

$faq_rows = isset( $data['faq'] ) && is_array( $data['faq'] ) ? array_values( $data['faq'] ) : array();
if ( empty( $faq_rows ) ) {
	$faq_rows = array( array( 'question' => '', 'answer' => '' ) );
}

$custom_json = isset( $data['custom_json'] ) ? (string) $data['custom_json'] : '';

// Placeholders globais para LocalBusiness (dica visual).
$ph_name     = isset( $global['name'] ) ? (string) $global['name'] : '';
$ph_locality = isset( $global['address']['addressLocality'] ) ? (string) $global['address']['addressLocality'] : '';
$ph_lat      = isset( $global['geo']['latitude'] ) ? (string) $global['geo']['latitude'] : '';
$ph_lng      = isset( $global['geo']['longitude'] ) ? (string) $global['geo']['longitude'] : '';

// Helper inline para visibilidade inicial das seções.
$is_section = static function ( $section_type ) use ( $type ) {
	return $type === $section_type;
};
?>
<div class="loomi-schema-metabox">

	<p>
		<label for="loomi_schema_type"><strong><?php esc_html_e( 'Tipo de Schema', 'loomi-studio' ); ?></strong></label><br />
		<select name="loomi_schema_type" id="loomi_schema_type" class="regular-text">
			<?php foreach ( $types as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>

	<?php // ---------- LocalBusiness ---------- ?>
	<div data-schema-section="<?php echo esc_attr( Loomi_Schema::TYPE_LOCAL_BUSINESS ); ?>" <?php echo $is_section( Loomi_Schema::TYPE_LOCAL_BUSINESS ) ? '' : 'hidden'; ?>>
		<p class="description">
			<?php esc_html_e( 'Deixe em branco para herdar do global.', 'loomi-studio' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="loomi_schema_lb_name"><?php esc_html_e( 'Nome', 'loomi-studio' ); ?></label></th>
					<td>
						<input
							type="text"
							id="loomi_schema_lb_name"
							name="loomi_schema[name]"
							class="regular-text"
							value="<?php echo esc_attr( $lb_name ); ?>"
							placeholder="<?php echo esc_attr( $ph_name ); ?>"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="loomi_schema_lb_locality"><?php esc_html_e( 'Cidade (addressLocality)', 'loomi-studio' ); ?></label></th>
					<td>
						<input
							type="text"
							id="loomi_schema_lb_locality"
							name="loomi_schema[addressLocality]"
							class="regular-text"
							value="<?php echo esc_attr( $lb_locality ); ?>"
							placeholder="<?php echo esc_attr( $ph_locality ); ?>"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="loomi_schema_lb_lat"><?php esc_html_e( 'Latitude', 'loomi-studio' ); ?></label></th>
					<td>
						<input
							type="number"
							id="loomi_schema_lb_lat"
							name="loomi_schema[latitude]"
							class="regular-text"
							step="0.000001"
							min="-90"
							max="90"
							value="<?php echo esc_attr( $lb_lat ); ?>"
							placeholder="<?php echo esc_attr( $ph_lat ); ?>"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="loomi_schema_lb_lng"><?php esc_html_e( 'Longitude', 'loomi-studio' ); ?></label></th>
					<td>
						<input
							type="number"
							id="loomi_schema_lb_lng"
							name="loomi_schema[longitude]"
							class="regular-text"
							step="0.000001"
							min="-180"
							max="180"
							value="<?php echo esc_attr( $lb_lng ); ?>"
							placeholder="<?php echo esc_attr( $ph_lng ); ?>"
						/>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<?php // ---------- Service ---------- ?>
	<div data-schema-section="<?php echo esc_attr( Loomi_Schema::TYPE_SERVICE ); ?>" <?php echo $is_section( Loomi_Schema::TYPE_SERVICE ) ? '' : 'hidden'; ?>>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="loomi_schema_svc_type">
							<?php esc_html_e( 'Tipo de serviço', 'loomi-studio' ); ?>
							<span style="color:#d63638;" aria-hidden="true">*</span>
						</label>
					</th>
					<td>
						<input
							type="text"
							id="loomi_schema_svc_type"
							name="loomi_schema[serviceType]"
							class="regular-text"
							value="<?php echo esc_attr( $svc_type ); ?>"
							required
						/>
						<p class="description"><?php esc_html_e( 'Ex.: "Limpeza de fossa", "Consultoria contábil".', 'loomi-studio' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="loomi_schema_svc_description"><?php esc_html_e( 'Descrição', 'loomi-studio' ); ?></label></th>
					<td>
						<textarea
							id="loomi_schema_svc_description"
							name="loomi_schema[description]"
							class="large-text"
							rows="4"
						><?php echo esc_textarea( $svc_description ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Áreas atendidas', 'loomi-studio' ); ?></th>
					<td>
						<div data-loomi-area-repeater>
							<?php foreach ( $svc_area as $idx => $city ) : ?>
								<div class="loomi-area-row" style="margin-bottom:6px; display:flex; gap:6px; align-items:center;">
									<input
										type="text"
										name="loomi_schema[areaServed][]"
										class="regular-text"
										value="<?php echo esc_attr( $city ); ?>"
										placeholder="<?php esc_attr_e( 'Cidade atendida', 'loomi-studio' ); ?>"
									/>
									<button
										type="button"
										class="button button-secondary"
										data-loomi-area-remove
									><?php esc_html_e( 'Remover', 'loomi-studio' ); ?></button>
								</div>
							<?php endforeach; ?>
						</div>
						<p>
							<button
								type="button"
								class="button"
								data-loomi-area-add
							>+ <?php esc_html_e( 'Adicionar cidade', 'loomi-studio' ); ?></button>
						</p>

						<template id="loomi-area-template">
							<div class="loomi-area-row" style="margin-bottom:6px; display:flex; gap:6px; align-items:center;">
								<input
									type="text"
									name="loomi_schema[areaServed][]"
									class="regular-text"
									value=""
									placeholder="<?php esc_attr_e( 'Cidade atendida', 'loomi-studio' ); ?>"
								/>
								<button
									type="button"
									class="button button-secondary"
									data-loomi-area-remove
								><?php esc_html_e( 'Remover', 'loomi-studio' ); ?></button>
							</div>
						</template>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<?php // ---------- FAQPage ---------- ?>
	<div data-schema-section="<?php echo esc_attr( Loomi_Schema::TYPE_FAQ_PAGE ); ?>" <?php echo $is_section( Loomi_Schema::TYPE_FAQ_PAGE ) ? '' : 'hidden'; ?>>
		<p class="description">
			<?php esc_html_e( 'Cada pergunta/resposta vira um item Question dentro do FAQPage.', 'loomi-studio' ); ?>
		</p>

		<div data-loomi-faq-repeater>
			<?php foreach ( $faq_rows as $idx => $row ) :
				$q = isset( $row['question'] ) ? (string) $row['question'] : '';
				$a = isset( $row['answer'] ) ? (string) $row['answer'] : '';
				?>
				<div class="loomi-faq-row" style="border:1px solid #c3c4c7; padding:10px; margin-bottom:10px; background:#fff;">
					<p>
						<label><strong><?php esc_html_e( 'Pergunta', 'loomi-studio' ); ?></strong></label><br />
						<input
							type="text"
							name="loomi_schema[faq][<?php echo esc_attr( (string) $idx ); ?>][question]"
							class="large-text"
							value="<?php echo esc_attr( $q ); ?>"
							data-loomi-faq-field="question"
						/>
					</p>
					<p>
						<label><strong><?php esc_html_e( 'Resposta', 'loomi-studio' ); ?></strong></label><br />
						<textarea
							name="loomi_schema[faq][<?php echo esc_attr( (string) $idx ); ?>][answer]"
							class="large-text"
							rows="3"
							data-loomi-faq-field="answer"
						><?php echo esc_textarea( $a ); ?></textarea>
					</p>
					<p>
						<button
							type="button"
							class="button button-secondary"
							data-loomi-faq-remove
						><?php esc_html_e( 'Remover pergunta', 'loomi-studio' ); ?></button>
					</p>
				</div>
			<?php endforeach; ?>
		</div>

		<p>
			<button
				type="button"
				class="button"
				data-loomi-faq-add
			>+ <?php esc_html_e( 'Adicionar pergunta', 'loomi-studio' ); ?></button>
		</p>

		<template id="loomi-faq-template">
			<div class="loomi-faq-row" style="border:1px solid #c3c4c7; padding:10px; margin-bottom:10px; background:#fff;">
				<p>
					<label><strong><?php esc_html_e( 'Pergunta', 'loomi-studio' ); ?></strong></label><br />
					<input
						type="text"
						name="loomi_schema[faq][__INDEX__][question]"
						class="large-text"
						value=""
						data-loomi-faq-field="question"
					/>
				</p>
				<p>
					<label><strong><?php esc_html_e( 'Resposta', 'loomi-studio' ); ?></strong></label><br />
					<textarea
						name="loomi_schema[faq][__INDEX__][answer]"
						class="large-text"
						rows="3"
						data-loomi-faq-field="answer"
					></textarea>
				</p>
				<p>
					<button
						type="button"
						class="button button-secondary"
						data-loomi-faq-remove
					><?php esc_html_e( 'Remover pergunta', 'loomi-studio' ); ?></button>
				</p>
			</div>
		</template>
	</div>

	<?php // ---------- Custom JSON ---------- ?>
	<div data-schema-section="<?php echo esc_attr( Loomi_Schema::TYPE_CUSTOM_JSON ); ?>" <?php echo $is_section( Loomi_Schema::TYPE_CUSTOM_JSON ) ? '' : 'hidden'; ?>>
		<p>
			<label for="loomi_schema_custom_json">
				<strong><?php esc_html_e( 'JSON-LD personalizado', 'loomi-studio' ); ?></strong>
			</label>
		</p>
		<textarea
			id="loomi_schema_custom_json"
			name="loomi_schema[custom_json]"
			class="large-text"
			rows="12"
			style="font-family:monospace;"
		><?php echo esc_textarea( $custom_json ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Cole o JSON-LD completo. Será validado no salvar.', 'loomi-studio' ); ?>
		</p>
	</div>

	<?php // ---------- Rich Results Test ---------- ?>
	<?php if ( '' !== $permalink ) : ?>
		<p style="margin-top:16px;">
			<a
				href="https://search.google.com/test/rich-results?url=<?php echo esc_attr( rawurlencode( $permalink ) ); ?>"
				target="_blank"
				rel="noopener noreferrer"
				class="button button-secondary"
			>
				<?php esc_html_e( 'Testar no Google Rich Results', 'loomi-studio' ); ?>
			</a>
		</p>
	<?php endif; ?>

</div>

<script>
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var root = document.querySelector( '.loomi-schema-metabox' );
		if ( ! root ) {
			return;
		}

		var select   = root.querySelector( '#loomi_schema_type' );
		var sections = root.querySelectorAll( '[data-schema-section]' );

		function applyVisibility( value ) {
			sections.forEach( function ( section ) {
				if ( section.getAttribute( 'data-schema-section' ) === value ) {
					section.removeAttribute( 'hidden' );
				} else {
					section.setAttribute( 'hidden', '' );
				}
			} );
		}

		if ( select ) {
			select.addEventListener( 'change', function () {
				applyVisibility( select.value );
			} );
			applyVisibility( select.value );
		}

		// -------- areaServed repeater --------
		var areaRepeater = root.querySelector( '[data-loomi-area-repeater]' );
		var areaAddBtn   = root.querySelector( '[data-loomi-area-add]' );
		var areaTemplate = root.querySelector( '#loomi-area-template' );

		if ( areaRepeater && areaAddBtn && areaTemplate ) {
			areaAddBtn.addEventListener( 'click', function () {
				var clone = areaTemplate.content.firstElementChild.cloneNode( true );
				areaRepeater.appendChild( clone );
			} );

			areaRepeater.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '[data-loomi-area-remove]' );
				if ( ! btn ) {
					return;
				}
				var row = btn.closest( '.loomi-area-row' );
				if ( ! row ) {
					return;
				}
				var rows = areaRepeater.querySelectorAll( '.loomi-area-row' );
				if ( rows.length <= 1 ) {
					// Mantém ao menos uma linha (vazia).
					row.querySelector( 'input[name="loomi_schema[areaServed][]"]' ).value = '';
					return;
				}
				row.parentNode.removeChild( row );
			} );
		}

		// -------- FAQ repeater --------
		var faqRepeater = root.querySelector( '[data-loomi-faq-repeater]' );
		var faqAddBtn   = root.querySelector( '[data-loomi-faq-add]' );
		var faqTemplate = root.querySelector( '#loomi-faq-template' );

		function reindexFaq() {
			if ( ! faqRepeater ) {
				return;
			}
			var rows = faqRepeater.querySelectorAll( '.loomi-faq-row' );
			rows.forEach( function ( row, idx ) {
				var qField = row.querySelector( '[data-loomi-faq-field="question"]' );
				var aField = row.querySelector( '[data-loomi-faq-field="answer"]' );
				if ( qField ) {
					qField.setAttribute( 'name', 'loomi_schema[faq][' + idx + '][question]' );
				}
				if ( aField ) {
					aField.setAttribute( 'name', 'loomi_schema[faq][' + idx + '][answer]' );
				}
			} );
		}

		if ( faqRepeater && faqAddBtn && faqTemplate ) {
			faqAddBtn.addEventListener( 'click', function () {
				var clone = faqTemplate.content.firstElementChild.cloneNode( true );
				faqRepeater.appendChild( clone );
				reindexFaq();
			} );

			faqRepeater.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '[data-loomi-faq-remove]' );
				if ( ! btn ) {
					return;
				}
				var row = btn.closest( '.loomi-faq-row' );
				if ( ! row ) {
					return;
				}
				var rows = faqRepeater.querySelectorAll( '.loomi-faq-row' );
				if ( rows.length <= 1 ) {
					// Mantém ao menos uma linha (limpa).
					var q = row.querySelector( '[data-loomi-faq-field="question"]' );
					var a = row.querySelector( '[data-loomi-faq-field="answer"]' );
					if ( q ) { q.value = ''; }
					if ( a ) { a.value = ''; }
					return;
				}
				row.parentNode.removeChild( row );
				reindexFaq();
			} );
		}
	} );
}() );
</script>
