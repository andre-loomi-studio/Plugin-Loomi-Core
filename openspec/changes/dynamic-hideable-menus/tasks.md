## 1. Redefinir HIDEABLE_MENUS para 5 itens reais

- [x] 1.1 Em `class-settings-repository.php`, **reduzir HIDEABLE_MENUS para exatamente 5 entries**:
  - `'edit.php' => 'Posts'`
  - `'edit.php?post_type=page' => 'Páginas'` (novo)
  - `'edit-comments.php' => 'Comentários'`
  - `'upload.php' => 'Mídia'`
  - `'tools.php' => 'Ferramentas'`
- [x] 1.2 **Remover** `themes.php`, `plugins.php`, `users.php` do array (redundantes — WP já esconde por capability)
- [x] 1.3 Comentário em PHP acima do array explicando: "Estes são os menus que editor/loomi_client realmente vê. WP já esconde Plugins/Themes/Users por capability."

## 2. Método dinâmico `hideable_menus()`

- [x] 2.1 Adicionar campo estático privado `$hideable_cache` (nullable array) em `Settings_Repository`
- [x] 2.2 Criar método estático público `hideable_menus(): array` retornando merge de `HIDEABLE_MENUS` + CPTs descobertos
- [x] 2.3 Descoberta via `get_post_types( ['show_ui' => true, 'show_in_menu' => true, '_builtin' => false], 'objects' )`
- [x] 2.4 Para cada CPT, slug = `'edit.php?post_type=' . $cpt->name`; label = `$cpt->labels->menu_name ?? $cpt->labels->name ?? $cpt->name`
- [x] 2.5 Pular CPT cujo slug já está em `HIDEABLE_MENUS` (core tem precedência)
- [x] 2.6 Memoizar resultado em `self::$hideable_cache`; retornar cache em chamadas subsequentes do mesmo request
- [x] 2.7 Atualizar `Settings_Repository::clear_cache()` para zerar também `$hideable_cache`

## 3. Sanitizer valida contra lista dinâmica

- [x] 3.1 Em `Settings_Sanitizer::sanitize()`, substituir `Settings_Repository::HIDEABLE_MENUS` por `Settings_Repository::hideable_menus()` na validação de `hidden_menus`
- [x] 3.2 Confirmar que blacklist (BLACKLISTED_MENUS) continua sendo respeitado antes do whitelist check

## 4. Admin_Menu usa lista dinâmica

- [x] 4.1 Em `Loomi_Admin_Menu::hide_menus()`, substituir `Settings_Repository::HIDEABLE_MENUS` por `Settings_Repository::hideable_menus()`
- [x] 4.2 Confirmar que `remove_menu_page( 'edit.php?post_type=foo' )` funciona pra CPTs top-level (não submenu)

## 5. UI: Tab_Hide_Menus com disclaimer + dois grupos

- [x] 5.1 No topo do `render()`, renderizar `<div class="notice notice-info inline"><p>` com texto: "Esta lista contém apenas menus que usuários sem permissão de administrador (editores, clientes Loomi) normalmente veem. O WordPress já esconde automaticamente Plugins, Temas, Usuários e Configurações para usuários sem a capability correspondente."
- [x] 5.2 Separar `core_menus = HIDEABLE_MENUS` de `cpt_menus = array_diff_key( hideable_menus(), HIDEABLE_MENUS )`
- [x] 5.3 Renderizar `<fieldset>` com legend "WordPress" contendo os 5 checkboxes core
- [x] 5.4 Renderizar segundo `<fieldset>` com legend "Custom Post Types" contendo os N CPTs (ordenados alfabeticamente por label)
- [x] 5.5 Se `cpt_menus` vazio, mostrar `<p>` informativo "Nenhum Custom Post Type encontrado neste site." em vez de checkboxes
- [x] 5.6 Manter o `<p class="description">` final sobre Dashboard/Configurações não-removíveis

## 6. Defaults — 5 core slugs pré-marcados

- [x] 6.1 Em `Settings_Repository::defaults()`, confirmar que `array_keys( self::HIDEABLE_MENUS )` retorna 5 itens (Posts, Páginas, Comentários, Mídia, Ferramentas)
- [x] 6.2 CPTs não-core continuam fora do default `hidden_menus`

## 7. Testes — HideMenusTest

- [x] 7.1 Adicionar `register_post_type('mock_cpt', [...])` em `set_up()` ou em test específico, com `tear_down()` chamando `unregister_post_type`
- [x] 7.2 Novo teste `test_dynamic_cpt_appears_in_hideable_menus`: registra mock_cpt, assert que `Settings_Repository::hideable_menus()` contém `edit.php?post_type=mock_cpt`
- [x] 7.3 Novo teste `test_cpt_menu_hidden_when_configured`: marca CPT em `hidden_menus`, popula `$menu` global, chama `hide_menus()`, assert que CPT slug some
- [x] 7.4 Novo teste `test_builtin_cpts_not_duplicated_in_hideable_menus`: assert que `hideable_menus()` NÃO contém `edit.php?post_type=post` (já está como `edit.php` core)
- [x] 7.5 Novo teste `test_pages_included_in_core_hideable_menus`: assert que `HIDEABLE_MENUS` tem 5 entries e contém `edit.php?post_type=page`
- [x] 7.6 Novo teste `test_redundant_core_slugs_removed`: assert que `HIDEABLE_MENUS` NÃO contém `themes.php`, `plugins.php`, `users.php`
- [x] 7.7 Novo teste `test_tab_renders_disclaimer`: render do `Tab_Hide_Menus`, assert que HTML tem "WordPress já esconde automaticamente"
- [x] 7.8 **Atualizar testes que assumiam 7 itens** em `HideMenusTest`/`SettingsRepositoryTest`/`SettingsSanitizerTest`: ajustar contagens e listas esperadas para 5 itens core

## 8. Testes — SettingsRepositoryTest

- [x] 8.1 Novo teste `test_hideable_menus_returns_core_when_no_cpts`: vanilla WP retorna 8 entries
- [x] 8.2 Novo teste `test_hideable_menus_memoizes`: chama duas vezes, mock `get_post_types` count de chamadas = 1 (via tracking flag global)
- [x] 8.3 Novo teste `test_clear_cache_resets_hideable_memo`: clear_cache + nova CPT registrada → aparece no próximo call

## 9. Testes — SettingsSanitizerTest

- [x] 9.1 Novo teste `test_cpt_slug_accepted`: registra CPT, sanitize com `hidden_menus=['edit.php?post_type=foo']` → slug preservado
- [x] 9.2 Novo teste `test_unregistered_cpt_slug_filtered`: sanitize com slug de CPT NÃO registrado → slug dropado
- [x] 9.3 Atualizar `test_unknown_menu_slug_filtered` se ainda relevante (semântica não muda)

## 10. Sync + lint + run suite

- [x] 10.1 Lint PHP de todos arquivos alterados (4 do plugin + 3 de tests)
- [x] 10.2 Sync pro container `loomi-clean-wp` via `docker cp`
- [x] 10.3 Reset do option (`wp option delete loomi_studio_setup_settings`) pra forçar defaults atualizados
- [x] 10.4 Rodar suite completa: `bash tests/run.sh` — esperado **70 anteriores + ~9 novos = ~79 testes passando**

## 11. Validação visual manual (cURL)

- [x] 11.1 Sobe stack docker se necessário, acessa `/wp-admin/options-general.php?page=loomi-studio-setup&tab=hide-menus`
- [x] 11.2 Confirma via HTML curl: tem 8 checkboxes no fieldset "WordPress" + mensagem "Nenhum Custom Post Type encontrado" (stack base sem plugins extras)
- [x] 11.3 (Opcional) Instalar Akismet/Hello Dolly não adiciona CPT — confirma que estado vazio do CPT fieldset persiste

## 12. README + ZIP

- [x] 12.1 Atualizar seção "Esconder Menus" no `README.md`: agora **5 core** (Posts, Páginas, Comentários, Mídia, Ferramentas) + CPTs dinâmicos descobertos
- [x] 12.2 Adicionar nota: "Plugins/Themes/Users/Settings NÃO estão na lista porque WordPress já esconde esses menus automaticamente para usuários sem `activate_plugins`/`switch_themes`/`list_users`/`manage_options`. O `loomi_client` herda de `editor` que não tem nenhuma dessas caps."
- [x] 12.3 Adicionar nota "Known limitation: CPTs como submenu (`show_in_menu => string`) não são removíveis em v1"
- [x] 12.4 Rebuild ZIP de produção (sem tests/vendor) via Python `zipfile`
- [x] 12.5 Confirmar `loomi-studio-setup-1.0.0.zip` tem nova estrutura e arquivos alterados
