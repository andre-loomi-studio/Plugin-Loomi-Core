## Why

Hoje o brand Loomi aplicado ao admin (sidebar, topbar, painel, dashboard widget) é **só dark** — preto absoluto + acento amarelo. Funciona pra clientes Loomi com gosto por dark UI, mas:

1. **Acessibilidade visual**: alguns usuários têm fatiga visual em dark mode prolongado; outros têm baixa visão e light mode oferece mais contraste percebido em ambientes claros.
2. **Preferência do usuário**: WP 5.7+ tem color schemes nativos (Light, Modern, Coffee, etc.) — usuários esperam ter escolha visual.
3. **Ambiente**: light mode é mais comum em escritórios bem iluminados; dark mode em editing sessions noturnas. Auto-switch via `prefers-color-scheme` permite ambos sem intervenção.

Solução: **toggle dark/light/auto** no painel de Settings, persistido por site, aplicado consistentemente no painel + dashboard widget + brand global. Acento amarelo Loomi (`#FBD603`) mantém em ambos os modos (identidade preservada).

## What Changes

- Novo toggle `loomi_theme` (default `dark`) com 3 opções: `dark`, `light`, `auto`.
- **`auto`**: aplica via `@media (prefers-color-scheme: light)` — segue setting do OS/browser.
- Painel admin do plugin (`Configurações → Loomi Studio`): cores mudam por theme. Acento amarelo + preto (logos, CTA) permanecem.
- **Welcome widget dashboard** (`/wp-admin/index.php`): bg preto/branco conforme theme. Texto/stats invertem.
- **Sidebar + topbar globais**: hoje todo preto. Em light mode, vira branco/cinza claro com texto escuro e mesmo acento amarelo nos active items.
- Nova tab "Aparência" no painel OU campo na tab "Dashboard" pra escolher theme.
- Todas as cores usam **CSS variables** (`--loomi-bg`, `--loomi-fg`, etc.) com dois sets — light e dark — em vez de hex hardcoded.
- Suite WP-PHPUnit: testes pro toggle (default value, persistência, CSS class aplicada no `<body>`).

## Capabilities

### New Capabilities
- `theme-mode-toggle`: 3-state toggle (dark/light/auto) persistido por site, aplicado consistentemente em painel + dashboard + global brand; respeita `prefers-color-scheme` em modo auto.

### Modified Capabilities
<!-- A capability `brand-ui` (arquivada em loomi-brand-ui) tem suas cores parametrizadas via CSS variables agora — comportamento default (dark) idêntico, mas extensível. -->

## Impact

- **Arquivos novos**:
  - Tests novos em `ThemeToggleTest.php` (~5 testes).
- **Arquivos alterados**:
  - `includes/support/class-settings-repository.php` — adicionar default `loomi_theme => 'dark'` (em BOOL_FIELDS não, é enum).
  - `includes/support/class-settings-sanitizer.php` — validar enum `['dark', 'light', 'auto']`.
  - `includes/settings/tabs/class-tab-dashboard.php` (ou novo `class-tab-appearance.php`) — adicionar select/radio pro tema.
  - `assets/admin.css` — variables CSS organizadas em `:root[data-loomi-theme="dark"]` e `[data-loomi-theme="light"]` + `@media (prefers-color-scheme: light)` pra auto.
  - `assets/admin-global.css` — idem; aplica light/dark conforme `<body>` ou `<html>` data attribute.
  - `includes/settings/class-settings-page.php` — emite `data-loomi-theme` no `<body>` admin (via `admin_body_class` filter).
  - `README.md` — documentar a feature.
- **Sem mudança de DB**: nova chave dentro da option existente.
- **Performance**: zero impacto — CSS variables são free; `@media (prefers-color-scheme)` é nativo.
- **Compatibilidade**: WP ≥ 6.0; light mode requer browser com `prefers-color-scheme` (Chrome 76+, Firefox 67+, Safari 12.1+ — todos navegadores modernos OK).
- **Acessibilidade**: WCAG AA mínimo em ambos os modos. Acento amarelo (#FBD603) contra fundo preto (AAA) E contra fundo branco (AA — ainda passa, mas mais sutil; pra texto pequeno usaremos preto sobre amarelo).
- **Risco**: theme light precisa de variantes em ~30 valores de cor que hoje estão hardcoded. Trabalho não-trivial mas mecânico.
