# Loomi Studio Setup

> Pacote interno de ajustes WordPress da Loomi. Um único plugin que substitui os snippets soltos em `functions.php` e padroniza a configuração de todos os sites — com auto-update centralizado.

[![PHP](https://img.shields.io/badge/PHP-%E2%89%A57.4-777BB4)]()
[![WordPress](https://img.shields.io/badge/WordPress-%E2%89%A56.0-21759B)]()
[![License](https://img.shields.io/badge/License-GPL--2.0%2B-blue)]()
[![Version](https://img.shields.io/badge/Version-1.0.0-green)]()

---

## Sumário

- [O que faz](#o-que-faz)
- [Instalação](#instalação)
- [Configuração](#configuração)
- [Recursos](#recursos)
  - [Upload de SVG (sempre ativo)](#upload-de-svg-sempre-ativo)
  - [Custom Login](#custom-login)
  - [Login Slug](#login-slug)
  - [Esconder Menus](#esconder-menus)
  - [Role Cliente Loomi](#role-cliente-loomi)
  - [Duplicar Posts e Páginas (sempre ativo)](#duplicar-posts-e-páginas-sempre-ativo)
  - [Auto-update](#auto-update)
- [Arquitetura](#arquitetura)
- [Segurança](#segurança)
- [Desenvolvimento](#desenvolvimento)
- [Roadmap](#roadmap)

---

## O que faz

Cada site WordPress da Loomi recebia, manualmente, o mesmo pacote de tweaks em `functions.php` — habilitar SVG, customizar o login, esconder menus do admin, criar uma role limitada para o cliente, duplicar páginas. Esse plugin transforma esse trabalho em **um produto interno versionado**, com painel de configuração único e mecanismo de update centralizado.

| Recurso | Sempre ativo | Configurável | Por site |
|---|:---:|:---:|:---:|
| Upload de SVG (com sanitização) | ✅ | — | — |
| Duplicar posts/páginas | ✅ | — | — |
| Custom Login (cor + logo) | — | ✅ | ✅ |
| Login Slug customizada | — | ✅ | ✅ |
| Esconder menus do WP | — | ✅ | ✅ |
| Role Cliente Loomi | — | ✅ | ✅ |
| Dependência Wordfence (auto-install) | ✅ | — | — |
| Auto-update server-driven | ✅ | — | — |

---

## Instalação

> ⚠️ **Dependência:** este plugin recomenda fortemente o **Wordfence Security** (gratuito, wp.org). A ativação **não é bloqueada** se Wordfence estiver ausente, mas um aviso vermelho persiste em todo o admin até você clicar em **"Instalar Wordfence agora"** (botão dentro do próprio aviso). Admins sem `install_plugins` recebem instrução para pedir ao responsável pelo site.

### Via FTP / Plugin Manager

1. Faça upload da pasta `loomi-studio-setup/` para `/wp-content/plugins/`.
2. Acesse **Plugins** no admin e ative **Loomi Studio Setup**.
3. (Se Wordfence não estiver presente) clique em **"Instalar Wordfence agora"** no aviso vermelho do topo.
4. Configure em **Configurações → Loomi Studio**.

### Via WP-CLI

```bash
# Copie a pasta para wp-content/plugins/ e ative
wp plugin activate loomi-studio-setup
```

### Via Docker (dev)

Há um `docker-compose.yml` na raiz do repo que sobe um stack WP 6.7 + PHP 8.2 + MySQL 8 com o plugin volume-mountado:

```bash
docker compose up -d
docker compose exec wpcli wp core install \
  --url=http://localhost:8088 \
  --title="Loomi Test" \
  --admin_user=admin \
  --admin_password=admin123 \
  --admin_email=admin@loomi.test \
  --skip-email \
  --allow-root
docker compose exec wpcli wp plugin activate loomi-studio-setup --allow-root
```

Acesse `http://localhost:8088/wp-admin` (admin / admin123).

---

## Configuração

Tudo num único painel em **Configurações → Loomi Studio**, organizado em 4 abas:

```
┌─────────────────────────────────────────────────────────────┐
│ Loomi Studio Setup                                          │
├─────────────────────────────────────────────────────────────┤
│ [Custom Login] [Login Slug] [Esconder Menus] [Role Cliente] │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ☐ Ativar Custom Login                                      │
│  Cor de fundo:    [████ #000000  ]                          │
│  Logo:            [Imagem] [Selecionar] [Remover]           │
│                                                             │
│                                              [Salvar]       │
└─────────────────────────────────────────────────────────────┘
```

- **Persistência**: uma única `wp_option` (`loomi_studio_setup_settings`) com autoload — zero queries extras por request.
- **Sanitização**: cada campo passa pelo `sanitize_callback` (hex color, attachment ID, whitelist de slugs, etc.).
- **Capability**: a página exige `manage_options` (só administradores).
- **Tab switching**: troca instantânea via JS (sem reload, sem perder mudanças não salvas).

---

## Recursos

### Upload de SVG (sempre ativo)

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

**Suite de testes** (`test-svg-sanitizer.php`): 11 payloads conhecidos — 11 PASS.

```bash
docker compose exec wordpress bash -c 'cd wp-content/plugins/loomi-studio-setup && php test-svg-sanitizer.php'
```

Também corrige o **preview na Media Library** via `wp_prepare_attachment_for_js` — SVGs aparecem como thumb no grid view ao invés do ícone genérico.

### Custom Login

Personaliza `wp-login.php`:

- 🎨 **Cor de fundo** (color picker WP nativo com hex input + palette)
- 🖼️ **Logo customizado** (seleção via Media Library)
- 🔗 Link do logo aponta para `home_url()` (não wordpress.org)
- 📝 Title do logo = nome do site (`get_bloginfo('name')`)

Implementação: `<style>` inline injetado em `login_enqueue_scripts` — mantém compatibilidade total com o markup nativo do WP, sem substituir templates.

### Login Slug

Esconde o `/wp-login.php` por trás de uma slug customizada (default `/studio-access`).

- Visitante anônimo abre `/wp-login.php` → **404**
- Visitante anônimo abre `/studio-access/` → tela de login normal
- Fluxos `logout`, `lostpassword`, `resetpass` continuam funcionando
- Usuários autenticados acessam `/wp-login.php` normalmente
- Slugs reservadas (`wp-admin`, `login`, etc.) são rejeitadas

> ⚠️ **Limitação consciente**: POSTs para `/wp-login.php` continuam aceitos (o form HTML aponta pra lá). A slug protege contra bot-scans GET, **não** contra credential-stuffing direcionado — combine com rate-limit/2FA se for sua preocupação.

### Esconder Menus

Multi-select com 7 menus padrão que podem ser ocultados para usuários **sem `manage_options`** (clientes, editores):

- Posts (`edit.php`)
- Comentários (`edit-comments.php`)
- Ferramentas (`tools.php`)
- Aparência (`themes.php`)
- Plugins (`plugins.php`)
- Usuários (`users.php`)
- Mídia (`upload.php`)

Os 7 menus já vêm **pré-marcados** nos defaults — basta ativar o toggle para esconder todos. Defesa em profundidade: blacklist permanente (`index.php`, `options-general.php`) que **nunca** pode ser escondida, mesmo se forçada via DB.

### Role Cliente Loomi

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

### Duplicar Posts e Páginas (sempre ativo)

Adiciona link **Duplicar** nas row actions de Posts e Páginas:

- ✅ Cria draft com título sufixado `(cópia)`
- ✅ Copia content, excerpt, autor, parent, menu_order, comment_status
- ✅ Copia **todos** os meta (incluindo ACF e featured image)
- ✅ Copia **todas** as taxonomias (categories, tags, custom taxonomies)
- ✅ Nonce + `current_user_can('edit_post')` em cada request
- ✅ Cópia de meta via `$wpdb` direto (sem `maybe_unserialize` — fecha vetor de POP-chain injection)

### Auto-update

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

---

## Arquitetura

```
loomi-studio-setup/
├── loomi-studio-setup.php         # Bootstrap + constantes + activation/deactivation
├── uninstall.php                   # Cleanup completo (role, option, transient)
├── README.md
├── test-svg-sanitizer.php         # Suite de testes do sanitizador
├── includes/
│   ├── class-loomi-settings.php   # Settings API + tabs + sanitize
│   ├── class-loomi-updater.php    # Update server checker
│   └── modules/
│       ├── class-loomi-svg.php          # Upload + sanitização + preview
│       ├── class-loomi-login.php        # Custom CSS + slug rewrite + gate
│       ├── class-loomi-admin-menu.php   # Hide menus para não-admins
│       ├── class-loomi-role.php         # loomi_client create/remove
│       └── class-loomi-duplicate.php    # Duplicar post/page
├── assets/
│   └── admin.css                   # Estilos do painel (apenas)
└── languages/
    └── loomi-studio-setup.pot      # Translations
```

**Princípios:**

- 🪶 **Sem build step** — copia e instala. Sem composer, sem webpack, sem React.
- ⚡ **Performance** — zero asset no front-end público; admin assets carregam **só** na tela do plugin.
- 🔌 **Hooks lazy** — cada módulo só registra hooks se seu toggle estiver on (exceto SVG e Duplicate, sempre on).
- 💾 **Uma option, um query** — `loomi_studio_setup_settings` com autoload; resto vem do cache.
- 🧱 **Sem dependência externa** — `DOMDocument` (nativo do PHP) é suficiente para o sanitizador.

---

## Segurança

O plugin passou por **review de segurança independente** antes do release. Vetores conhecidos endereçados:

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

Reporte vulnerabilidades para `dev@loomi.studio` (sem disclosure público antes da correção).

---

## Desenvolvimento

### Stack de teste local

```bash
# Sobe WP 6.7 + PHP 8.2 + MySQL 8 com o plugin montado
docker compose up -d

# Instala WP e ativa o plugin
docker compose exec wpcli wp core install \
  --url=http://localhost:8088 --title="Test" \
  --admin_user=admin --admin_password=admin123 \
  --admin_email=a@a.com --skip-email --allow-root
docker compose exec wpcli wp plugin activate loomi-studio-setup --allow-root

# Acesse http://localhost:8088/wp-admin
```

### Testes automatizados

```bash
# SVG sanitizer (11 payloads)
docker compose exec wordpress bash -c 'cd wp-content/plugins/loomi-studio-setup && php test-svg-sanitizer.php'

# Inspecionar settings
docker compose exec wpcli wp option get loomi_studio_setup_settings --format=json --allow-root

# Inspecionar capabilities da role
docker compose exec wpcli wp role list --allow-root
```

### Lint PHP

```bash
find loomi-studio-setup -name "*.php" -exec php -l {} \;
```

### Workflow de release

1. Bump `Version` em `loomi-studio-setup.php` e na constante `LOOMI_STUDIO_VERSION`.
2. Gerar ZIP: `git archive --format=zip -o loomi-studio-setup-X.Y.Z.zip HEAD loomi-studio-setup/`
3. Upload do ZIP para `updates.loomi.studio`.
4. Atualizar `loomi-studio-setup.json` com nova `version` + `download_url` + `changelog`.
5. Os sites checam a cada 12h (ou imediatamente se forçar via `delete_transient('loomi_update_check')`).

---

## Roadmap

- [ ] Whitelist de menus configurável via filtro PHP (`apply_filters('loomi_hideable_menus', ...)`)
- [ ] Suporte a duplicar Custom Post Types (atualmente só `post` e `page`)
- [ ] Painel de "status" mostrando última checagem de update, transient cache, etc.
- [ ] Logs de auditoria opcionais (quem ligou/desligou cada feature)
- [ ] i18n: arquivo `.pot` populado e traduções pt_BR

---

**Loomi** · [loomi.studio](https://loomi.studio) · `dev@loomi.studio`
