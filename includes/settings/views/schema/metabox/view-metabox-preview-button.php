<?php
/**
 * Partial: "Preview JSON-LD" button at the bottom of the schema metabox.
 *
 * Triggers AJAX to Loomi_Schema_Post_Preview_Handler — no inline JS, the
 * click handler lives in assets/schema-metabox.js.
 */
defined( 'ABSPATH' ) || exit;
?>
<hr class="loomi-schema-preview-separator">
<p>
	<button type="button" class="button" id="loomi-schema-post-preview-btn">
		<?php esc_html_e( 'Visualizar JSON-LD que será emitido', 'loomi-studio-setup' ); ?>
	</button>
	<span class="description loomi-schema-preview-hint">
		<?php esc_html_e( 'Preview dos schemas (manual + automáticos) sem salvar.', 'loomi-studio-setup' ); ?>
	</span>
</p>
<pre id="loomi-schema-post-preview-output" class="loomi-schema-post-preview-output" hidden></pre>
