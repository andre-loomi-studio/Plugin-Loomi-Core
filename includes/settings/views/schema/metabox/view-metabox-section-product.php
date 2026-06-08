<?php
/**
 * Partial: Product section of the schema metabox.
 *
 * @var bool   $is_active
 * @var bool   $is_wc_product   true when the current post is a WC product
 * @var array  $product_data    Saved manual overrides
 */
defined( 'ABSPATH' ) || exit;

$required_marker = $is_wc_product ? '' : ' *';
$required_attr   = $is_wc_product ? '' : 'required';
$availabilities  = Loomi_Schema_Product_Sanitizer::ALLOWED_AVAILABILITY;
?>
<div data-schema-section="<?php echo esc_attr( Loomi_Schema::TYPE_PRODUCT ); ?>" <?php echo $is_active ? '' : 'hidden'; ?>>
	<?php if ( $is_wc_product ) : ?>
		<div class="notice notice-info inline loomi-schema-product-wc-notice">
			<p>
				<strong><?php esc_html_e( 'Auto-preenchido do WooCommerce.', 'loomi-studio-setup' ); ?></strong>
				<?php esc_html_e( 'Os campos abaixo são overrides opcionais — preencha apenas o que quiser sobrescrever em relação aos dados do WC.', 'loomi-studio-setup' ); ?>
			</p>
		</div>
	<?php endif; ?>
	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><label for="loomi_schema_product_name"><?php esc_html_e( 'Nome', 'loomi-studio-setup' ); ?><?php echo esc_html( $required_marker ); ?></label></th>
				<td><input type="text" id="loomi_schema_product_name" name="loomi_schema[name]" class="regular-text"
					value="<?php echo esc_attr( $product_data['name'] ?? '' ); ?>" <?php echo esc_attr( $required_attr ); ?> /></td>
			</tr>
			<tr>
				<th scope="row"><label for="loomi_schema_product_sku"><?php esc_html_e( 'SKU', 'loomi-studio-setup' ); ?></label></th>
				<td><input type="text" id="loomi_schema_product_sku" name="loomi_schema[sku]" class="regular-text"
					value="<?php echo esc_attr( $product_data['sku'] ?? '' ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="loomi_schema_product_brand"><?php esc_html_e( 'Marca', 'loomi-studio-setup' ); ?></label></th>
				<td><input type="text" id="loomi_schema_product_brand" name="loomi_schema[brand]" class="regular-text"
					value="<?php echo esc_attr( $product_data['brand'] ?? '' ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="loomi_schema_product_description"><?php esc_html_e( 'Descrição', 'loomi-studio-setup' ); ?></label></th>
				<td><textarea id="loomi_schema_product_description" name="loomi_schema[description]" class="large-text" rows="3"><?php echo esc_textarea( $product_data['description'] ?? '' ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="loomi_schema_product_price"><?php esc_html_e( 'Preço', 'loomi-studio-setup' ); ?><?php echo esc_html( $required_marker ); ?></label></th>
				<td><input type="text" id="loomi_schema_product_price" name="loomi_schema[price]" class="small-text"
					value="<?php echo esc_attr( (string) ( $product_data['price'] ?? '' ) ); ?>"
					placeholder="49.90" <?php echo esc_attr( $required_attr ); ?> /></td>
			</tr>
			<tr>
				<th scope="row"><label for="loomi_schema_product_currency"><?php esc_html_e( 'Moeda (ISO 4217)', 'loomi-studio-setup' ); ?></label></th>
				<td><input type="text" id="loomi_schema_product_currency" name="loomi_schema[priceCurrency]" class="small-text"
					maxlength="3"
					value="<?php echo esc_attr( $product_data['priceCurrency'] ?? '' ); ?>" placeholder="BRL" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="loomi_schema_product_availability"><?php esc_html_e( 'Disponibilidade', 'loomi-studio-setup' ); ?></label></th>
				<td>
					<select id="loomi_schema_product_availability" name="loomi_schema[availability]">
						<option value=""><?php esc_html_e( '— (auto)', 'loomi-studio-setup' ); ?></option>
						<?php foreach ( $availabilities as $opt ) : ?>
							<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $product_data['availability'] ?? '', $opt ); ?>><?php echo esc_html( $opt ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</tbody>
	</table>
</div>
