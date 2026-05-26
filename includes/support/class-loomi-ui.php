<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loomi_UI {

	/**
	 * Lucide-style SVG icons (stroke 1.75, currentColor). Inline pra zero request extra.
	 */
	const ICONS = [
		'login'        => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>',
		'lock'         => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
		'shield'       => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>',
		'eye-off'      => '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/>',
		'user'         => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		'shield-off'   => '<path d="M19.69 14a6.9 6.9 0 0 0 .31-2V5l-8-3-3.16 1.18"/><path d="M4.73 4.73L4 5v7c0 6 8 10 8 10a20.29 20.29 0 0 0 5.62-4.38"/><line x1="2" y1="2" x2="22" y2="22"/>',
		'puzzle'       => '<path d="M19.439 7.85c-.049.322.059.648.289.878l1.568 1.568c.47.47.706 1.087.706 1.704s-.235 1.233-.706 1.704l-1.611 1.611a.98.98 0 0 1-.837.276c-.47-.07-.802-.48-.968-.925a2.501 2.501 0 1 0-3.214 3.214c.446.166.855.497.925.968a.979.979 0 0 1-.276.837l-1.61 1.61a2.404 2.404 0 0 1-1.705.707 2.402 2.402 0 0 1-1.704-.706l-1.568-1.568a1.026 1.026 0 0 0-.877-.29c-.493.074-.84.504-1.02.968a2.5 2.5 0 1 1-3.237-3.237c.464-.18.894-.527.967-1.02a1.026 1.026 0 0 0-.289-.877l-1.568-1.568A2.402 2.402 0 0 1 1.998 12c0-.617.236-1.234.706-1.704L4.23 8.77c.24-.24.581-.353.917-.303.515.077.877.528 1.073 1.01a2.5 2.5 0 1 0 3.259-3.259c-.482-.196-.933-.558-1.01-1.073-.05-.336.062-.676.303-.917l1.525-1.525A2.402 2.402 0 0 1 12 1.998c.617 0 1.234.236 1.704.706l1.568 1.568c.23.23.556.338.877.29.493-.074.84-.504 1.02-.968a2.5 2.5 0 1 1 3.237 3.237c-.464.18-.894.527-.967 1.02z"/>',
		'mail'         => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
		'globe'        => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
		'check'        => '<polyline points="20 6 9 17 4 12"/>',
		'plus'         => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
		'external'     => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
		'x'            => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
		'alert'        => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
	];

	public static function icon( string $name, int $size = 20 ) : string {
		if ( ! isset( self::ICONS[ $name ] ) ) {
			return '';
		}
		return sprintf(
			'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			$size,
			self::ICONS[ $name ]
		);
	}


	/**
	 * Renderiza um switch (toggle on/off) acessível.
	 * Visual: track + thumb desenhado via CSS; semantically é <input type="checkbox">.
	 *
	 * @param string $name   Nome do campo (será wrapped por OPTION_KEY[name]).
	 * @param bool   $checked Estado atual.
	 * @param string $label  Texto descritivo ao lado do switch.
	 */
	public static function toggle( string $name, bool $checked, string $label = '' ) : void {
		$field_name = Plugin::OPTION_KEY . '[' . $name . ']';
		?>
		<label class="loomi-toggle">
			<input
				type="checkbox"
				name="<?php echo esc_attr( $field_name ); ?>"
				value="1"
				<?php checked( $checked ); ?>
			/>
			<span class="loomi-toggle-track" aria-hidden="true">
				<span class="loomi-toggle-thumb"></span>
			</span>
			<?php if ( $label !== '' ) : ?>
				<span class="loomi-toggle-label"><?php echo wp_kses_post( $label ); ?></span>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Toggle para item de array (ex: hidden_menus[] com múltiplos slugs).
	 *
	 * @param string $field   Nome do campo array (sem [], adicionado internamente).
	 * @param string $value   Valor que este toggle representa (ex: slug do menu).
	 * @param bool   $checked Estado atual.
	 * @param string $label   Texto descritivo ao lado do switch.
	 */
	public static function array_toggle( string $field, string $value, bool $checked, string $label = '' ) : void {
		$field_name = Plugin::OPTION_KEY . '[' . $field . '][]';
		?>
		<label class="loomi-toggle">
			<input
				type="checkbox"
				name="<?php echo esc_attr( $field_name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				<?php checked( $checked ); ?>
			/>
			<span class="loomi-toggle-track" aria-hidden="true">
				<span class="loomi-toggle-thumb"></span>
			</span>
			<?php if ( $label !== '' ) : ?>
				<span class="loomi-toggle-label"><?php echo wp_kses_post( $label ); ?></span>
			<?php endif; ?>
		</label>
		<?php
	}
}
