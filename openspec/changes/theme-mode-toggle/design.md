## Context

O plugin hoje aplica brand Loomi (preto + amarelo `#FBD603`) em:
- Painel de Settings (`assets/admin.css`)
- Sidebar + topbar globais (`assets/admin-global.css`)
- Welcome widget no Dashboard

Todas as cores escuras (bg preto, text branco, gray-* tons frios) estão hardcoded ou em variables CSS num único bloco. Light mode = inverter bg/text + ajustar grays + manter acent amarelo.

Constraints:
- Não usar JS pra trocar tema (CSS-only + class no body via PHP).
- Acento amarelo preservado em ambos os modos — identidade visual da Loomi não muda.
- Contraste WCAG AA mínimo em todas as combinações.

## Goals / Non-Goals

**Goals:**
- 3 modos: `dark` (default), `light`, `auto` (segue OS via `prefers-color-scheme`).
- Aplicação consistente em painel + dashboard widget + admin global (sidebar, topbar, buttons).
- Toggle no painel — UI clara, persistido por site.
- Zero JS para troca de tema (puro CSS via class/data attribute).
- Acessibilidade WCAG AA em ambos os temas.

**Non-Goals:**
- Tema por-usuário (user meta) — adia pra futuro se valer; nesta change é site-wide.
- Personalização de cores além de dark/light (ex: high-contrast, sepia) — out of scope.
- Animação suave entre temas (transition) — nice-to-have, fora de v1.
- Theme custom além de Loomi brand (ex: cliente quer roxo + verde) — out of scope.

## Decisions

### 1. Storage: enum `loomi_theme` em settings option

```php
'loomi_theme' => 'dark', // ou 'light' ou 'auto'
```

Sanitizer valida contra whitelist `['dark', 'light', 'auto']`. Default `dark` (mantém comportamento atual).

### 2. Class no `<body>` via `admin_body_class` filter

```php
add_filter( 'admin_body_class', function( $classes ) {
    $theme = Settings_Repository::get( 'loomi_theme', 'dark' );
    return $classes . ' loomi-theme-' . $theme;
} );
```

Resultado: `<body class="... loomi-theme-dark">` ou `loomi-theme-light` ou `loomi-theme-auto`.

CSS então usa:
```css
body.loomi-theme-dark .loomi-studio-wrap { ... dark colors ... }
body.loomi-theme-light .loomi-studio-wrap { ... light colors ... }
body.loomi-theme-auto .loomi-studio-wrap { ... dark colors (default) ... }
@media (prefers-color-scheme: light) {
    body.loomi-theme-auto .loomi-studio-wrap { ... light colors ... }
}
```

### 3. CSS variables sets: dark vs light

Refatorar `admin.css` e `admin-global.css` pra usar variables em todo lugar:

```css
.loomi-studio-wrap {
    --loomi-bg: var(--loomi-bg-dark);
    --loomi-bg-elevated: var(--loomi-bg-elevated-dark);
    --loomi-text: var(--loomi-text-dark);
    --loomi-text-muted: var(--loomi-text-muted-dark);
    --loomi-border: var(--loomi-border-dark);

    /* Constantes (não mudam com tema) */
    --loomi-accent: #FBD603;
    --loomi-accent-strong: #FBD603;
}

body.loomi-theme-dark .loomi-studio-wrap,
body.loomi-theme-auto .loomi-studio-wrap {
    --loomi-bg: #000000;
    --loomi-bg-elevated: #0d0d0d;
    --loomi-text: #ffffff;
    --loomi-text-muted: rgba(255, 255, 255, 0.6);
    --loomi-border: rgba(255, 255, 255, 0.08);
}

body.loomi-theme-light .loomi-studio-wrap {
    --loomi-bg: #ffffff;
    --loomi-bg-elevated: #f8f8f8;
    --loomi-text: #000000;
    --loomi-text-muted: rgba(0, 0, 0, 0.6);
    --loomi-border: rgba(0, 0, 0, 0.08);
}

@media (prefers-color-scheme: light) {
    body.loomi-theme-auto .loomi-studio-wrap {
        --loomi-bg: #ffffff;
        --loomi-bg-elevated: #f8f8f8;
        --loomi-text: #000000;
        /* ... */
    }
}
```

E todo lugar que usa `#000`, `#fff`, `rgba(255,255,255,...)`, `rgba(0,0,0,...)` etc. troca pra variables.

### 4. Acento amarelo NÃO muda

Constante visual da marca. Em ambos os modos:
- Background dos elementos primary buttons / active states: `#FBD603`
- Texto sobre amarelo: sempre `#000` (preto), porque amarelo é cor clara.

### 5. UI do toggle: tab "Dashboard" ou nova "Aparência"?

Decisão: **adicionar dentro da tab "Dashboard"** (não cria nova tab). Mantém menu enxuto.

Posição: no topo da tab Dashboard, antes do welcome. Layout:

```
┌─ Tema: ◯ Dark  ◯ Light  ◯ Auto (segue sistema) ─┐
└──────────────────────────────────────────────────┘

Bem-vindo ao Loomi Studio Setup
...
```

Renderiza como 3 radio buttons estilizados (ou um toggle visual de 3 opções).

### 6. Welcome widget no Dashboard WP

`#loomi_welcome_widget` também passa a usar variables — bg/text invertem com theme. Glow amarelo mantém. Ícones de stat cards invertem cor (em light mode, ícone preto sobre fundo cinza claro).

### 7. Sidebar/topbar globais

Em light mode:
- Sidebar bg: `#ffffff` ou `#f8f8f8`
- Sidebar items text: `#404040`
- Active item bg: `#FBD603` + text `#000` (mantém)
- Hover bg: `rgba(0,0,0,0.04)`
- Topbar bg: `#ffffff` ou `#1a1a1a` (escolher — talvez manter topbar dark sempre por contraste com main content)

**Decisão sub-q:** topbar segue tema (totalmente light em light mode) ou fica sempre dark? **Resposta:** segue tema. Consistência total.

### 8. Sanitizer: validação enum

```php
$theme = $input['loomi_theme'] ?? 'dark';
$valid = [ 'dark', 'light', 'auto' ];
if ( ! in_array( $theme, $valid, true ) ) {
    $theme = 'dark'; // fallback safe
    add_settings_error( ..., 'Tema inválido — usado dark.' );
}
$out['loomi_theme'] = $theme;
```

### 9. Migration: como existing sites (sem `loomi_theme` na option)?

`Settings_Repository::get('loomi_theme', 'dark')` — fallback default cobre sites existentes. Eles continuam dark sem ação.

## Risks / Trade-offs

- **[Risco] Light mode visualmente menos polido que dark (acento amarelo perde impacto em bg branco)** → Mitigação: usar amarelo + preto pra textos primary em light (ex: nome do plugin em amarelo+preto). Box shadows mais sutis pra dar profundidade no light.
- **[Risco] CSS refactor pra variables introduz regressões em telas que não testamos** → Mitigação: re-rodar suite de 114 tests. Manualmente validar painel + dashboard + 2-3 telas wp-admin globais.
- **[Risco] Auto mode + browser sem suporte `prefers-color-scheme`** → Aceito: navegadores < 2019 não suportam; fallback é o set default (dark). Quase ninguém usa browser tão antigo no admin.
- **[Trade-off] Topbar light em light mode tira impacto visual da marca** → Aceito; user pode escolher dark se quiser topbar preta sempre.
- **[Trade-off] Mais ~80 linhas de CSS pra variables + light set** → Aceito; mantenibilidade ganha (mudar paleta = editar 1 lugar).

## Migration Plan

1. Refatorar variables no admin.css e admin-global.css (sem mudar comportamento — defaults = dark).
2. Adicionar default `loomi_theme => 'dark'` em Repository.
3. Sanitizer enum validation.
4. UI no Tab_Dashboard (3 radios).
5. Filter `admin_body_class` aplica class.
6. Light theme CSS sets.
7. `@media (prefers-color-scheme: light)` pra modo auto.
8. Tests.
9. Sync container + rodar suite.
10. Validação manual: dark → switch pra light → switch pra auto → mudar OS theme → confirmar auto segue.

Rollback: setting volta a `dark`, tudo igual antes.

## Open Questions

- **Tab dedicada "Aparência" ou inline no Dashboard tab?** — decisão atual: inline no Dashboard tab. Mais simples; admins veem na primeira tela.
- **Topbar segue theme ou sempre dark?** — decisão atual: segue theme. Consistência.
- **Per-user preference futuro?** — out of scope. Se demanda surgir, vira change `theme-per-user` adicionando user meta override.
