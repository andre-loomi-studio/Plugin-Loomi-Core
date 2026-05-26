## Context

O painel atual usa estilos default do WP admin com 1 file CSS `assets/admin.css` que tem apenas 6 linhas de override (margem em tabs, width em form-table th, estilos mínimos do logo picker). Aproveita 100% do Iris (color picker), Settings API, nav-tab-wrapper. Funciona mas é genérico.

Loomi é agência de design. Identidade visual é parte do produto. Aplicar tom Loomi no plugin reforça percepção de qualidade e ownership.

Constraints:
- WP admin tem ~5MB de CSS próprio em cascade — precisamos seletores fortes (especificidade) mas sem `!important` em excesso (anti-pattern).
- Não podemos quebrar acessibilidade WP (focus visible, ARIA, contraste).
- Não podemos vazar estilo pra fora do painel do plugin.
- Sem build step (já é constraint do plugin) — CSS escrito à mão, sem PostCSS/Sass.
- Sem fonts externas (Google Fonts) — performance + privacy.

## Goals / Non-Goals

**Goals:**
- UI **distintamente Loomi** ao abrir `Configurações → Loomi Studio` — usuário reconhece marca instantaneamente.
- **Acessibilidade preservada** (contraste AAA, focus visible, tab navigation).
- **Scope estrito** — outras telas do admin não são afetadas.
- **Performance** — CSS minúsculo (~3KB), nenhuma fonte externa, nenhuma imagem raster.
- **Manutenibilidade** — variáveis CSS para cores no topo do arquivo; mudar paleta = editar 5 linhas.

**Non-Goals:**
- Não customizar admin bar (topbar WP) — invasivo, quebra UX, fora do escopo do plugin.
- Não substituir o Iris (color picker WP) — funciona bem, refatorar daria churn.
- Não criar tema admin completo tipo "Loomi Admin Theme" — esta change foca só no nosso painel.
- Não usar React/Vue/build step — KISS, CSS puro.
- Não suportar dark mode customizado nesta versão — sistema do user (se usa dark mode plugin) decide; podemos prefixar com `@media (prefers-color-scheme: dark)` em outra change futura se valer.
- Não tocar na tela de login (`/studio-access/`) — usuário já configura cor/logo via Tab_Login; o preset "Aplicar branding Loomi" é opcional e separável.

## Decisions

### 1. CSS variables no topo do arquivo

```css
.loomi-studio-wrap {
    --loomi-black: #000000;
    --loomi-yellow: #FBD603;
    --loomi-white: #ffffff;
    --loomi-gray-100: #f5f5f5;
    --loomi-gray-300: #d0d0d0;
    --loomi-gray-600: #6b6b6b;
    --loomi-radius: 6px;
    --loomi-shadow: 0 1px 3px rgba(0,0,0,.08);
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
}
```

Mudar paleta no futuro = editar 5 linhas. Todos os seletores usam `var(--loomi-*)`.

### 2. Header customizado com SVG inline

```html
<div class="loomi-header">
    <div class="loomi-brand">
        <svg viewBox="0 0 80 24" class="loomi-logo" aria-label="Loomi">...</svg>
        <span class="loomi-divider"></span>
        <span class="loomi-product">Studio Setup</span>
    </div>
    <span class="loomi-version">v1.0.0</span>
</div>
```

Background `--loomi-black`, padding 24px, logo branco + acento amarelo no `studio setup` separator. Versão à direita em tom cinza claro.

SVG wordmark "loomi" — letras lowercase, sem serif, ~120px width. Inline pra evitar request extra + permite controle CSS de cor (preto/branco/yellow conforme contexto).

### 3. Tabs flat com underline amarelo

```css
.loomi-studio-wrap .nav-tab-wrapper {
    border-bottom: 1px solid var(--loomi-gray-300);
    margin: 24px 0;
}
.loomi-studio-wrap .nav-tab {
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--loomi-gray-600);
    font-weight: 500;
    margin: 0 4px -1px 0;
    padding: 12px 16px;
}
.loomi-studio-wrap .nav-tab:hover {
    color: var(--loomi-black);
}
.loomi-studio-wrap .nav-tab.nav-tab-active {
    color: var(--loomi-black);
    border-bottom-color: var(--loomi-yellow);
}
```

Tab ativa: underline amarelo 3px, texto preto. Hover: texto fica preto, sem fill. Limpo, sem chrome pesado.

### 4. Botão primário (Salvar)

```css
.loomi-studio-wrap .button-primary {
    background: var(--loomi-black);
    border: 2px solid var(--loomi-black);
    color: var(--loomi-white);
    box-shadow: none;
    text-shadow: none;
    padding: 8px 24px;
    border-radius: var(--loomi-radius);
    transition: background .15s, color .15s;
}
.loomi-studio-wrap .button-primary:hover,
.loomi-studio-wrap .button-primary:focus {
    background: var(--loomi-yellow);
    color: var(--loomi-black);
    border-color: var(--loomi-black);
}
```

Hover inverte: preto → amarelo + branco → preto. Distintivo. Sem gradientes, sem `!important`.

### 5. Checkboxes customizados

```css
.loomi-studio-wrap input[type="checkbox"] {
    appearance: none;
    width: 18px;
    height: 18px;
    border: 2px solid var(--loomi-black);
    border-radius: 3px;
    background: var(--loomi-white);
    vertical-align: middle;
    cursor: pointer;
    position: relative;
}
.loomi-studio-wrap input[type="checkbox"]:checked {
    background: var(--loomi-yellow);
    border-color: var(--loomi-black);
}
.loomi-studio-wrap input[type="checkbox"]:checked::after {
    content: "";
    position: absolute;
    left: 4px;
    top: 0;
    width: 5px;
    height: 10px;
    border: solid var(--loomi-black);
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}
.loomi-studio-wrap input[type="checkbox"]:focus {
    outline: 2px solid var(--loomi-yellow);
    outline-offset: 1px;
}
```

Custom check via `::after` (CSS-only checkmark). Focus ring amarelo. Acessibilidade preservada (ainda é `<input type=checkbox>` — screen readers OK).

### 6. Notices internos do painel

```css
.loomi-studio-wrap .notice {
    border-left-width: 4px;
    border-left-color: var(--loomi-yellow);
    background: var(--loomi-gray-100);
}
.loomi-studio-wrap .notice-error {
    border-left-color: #d63638; /* WP red, mantém pra erro */
}
.loomi-studio-wrap .notice-success {
    border-left-color: #00a32a; /* WP green pra success */
}
```

Notice-info usa amarelo (cor da marca). Erro e sucesso mantêm cor semântica WP — porque amarelo "warning" não tem essa conotação aqui (é nossa cor de marca). Decisão consciente: privilegiar marca em info, semântica em erro/success.

### 7. Inputs (text, color, etc.)

```css
.loomi-studio-wrap input[type="text"],
.loomi-studio-wrap input[type="number"],
.loomi-studio-wrap input[type="email"],
.loomi-studio-wrap input[type="url"] {
    border: 1px solid var(--loomi-gray-300);
    border-radius: var(--loomi-radius);
    padding: 8px 12px;
    box-shadow: none;
    font-family: inherit;
}
.loomi-studio-wrap input:focus {
    border-color: var(--loomi-black);
    outline: 2px solid var(--loomi-yellow);
    outline-offset: -1px;
    box-shadow: none;
}
```

Sem o box-shadow padrão WP (cinza). Focus ring amarelo. Tipografia herda do wrapper (system-ui).

### 8. SVG logo — wordmark inline

```html
<svg viewBox="0 0 100 24" xmlns="http://www.w3.org/2000/svg" class="loomi-logo">
    <text x="0" y="20" font-family="system-ui" font-weight="700" font-size="22" fill="currentColor">loomi</text>
    <circle cx="92" cy="6" r="3" fill="#FBD603"/>
</svg>
```

"loomi" em lowercase bold + ponto amarelo no canto superior direito (acento da marca). `fill="currentColor"` permite mudar cor via CSS conforme contexto (header tem cor branca, mas o SVG pode ser usado em outros pontos com `color: black`).

Trade-off: usar `<text>` em SVG (não path) significa que renderiza com a font do sistema. Pro nosso wordmark é OK — system-ui é consistente. Se quiser fidelidade de marca exata (font customizada), trocar por paths gerados de uma font tipo Inter Bold. Out of scope v1.

### 9. Sem `!important` em CSS

Estratégia de especificidade: scope tudo sob `.loomi-studio-wrap` (1 classe) + tag selector (`.loomi-studio-wrap input[type=checkbox]`). Especificidade 0-1-1 vs WP core (1-0-1) — WP ainda ganha em alguns casos. Pra esses específicos, usar `:where()` ou classe extra `.loomi-studio-wrap.loomi-styled`. Evita corrida de `!important`.

### 10. Preset "Aplicar branding Loomi" no Tab_Login

Pequena adição UX: botão abaixo do color picker que ao clicar:
- Seta `custom_login_bg_color` para `#000000` via JS
- (Opcional) seta cor de complemento em futuras extensions

Código JS simples (10 linhas) adicionado ao `render_login_tab`. Sem dependência nova.

```js
$('#loomi-apply-brand').on('click', function(e){
    e.preventDefault();
    $('#loomi-bg-color').val('#000000').trigger('change');
});
```

## Risks / Trade-offs

- **[Risco] WP atualização muda HTML do nav-tab ou checkbox** → Mitigação: seletores baseiam em tags + classes semânticas (`.nav-tab`, `input[type=checkbox]`). WP raramente quebra esses contratos.
- **[Risco] CSS escape do scope** (alguma regra vaza pra fora `.loomi-studio-wrap`) → Mitigação: prefixar TODA regra com `.loomi-studio-wrap`. Code review do CSS final pra confirmar.
- **[Risco] Conflito com plugins de admin theme** (ex.: White Label CMS) → Aceito; user pode desligar nosso wrapper class adicionando `define('LOOMI_STUDIO_NO_BRAND', true)` em wp-config (escape hatch — sugestão pra próxima iteração se aparecer demanda).
- **[Risco] SVG wordmark com `<text>` renderiza diferente em fonts de sistemas diferentes** → Aceito v1 (system-ui é consistente o suficiente). Se quiser fidelidade absoluta, futura change converte pra path.
- **[Trade-off] Sem dark mode** → Aceito v1. Adicionar `@media (prefers-color-scheme: dark)` é trivial e pode entrar em change futura quando demanda surgir.
- **[Trade-off] Color picker (Iris) mantém seu próprio visual** → Aceito; refatorar o Iris é complexidade desproporcional. Pequeno gap visual no campo de cor.

## Migration Plan

1. Adicionar variáveis CSS no topo de `admin.css`.
2. Reescrever as 6 linhas atuais + adicionar ~150 linhas com header, tabs, buttons, checkboxes, notices, inputs.
3. Criar `assets/loomi-logo.svg` (ou inline SVG no PHP de Settings_Page se preferir manutenção em 1 arquivo).
4. Editar `Settings_Page::render()` adicionando `<div class="loomi-header">` antes do `<h1>` (manter o h1 pra acessibilidade — pode esconder visualmente via CSS se ficar redundante).
5. Editar `Tab_Login::render()` adicionando botão "Aplicar branding Loomi".
6. Adicionar tests (2-3 em `BrandUITest`).
7. Sync + suite.
8. Capturar 1 screenshot pra README (opcional).
9. Rebuild ZIP.

Rollback: restaurar `admin.css` da versão anterior + reverter Settings_Page e Tab_Login. Comportamento idêntico, só perde a identidade visual.

## Open Questions

- **Dark mode**: implementar `@media (prefers-color-scheme: dark)` agora ou esperar demanda? Decisão atual: **esperar**. Forma trivial de adicionar quando aparecer.
- **Logo customizável por site**: futuro? Por enquanto wordmark Loomi é fixo. Se sites white-label da Loomi precisarem do brand do cliente, vira futura change `client-brand-override`.
- **Escape hatch via constante** (`LOOMI_STUDIO_NO_BRAND`): adicionar agora ou esperar? Decisão atual: **esperar**. Se conflitar com algum plugin de admin theme, adicionamos.
