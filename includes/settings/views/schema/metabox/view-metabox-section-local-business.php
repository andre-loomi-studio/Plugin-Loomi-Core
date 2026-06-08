<?php
/**
 * Partial: LocalBusiness section of the schema metabox.
 *
 * @var bool   $is_active     true when this is the currently selected type
 * @var string $lb_name       Saved name override (or empty)
 * @var string $lb_locality   Saved locality override (or empty)
 * @var string $lb_lat        Saved latitude override (or empty)
 * @var string $lb_lng        Saved longitude override (or empty)
 * @var string $ph_name       Placeholder text (from global)
 * @var string $ph_locality   Placeholder text
 * @var string $ph_lat        Placeholder text
 * @var string $ph_lng        Placeholder text
 */
defined( 'ABSPATH' ) || exit;
?>
<div data-schema-section="<?php echo esc_attr( Loomi_Schema::TYPE_LOCAL_BUSINESS ); ?>" <?php echo $is_active ? '' : 'hidden'; ?>>
	<p class="description">
		<?php esc_html_e( 'Deixe em branco para herdar do global.', 'loomi-studio' ); ?>
	</p>
	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><label for="loomi_schema_lb_name"><?php esc_html_e( 'Nome', 'loomi-studio' ); ?></label></th>
				<td>
					<input type="text" id="loomi_schema_lb_name" name="loomi_schema[name]" class="regular-text"
						value="<?php echo esc_attr( $lb_name ); ?>"
						placeholder="<?php echo esc_attr( $ph_name ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="loomi_schema_lb_locality"><?php esc_html_e( 'Cidade (addressLocality)', 'loomi-studio' ); ?></label></th>
				<td>
					<input type="text" id="loomi_schema_lb_locality" name="loomi_schema[addressLocality]" class="regular-text"
						value="<?php echo esc_attr( $lb_locality ); ?>"
						placeholder="<?php echo esc_attr( $ph_locality ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="loomi_schema_lb_lat"><?php esc_html_e( 'Latitude', 'loomi-studio' ); ?></label></th>
				<td>
					<input type="number" id="loomi_schema_lb_lat" name="loomi_schema[latitude]" class="regular-text"
						step="0.000001" min="-90" max="90"
						value="<?php echo esc_attr( $lb_lat ); ?>"
						placeholder="<?php echo esc_attr( $ph_lat ); ?>" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="loomi_schema_lb_lng"><?php esc_html_e( 'Longitude', 'loomi-studio' ); ?></label></th>
				<td>
					<input type="number" id="loomi_schema_lb_lng" name="loomi_schema[longitude]" class="regular-text"
						step="0.000001" min="-180" max="180"
						value="<?php echo esc_attr( $lb_lng ); ?>"
						placeholder="<?php echo esc_attr( $ph_lng ); ?>" />
				</td>
			</tr>
		</tbody>
	</table>
</div>
