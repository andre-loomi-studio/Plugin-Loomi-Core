<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Tab_Logs implements Loomi_Settings_Tab {

	public function slug() : string {
		return 'logs';
	}

	public function label() : string {
		return __( 'Logs', 'loomi-studio-setup' );
	}

	public function render( array $settings ) : void {
		?>
		<p class="description">
			<?php esc_html_e( 'Eventos críticos capturados automaticamente: exceções não tratadas, erros fatais do PHP e ações sensíveis registradas pelos módulos (ex.: impersonate). Arquivos crus ficam em ', 'loomi-studio-setup' ); ?>
			<code><?php echo esc_html( basename( Plugin::log_dir() ) ); ?>/<?php echo esc_html( Plugin::LOG_FILE_PREFIX ); ?>YYYY-MM-DD.log</code>
			<?php
			printf(
				/* translators: %d retention in days */
				esc_html__( ' (retenção: %d dias).', 'loomi-studio-setup' ),
				(int) Loomi_Critical_Logger::retention_days()
			);
			?>
		</p>

		<?php
		if ( Loomi_Critical_Logger::is_disabled() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Logger desativado por constante (LOOMI_LOG_DISABLED).', 'loomi-studio-setup' ) . '</p></div>';
			return;
		}

		$dir = Plugin::log_dir();
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Sem permissão de escrita no diretório de logs. Verifique permissões em ', 'loomi-studio-setup' ) . '<code>' . esc_html( $dir ) . '</code>.</p></div>';
			return;
		}

		$count   = Loomi_Critical_Logger::count_recent_events( 7 );
		$entries = Loomi_Critical_Logger::list_recent_events( 7, 50 );

		echo '<p><strong>' . (int) $count . '</strong> '
			. esc_html__( 'evento(s) crítico(s) nos últimos 7 dias.', 'loomi-studio-setup' )
			. ' ';

		// Download links: today + previous 2 days if they exist.
		$today = Loomi_Log_Writer::today();
		$links = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$date  = gmdate( 'Y-m-d', strtotime( $today . ' -' . $i . ' day' ) );
			$path  = Loomi_Log_Writer::log_path_for( $date );
			if ( is_readable( $path ) ) {
				$url = wp_nonce_url(
					admin_url( 'admin-post.php?action=loomi_download_log&date=' . rawurlencode( $date ) ),
					'loomi_download_log'
				);
				$label = $i === 0
					? __( 'Hoje', 'loomi-studio-setup' )
					: ( $i === 1
						? __( 'Ontem', 'loomi-studio-setup' )
						: sprintf( /* translators: %d days ago */ __( '%d dias atrás', 'loomi-studio-setup' ), $i ) );
				$links[] = '<a class="button button-small" href="' . esc_url( $url ) . '">'
					. esc_html__( 'Baixar log: ', 'loomi-studio-setup' ) . esc_html( $label )
					. '</a>';
			}
		}
		if ( $links ) {
			echo implode( ' ', $links );
		}
		echo '</p>';

		if ( empty( $entries ) ) {
			echo '<p>' . esc_html__( 'Nenhum evento crítico nos últimos 7 dias.', 'loomi-studio-setup' ) . '</p>';
			return;
		}

		?>
		<table class="widefat striped loomi-logs-table">
			<thead>
				<tr>
					<th style="width:160px;"><?php esc_html_e( 'Quando', 'loomi-studio-setup' ); ?></th>
					<th style="width:120px;"><?php esc_html_e( 'Tipo', 'loomi-studio-setup' ); ?></th>
					<th><?php esc_html_e( 'Mensagem', 'loomi-studio-setup' ); ?></th>
					<th style="width:140px;"><?php esc_html_e( 'Origem', 'loomi-studio-setup' ); ?></th>
					<th style="width:120px;"><?php esc_html_e( 'Usuário', 'loomi-studio-setup' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $entries as $i => $entry ) :
					$ts       = isset( $entry['ts'] ) ? (string) $entry['ts'] : '';
					$ts_label = $ts !== '' ? self::format_when( $ts ) : '—';
					$type     = isset( $entry['type'] ) ? (string) $entry['type'] : '—';
					$severity = isset( $entry['severity'] ) ? (string) $entry['severity'] : '';
					$message  = isset( $entry['message'] ) ? (string) $entry['message'] : '';
					$file     = isset( $entry['file'] ) ? (string) $entry['file'] : '';
					$line_no  = isset( $entry['line'] ) ? (int) $entry['line'] : 0;
					$user     = isset( $entry['user'] ) && is_array( $entry['user'] ) ? $entry['user'] : [];
					$user_lbl = isset( $user['login'] ) && $user['login'] !== ''
						? (string) $user['login']
						: ( isset( $user['id'] ) ? '#' . (int) $user['id'] : '—' );
					$origin   = $file !== ''
						? basename( $file ) . ( $line_no > 0 ? ':' . $line_no : '' )
						: '—';

					$row_id = 'loomi-log-row-' . $i;
					?>
					<tr>
						<td>
							<time datetime="<?php echo esc_attr( $ts ); ?>"><?php echo esc_html( $ts_label ); ?></time>
						</td>
						<td>
							<code><?php echo esc_html( $type ); ?></code>
							<?php if ( $severity !== '' ) : ?>
								<br><small><?php echo esc_html( $severity ); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( $message ); ?>
							<button type="button" class="button-link loomi-log-toggle" data-target="<?php echo esc_attr( $row_id ); ?>">
								<?php esc_html_e( 'detalhes', 'loomi-studio-setup' ); ?>
							</button>
							<pre id="<?php echo esc_attr( $row_id ); ?>" class="loomi-log-detail" hidden><?php echo esc_html( wp_json_encode( $entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre>
						</td>
						<td><code><?php echo esc_html( $origin ); ?></code></td>
						<td><?php echo esc_html( $user_lbl ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<style>
			.loomi-logs-table { margin-top: 12px; }
			.loomi-logs-table td { vertical-align: top; }
			.loomi-logs-table code { font-size: 12px; word-break: break-word; }
			.loomi-log-toggle { margin-left: 8px; font-size: 12px; color: var(--loomi-text-on-elevated, currentColor); }
			.loomi-log-detail {
				margin-top: 8px;
				padding: 12px;
				background: rgba(0,0,0,0.4);
				color: #e6e6e6;
				border-radius: 4px;
				font-size: 12px;
				line-height: 1.5;
				max-height: 320px;
				overflow: auto;
				white-space: pre-wrap;
				word-break: break-word;
			}
		</style>
		<script>
			(function () {
				document.querySelectorAll('.loomi-log-toggle').forEach(function (btn) {
					btn.addEventListener('click', function () {
						var id = btn.getAttribute('data-target');
						var pre = id ? document.getElementById(id) : null;
						if (!pre) return;
						pre.hidden = !pre.hidden;
					});
				});
			})();
		</script>
		<?php
	}

	private static function format_when( string $ts ) : string {
		$timestamp = strtotime( $ts );
		if ( ! $timestamp ) {
			return $ts;
		}
		$diff = time() - $timestamp;
		if ( $diff < 60 ) {
			return __( 'há instantes', 'loomi-studio-setup' );
		}
		if ( $diff < HOUR_IN_SECONDS ) {
			return sprintf( /* translators: %d minutes */ __( 'há %d min', 'loomi-studio-setup' ), (int) ( $diff / 60 ) );
		}
		if ( $diff < DAY_IN_SECONDS ) {
			return sprintf( /* translators: %d hours */ __( 'há %d h', 'loomi-studio-setup' ), (int) ( $diff / HOUR_IN_SECONDS ) );
		}
		// Older: show absolute date/time in site's timezone.
		return mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), gmdate( 'Y-m-d H:i:s', $timestamp ) );
	}
}
