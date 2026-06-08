<?php
/**
 * Partial: schema type selector.
 *
 * @var array  $types Map of TYPE_* => label
 * @var string $type  Currently selected type
 */
defined( 'ABSPATH' ) || exit;
?>
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
