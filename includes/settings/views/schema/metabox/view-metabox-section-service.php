<?php
/**
 * Partial: Service section of the schema metabox.
 *
 * @var bool   $is_active
 * @var string $svc_type
 * @var string $svc_description
 * @var array  $svc_area
 */
defined( 'ABSPATH' ) || exit;
?>
<div data-schema-section="<?php echo esc_attr( Loomi_Schema::TYPE_SERVICE ); ?>" <?php echo $is_active ? '' : 'hidden'; ?>>
	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">
					<label for="loomi_schema_svc_type">
						<?php esc_html_e( 'Tipo de serviço', 'loomi-studio' ); ?>
						<span class="loomi-schema-required" aria-hidden="true">*</span>
					</label>
				</th>
				<td>
					<input type="text" id="loomi_schema_svc_type" name="loomi_schema[serviceType]" class="regular-text"
						value="<?php echo esc_attr( $svc_type ); ?>" required />
					<p class="description"><?php esc_html_e( 'Ex.: "Limpeza de fossa", "Consultoria contábil".', 'loomi-studio' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="loomi_schema_svc_description"><?php esc_html_e( 'Descrição', 'loomi-studio' ); ?></label></th>
				<td>
					<textarea id="loomi_schema_svc_description" name="loomi_schema[description]" class="large-text" rows="4"><?php echo esc_textarea( $svc_description ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Áreas atendidas', 'loomi-studio' ); ?></th>
				<td>
					<div data-loomi-area-repeater>
						<?php foreach ( $svc_area as $city ) : ?>
							<div class="loomi-area-row">
								<input type="text" name="loomi_schema[areaServed][]" class="regular-text"
									value="<?php echo esc_attr( $city ); ?>"
									placeholder="<?php esc_attr_e( 'Cidade atendida', 'loomi-studio' ); ?>" />
								<button type="button" class="button button-secondary" data-loomi-area-remove>
									<?php esc_html_e( 'Remover', 'loomi-studio' ); ?>
								</button>
							</div>
						<?php endforeach; ?>
					</div>
					<p>
						<button type="button" class="button" data-loomi-area-add>+ <?php esc_html_e( 'Adicionar cidade', 'loomi-studio' ); ?></button>
					</p>

					<template id="loomi-area-template">
						<div class="loomi-area-row">
							<input type="text" name="loomi_schema[areaServed][]" class="regular-text" value=""
								placeholder="<?php esc_attr_e( 'Cidade atendida', 'loomi-studio' ); ?>" />
							<button type="button" class="button button-secondary" data-loomi-area-remove>
								<?php esc_html_e( 'Remover', 'loomi-studio' ); ?>
							</button>
						</div>
					</template>
				</td>
			</tr>
		</tbody>
	</table>
</div>
