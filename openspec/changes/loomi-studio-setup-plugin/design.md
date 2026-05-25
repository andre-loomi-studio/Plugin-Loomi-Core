## Context

A Loomi mantém vários sites WordPress como agência/studio. Hoje, cada site recebe manualmente um pacote de snippets em `functions.php` ou plugins ad-hoc para resolver coisas recorrentes: upload de SVG, customização da tela de login, ocultação de menus do admin para clientes, role de cliente, duplicação de posts/páginas. Não há um canal único para distribuir correções/atualizações desses snippets — quando algo muda, é preciso editar site por site.

Pilha-alvo: WordPress ≥ 6.0, PHP ≥ 7.4, sem build step obrigatório, sem dependência de plugins de terceiros. Os sites variam entre instalações com Elementor, ACF, WooCommerce e WPML, então o plugin precisa ser leve, idempotente e não-intrusivo.

Stakeholders: time de desenvolvimento da Loomi (instala/configura o plugin em novos sites), clientes finais (logam com a role `loomi_client` e veem um admin enxuto), Loomi central (publica novas versões no servidor de updates).

## Goals / Non-Goals

**Goals:**
- Unificar em um único plugin os ajustes recorrentes que hoje vivem como snippets soltos.
- Painel admin único, simples (Settings API nativa), com toggles por funcionalidade.
- Permitir distribuir atualizações para todos os sites via auto-update server-driven.
- SVG upload com **sanitização** (não apenas liberar o MIME — é vetor de XSS conhecido).
- Login slug customizada bloqueando `wp-login.php` para não autenticados (proteção básica contra bots).
- Performance: zero impacto no front-end público, hooks lazy no admin.

**Non-Goals:**
- Não substituir um plugin completo de white-label de admin (ex.: White Label CMS) — o escopo é o que a Loomi usa, não tudo que existe.
- Não implementar duplicação de Custom Post Types arbitrários — apenas `post` e `page` (extensível depois via filtro).
- Não fazer 2FA, brute-force protection ou outros recursos de segurança avançada no login — só a renomeação da slug + customização visual.
- Não construir UI em React/JS pesado — Settings API + CSS mínimo.
- Não suportar PHP < 7.4 nem WP < 6.0.

## Decisions

### 1. Estrutura de arquivos: organização leve sem framework

```
loomi-studio-setup/
├── loomi-studio-setup.php           # Plugin header + bootstrap
├── uninstall.php                     # Cleanup ao desinstalar
├── includes/
│   ├── class-loomi-plugin.php        # Singleton bootstrap, registra módulos
│   ├── class-loomi-settings.php      # Settings API + render do painel
│   ├── class-loomi-updater.php       # Auto-update via JSON endpoint
│   └── modules/
│       ├── class-loomi-svg.php       # Upload + sanitização SVG (sempre on)
│       ├── class-loomi-login.php     # Custom login (visual + slug)
│       ├── class-loomi-admin-menu.php # Hide menus
│       ├── class-loomi-role.php      # loomi_client role
│       └── class-loomi-duplicate.php # Duplicar post/page (sempre on)
├── assets/
│   ├── admin.css                     # Estilos do painel (carregado só na tela do plugin)
│   └── login.css                     # Template CSS do login (variáveis injetadas inline)
└── languages/
    └── loomi-studio-setup.pot
```

**Por quê assim?** Cada módulo é uma classe autocontida com método `init()` chamado pelo bootstrap. Isso mantém os hooks isolados, facilita testar e ligar/desligar conforme settings. Sem PSR-4 nem composer obrigatório — autoload manual via `require_once` no bootstrap; mantém o plugin instalável apenas copiando arquivos.

**Alternativa considerada:** OOP completo com namespaces + composer autoload. Rejeitado por adicionar fricção de build/deploy para um plugin pequeno de uso interno.

### 2. Persistência: uma única option agregada

Todas as configs vivem em `wp_options` sob a chave `loomi_studio_setup_settings` (autoload `yes`), como array associativo:

```php
[
  'custom_login_enabled'   => true,
  'custom_login_bg_color'  => '#000000',
  'custom_login_logo_id'   => 0,           // attachment ID
  'login_slug_enabled'     => true,
  'login_slug'             => 'studio-access',
  'hide_menus_enabled'     => true,
  'hidden_menus'           => ['edit-comments.php', 'edit.php'],
  'client_role_enabled'    => true,
]
```

**Por quê?** Uma única leitura de option carrega tudo (autoload em memória). Evita N queries por request. SVG upload e duplicate são sempre on — não vão para option.

**Alternativa considerada:** uma option por módulo. Rejeitado: multiplica I/O e complica defaults/migração.

### 3. SVG: liberar MIME **com** sanitização

Apenas registrar `image/svg+xml` em `upload_mimes` é inseguro — SVG pode conter `<script>`, `onload`, payloads XSS. Decisão: usar `wp_handle_upload_prefilter` para passar o conteúdo do arquivo por um sanitizador antes de aceitar. Implementação mínima própria (lista de tags/atributos permitidos via `DOMDocument`) para não adicionar dependência composer.

**Alternativa considerada:** lib `enshrined/svg-sanitize`. Rejeitada inicialmente para zero deps; se a sanitização própria provar insuficiente, migra para a lib via composer e bundle.

### 4. Custom login: CSS por variáveis injetadas, não template completo

`login_enqueue_scripts` injeta um `<style>` inline com:
```css
:root { --loomi-login-bg: #000; --loomi-login-logo: url('...'); }
body.login { background: var(--loomi-login-bg); }
.login h1 a { background-image: var(--loomi-login-logo); width:320px; height:120px; background-size:contain; }
```

**Por quê?** Mantém compatibilidade total com o markup nativo do `wp-login.php`. Não substituímos a tela — só estilizamos. Permite WP atualizar a tela sem quebrar nosso CSS.

### 5. Login slug: rewrite + gate em `login_init`

Duas camadas:
1. `add_rewrite_rule('^' . $slug . '/?$', 'wp-login.php', 'top')` — permite acessar a tela pela URL custom.
2. Hook em `login_init`: se a request veio para `wp-login.php` literal E o usuário não está autenticado E não é uma `action` permitida (`logout`, `lostpassword`, `rp`, `resetpass`, `postpass`), retorna 404 com `wp_die()`.

Flush rewrite em `update_option_loomi_studio_setup_settings` quando a slug muda (não a cada save — só se mudou).

**Alternativa considerada:** mover o arquivo `wp-login.php` ou interceptar em `init`. Rejeitada — modificar core ou interceptar mais cedo quebra fluxos de plugins de cache/CDN e tem efeitos colaterais (logout, recuperar senha).

### 6. Hide menus: filtro `admin_menu` com prioridade 999

Aplicado APENAS quando o usuário NÃO tem capability `manage_options` (clientes), OU quando o admin está com toggle "esconder também para mim" — por enquanto, só para não-admins. Lista de slugs configurável: `edit.php`, `edit-comments.php`, `tools.php`, `themes.php`, etc.

Implementação: `remove_menu_page($slug)` para cada item ativado.

### 7. Role `loomi_client`: criada na ativação, removida no uninstall

Criada em `register_activation_hook` com capabilities mínimas (mesmas de `editor` MENOS `edit_theme_options`, `manage_options`, `list_users`, `edit_users`, etc.). Toggle controla apenas se a UI extra de ocultação se aplica — a role permanece existindo enquanto o plugin estiver ativo, para não desassociar usuários já criados.

Uninstall (`uninstall.php`) remove a role e reatribui usuários para `subscriber` (decisão segura — não apaga usuários).

### 8. Duplicar post/page: ação no row + handler

`post_row_actions` e `page_row_actions` adicionam link `?action=loomi_duplicate_post&post={id}&_wpnonce=...`. Handler em `admin_action_loomi_duplicate_post`:
1. `current_user_can('edit_posts')` + verify nonce.
2. `wp_insert_post()` com `post_status='draft'`, copiando título (sufixado `(cópia)`), content, excerpt, author, parent.
3. Copia: meta (`get_post_meta($id)`), taxonomias (`wp_set_object_terms`), featured image (`_thumbnail_id`).
4. Redirect para `edit.php?...&duplicated=1` com admin_notice.

**Não copia:** comentários, post date (usa "now"). Sempre cria como draft.

### 9. Auto-update: JSON endpoint controlado pela Loomi

Constante `LOOMI_STUDIO_UPDATE_SERVER` em `loomi-studio-setup.php` aponta para `https://updates.loomi.studio/loomi-studio-setup.json`. Servidor responde:
```json
{
  "version": "1.2.0",
  "download_url": "https://updates.loomi.studio/loomi-studio-setup-1.2.0.zip",
  "requires": "6.0",
  "tested": "6.7",
  "requires_php": "7.4",
  "sections": { "changelog": "...", "description": "..." }
}
```

Hooks:
- `pre_set_site_transient_update_plugins`: injeta resposta se versão remota > local. Cache: `set_transient('loomi_update_check', $data, 12 * HOUR_IN_SECONDS)`.
- `plugins_api`: serve metadata para o modal "Ver detalhes" do WP.
- `upgrader_process_complete`: limpa transient após update.

**Por quê não usar repositório oficial WP.org?** Plugin é interno/proprietário; queremos controle de quem recebe updates.

**Alternativa considerada:** GitHub releases via plugin `plugin-update-checker` da YahnisElsts. Boa opção; decisão: implementar mínimo próprio para não trazer dep; se o checker próprio crescer, migra.

### 10. Painel admin: Settings API nativa

Página em `Configurações → Loomi Studio` (`options-general.php?page=loomi-studio-setup`). Tabs visuais via CSS (`<a class="nav-tab">`) mas tudo um único form. `register_setting`, `add_settings_section`, `add_settings_field` — zero JS além do media uploader nativo do WP para o logo.

**Por quê?** Settings API é a forma canônica, recebe nonces, sanitize callbacks e capability checks gratuitamente. Sem React/Vue/jQuery extra.

## Risks / Trade-offs

- **[Risco] Sanitização SVG própria pode deixar passar payload XSS** → Mitigação: começar com whitelist restrita de tags/atributos (`svg, g, path, rect, circle, ellipse, line, polyline, polygon, text, defs, use, title, desc, style, linearGradient, radialGradient, stop, mask, clipPath` + atributos `d, fill, stroke, transform, viewBox, width, height, x, y, cx, cy, r, rx, ry, points, opacity, class, id`); bloquear `<script>`, `on*` handlers, `xlink:href` com `javascript:`. Testar com payloads conhecidos (OWASP XSS cheat). Se complexidade crescer, adotar `enshrined/svg-sanitize`.

- **[Risco] Renomear login slug pode quebrar plugins de cache/CDN que reescrevem `wp-login.php`** → Mitigação: ao desativar o plugin, restaurar acesso normal a `wp-login.php` automaticamente; documentar incompatibilidades conhecidas (WP Rocket, Cloudflare Page Rules) no README.

- **[Risco] Auto-update apontando para endpoint externo cria dependência operacional** → Mitigação: timeout curto (3s) no `wp_remote_get`, fallback silencioso (não bloqueia o admin se o endpoint estiver fora), cache de 12h. Endpoint deve servir HTTPS válido e ter SLA documentado.

- **[Risco] Conflito com plugins que também filtram `upload_mimes` ou `admin_menu`** → Mitigação: usar prioridades altas (99–999) em todos os filtros; documentar ordem de carregamento. Em caso de conflito, settings podem ser desligadas individualmente.

- **[Risco] Ocultar menus do admin pode esconder algo crítico para o cliente em fluxos específicos** → Mitigação: nenhum menu fica oculto por default; admin escolhe explicitamente quais esconder; lista pré-aprovada (não permite esconder Dashboard nem o próprio Settings).

- **[Trade-off] Uma única option autoload acelera leitura mas inflaciona o `alloptions` cache** → Aceito: array é pequeno (~10 chaves, < 1KB). Bem abaixo do limiar de problema (>1MB de autoload).

- **[Trade-off] Sem build step / sem composer mantém deploy simples mas limita uso de libs externas** → Aceito enquanto escopo for pequeno; revisar se chegar a 5+ módulos novos ou se sanitização SVG provar insuficiente.

## Migration Plan

- **Instalação inicial em site existente**: copiar plugin via FTP ou Plugin Manager, ativar. `register_activation_hook` cria role `loomi_client` e popula defaults seguros (todos os toggles `false`). Admin abre `Configurações → Loomi Studio` e ativa o que quiser.
- **Rollback**: desativar plugin restaura comportamento WP default (login slug volta para `wp-login.php`, menus voltam, SVG deixa de ser aceito). Desinstalar remove option e role.
- **Distribuição de updates**: publicar nova versão no servidor (`updates.loomi.studio`); todos os sites com o plugin checam em até 12h e mostram "atualização disponível" no admin.

## Open Questions

- A slug `/studio-access` deve ser fixa entre todos os sites Loomi (padronização) ou configurável por site? Proposal diz "redefine a slug que será igual para todos" — interpretado como **default igual em todos, mas com possibilidade de override por site**. Confirmar com o usuário.
- O servidor de updates (`updates.loomi.studio`) já existe ou precisa ser provisionado? Se ainda não existe, o módulo `class-loomi-updater.php` fica desligado por flag até que o endpoint esteja no ar.
- Lista exata de menus que aparecem como opções de "esconder" no painel — confirmar se inclui Plugins/Aparência/Usuários (que tecnicamente o `loomi_client` já não vê via capabilities) ou só os menus que mesmo admins podem querer esconder (Comments, Posts, Tools).
