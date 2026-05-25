## 1. Header e bootstrap

- [x] 1.1 Adicionar header `Requires Plugins: wordfence` em `loomi-studio-setup.php` (logo abaixo de `Requires PHP`)
- [x] 1.2 Adicionar `require_once LOOMI_STUDIO_DIR . 'includes/modules/class-loomi-wordfence-check.php';` no bloco de requires
- [x] 1.3 Chamar `Loomi_Wordfence_Check::init();` dentro do callback de `plugins_loaded` (junto dos outros módulos)

## 2. Módulo de detecção e notice

- [x] 2.1 Criar `includes/modules/class-loomi-wordfence-check.php` com guard `defined('ABSPATH')`
- [x] 2.2 Constante `WORDFENCE_PLUGIN_FILE = 'wordfence/wordfence.php'`
- [x] 2.3 Método estático `init()`: registra `add_action('admin_init', [Loomi_Wordfence_Check::class, 'maybe_render_handler_notices'])`, `add_action('admin_notices', ...)`, `add_action('admin_post_loomi_install_wordfence', ...)`
- [x] 2.4 Método `get_state()`: retorna `'active'` se `is_plugin_active(...)`, senão `'installed_inactive'` se `file_exists(WP_PLUGIN_DIR . '/' . WORDFENCE_PLUGIN_FILE)`, senão `'absent'`
- [x] 2.5 Garantir que `is_plugin_active` esteja disponível (requer `wp-admin/includes/plugin.php` em contextos sem admin bootstrap)

## 3. Notice (admin_notices)

- [x] 3.1 Método `render_notice()`: early-return se `! current_user_can('activate_plugins')` ou state === 'active'
- [x] 3.2 Renderizar `<div class="notice notice-error"><p>...mensagem...</p>...form...</div>` (sem `is-dismissible`)
- [x] 3.3 Texto da mensagem varia por state: 'absent' → "Wordfence não está instalado", 'installed_inactive' → "Wordfence está instalado mas não ativo"
- [x] 3.4 Renderizar `<form action="admin-post.php" method="post">` com `<input name="action" value="loomi_install_wordfence">` + `wp_nonce_field('loomi_install_wordfence')`
- [x] 3.5 Botão `<button type="submit" class="button button-primary">` com label que varia por state: 'absent' → "Instalar Wordfence agora", 'installed_inactive' → "Ativar Wordfence"
- [x] 3.6 Se usuário tem `activate_plugins` mas não `install_plugins` E state === 'absent', mostrar texto informativo sem botão ("Solicite ao administrador a instalação do Wordfence")

## 4. Handler admin-post

- [x] 4.1 Método `handle_install()`: `check_admin_referer('loomi_install_wordfence')`
- [x] 4.2 Determinar capability necessária: state === 'absent' requer `install_plugins`, state === 'installed_inactive' requer `activate_plugins`
- [x] 4.3 Se cap check falhar, `wp_die(__('Permissão negada.', 'loomi-studio-setup'), '', ['response' => 403])`
- [x] 4.4 Se state === 'absent': carregar `wp-admin/includes/plugin-install.php`, `wp-admin/includes/file.php`, `wp-admin/includes/class-plugin-upgrader.php`, `wp-admin/includes/class-wp-upgrader-skins.php`
- [x] 4.5 Chamar `plugins_api('plugin_information', ['slug' => 'wordfence', 'fields' => ['sections' => false]])`; se WP_Error, redirect com `loomi_wf_status=error&loomi_wf_msg=...`
- [x] 4.6 Instanciar `new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() )` e chamar `install($api->download_link)`; capturar WP_Error
- [x] 4.7 Após install OK ou se state era 'installed_inactive', chamar `activate_plugin('wordfence/wordfence.php', '', false, true)` (silent = true)
- [x] 4.8 Redirect com `wp_safe_redirect( add_query_arg(['loomi_wf_status' => $status], $referer) )`; status: `ok`, `activated`, ou `error`
- [x] 4.9 Sanitizar `wp_get_referer()`; fallback para `admin_url()` se vazio

## 5. Notice transitório de resultado

- [x] 5.1 Em `admin_notices`, se `$_GET['loomi_wf_status']` presente, renderizar notice transitório
- [x] 5.2 `ok` ou `activated` → `notice-success` com "Wordfence ativo. Obrigado!"
- [x] 5.3 `error` → `notice-error` com `sanitize_text_field( wp_unslash( $_GET['loomi_wf_msg'] ?? '' ) )`

## 6. Documentação

- [x] 6.1 Atualizar `README.md` seção Instalação: documentar dependência Wordfence + comportamento do notice
- [x] 6.2 Atualizar `README.md` tabela de recursos: adicionar linha "Dependência Wordfence (auto-install)"
- [x] 6.3 Atualizar tabela de Segurança no README com a entrada "Hardening de borda → Wordfence (peer plugin obrigatório)"

## 7. Validação

- [x] 7.1 Lint PHP: `php -l includes/modules/class-loomi-wordfence-check.php` — *sem erros*
- [x] 7.2 No stack docker, desativar Wordfence (se já estiver) e ver notice aparecer no admin — *state=installed_inactive, botão "Ativar" renderizado*
- [x] 7.3 Clicar "Instalar Wordfence agora" como admin → confirmar download + ativação — *wordfence 8.2.2 baixado da wp.org, ativado, state=active*
- [x] 7.4 Logar como `loomi_client` no admin → confirmar que notice NÃO aparece — *output 0 chars*
- [ ] 7.5 Forçar erro de instalação (apontar `plugins_api` mock pra URL inválida via filtro de teste) e confirmar mensagem de erro graciosa
- [ ] 7.6 Em WP 6.5+: tentar ativar Loomi sem Wordfence presente → confirmar que WP recusa nativamente
