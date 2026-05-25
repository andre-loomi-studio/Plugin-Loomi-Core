## Why

A Loomi opera/mantém múltiplos sites WordPress para clientes e replica, manualmente em cada site, o mesmo conjunto de ajustes (upload de SVG, login customizado, ocultação de menus do admin, role de cliente, duplicação de páginas/posts). Esse trabalho repetitivo gera inconsistência entre sites, perda de tempo a cada novo projeto e dificulta lançar correções/ajustes em todos os ambientes ao mesmo tempo. Um único plugin proprietário, com painel de configuração por site e mecanismo de auto-update centralizado, transforma esses ajustes em um produto interno versionado e governável.

## What Changes

- Novo plugin WordPress **Loomi Studio Setup** (slug: `loomi-studio-setup`) com painel admin único em `Configurações → Loomi Studio`.
- **Allow SVG** (sempre ativo, sem UI): habilita upload de `image/svg+xml`, faz sanitização do conteúdo SVG antes do upload e corrige o preview na Media Library.
- **Custom Login** (toggle on/off + personalização): cor de fundo da tela de login, logo customizado (upload de mídia), link do logo apontando para `home_url()`, título do logo igual ao nome do site.
- **Custom Login Slug** (toggle on/off + campo de texto): redefine a URL de login (default `/studio-access`) bloqueando o acesso direto a `wp-login.php` para não-logados.
- **Hide Admin Menus** (toggle on/off + multi-select): permite ocultar menus padrão do WP (Posts, Comentários, Ferramentas, etc.) na visão do admin/cliente — configuração por site.
- **Loomi Client Role** (toggle on/off): cria a role `loomi_client` com capabilities reduzidas (sem acesso a Aparência, Plugins, Usuários, Ferramentas, Configurações); quando o toggle é desligado a role é mantida mas o admin recupera a UI completa.
- **Duplicate Posts & Pages** (sempre ativo, sem UI): adiciona ação "Duplicar" nas listagens de `post` e `page`, criando rascunho com mesmo conteúdo, meta, taxonomias e featured image.
- **Auto-update**: integração com servidor de updates próprio (JSON endpoint) via filtro `pre_set_site_transient_update_plugins`, permitindo distribuir novas versões para todos os sites que tenham o plugin.
- Todas as opções persistem em uma única option (`loomi_studio_setup_settings`) com defaults seguros; o painel renderiza via Settings API nativa do WP (sem React/JS pesado) para manter performance.

## Capabilities

### New Capabilities
- `svg-upload`: habilitação segura (com sanitização) de upload de SVG e fix do preview na Media Library — sempre ativo.
- `custom-login`: personalização visual da tela `wp-login.php` (cor de fundo, logo, URL/título do logo) com toggle e campos por site.
- `login-slug`: redefinição da slug de login com bloqueio do acesso a `wp-login.php` por usuários não autenticados.
- `admin-menu-hider`: ocultação seletiva de menus padrão do WP admin, configurável por site, aplicada por role.
- `loomi-client-role`: role `loomi_client` com capabilities reduzidas, toggle de ativação/desativação por site.
- `post-duplication`: ação "Duplicar" para `post` e `page` na listagem do admin — sempre ativa.
- `plugin-settings`: painel de configuração único em `Configurações → Loomi Studio` que orquestra todos os toggles e campos via Settings API.
- `auto-update`: mecanismo de update server-driven para distribuir novas versões do plugin a todos os sites instalados.

### Modified Capabilities
<!-- Não aplicável: nenhum spec pré-existente em openspec/specs/. -->

## Impact

- **Novo plugin** standalone em `loomi-studio-setup/` (estrutura PSR-4 leve + arquivos procedurais para hooks). Sem dependências externas (composer opcional só para a lib de sanitização SVG, ou implementação interna mínima).
- **Banco**: 1 nova option (`loomi_studio_setup_settings`, autoload `yes`) + 1 transient para o checker de update; criação da role `loomi_client` em `register_activation_hook` e remoção em `register_uninstall_hook`.
- **wp-login.php**: hooks `login_enqueue_scripts`, `login_headerurl`, `login_headertext`, `login_init` (para o gate da slug). Rewrite rule para a slug custom via `add_rewrite_rule` + flush no salvar das settings.
- **Media**: filtros `upload_mimes`, `wp_check_filetype_and_ext`, `wp_handle_upload_prefilter` (sanitização SVG), `wp_prepare_attachment_for_js` (preview).
- **Admin UI**: filtros `admin_menu`, `add_filter('post_row_actions')`, `add_filter('page_row_actions')`, `admin_action_loomi_duplicate_post`.
- **Updates**: filtros `pre_set_site_transient_update_plugins`, `plugins_api`, `upgrader_process_complete`; endpoint JSON em servidor controlado pela Loomi (URL definida em constante do plugin).
- **Compatibilidade**: WP ≥ 6.0, PHP ≥ 7.4. Sem conflito esperado com Elementor/ACF/WPML (apenas hooks padrão).
- **Performance**: tudo carregado via hooks lazy; nenhum asset CSS/JS no front-end público; admin assets só na tela de settings do plugin.
