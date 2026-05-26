## Context

A sidebar do WordPress admin tem 3 elementos sobrepostos no DOM:

```
#adminmenuback   ← div fixa atrás (background fallback)
#adminmenuwrap   ← container intermediário (position: relative)
  └─ #adminmenu  ← <ul> dos itens (largura nominal 160px)
```

O `#wpcontent` à direita tem `margin-left: 160px` (default WP) compensando. Quando aplicamos `padding: 8px` no `#adminmenu` (admin-global.css linha 203), o **conteúdo interno encolhe pra 144px** mas o `#adminmenuwrap` continua 160px — sem bleed por enquanto.

**O bug visível:** o item `.current` (ativo) tem `background: #FBD603` aplicado no `<a class="menu-top">` que ocupa **100% da largura do `<li>`**. Mas o `<li>` tem `margin: 2px 0` (admin-global.css linha 265) e `border-radius: 8px` (linha 264) com `overflow: hidden`. Em alguns browsers / zoom levels, esse `border-radius` + `overflow: hidden` no `<li>` interage com o `padding: 8px` do `<ul>` pai resultando em **1-2px de bleeding** do amarelo pra fora da bbox visual (o overflow:hidden clipa o conteúdo interno mas não o próprio box quando há fractional pixels).

Adicionalmente, **`#adminmenuback` não tem `width` ou `right` explícito** — depende do WP core JS pra calcular. Em transições de collapsed↔expanded, ou em pages com `body.wp-admin` que carregam tarde, o `back` pode ficar 1-2px mais largo que o `wrap`, expondo um stripe preto no gap.

## Goals / Non-Goals

**Goals:**
- Pixel-perfect alignment entre sidebar (160px desktop, 36px collapsed, 0 mobile) e `#wpcontent` start
- Item `.current` amarelo NUNCA pode visualmente extrapolar a largura do `<li>` pai
- `#adminmenuback` e `#adminmenuwrap` SEMPRE com mesma largura visual exata (sem stripe de bleed)
- Funciona em: desktop expanded (160px), desktop collapsed (36px), mobile (`<783px`, sidebar overlay), folded em viewports estranhos
- Regression test detecta pixels pretos fora da bbox nominal

**Non-Goals:**
- Não vamos mexer no `margin-left` do `#wpcontent` (WP core controla)
- Não vamos eliminar o `#adminmenuback` (faz parte do mecanismo de scroll fixo do WP)
- Não vamos converter sidebar pra Loomi-custom (manter compatibilidade total com WP core hooks)
- Não vamos refatorar todo o admin-global.css (mudança focada na borda direita)

## Decisions

### D1: ~~`overflow: hidden` no `#adminmenuwrap`~~ **REVERTED**

**Decisão original:** aplicar `overflow: hidden` no `#adminmenuwrap` como safety net pra clipar qualquer bleed lateral.

**Por que foi revertido:** em modo expandido o WP renderiza `.wp-submenu` de itens NÃO-current como `position: absolute; left: 160px+` (flyout pra direita ao passar hover). `overflow: hidden` no `#adminmenuwrap` também clipa esse flyout, escondendo subitens em hover — regressão funcional. O design original não considerou o flyout de não-current em modo expandido (só o popup de collapsed, que tem comportamento similar).

**Por que D1 não é necessário:** o bleed real era causado pela interação `border-radius: 8px + overflow: hidden + padding` no `<li>` (vide D3). Removendo o `overflow: hidden` do `<li>` (D3) elimina a fonte do bleed. D2 (width travada do back/wrap) garante alinhamento. D1 era safety net pra cenário que D3 + D2 já cobrem.

**Alternativas consideradas (e descartadas):**
- `overflow-x: hidden + overflow-y: visible` no wrap — `overflow-x:hidden` ainda clipa o flyout horizontal
- `clip-path: inset(0 0 0 0)` — também clipa filhos absolute
- Mover clip pro `<a class="menu-top">` — `<a>` e `.wp-submenu` são irmãos dentro do `<li>`, clip no `<a>` não afeta o `.wp-submenu`, mas o bleed do `<a>` não escapa do bbox do próprio `<a>` (que já tem border-radius)

### D2: Force width + right:0 no `#adminmenuback`

**Decisão:** `#adminmenuback { width: 160px !important; right: auto !important; }` (e `36px` em `.folded`).

**Por quê:** elimina o cálculo dinâmico do WP que causa bleed de 1-2px. Width fixo bate exatamente com `#wpcontent { margin-left: 160px }` do WP core.

**Alternativas consideradas:**
- Deixar WP core gerenciar — descartado: é exatamente o que causa o bug
- Usar `inset: 0 auto 0 0; width: 160px` — funciona mas é mais verboso

### D3: Remover `overflow: hidden` do `#adminmenu li.menu-top`

**Decisão:** `#adminmenu li.menu-top { overflow: visible }` (atualmente `hidden` na linha 266).

**Por quê:** com `border-radius: 8px` o overflow:hidden no `<li>` causa exatamente o fractional pixel bleed descrito. O clipping correto agora vive no `#adminmenuwrap` (D1) — o `<li>` não precisa clipar nada.

### D4: Test visual com tolerância zero pra pixel preto fora da bbox

**Decisão:** novo `tests/visual/sidebar-overflow.mjs` (Playwright):
1. Login admin
2. Goto `/wp-admin/edit.php` (página com item active)
3. Screenshot da área `x: [156, 200], y: [0, 600]` (sidebar boundary + 40px de content)
4. Pixelmatch contra baseline OU detecção heurística: contar pixels com `#0a0a0a` ou `#000` na coluna `x ∈ [161, 200]` (40px à direita da sidebar de 160px). Deve ser **0**.

**Por quê:** texto da CLAUDE.md global seção 5 — "regression test before declaring done". O bug é puramente visual, lint PHP não pega. Sem teste, regressão silenciosa volta no próximo refactor de CSS.

**Alternativas consideradas:**
- Pixel diff full-page — overkill, e a sidebar muda com themes/Wordfence notices
- Manual screenshot review — não é regressão automática

## Risks / Trade-offs

- **[Risk] `overflow: hidden` no wrap quebra tooltips do WP** → Mitigação: WP tooltips são `position: fixed` no `<body>`, não dentro do wrap. Validado lendo wp-admin/css/common.css linha ~1200.
- **[Risk] `width: 160px !important` quebra plugins que customizam sidebar (ex: WP Toolkit)** → Mitigação: documentar em comentário inline + se aparecer relato, escopar com `body:not(.wp-toolkit-active)`.
- **[Risk] Test visual flaky por anti-aliasing diferente entre runs** → Mitigação: tolerância de 5 pixels (não 0) na detecção; alvo é "stripe contíguo de N pixels pretos", não "0 pixels pretos".
- **[Trade-off] Não dá pra eliminar 100% do `#adminmenuback` (WP core depende)** → Aceitamos manter o elemento, só forçamos largura idêntica ao `#adminmenuwrap`.

## Migration Plan

1. **CSS-only edit**, sem mudança em DB/options/PHP → zero migration
2. Rebuild ZIP (`fix-sidebar-overflow` v1.0.1 patch — só CSS)
3. Sites Loomi puxam via update server na próxima checagem (12h cache)
4. Rollback: reverter commit no admin-global.css → push ZIP versão anterior
