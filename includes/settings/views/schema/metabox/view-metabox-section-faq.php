<?php
/**
 * Partial: FAQPage section of the schema metabox.
 *
 * @var bool  $is_active
 * @var array $faq_rows  Each row has 'question' and 'answer' keys
 */
defined( 'ABSPATH' ) || exit;
?>
<div data-schema-section="<?php echo esc_attr( Loomi_Schema::TYPE_FAQ_PAGE ); ?>" <?php echo $is_active ? '' : 'hidden'; ?>>
	<p class="description">
		<?php esc_html_e( 'Cada pergunta/resposta vira um item Question dentro do FAQPage.', 'loomi-studio' ); ?>
	</p>

	<div data-loomi-faq-repeater>
		<?php foreach ( $faq_rows as $idx => $row ) :
			$q = isset( $row['question'] ) ? (string) $row['question'] : '';
			$a = isset( $row['answer'] ) ? (string) $row['answer'] : '';
			?>
			<div class="loomi-faq-row">
				<p>
					<label><strong><?php esc_html_e( 'Pergunta', 'loomi-studio' ); ?></strong></label><br />
					<input type="text"
						name="loomi_schema[faq][<?php echo esc_attr( (string) $idx ); ?>][question]"
						class="large-text"
						value="<?php echo esc_attr( $q ); ?>"
						data-loomi-faq-field="question" />
				</p>
				<p>
					<label><strong><?php esc_html_e( 'Resposta', 'loomi-studio' ); ?></strong></label><br />
					<textarea
						name="loomi_schema[faq][<?php echo esc_attr( (string) $idx ); ?>][answer]"
						class="large-text" rows="3"
						data-loomi-faq-field="answer"><?php echo esc_textarea( $a ); ?></textarea>
				</p>
				<p>
					<button type="button" class="button button-secondary" data-loomi-faq-remove>
						<?php esc_html_e( 'Remover pergunta', 'loomi-studio' ); ?>
					</button>
				</p>
			</div>
		<?php endforeach; ?>
	</div>

	<p>
		<button type="button" class="button" data-loomi-faq-add>+ <?php esc_html_e( 'Adicionar pergunta', 'loomi-studio' ); ?></button>
	</p>

	<template id="loomi-faq-template">
		<div class="loomi-faq-row">
			<p>
				<label><strong><?php esc_html_e( 'Pergunta', 'loomi-studio' ); ?></strong></label><br />
				<input type="text" name="loomi_schema[faq][__INDEX__][question]" class="large-text" value="" data-loomi-faq-field="question" />
			</p>
			<p>
				<label><strong><?php esc_html_e( 'Resposta', 'loomi-studio' ); ?></strong></label><br />
				<textarea name="loomi_schema[faq][__INDEX__][answer]" class="large-text" rows="3" data-loomi-faq-field="answer"></textarea>
			</p>
			<p>
				<button type="button" class="button button-secondary" data-loomi-faq-remove>
					<?php esc_html_e( 'Remover pergunta', 'loomi-studio' ); ?>
				</button>
			</p>
		</div>
	</template>
</div>
