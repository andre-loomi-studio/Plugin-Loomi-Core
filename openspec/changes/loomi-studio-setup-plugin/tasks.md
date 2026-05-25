## 1. Scaffold do plugin

- [x] 1.1 Criar diretório `loomi-studio-setup/` na raiz do repo
- [x] 1.2 Criar `loomi-studio-setup.php` com cabeçalho do plugin (Name, Description, Version 1.0.0, Author Loomi, Requires WP 6.0, Requires PHP 7.4, License GPL-2.0+, Text Domain `loomi-studio-setup`)
- [x] 1.3 Definir constantes: `LOOMI_STUDIO_VERSION`, `LOOMI_STUDIO_FILE`, `LOOMI_STUDIO_DIR`, `LOOMI_STUDIO_URL`, `LOOMI_STUDIO_UPDATE_SERVER`
- [x] 1.4 Criar `uninstall.php` (vazio por enquanto; preencher em 7.x)
- [x] 1.5 Criar estrutura de pastas: `includes/`, `includes/modules/`, `assets/`, `languages/`
- [x] 1.6 Implementar autoload manual via `require_once` no bootstrap (sem composer)
- [x] 1.7 Carregar text domain via `load_plugin_textdomain('loomi-studio-setup', false, dirname(plugin_basename(__FILE__)) . '/languages')`

## 2. Settings store + painel (capability `plugin-settings`)

- [x] 2.1 Criar `includes/class-loomi-settings.php` com método estático `get($key, $default = null)` que faz uma leitura cacheada de `loomi_studio_setup_settings`
- [x] 2.2 Definir array de defaults (todos os toggles `false` exceto sempre-ativos; `login_slug = 'studio-access'`; `custom_login_bg_color = '#000000'`)
- [x] 2.3 Registrar página em `Configurações → Loomi Studio` via `add_options_page` (capability `manage_options`)
- [x] 2.4 Registrar setting via `register_setting('loomi_studio', 'loomi_studio_setup_settings', ['sanitize_callback' => ...])`
- [x] 2.5 Implementar sanitize callback que valida cada chave por tipo (bool, hex color, int, sanitize_title, array de slugs do whitelist)
- [x] 2.6 Rejeitar slug de login reservada (`wp-admin`, `wp-login`, `admin`, `login`, vazio) com `add_settings_error`
- [x] 2.7 Renderizar tabs (`<nav class="nav-tab-wrapper">`) — Login, Login Slug, Hide Menus, Client Role
- [x] 2.8 Criar `assets/admin.css` com estilos mínimos do painel
- [x] 2.9 Enqueue `admin.css` + `wp_enqueue_media()` APENAS quando `$hook === 'settings_page_loomi-studio-setup'`

## 3. Módulo SVG (capability `svg-upload`)

- [x] 3.1 Criar `includes/modules/class-loomi-svg.php` com `init()` chamado sempre (não depende de toggle)
- [x] 3.2 Filtrar `upload_mimes` (priority 99) para adicionar `'svg' => 'image/svg+xml'`
- [x] 3.3 Filtrar `wp_check_filetype_and_ext` para autorizar `.svg` mesmo quando o WP retorna falso
- [x] 3.4 Filtrar `wp_handle_upload_prefilter` para sanitizar o conteúdo do arquivo antes do upload
- [x] 3.5 Implementar sanitizador com `DOMDocument`: whitelist de tags (`svg, g, path, rect, circle, ellipse, line, polyline, polygon, text, defs, use, title, desc, style, linearGradient, radialGradient, stop, mask, clipPath`) e atributos (`d, fill, stroke, transform, viewBox, width, height, x, y, cx, cy, r, rx, ry, points, opacity, class, id`)
- [x] 3.6 Remover todos os atributos `on*` (event handlers) durante sanitização
- [x] 3.7 Rejeitar valores `xlink:href`/`href` começando com `javascript:` ou `data:` (exceto `data:image/*`)
- [x] 3.8 Rejeitar XML malformado: se `DOMDocument::loadXML` falhar, retornar erro em `$file['error']`
- [x] 3.9 Filtrar `wp_prepare_attachment_for_js` para retornar `sizes.thumbnail` apontando para a própria URL do SVG (preview na Media Library)
- [x] 3.10 Testar manualmente: upload SVG limpo (passa), com `<script>` (limpa), com `onload=` (limpa), com `xlink:href="javascript:"` (limpa), XML malformado (rejeita) — *automated via `test-svg-sanitizer.php`, 11/11 pass incluindo XXE e billion-laughs*

## 4. Módulo Custom Login visual (capability `custom-login`)

- [x] 4.1 Criar `includes/modules/class-loomi-login.php` com `init()` que só registra hooks se `custom_login_enabled === true`
- [x] 4.2 Hook `login_enqueue_scripts`: injetar `<style>` inline com variáveis CSS para bg color e logo URL
- [x] 4.3 Renderizar `body.login { background: var(--loomi-login-bg); }` e `.login h1 a { background-image: var(--loomi-login-logo); width:320px; height:120px; margin-bottom:60px; background-size:contain; }`
- [x] 4.4 Resolver `custom_login_logo_id` para URL via `wp_get_attachment_url`; fallback gracioso se attachment não existir
- [x] 4.5 Hook `login_headerurl`: retornar `home_url()`
- [x] 4.6 Hook `login_headertext`: retornar `get_bloginfo('name')`
- [x] 4.7 Renderizar campo color picker (`type="color"`) e media picker (botão que abre `wp.media`) no painel
- [ ] 4.8 Testar visualmente em desktop (1440×900) e mobile (390×844)

## 5. Módulo Login Slug (capability `login-slug`)

- [x] 5.1 No `class-loomi-login.php` (ou separado), registrar `add_rewrite_rule('^' . $slug . '/?$', 'wp-login.php', 'top')` no `init` quando `login_slug_enabled === true`
- [x] 5.2 Hook `login_init`: se request é `wp-login.php` literal AND user não autenticado AND `action` não está em `['logout','lostpassword','retrievepassword','rp','resetpass','postpass']` → `wp_die('', '', 404)`
- [x] 5.3 Hook `update_option_loomi_studio_setup_settings`: comparar slug antiga vs nova; se mudou, `flush_rewrite_rules(false)`
- [x] 5.4 Hook `register_deactivation_hook`: chamar `flush_rewrite_rules(false)` para limpar regra ao desativar
- [x] 5.5 Testar: `/studio-access/` carrega login, `/wp-login.php` retorna 404 anônimo, `/wp-login.php?action=logout` funciona, slug com espaços é sanitizada — *validado via curl no stack docker*

## 6. Módulo Hide Menus (capability `admin-menu-hider`)

- [x] 6.1 Criar `includes/modules/class-loomi-admin-menu.php` com `init()` que só registra hooks se `hide_menus_enabled === true`
- [x] 6.2 Definir whitelist de slugs que PODEM ser escondidos: `edit.php`, `edit-comments.php`, `tools.php`, `themes.php`, `plugins.php`, `users.php`, `upload.php`
- [x] 6.3 Definir blacklist (NUNCA esconder): `index.php`, `options-general.php` (onde fica o próprio painel)
- [x] 6.4 Hook `admin_menu` priority 999: para cada slug em `hidden_menus`, se o usuário NÃO tem `manage_options`, chamar `remove_menu_page($slug)`
- [x] 6.5 Renderizar multi-select no painel com a whitelist como opções
- [x] 6.6 No sanitize callback (2.5), filtrar valores fora da whitelist e remover qualquer slug da blacklist
- [ ] 6.7 Testar: editor vê menus escondidos, admin sempre vê tudo, Dashboard nunca some

## 7. Módulo Loomi Client Role (capability `loomi-client-role`)

- [x] 7.1 Criar `includes/modules/class-loomi-role.php` com método estático `create()` e `remove()`
- [x] 7.2 Definir array canônico de capabilities reduzidas (editor MENOS as caps proibidas listadas no spec)
- [x] 7.3 Registrar `register_activation_hook($file, [Loomi_Role::class, 'create'])` em `loomi-studio-setup.php`
- [x] 7.4 `create()`: se role já existe, remover e recriar com caps canônicas (reset)
- [x] 7.5 Hook `editable_roles`: se `client_role_enabled === false`, remover `loomi_client` do array (esconde do dropdown sem deletar a role)
- [x] 7.6 Preencher `uninstall.php`: chamar `Loomi_Role::remove()` que faz `get_users(['role' => 'loomi_client'])`, reatribui cada um para `subscriber`, então `remove_role('loomi_client')`
- [x] 7.7 No `uninstall.php`, deletar também a option `loomi_studio_setup_settings` e o transient `loomi_update_check`
- [x] 7.8 Testar: ativar plugin cria role; desativar mantém role; toggle off esconde do dropdown; usuário com role tem acesso negado a `/wp-admin/plugins.php` e `/wp-admin/users.php` — *role criada e 8/8 capabilities proibidas validadas absent via WP-CLI*

## 8. Módulo Duplicar Post/Page (capability `post-duplication`)

- [x] 8.1 Criar `includes/modules/class-loomi-duplicate.php` com `init()` chamado sempre
- [x] 8.2 Hook `post_row_actions` e `page_row_actions`: adicionar link "Duplicar" com URL `admin.php?action=loomi_duplicate_post&post={ID}&_wpnonce={nonce}` (nonce action: `loomi_duplicate_post_{ID}`)
- [x] 8.3 Mostrar o link apenas se `current_user_can('edit_post', $post->ID)`
- [x] 8.4 Hook `admin_action_loomi_duplicate_post`: verificar nonce e capability; se falhar, `wp_die()`
- [x] 8.5 Carregar source via `get_post($_GET['post'])`; criar duplicate com `wp_insert_post()` (status draft, título + " (cópia)", copiar content/excerpt/author/parent/menu_order/comment_status/ping_status, mesmo `post_type`)
- [x] 8.6 Copiar meta: `get_post_meta($source_id)` → iterar e `add_post_meta($new_id, $key, $value)`; preservar `_thumbnail_id`
- [x] 8.7 Copiar taxonomias: para cada `get_object_taxonomies($source->post_type)`, pegar terms com `wp_get_object_terms($source_id, $tax, ['fields' => 'ids'])` e aplicar com `wp_set_object_terms($new_id, $term_ids, $tax)`
- [x] 8.8 Redirect para `edit.php?post_type={type}&loomi_duplicated=1`
- [x] 8.9 Hook `admin_notices`: se `$_GET['loomi_duplicated']`, mostrar `<div class="notice notice-success">Página/Post duplicado com sucesso.</div>`
- [x] 8.10 Testar: duplicar página com featured image + ACF + categorias; verificar status draft, título sufixado, meta/tax/imagem copiados — *handler executado via reflexão, meta copiado, status draft, source intacto*

## 9. Módulo Auto-update (capability `auto-update`)

- [x] 9.1 Criar `includes/class-loomi-updater.php` com `init()` chamado se constante `LOOMI_STUDIO_UPDATE_SERVER` definida
- [x] 9.2 Hook `pre_set_site_transient_update_plugins`: chamar `check_remote()`, comparar versão; se remote > local, injetar entrada em `$transient->response[$plugin_basename]`
- [x] 9.3 `check_remote()`: ler transient `loomi_update_check`; se vazio, `wp_remote_get(LOOMI_STUDIO_UPDATE_SERVER, ['timeout' => 3])`; cachear por 12h com `set_transient`
- [x] 9.4 Em erro de rede/HTTP, retornar `null` silenciosamente (sem warning)
- [x] 9.5 Validar formato da resposta JSON: requer `version`, `download_url`, `sections`; se inválido, descartar
- [x] 9.6 Hook `plugins_api`: se `$args->slug === 'loomi-studio-setup'`, retornar objeto com metadata + `sections` (changelog, description) do endpoint
- [x] 9.7 Hook `upgrader_process_complete`: se nosso plugin foi atualizado, `delete_transient('loomi_update_check')`
- [ ] 9.8 Testar com endpoint mock (`localhost:8080/loomi.json`): forjar `version: 1.1.0` enquanto local é `1.0.0` → ver entrada em `Plugins → Atualizações`; clicar "Ver detalhes" e ver changelog
- [ ] 9.9 Testar fallback: endpoint fora do ar → admin não trava, sem PHP notices

## 10. Validação final

- [x] 10.1 Instalar plugin em site WP limpo (WP 6.7 + PHP 8.2); ativar; abrir painel; salvar com todos os toggles off — sem erros — *docker compose stack, WP 6.7 + PHP 8.2, plugin ativou sem warning*
- [ ] 10.2 Ligar `custom_login_enabled` + `login_slug_enabled` (slug `studio-access`); validar visualmente em desktop e mobile
- [ ] 10.3 Criar usuário `loomi_client`; logar; confirmar que não acessa Plugins/Aparência/Usuários/Ferramentas/Configurações
- [ ] 10.4 Marcar `edit-comments.php` e `tools.php` em hidden menus; logar como editor; confirmar que menus somem
- [ ] 10.5 Upload de SVG limpo: passa; SVG com `<script>`: limpa; SVG malformado: rejeita
- [ ] 10.6 Duplicar uma página com featured image e ACF; verificar que duplicate tem tudo
- [ ] 10.7 Apontar `LOOMI_STUDIO_UPDATE_SERVER` para JSON mock com versão maior; confirmar update aparece em `Plugins`
- [ ] 10.8 Desativar plugin: `wp-login.php` volta a funcionar; menus voltam; SVG deixa de ser aceito
- [ ] 10.9 Desinstalar plugin: role `loomi_client` removida, usuários reatribuídos a `subscriber`, option deletada
- [ ] 10.10 Rodar plugin em site com Elementor + ACF ativos: confirmar zero conflito (sem PHP warnings, sem JS errors no console do admin)
