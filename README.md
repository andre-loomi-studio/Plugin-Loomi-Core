# Loomi Studio Setup

> Pacote interno de ajustes WordPress da Loomi. Um único plugin que substitui os snippets soltos em `functions.php` e padroniza a configuração de todos os sites — com auto-update centralizado.

[![PHP](https://img.shields.io/badge/PHP-%E2%89%A57.4-777BB4)]()
[![WordPress](https://img.shields.io/badge/WordPress-%E2%89%A56.0-21759B)]()
[![License](https://img.shields.io/badge/License-GPL--2.0%2B-blue)]()
[![Version](https://img.shields.io/badge/Version-1.0.9-green)]()
[![Tests](https://img.shields.io/badge/Tests-121%20passing-success)]()

---

## ⚡ Quick start

```bash
# 1. Upload pelo WP admin: Plugins → Adicionar Novo → Enviar Plugin
#    OU via WP-CLI
wp plugin install loomi-studio-setup-1.0.0.zip --activate

# 2. (Se Wordfence não estiver no site) clique em "Instalar Wordfence agora"
#    no aviso vermelho do topo do admin

# 3. Configure em Configurações → Loomi Studio
```

**5 abas no painel:** Custom Login · Login Slug · Esconder Menus · Role Cliente · Anti-Spam

---

## 📦 Recursos

| Recurso | Sempre ativo | Configurável | Por site |
|---|:---:|:---:|:---:|
| Upload de SVG (com sanitização) | ✅ | — | — |
| Duplicar posts/páginas | ✅ | — | — |
| Custom Login (cor + logo) | — | ✅ | ✅ |
| Login Slug customizada | — | ✅ | ✅ |
| Esconder menus (com descoberta dinâmica de CPTs) | — | ✅ | ✅ |
| Role Cliente Loomi | — | ✅ | ✅ |
| Anti-spam baseline (honeypot + time check + lockdown + Akismet) | — | ✅ | ✅ |
| Hardening de `/wp-admin/` | — | ✅ | ✅ |
| Dependência Wordfence (auto-install) | ✅ | — | — |
| Auto-update server-driven | ✅ | — | — |
| Suite de testes (WP-PHPUnit, 121 testes) | ✅ | — | — |
| UI brand Loomi (preto #000 + amarelo #FBD603, scoped) | ✅ | — | — |
| Theme dark/light/auto (configurável) | — | ✅ | ✅ |

---

<details>
<summary><h2>🚀 Instalação</h2></summary>

> ⚠️ **Dependência:** este plugin recomenda fortemente o **Wordfence Security** (gratuito, wp.org). A ativação **não é bloqueada** se Wordfence estiver ausente, mas um aviso vermelho persiste em todo o admin até você clicar em **"Instalar Wordfence agora"** (botão dentro do próprio aviso). Admins sem `install_plugins` recebem instrução para pedir ao responsável pelo site.

### Via FTP / Plugin Manager

1. Faça upload da pasta `loomi-studio-setup/` para `/wp-content/plugins/`.
2. Acesse **Plugins** no admin e ative **Loomi Studio Setup**.
3. (Se Wordfence não estiver presente) clique em **"Instalar Wordfence agora"** no aviso vermelho do topo.
4. Configure em **Configurações → Loomi Studio**.

### Via WP-CLI

```bash
wp plugin install loomi-studio-setup-1.0.0.zip --activate
```

### Via Docker (dev)

```bash
docker compose -f docker-compose.clean.yml up -d
docker exec --user www-data loomi-clean-cli wp core install \
  --url=http://localhost:8089 \
  --title="Loomi Test" \
  --admin_user=admin --admin_password=admin123 \
  --admin_email=admin@loomi.test --skip-email
docker exec --user www-data loomi-clean-cli wp plugin activate loomi-studio-setup
```

Acesse `http://localhost:8089/wp-admin` (admin / admin123).

</details>

---

<details>
<summary><h2>⚙️ Configuração</h2></summary>

Tudo num único painel em **Configurações → Loomi Studio**, organizado em 5 abas:

```
┌─────────────────────────────────────────────────────────────────────┐
│ Loomi Studio Setup                                                  │
├─────────────────────────────────────────────────────────────────────┤
│ [Custom Login] [Login Slug] [Esconder Menus] [Role Cliente] [Anti-Spam] │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ☐ Ativar Custom Login                                              │
│  Cor de fundo:    [████ #000000  ]                                  │
│  Logo:            [Imagem] [Selecionar] [Remover]                   │
│                                                                     │
│                                              [Salvar Alterações]    │
└─────────────────────────────────────────────────────────────────────┘
```

- **Persistência**: uma única `wp_option` (`loomi_studio_setup_settings`) com autoload — zero queries extras por request.
- **Sanitização**: cada campo passa pelo `sanitize_callback` (hex color, attachment ID, whitelist de slugs, etc.).
- **Capability**: a página exige `manage_options` (só administradores).
- **Tab switching**: troca instantânea via JS (sem reload, sem perder mudanças não salvas).

</details>

---

<details>
<summary><h2>🎨 Custom Login</h2></summary>

Personaliza `wp-login.php`:

- 🎨 **Cor de fundo** (color picker WP nativo com hex input + palette)
- 🖼️ **Logo customizado** (seleção via Media Library)
- 🔗 Link do logo aponta para `home_url()` (não wordpress.org)
- 📝 Title do logo = nome do site (`get_bloginfo('name')`)

Implementação: `<style>` inline injetado em `login_enqueue_scripts` — mantém compatibilidade total com o markup nativo do WP, sem substituir templates.

</details>

---

<details>
<summary><h2>🌗 Theme (dark / light / auto)</h2></summary>

Toggle de tema do painel administrativo Loomi (sidebar + topbar + widget de boas-vindas + painel de configurações). Default `dark`.

```
┌──────────────────────────────────────────────┐
│ Tema  [ ●Dark ] [ Light ] [ Auto ]           │
└──────────────────────────────────────────────┘
```

| Valor | Comportamento |
|---|---|
| `dark` (default) | Fundo preto `#0a0a0a`, texto branco. Identidade Loomi. |
| `light` | Fundo branco `#fafafa`, texto preto `#0a0a0a`. WCAG AAA (~18:1). |
| `auto` | Segue `prefers-color-scheme` do SO (light durante o dia, dark à noite em macOS/Windows com auto-switch). |

**Como funciona:**
- Toggle vive na aba **Dashboard** (segmented control, 3 opções)
- Valor sanitizado por enum (`dark|light|auto`) — qualquer outro valor é rejeitado pelo sanitizer e cai pro default
- `admin_body_class` filter injeta `loomi-theme-<value>` no `<body>` de TODAS as páginas do admin
- CSS usa variables (`--loomi-bg`, `--loomi-text`, etc.) escopadas por `body.loomi-theme-*`
- Auto mode usa `@media (prefers-color-scheme: light)` aninhado em `body.loomi-theme-auto`
- Amarelo `#FBD603` é hardcoded (brand accent não muda com tema)

**Por quê dark default?** A Loomi opera no escuro (identidade visual da agência + studio noturno). Light é pra clientes que querem contraste high-contrast. Auto pra desenvolvedores que alternam.

</details>

---

<details>
<summary><h2>🔐 Login Slug</h2></summary>

Esconde o `/wp-login.php` por trás de uma slug customizada (default `/studio-access`).

- Visitante anônimo abre `/wp-login.php` → **404**
- Visitante anônimo abre `/studio-access/` → tela de login normal
- Fluxos `logout`, `lostpassword`, `resetpass` continuam funcionando
- Usuários autenticados acessam `/wp-login.php` normalmente
- Slugs reservadas (`wp-admin`, `login`, etc.) são rejeitadas

### Esconder `/wp-admin/` também (recomendado)

Toggle adicional **`hide_admin_endpoint`** (default ON): bloqueia `/wp-admin/` para visitantes não-autenticados.

**Por quê?** Sem isso, request anônima a `/wp-admin/` recebe 302 com `Location: /studio-access/?reauth=1` — vaza a slug secreta no header. Com toggle ON, retorna 404 sem revelar nada.

**Trade-off:** admin precisa lembrar do slug (digitar `/wp-admin/` no browser também leva a 404).

**Escape hatch:** se admin esquecer o slug e ficar trancado:

```php
// wp-config.php
define( 'LOOMI_STUDIO_DISABLE_HARDENING', true );
```

Desliga o gate de `/wp-admin/` (volta a redirecionar normalmente) sem precisar do painel.

> ⚠️ **Limitação consciente**: POSTs para `/wp-login.php` continuam aceitos (o form HTML aponta pra lá). A slug protege contra bot-scans GET, **não** contra credential-stuffing direcionado — combine com rate-limit/2FA se for sua preocupação.

</details>

---

<details>
<summary><h2>🙈 Esconder Menus</h2></summary>

Multi-select com **5 menus core** que `editor`/`loomi_client` realmente vê — admins não estão na lista (o WP já esconde Plugins/Aparência/Usuários/Configurações automaticamente para usuários sem a capability correspondente):

- Posts (`edit.php`)
- Páginas (`edit.php?post_type=page`)
- Comentários (`edit-comments.php`)
- Mídia (`upload.php`)
- Ferramentas (`tools.php`)

### Descoberta dinâmica de CPTs

A lista também inclui **Custom Post Types públicos** registrados no site (Elementor → Templates, WooCommerce → Products/Orders, ACF → Field Groups, etc.) descobertos via `get_post_types()` no momento de render do painel.

Visual no painel:
```
WordPress
☑ Posts             (edit.php)
☑ Páginas           (edit.php?post_type=page)
☑ Comentários       (edit-comments.php)
☑ Mídia             (upload.php)
☑ Ferramentas       (tools.php)

Custom Post Types
☐ Produtos                 (edit.php?post_type=product)
☐ Pedidos                  (edit.php?post_type=shop_order)
☐ Templates Elementor      (edit.php?post_type=elementor_library)

(ou: "Nenhum Custom Post Type encontrado neste site.")
```

Defesa em profundidade: blacklist permanente (`index.php`, `options-general.php`) que **nunca** pode ser escondida, mesmo se forçada via DB.

> ⚠️ **Known limitation:** CPTs registrados como **submenu** de outro menu (`show_in_menu => 'string'`) não são removíveis em v1 — `remove_menu_page` só funciona para top-level. Out of scope.

</details>

---

<details>
<summary><h2>👤 Role Cliente Loomi</h2></summary>

Cria a role `loomi_client` (display name "Cliente Loomi") na ativação do plugin, com capabilities derivadas de `editor` **menos** privilégios sensíveis:

```
NÃO TEM: manage_options, edit_users, list_users, create_users,
         install_plugins, activate_plugins, edit_plugins,
         switch_themes, install_themes, edit_themes,
         edit_files, unfiltered_html, update_core, export, import
```

- ✅ Role criada em `register_activation_hook`
- ✅ Pode ser **escondida do dropdown** sem ser deletada (toggle off)
- ✅ Removida apenas em `uninstall` — usuários reatribuídos para `subscriber` (não perdem acesso)
- ✅ Acessos diretos a `/wp-admin/plugins.php` ou `/wp-admin/users.php` retornam "permissão negada"

### Comparação com editor padrão

| Capability | Editor | loomi_client |
|---|:---:|:---:|
| Editar posts/páginas próprios e de outros | ✅ | ✅ |
| Moderar comentários | ✅ | ✅ |
| Gerenciar categorias/tags | ✅ | ✅ |
| Upload de mídia | ✅ | ✅ |
| `unfiltered_html` (colar `<script>` em posts) | ✅ | ❌ |
| Acesso a Plugins / Temas / Usuários / Configurações | ❌ | ❌ |

</details>

---

<details>
<summary><h2>📋 Duplicar Posts e Páginas (sempre ativo)</h2></summary>

Adiciona link **Duplicar** nas row actions de Posts e Páginas:

- ✅ Cria draft com título sufixado `(cópia)`
- ✅ Copia content, excerpt, autor, parent, menu_order, comment_status
- ✅ Copia **todos** os meta (incluindo ACF e featured image)
- ✅ Copia **todas** as taxonomias (categories, tags, custom taxonomies)
- ✅ Nonce + `current_user_can('edit_post')` em cada request
- ✅ Cópia de meta via `$wpdb` direto (sem `maybe_unserialize` — fecha vetor de POP-chain injection)

</details>

---

<details>
<summary><h2>🖼️ Upload de SVG (sempre ativo)</h2></summary>

Habilita `image/svg+xml` no `upload_mimes` **com sanitização real** — não basta liberar o MIME, SVG é vetor XSS conhecido.

**O sanitizador (DOMDocument, sem dependência externa):**

- ✅ Whitelist de **26 tags** seguras (`svg, g, path, rect, circle, ellipse, text, defs, use, linearGradient, ...`)
- ✅ Whitelist de **~50 atributos** (`d, fill, stroke, transform, viewBox, x, y, ...`)
- ✅ Remove todos os atributos `on*` (`onclick`, `onload`, `onmouseover`, ...)
- ✅ Bloqueia `href`/`xlink:href` com `javascript:` ou `data:image/svg+xml`
- ✅ Remove `<script>`, `<style>`, `<foreignObject>` inteiros
- ✅ Remove DOCTYPE via DOM (não regex frágil) — fecha **XXE** e **billion-laughs**
- ✅ `LIBXML_NONET` ativo, `LIBXML_NOENT` **desativado** explicitamente
- ✅ Rejeita XML malformado com erro em `wp_handle_upload_prefilter`

Também corrige o **preview na Media Library** via `wp_prepare_attachment_for_js` — SVGs aparecem como thumb no grid view ao invés do ícone genérico.

</details>

---

<details>
<summary><h2>🛡️ Anti-Spam (4 técnicas, zero CAPTCHA)</h2></summary>

Proteção zero-config contra bots. **Não exige reCAPTCHA, Akismet ou credencial externa.** Combinado com Wordfence cobre >95% do spam genérico em forms nativos do WordPress.

| Técnica | Como funciona | Cobertura |
|---|---|---|
| **Honeypot** | Campo `loomi_hp` invisível em login, registro e comentário. Bot preenche tudo → rejeitamos. | ~80% bots dumb |
| **Time check** | Campo `loomi_t` com timestamp. Submit em <2s = bot. | +10% bots rápidos |
| **Comment lockdown** | Desliga pingback/trackback (vetor #1) + força hold-for-moderation + remove `pingback.ping` do XML-RPC. | ~99% spam em comments |
| **Akismet auto-config** | Se `LOOMI_AKISMET_KEY` definida em wp-config + Akismet ativo, propaga a key automaticamente. | Comments restantes |

### Quanto tempo bloqueia?

**Nenhum block temporal** — proteção é **por request**. Bot pode tentar de novo imediatamente. Quem faz IP block temporal é o **Wordfence** (rate-limit + lockout de 4h+ depois de N tentativas falhas). As camadas são complementares: nosso plugin filtra por **conteúdo do form**, Wordfence filtra por **rate/IP**.

### Akismet centralizado (Loomi central)

Pra ativar a propagação automática em todos os sites Loomi, adicionar em cada `wp-config.php`:

```php
define( 'LOOMI_AKISMET_KEY', 'sua-api-key-aqui' );
```

1 conta Akismet (~$9-15/mês) cobre todos os sites — config zero por site.

### Fora do escopo

Contact forms de plugins terceiros (Contact Form 7, WPForms, Gravity Forms, Elementor Forms). Cada plugin tem sua própria proteção integrada — configure separadamente.

</details>

---

<details>
<summary><h2>🔄 Auto-update</h2></summary>

Distribui novas versões do plugin para **todos os sites Loomi** sem editar cada um manualmente.

```
┌──────────────────────────┐
│ updates.loomi.studio     │
│ ├─ loomi-studio-setup.json  ◄── metadata
│ └─ loomi-studio-setup-1.2.0.zip ◄── package
└──────────────────────────┘
            ▲
            │ wp_remote_get (3s timeout, 12h cache)
            │
┌───────────┴──────────────┐
│ Site 1  │  Site 2  │ ... │
│ (plugin com checker)     │
└──────────────────────────┘
```

**Schema do JSON esperado:**

```json
{
  "version": "1.2.0",
  "download_url": "https://updates.loomi.studio/loomi-studio-setup-1.2.0.zip",
  "requires": "6.0",
  "tested": "6.7",
  "requires_php": "7.4",
  "sections": {
    "description": "...",
    "changelog": "..."
  }
}
```

**Hardening:**
- ✅ `download_url` validado contra **mesmo host** do `LOOMI_STUDIO_UPDATE_SERVER` (defesa anti-hijack)
- ✅ Ambos os hosts forçados a **HTTPS**
- ✅ Timeout de 3s, fallback silencioso em erro de rede
- ✅ Cache de 12h em transient (sem hits desnecessários)
- ✅ Validação completa do schema (`version`, `download_url`, `sections`) antes de injetar
- ✅ Override via `define('LOOMI_STUDIO_UPDATE_SERVER', '...')` em `wp-config.php`

</details>

---

<details>
<summary><h2>🔒 Security model — defesa em camadas</h2></summary>

O Loomi Studio Setup aplica **5 camadas independentes** de proteção:

| Camada | Função | Ataque que bloqueia |
|---|---|---|
| Custom slug (`login-slug`) | Endpoint de login não óbvio (`/studio-access/`) | Scanner que tenta `/wp-login.php` direto |
| Admin endpoint hardening (`hide_admin_endpoint`) | `/wp-admin/` → 404 quando anônimo | Scanner que inferiria a slug via redirect `Location` |
| Anti-spam baseline | Honeypot + time check em forms nativos + comment lockdown | Bots dumb em login, registro e comentários (sem CAPTCHA) |
| Wordfence (peer plugin) | Rate-limit + IP block + WAF | Brute-force depois que slug é descoberta |
| Role `loomi_client` | Capabilities reduzidas (sem `manage_options`, `install_plugins`, etc.) | Privilege escalation pós-login |

### Vetores conhecidos e mitigações

O plugin passou por **review de segurança independente** antes do release:

| Vetor | Mitigação |
|---|---|
| XSS via SVG `<script>` / `on*` | DOMDocument allowlist + remoção de attrs `on*` |
| XSS via `<style>` em SVG | `<style>` removido do allowlist |
| XXE / billion-laughs | DOCTYPE removido via DOM, `LIBXML_NOENT` off, `LIBXML_NONET` on |
| Nested SVG via `<use href="data:image/svg+xml">` | `data:image/svg*` bloqueado em href |
| Login slug bypass via path encoding (`%73tudio-access`) | `rawurldecode()` antes da comparação |
| POP-chain via `maybe_unserialize` no duplicate | Cópia de meta via `$wpdb` raw (sem unserialize) |
| Update package URL hijack | `is_trusted_package_url()` — mesmo host + HTTPS |
| CSRF em duplicate | `wp_nonce` + `check_admin_referer` por post ID |
| Privilege escalation via role | Capabilities derivadas de editor menos lista explícita de proibidas |
| Hardening de borda | Wordfence (peer plugin recomendado, notice persistente + auto-install) |
| Slug leak via header `Location` em `/wp-admin/` | Toggle `hide_admin_endpoint` (default ON) → 404 em vez de 302 |
| Bots dumb em forms nativos (login, comment, registro) | Honeypot + time check (zero CAPTCHA, zero credencial) |
| Spam em comentários e pingback/trackback | Comment lockdown + Akismet autoconfig opcional via `LOOMI_AKISMET_KEY` |

Reporte vulnerabilidades para `dev@loomi.studio` (sem disclosure público antes da correção).

</details>

---

<details>
<summary><h2>🏗️ Arquitetura</h2></summary>

```
loomi-studio-setup/
├── loomi-studio-setup.php           # Bootstrap (lista de módulos + activation/deactivation hooks)
├── uninstall.php                     # Cleanup completo (role, option, transient)
├── README.md
├── includes/
│   ├── class-plugin.php              # Constantes globais (SLUG, OPTION_KEY, WORDFENCE_FILE, etc)
│   ├── class-loomi-settings.php      # @deprecated 1.1.0 — alias para Settings_Repository/Sanitizer
│   ├── class-loomi-updater.php       # Update server checker
│   ├── contracts/
│   │   ├── interface-module.php       # interface Loomi_Module com register(): void
│   │   └── interface-settings-tab.php # interface Loomi_Settings_Tab com slug/label/render
│   ├── support/
│   │   ├── class-settings-repository.php  # defaults, cache, get/get_bool, constantes
│   │   ├── class-settings-sanitizer.php   # sanitize_callback da Settings API
│   │   └── class-login-urls.php           # Login_URLs::build() — gera URLs slug-based
│   ├── settings/
│   │   ├── class-settings-page.php    # Loomi_Settings_Page (admin_menu + render)
│   │   └── tabs/
│   │       ├── class-tab-login.php
│   │       ├── class-tab-slug.php
│   │       ├── class-tab-hide-menus.php
│   │       ├── class-tab-client-role.php
│   │       └── class-tab-anti-spam.php
│   └── modules/
│       ├── class-loomi-svg.php           # implements Loomi_Module
│       ├── class-loomi-login.php
│       ├── class-loomi-admin-menu.php
│       ├── class-loomi-role.php
│       ├── class-loomi-duplicate.php
│       ├── class-loomi-anti-spam.php
│       └── class-loomi-wordfence-check.php
└── tests/
    ├── integration/                  # 121 testes WP-PHPUnit
    ├── helpers/BaseTestCase.php
    └── bootstrap.php
```

**Princípios** (refactor SOLID + DRY aplicado em 1.0.0):
- **SRP**: cada classe < 150 linhas, uma responsabilidade clara
- **OCP**: novos módulos implementam `Loomi_Module::register()` e são adicionados à lista no bootstrap, sem editar outros arquivos
- **DRY**: `Login_URLs::build()` substitui 5 métodos quase idênticos; `Settings_Repository::hideable_menus()` substitui hardcode
- **Hooks lazy**: módulos checam toggle no próprio `register()` antes de adicionar hooks
- **Cache estático único**: `Settings_Repository::$cache` é o único ponto de cache
- **Sem namespaces / sem composer no runtime**: WP plugin tradicional — `require_once` manual em ordem de dependência

</details>

---

<details>
<summary><h2>🧪 Testes (WP-PHPUnit)</h2></summary>

Suite completa de **121 testes, 256 assertions, ~1.4 segundos** cobrindo todos os módulos.

### Setup (uma vez)

```bash
# 1. Sobe stack
docker compose -f docker-compose.clean.yml up -d

# 2. Instala deps + WP test framework + DB de teste
docker exec --user root loomi-clean-wp bash -c "
    apt-get update -qq && apt-get install -y subversion default-mysql-client &&
    cd /var/www/html/wp-content/plugins/loomi-studio-setup &&
    composer install --no-interaction &&
    bash tests/install-wp-tests.sh wordpress_test wordpress wordpress db:3306 6.7.2
"

# 3. Grant DB de teste pro user wordpress
docker exec loomi-clean-wp mysql -uroot -prootpass -h db -e \
    "GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wordpress'@'%'; FLUSH PRIVILEGES;"
```

### Rodar a suite

```bash
# Todos os 121 testes
bash tests/run.sh

# Uma classe específica
bash tests/run.sh --filter SvgSanitizerTest

# Um método específico
bash tests/run.sh --filter test_xxe_neutered
```

### Cobertura

| Test class | Testes | Cobre |
|---|---|---|
| `SvgSanitizerTest` | 12 | script tag, onload, JS href, style payload, XXE, billion-laughs, foreignObject, data:svg, malformed |
| `DuplicatorTest` | 8 | featured image, ACF-like meta, edit_lock skip, source unchanged, capability gating |
| `LoginSlugRoutingTest` | 7 | gate behavior, filter_*_url filters |
| `LoginUrlsTest` | 7 | `Login_URLs::build()` em todas combinações |
| `RoleTest` | 7 | caps proibidas, editable_roles filter, uninstall reassignment |
| `HideMenusTest` | 10 | editor x admin x blacklist, CPT discovery, disclaimer |
| `WordfenceCheckTest` | 4 | get_state nos 3 cenários, notice gating |
| `SettingsRepositoryTest` | 13 | defaults, cache, get_bool coercion (incluindo regression bug), hideable_menus dinâmico |
| `SettingsSanitizerTest` | 10 | color, slug, hidden_menus, booleans, CPT slugs |
| `UpdaterTest` | 6 | mock endpoint, untrusted URL, malformed JSON, offline fallback |
| `AdminEndpointSecurityTest` | 8 | hardening /wp-admin/, escape hatch constant, REST/AJAX bypass |
| `AntiSpamTest` | 16 | honeypot, time check, comment lockdown, akismet config |
| `ThemeToggleTest` | 7 | default dark, enum sanitizer (dark/light/auto), body class filter, fallback inválido, 3 radios renderizados |

#### Visual regression (Playwright)

| Test file | Cobre |
|---|---|
| `tests/visual/sidebar-overflow.mjs` | Sidebar não vaza pra direita em 1440×900 expanded + collapsed (`body.folded`) + mobile 375×812 overlay. Asserções: `#adminmenuback.width === #adminmenuwrap.width === nominal`; 0 pixels `#FBD603` ou `var(--loomi-g-bg)` em `x ∈ [161, 200]` (tolerância 5px anti-aliasing). |

Rodar:

```bash
# 1. Sobe o ambiente dev (porta 8088, plugin como volume + WP instalado)
docker compose up -d

# 2. Instala deps locais (uma vez)
cd loomi-studio-setup
npm install
npm run test:visual:install   # baixa chromium

# 3. Roda o teste
npm run test:visual
# ou diretamente:
LOOMI_TEST_URL=http://localhost:8088 node tests/visual/sidebar-overflow.mjs
```

Env vars: `LOOMI_TEST_URL` (default `http://localhost:8088`), `LOOMI_TEST_USER` (default `admin`), `LOOMI_TEST_PASS` (default `admin123`).

Exit code 0 em sucesso, 1 em falha. Output: `✓ sidebar boundary clean (viewport=NxM, mode=…)` por cenário.

Stack: PHPUnit 9.6 + WP-PHPUnit 6.7 + Yoast Polyfills 2.0 + PHP 8.2 + WP 6.7.2 + MySQL 8. Visual: Playwright 1.48 + pngjs 7 + Chromium.

Docs: [WP-PHPUnit oficial](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/)

</details>

---

<details>
<summary><h2>👨‍💻 Desenvolvimento</h2></summary>

### Stack de teste local

```bash
# Sobe WP 6.7 + PHP 8.2 + MySQL 8 com o plugin montado
docker compose -f docker-compose.clean.yml up -d

# Acesse http://localhost:8089/wp-admin
```

### Inspecionar estado

```bash
# Plugins
docker exec --user www-data loomi-clean-wp wp plugin list

# Settings atual
docker exec --user www-data loomi-clean-wp wp option get loomi_studio_setup_settings --format=json

# Capabilities da role
docker exec --user www-data loomi-clean-wp wp role list
```

### Lint PHP

```bash
find loomi-studio-setup -name "*.php" -not -path "*/vendor/*" -exec php -l {} \;
```

### Constantes mágicas (wp-config.php)

| Constante | O que faz |
|---|---|
| `LOOMI_STUDIO_UPDATE_SERVER` | Override do endpoint de auto-update (default `https://updates.loomi.studio/...`) |
| `LOOMI_STUDIO_DISABLE_HARDENING` | Desativa o gate de `/wp-admin/` (escape hatch se admin se trancar) |
| `LOOMI_AKISMET_KEY` | API key central do Akismet — plugin propaga automaticamente |

### Workflow de release

1. Bump `Version` em `loomi-studio-setup.php` e na constante `LOOMI_STUDIO_VERSION`.
2. Gerar ZIP via Python script (exclui `tests/`, `vendor/`, `composer.*`, `phpunit.xml.dist`).
3. Upload do ZIP para `updates.loomi.studio`.
4. Atualizar `loomi-studio-setup.json` com nova `version` + `download_url` + `changelog`.
5. Os sites checam a cada 12h (ou imediatamente se forçar via `delete_transient('loomi_update_check')`).

</details>

---

<details>
<summary><h2>🗺️ Roadmap</h2></summary>

- [x] **Sidebar visual integrity + tema dark consistency** (1.0.2) — clipa bleed lateral do `#adminmenuback`/`#adminmenuwrap`; arruma tab strip, cards do dashboard, inputs, checkboxes, botões secundários e ícone de dependências que renderizavam em fundo branco hardcoded no tema dark.
- [x] **Refactor SOLID + DRY** (1.0.0) — Settings dividido em Repository/Sanitizer/Page, Login_URLs helper, interfaces Module/Tab, Plugin constantes.
- [x] **Suite WP-PHPUnit** (1.0.0) — 121 testes / 256 assertions cobrindo todos os módulos.
- [x] **Descoberta dinâmica de CPTs** (1.0.0) — menus de CPTs aparecem no painel automaticamente.
- [x] **Hardening de `/wp-admin/`** (1.0.0) — bloqueia leak da slug via header Location.
- [x] **Anti-spam baseline** (1.0.0) — honeypot + time check + comment lockdown + Akismet autoconfig.
- [ ] **Remoção da classe `Loomi_Settings` deprecated** (planejado 1.2.0) — migrar callers para `Settings_Repository::*` diretamente.
- [ ] CI pipeline (GitHub Actions) — change futura `tests-ci`.
- [ ] Suporte a CPTs como submenu (`show_in_menu => string`) no hide-menus.
- [ ] Suporte a duplicar Custom Post Types (atualmente só `post` e `page`).
- [ ] Painel de "status" mostrando última checagem de update, transient cache, etc.
- [ ] Stats anti-spam ("X bots bloqueados nos últimos 30 dias").
- [ ] Cloudflare Turnstile como toggle opcional (alternativa quando honeypot não é suficiente).
- [ ] Logs de auditoria opcionais (quem ligou/desligou cada feature).
- [ ] i18n: arquivo `.pot` populado e traduções pt_BR.

</details>

---

**Loomi** · [loomi.studio](https://loomi.studio) · `dev@loomi.studio`
