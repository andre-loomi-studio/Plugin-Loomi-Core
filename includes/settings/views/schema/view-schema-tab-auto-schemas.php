<?php
/**
 * Partial: "Schemas automáticos" section of the Schema settings tab.
 *
 * Shows three checkboxes (Article / Breadcrumb / Product) gated by:
 *   - SEO plugin conflict detection (forces all OFF + disabled)
 *   - WooCommerce activation (gates Product checkbox)
 *
 * Variables from caller scope:
 *
 * @var array $s        Full settings array
 * @var string $opt_key Plugin::OPTION_KEY
 */
defined( 'ABSPATH' ) || exit;

$conflicts    = Loomi_Schema_Conflict_Detector::detect();
$has_conflict = ! empty( $conflicts );
$has_wc       = class_exists( 'WooCommerce' );
?>
<h2><?php esc_html_e( 'Integração Rank Math', 'loomi-studio-setup' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Auto-corrigir Rank Math', 'loomi-studio-setup' ); ?></th>
		<td>
			<label>
				<input type="checkbox"
					name="<?php echo esc_attr( $opt_key ); ?>[loomi_rm_fix_invalid_types]"
					value="1"
					<?php checked( ! empty( $s['loomi_rm_fix_invalid_types'] ) ); ?> />
				<?php esc_html_e( 'Corrigir tipos inválidos no JSON-LD do Rank Math', 'loomi-studio-setup' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'O Rank Math Free deixa o usuário escolher tipos como "Geriatric" no Knowledge Graph, mas esses são MedicalSpecialty (não Business) e o Google rejeita como "Unrecognized type". Quando ligado, remapeamos automaticamente (Geriatric → MedicalBusiness, Dentistry → Dentist, etc.) via o filtro rank_math/json_ld.', 'loomi-studio-setup' ); ?>
			</p>
		</td>
	</tr>
</table>

<hr>

<h2><?php esc_html_e( 'Schemas automáticos', 'loomi-studio-setup' ); ?></h2>

<?php if ( $has_conflict ) : ?>
	<div class="notice notice-warning inline loomi-schema-auto-conflict-notice">
		<p>
			<strong><?php esc_html_e( 'Detectado:', 'loomi-studio-setup' ); ?></strong>
			<?php echo esc_html( Loomi_Schema_Conflict_Detector::detected_labels_text() ); ?>.
			<?php esc_html_e( 'Schemas automáticos foram desativados pra evitar duplicidade. Schemas manuais por página (LocalBusiness, Service, FAQ, Custom JSON, Product) continuam funcionando — eles cobrem casos que o plugin de SEO não cobre.', 'loomi-studio-setup' ); ?>
		</p>
	</div>
<?php else : ?>
	<p class="description">
		<?php esc_html_e( 'O plugin gera automaticamente Article (posts de blog), BreadcrumbList (páginas internas) e Product (WooCommerce). Você pode desativar individualmente abaixo.', 'loomi-studio-setup' ); ?>
	</p>
<?php endif; ?>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Article (posts de blog)', 'loomi-studio-setup' ); ?></th>
		<td>
			<label>
				<input type="checkbox"
					name="<?php echo esc_attr( $opt_key ); ?>[loomi_schema_auto_article]"
					value="1"
					<?php checked( ! empty( $s['loomi_schema_auto_article'] ) && ! $has_conflict ); ?>
					<?php disabled( $has_conflict ); ?> />
				<?php esc_html_e( 'Emitir Article em single posts automaticamente', 'loomi-studio-setup' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'BreadcrumbList', 'loomi-studio-setup' ); ?></th>
		<td>
			<label>
				<input type="checkbox"
					name="<?php echo esc_attr( $opt_key ); ?>[loomi_schema_auto_breadcrumb]"
					value="1"
					<?php checked( ! empty( $s['loomi_schema_auto_breadcrumb'] ) && ! $has_conflict ); ?>
					<?php disabled( $has_conflict ); ?> />
				<?php esc_html_e( 'Emitir trilha de breadcrumb em todas as páginas internas', 'loomi-studio-setup' ); ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Product (WooCommerce)', 'loomi-studio-setup' ); ?></th>
		<td>
			<label>
				<input type="checkbox"
					name="<?php echo esc_attr( $opt_key ); ?>[loomi_schema_auto_product]"
					value="1"
					<?php checked( ! empty( $s['loomi_schema_auto_product'] ) && ! $has_conflict && $has_wc ); ?>
					<?php disabled( $has_conflict || ! $has_wc ); ?> />
				<?php esc_html_e( 'Emitir Product em páginas de produto WooCommerce', 'loomi-studio-setup' ); ?>
				<?php if ( ! $has_wc ) : ?>
					<span class="description"><?php esc_html_e( '(WooCommerce inativo)', 'loomi-studio-setup' ); ?></span>
				<?php endif; ?>
			</label>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'LocalBusiness (homepage)', 'loomi-studio-setup' ); ?></th>
		<td>
			<label>
				<input type="checkbox"
					name="<?php echo esc_attr( $opt_key ); ?>[loomi_schema_localbusiness_home]"
					value="1"
					<?php checked( ! empty( $s['loomi_schema_localbusiness_home'] ) ); ?> />
				<?php esc_html_e( 'Emitir LocalBusiness na homepage', 'loomi-studio-setup' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'Emite LocalBusiness/MedicalBusiness com os dados globais (preenchidos abaixo) na homepage. Use mesmo com Rank Math ativo — ele não cobre LocalBusiness completo na versão Free.', 'loomi-studio-setup' ); ?>
			</p>
		</td>
	</tr>
</table>

<hr>
