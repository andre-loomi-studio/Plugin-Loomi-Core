<?php
/**
 * Partial: link button to Google Rich Results Test for the current post URL.
 *
 * @var string $permalink Empty when post is auto-draft; renders nothing in that case
 */
defined( 'ABSPATH' ) || exit;

if ( '' === $permalink ) {
	return;
}
?>
<p class="loomi-schema-rich-results">
	<a href="https://search.google.com/test/rich-results?url=<?php echo esc_attr( rawurlencode( $permalink ) ); ?>"
		target="_blank" rel="noopener noreferrer" class="button button-secondary">
		<?php esc_html_e( 'Testar no Google Rich Results', 'loomi-studio' ); ?>
	</a>
</p>
