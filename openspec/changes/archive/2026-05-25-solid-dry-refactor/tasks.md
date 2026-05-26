## 1. Estrutura de pastas e constantes

- [x] 1.1 Criar pasta `includes/contracts/`, `includes/support/`, `includes/settings/tabs/`
- [x] 1.2 Criar `includes/class-plugin.php` com classe final `Plugin` contendo constantes: `SLUG`, `VERSION`, `OPTION_KEY`, `SETTINGS_PAGE`, `TEXT_DOMAIN`, `NONCE_PREFIX`, `WORDFENCE_FILE`, `UPDATE_TRANSIENT`, `UPDATE_TTL`
- [x] 1.3 Adicionar `require_once` de `class-plugin.php` no topo dos requires em `loomi-studio-setup.php`

## 2. Contratos (interfaces)

- [x] 2.1 Criar `includes/contracts/interface-module.php` com interface `Loomi_Module` (método `register(): void`)
- [x] 2.2 Criar `includes/contracts/interface-settings-tab.php` com interface `Loomi_Settings_Tab` (`slug(): string`, `label(): string`, `render(array $settings): void`)

## 3. Settings Repository (extrair de `Loomi_Settings`)

- [x] 3.1 Criar `includes/support/class-settings-repository.php` com classe `Settings_Repository`
- [x] 3.2 Mover `defaults()`, `all()`, `get()`, `clear_cache()` para `Settings_Repository` (mesma assinatura)
- [x] 3.3 Adicionar `Settings_Repository::get_bool($key): bool` que força cast usando `FILTER_VALIDATE_BOOLEAN`
- [x] 3.4 Mover constantes `HIDEABLE_MENUS`, `BLACKLISTED_MENUS`, `RESERVED_SLUGS` para `Settings_Repository`
- [x] 3.5 Cache estático único em `Settings_Repository::$cache` (eliminar duplicação)

## 4. Settings Sanitizer

- [x] 4.1 Criar `includes/support/class-settings-sanitizer.php` com classe `Settings_Sanitizer`
- [x] 4.2 Mover método `sanitize($input)` de `Loomi_Settings` para `Settings_Sanitizer::sanitize($input)`
- [x] 4.3 Usar `Plugin::OPTION_KEY` e referências de `Settings_Repository` para defaults
- [x] 4.4 Invalidar cache após sanitize: chamar `Settings_Repository::clear_cache()`

## 5. Settings Tabs (Strategy pattern)

- [x] 5.1 Criar `includes/settings/tabs/class-tab-login.php` implementando `Loomi_Settings_Tab` — extrair `render_login_tab()` atual
- [x] 5.2 Criar `includes/settings/tabs/class-tab-slug.php` — extrair `render_slug_tab()`
- [x] 5.3 Criar `includes/settings/tabs/class-tab-hide-menus.php` — extrair `render_menus_tab()`
- [x] 5.4 Criar `includes/settings/tabs/class-tab-client-role.php` — extrair `render_role_tab()`
- [x] 5.5 Cada tab é um arquivo isolado, < 80 linhas, sem dependência cruzada

## 6. Settings Page

- [x] 6.1 Criar `includes/settings/class-settings-page.php` com classe `Loomi_Settings_Page implements Loomi_Module`
- [x] 6.2 Mover `register_page()`, `register_setting()`, `enqueue_assets()`, `render_page()` para `Settings_Page`
- [x] 6.3 Método `tabs(): array` retorna lista de instâncias das 4 tab classes (ordem fixa)
- [x] 6.4 `render_page()` itera sobre tabs() chamando `render($settings)` na ativa, escondendo as outras
- [x] 6.5 Settings_Page::register() registra hooks: `admin_menu`, `admin_init`, `admin_enqueue_scripts`

## 7. Login_URLs helper

- [x] 7.1 Criar `includes/support/class-login-urls.php` com classe `Login_URLs`
- [x] 7.2 Método estático `build($action = '', $extra = []): string` constrói URL slug-based
- [x] 7.3 Trim e validate slug (fallback pra default se vazio)
- [x] 7.4 rawurlencode em extras + tratamento de null/empty

## 8. Refactor Loomi_Login

- [x] 8.1 Adicionar `implements Loomi_Module` em `Loomi_Login`
- [x] 8.2 Renomear `init()` → `register()` (manter mesmo conteúdo)
- [x] 8.3 Substituir 5 métodos `rewrite_*_url` por wrappers thin (≤5 linhas) que chamam `Login_URLs::build()`
- [x] 8.4 Substituir `Loomi_Settings::get()` por `Settings_Repository::get()`/`get_bool()`
- [x] 8.5 Remover método privado `slug_url()` (substituído por `Login_URLs::build()`)

## 9. Refactor outros módulos para implementar Module

- [x] 9.1 `Loomi_SVG`: adicionar `implements Loomi_Module`, renomear `init()` → `register()`
- [x] 9.2 `Loomi_Admin_Menu`: idem; referenciar `Settings_Repository::HIDEABLE_MENUS` e `BLACKLISTED_MENUS`
- [x] 9.3 `Loomi_Role`: idem
- [x] 9.4 `Loomi_Duplicate`: idem
- [x] 9.5 `Loomi_Wordfence_Check`: idem; usar `Plugin::WORDFENCE_FILE`
- [x] 9.6 `Loomi_Updater`: idem; usar `Plugin::UPDATE_TRANSIENT` e `Plugin::UPDATE_TTL`

## 10. Backwards-compat alias

- [x] 10.1 Criar `includes/class-loomi-settings-legacy.php` (ou manter `class-loomi-settings.php` slim) com classe `Loomi_Settings`
- [x] 10.2 Métodos `Loomi_Settings::get($k, $default)` etc. são thin aliases para `Settings_Repository::*`
- [x] 10.3 Cada método legado dispara `_deprecated_function( __METHOD__, '1.1.0', 'Settings_Repository::*' )`
- [x] 10.4 Manter constantes `Loomi_Settings::OPTION_KEY` etc. como aliases para `Plugin::*`

## 11. Bootstrap simplificado

- [x] 11.1 Em `loomi-studio-setup.php`, listar módulos: `$modules = [Loomi_SVG::class, Loomi_Login::class, Loomi_Admin_Menu::class, Loomi_Role::class, Loomi_Duplicate::class, Loomi_Wordfence_Check::class, Loomi_Settings_Page::class]`
- [x] 11.2 Loop: `foreach ($modules as $m) { $m::register(); }`
- [x] 11.3 Updater fica separado (só roda se constante `LOOMI_STUDIO_UPDATE_SERVER` definida)
- [x] 11.4 Remover ifs específicos do bootstrap; cada módulo gatekeeps internamente

## 12. Lint e sync

- [x] 12.1 Rodar `php -l` em todos os arquivos novos e modificados
- [x] 12.2 Sync arquivos pro container docker via `docker cp`
- [x] 12.3 Verificar que nenhum erro fatal acontece no admin (`wp eval` simples)

## 13. Validação de paridade — re-rodar os 81 testes

- [x] 13.1 `test-svg-sanitizer.php` — 11/11 PASS
- [x] 13.2 Role caps proibidas — 8/8 absent
- [x] 13.3 Duplicate page com `_thumbnail_id` + ACF meta + array serializado
- [x] 13.4 Custom login CSS injetado (`<style id=loomi-login>`, cor `#0044ff`)
- [x] 13.5 Login slug: `/wp-login.php` → 404, `/studio-access/` → 200, `/wp-admin/` sem auth → 302 → `/studio-access/`
- [x] 13.6 POST de login via `/studio-access/` → 302 → `/wp-admin/`
- [x] 13.7 Updater offline (fallback null em <3.5s, sem warnings)
- [x] 13.8 Updater mock endpoint → inject_update + plugins_api
- [x] 13.9 Hide menus pra editor: edit-comments/tools = 0 `<li>` sidebar; Dashboard mantido
- [x] 13.10 loomi_client acesso negado: plugins/users/themes/options-general = 403
- [x] 13.11 Deactivate restaura `/wp-login.php` = 200 + remove SVG MIME
- [x] 13.12 Uninstall remove role + option + reatribui usuários pra subscriber
- [x] 13.13 Backwards-compat: `Loomi_Settings::get('login_slug')` retorna valor correto + dispara `_deprecated_function`

## 14. Empacotamento

- [x] 14.1 Rebuildar ZIP via Python `zipfile` (incluindo nova estrutura de pastas)
- [x] 14.2 Confirmar tamanho razoável (< 30 KB)
- [x] 14.3 Verificar estrutura: `loomi-studio-setup/includes/{contracts,support,settings/tabs,modules}/`
- [x] 14.4 Test install em stack docker limpo: `wp plugin install loomi-studio-setup-1.0.0.zip --activate`

## 15. Documentação

- [x] 15.1 Atualizar seção "Arquitetura" do `README.md` com a nova estrutura de pastas
- [x] 15.2 Adicionar nota em "Roadmap" sobre `Loomi_Settings` deprecated → remoção planejada em 1.2.0
