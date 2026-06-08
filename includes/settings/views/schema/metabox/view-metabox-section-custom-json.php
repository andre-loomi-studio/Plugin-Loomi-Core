<?php
/**
 * Partial: Custom JSON-LD section of the schema metabox.
 *
 * @var bool   $is_active
 * @var string $custom_json
 */
defined( 'ABSPATH' ) || exit;
?>
<div data-schema-section="<?php echo esc_attr( Loomi_Schema::TYPE_CUSTOM_JSON ); ?>" <?php echo $is_active ? '' : 'hidden'; ?>>
	<div class="notice notice-info inline loomi-schema-custom-json-help">
		<p>
			<strong><?php esc_html_e( 'Importante:', 'loomi-studio-setup' ); ?></strong>
			<?php
			$wrap_tag = '<code>&lt;script type="application/ld+json"&gt;…&lt;/script&gt;</code>';
			$bare_tag = '<code>&lt;script&gt;</code>';
			printf(
				/* translators: %1$s = the full ld+json wrap tag, %2$s = the bare script tag */
				esc_html__( 'O plugin já encapsula automaticamente em %1$s. Cole apenas o JSON puro (começando com { e terminando com }). NÃO inclua tags %2$s.', 'loomi-studio-setup' ),
				$wrap_tag,
				$bare_tag
			);
			?>
		</p>
	</div>
	<p>
		<label for="loomi_schema_custom_json">
			<strong><?php esc_html_e( 'JSON-LD personalizado', 'loomi-studio' ); ?></strong>
		</label>
	</p>
	<textarea id="loomi_schema_custom_json" name="loomi_schema[custom_json]" class="large-text loomi-schema-custom-json" rows="12"
		placeholder='{ "@context": "https://schema.org", "@type": "Article", ... }'><?php echo esc_textarea( $custom_json ); ?></textarea>
	<p class="description">
		<?php esc_html_e( 'Cole o JSON-LD completo. Será validado no salvar.', 'loomi-studio' ); ?>
	</p>
	<p class="loomi-schema-custom-json-error" hidden>
		<?php
		printf(
			/* translators: %s = bare script tag (HTML-encoded) */
			esc_html__( '⚠️ Remova as tags %s — o plugin adiciona automaticamente.', 'loomi-studio-setup' ),
			'&lt;script&gt;'
		);
		?>
	</p>
</div>
