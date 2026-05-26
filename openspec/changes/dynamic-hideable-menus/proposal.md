## Why

Três gaps identificados na feature "Esconder Menus":

1. **Páginas faltando**: a lista hardcoded tem Posts (`edit.php`) mas não tem `edit.php?post_type=page` — vários sites Loomi querem esconder páginas do cliente.
2. **Itens redundantes**: a lista atual tem `themes.php`, `plugins.php` e `users.php` — mas WordPress **já esconde esses menus automaticamente** para usuários sem as capabilities (`switch_themes`, `activate_plugins`, `list_users`). Como `loomi_client` herda de `editor` (que não tem nenhuma dessas), esses 3 nunca aparecem pra ele. Têm-los como opção polui a UI com falsa sensação de controle.
3. **CPTs invisíveis**: quando o site instala plugins que registram Custom Post Types (Elementor → Templates, WooCommerce → Products/Orders, ACF → Field Groups), esses menus **não aparecem** na lista do painel — admin não consegue escondê-los via UI sem código.

Em sites Loomi reais, isso significa que clientes logados como `loomi_client` veem menus de CPTs que não deveriam mexer (ex: WooCommerce Reports, Elementor Templates). Bug de UX recorrente.

Esta change torna a lista de menus dinâmica: **a cada render do painel, descobre CPTs registrados no site e os adiciona como opções de "esconder"**. Cada CPT pode então ser ligado/desligado individualmente, persistido por site, igual aos 7 menus core.

## What Changes

- **Reduzir `HIDEABLE_MENUS` de 7 para 5 itens** (remover `themes.php`, `plugins.php`, `users.php` — redundantes porque WP já esconde por capability), e **adicionar `edit.php?post_type=page` (Páginas)** — resultado: 5 menus que `editor`/`loomi_client` REALMENTE vê hoje:
  - `edit.php` — Posts
  - `edit.php?post_type=page` — Páginas
  - `edit-comments.php` — Comentários
  - `upload.php` — Mídia
  - `tools.php` — Ferramentas
- **Disclaimer visível no painel** em `Tab_Hide_Menus`: "Esta lista contém apenas menus que usuários sem permissão de administrador (editores, clientes Loomi) normalmente veem. O WordPress já esconde automaticamente Plugins, Temas, Usuários e Configurações para usuários sem a capability correspondente."
- Novo método `Settings_Repository::hideable_menus(): array` que retorna o array `[slug => label]` combinando os **5 menus core** hardcoded + CPTs públicos descobertos via `get_post_types( ['show_ui' => true, 'show_in_menu' => true, '_builtin' => false] )`.
- `Settings_Sanitizer::sanitize()` passa a validar `hidden_menus` contra a lista **dinâmica** (`hideable_menus()`) em vez da const `HIDEABLE_MENUS`.
- `Loomi_Admin_Menu::hide_menus()` idem — valida + remove menus tanto core quanto CPT.
- `Tab_Hide_Menus::render()` renderiza dois grupos visualmente separados: "WordPress" (7 itens core) e "Custom Post Types" (N itens, depende do site).
- CPTs **NÃO** entram em `hidden_menus` por padrão — admin precisa marcar explicitamente cada um.
- Suite WP-PHPUnit: novo teste registrando um CPT mock no setup e validando que ele aparece em `hideable_menus()`, é sanitizado corretamente, e é removido da sidebar quando incluído em `hidden_menus`.

## Capabilities

### New Capabilities
- `dynamic-menu-discovery`: descoberta runtime de Custom Post Types registrados como menus admin, integração com a feature existente de hide-menus, persistência opt-in por CPT.

### Modified Capabilities
<!-- A capability `admin-menu-hider` (na change arquivada loomi-studio-setup-plugin) ganha um comportamento adicional, mas sem quebra: o conjunto de slugs hideable agora é dinâmico em vez de hardcoded. Comportamento dos 7 menus core idêntico ao anterior. -->

## Impact

- **Arquivos alterados**:
  - `includes/support/class-settings-repository.php` — adiciona método `hideable_menus()` com memo de request.
  - `includes/support/class-settings-sanitizer.php` — troca `Settings_Repository::HIDEABLE_MENUS` (const) por `Settings_Repository::hideable_menus()` (método dinâmico).
  - `includes/modules/class-loomi-admin-menu.php` — mesmo ajuste.
  - `includes/settings/tabs/class-tab-hide-menus.php` — renderiza dois grupos: "WordPress" e "Custom Post Types".
  - `tests/integration/HideMenusTest.php` — adicionar 3 testes pra CPT discovery.
  - `tests/integration/SettingsRepositoryTest.php` — adicionar 2 testes pra `hideable_menus()`.
  - `tests/integration/SettingsSanitizerTest.php` — adicionar 1 teste pra CPT slug aceito + 1 teste pra slug não-registrado rejeitado.
  - `README.md` — atualizar seção "Esconder Menus" com nota sobre CPTs dinâmicos.
- **Sem mudança de banco**: a option `hidden_menus` continua sendo um array de slugs string; CPT slugs (ex: `edit.php?post_type=product`) entram exatamente como as core slugs entram hoje.
- **Sem mudança no contrato externo**: hooks, filtros e endpoints idênticos.
- **Performance**: `hideable_menus()` chama `get_post_types()` uma vez por request (memo estático). Em sites com poucos CPTs (5-20) é < 1ms.
- **Compatibilidade**: WP ≥ 6.0, PHP ≥ 7.4 (sem mudança). Pré-requisito: `solid-dry-refactor` aplicado (já arquivado).
- **Risco**: CPTs com `show_in_menu => string` (submenu de outro menu, ex: WooCommerce sub-itens) não são suportados em v1 — `remove_menu_page` não os remove. Documentar como known limitation; futura change v2 pode adicionar suporte via `remove_submenu_page`.
