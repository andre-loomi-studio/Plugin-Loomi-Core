## ADDED Requirements

### Requirement: Sidebar respeita largura nominal

A sidebar do admin (`#adminmenuback`, `#adminmenuwrap`, `#adminmenu`) MUST ocupar exatamente a largura nominal do WordPress core em cada modo:

- Desktop expanded: 180px
- Desktop collapsed (`body.folded`): 36px
- Mobile (`<783px`, sidebar como overlay): 0 fora da overlay aberta

Nenhum sub-elemento da sidebar (background, item active, hover state, border, shadow) SHALL extrapolar visualmente a largura nominal por mais de 0 pixels físicos.

#### Scenario: Item active em página Pages não vaza pra content area
- **WHEN** usuário navega para `/wp-admin/edit.php?post_type=page` em desktop expanded (viewport ≥783px)
- **THEN** o item `<li class="current">Pages</li>` SHALL ter background `#FBD603` que termina EXATAMENTE em `x=180px` desde a borda esquerda do viewport
- **AND** o pixel em `x=181px, y=<linha-do-item>` SHALL ser da cor de fundo do content area (NÃO amarelo, NÃO preto da sidebar)

#### Scenario: Background da sidebar não tem stripe de bleed
- **WHEN** o admin é carregado em qualquer página
- **THEN** a região vertical `x ∈ [181, 220], y ∈ [0, viewport-height]` SHALL conter ZERO pixels da cor `var(--loomi-g-bg)` (preto `#0a0a0a` no tema dark, branco no light)
- **AND** essa região SHALL ser inteiramente da cor de fundo do content area (`var(--loomi-g-content-bg)`)

#### Scenario: Modo collapsed também alinhado
- **WHEN** usuário clica em "Collapse menu" → `body` ganha classe `folded`
- **THEN** `#adminmenuwrap` SHALL ter `width: 36px` exato
- **AND** nenhum pixel da sidebar SHALL aparecer em `x ≥ 37px`

### Requirement: `#adminmenuback` e `#adminmenuwrap` com mesma largura

Os dois elementos de sidebar background do WP core MUST ter exatamente a mesma largura computada (`getBoundingClientRect().width`) — diferença máxima permitida: 0 pixels.

#### Scenario: Largura batida em desktop expanded
- **WHEN** página admin carrega em viewport 1440×900 sem `body.folded`
- **THEN** `document.querySelector('#adminmenuback').getBoundingClientRect().width === 180`
- **AND** `document.querySelector('#adminmenuwrap').getBoundingClientRect().width === 180`

#### Scenario: Largura batida em desktop collapsed
- **WHEN** página admin carrega em viewport 1440×900 com `body.folded`
- **THEN** `document.querySelector('#adminmenuback').getBoundingClientRect().width === 36`
- **AND** `document.querySelector('#adminmenuwrap').getBoundingClientRect().width === 36`

### Requirement: Item active não tem fractional pixel bleeding

O `<a class="menu-top">` dentro de `<li class="current">` ou `<li class="wp-has-current-submenu">` MUST ter seu background `#FBD603` clipado ao bbox visual do `<li>` pai, sem 1-2px de vazamento causado por interação `border-radius + overflow + padding`.

#### Scenario: Item current renderiza com bordas internas limpas
- **WHEN** screenshot é capturado da sidebar em página com item active
- **THEN** a coluna de pixels `x=180px` (borda direita da sidebar) SHALL ter SEM amarelo `#FBD603` em nenhum y
- **AND** o item active interno SHALL ter `border-radius: 8px` visível (sem clipping prematuro)

#### Scenario: Hover state também respeita borda
- **WHEN** usuário hover-a sobre um item da sidebar
- **THEN** o background `var(--loomi-g-hover)` SHALL ficar inteiramente dentro do bbox do `<li>`
- **AND** nenhum pixel de hover SHALL aparecer em `x ≥ 180px`

### Requirement: Suite visual de regressão da sidebar

O projeto MUST conter teste automatizado que valida a integridade visual da sidebar:

- Arquivo: `tests/visual/sidebar-overflow.mjs`
- Engine: Playwright + (pixelmatch OU detecção heurística pixel-color)
- Roda automaticamente antes de "done" em qualquer mudança em `admin-global.css`
- Detecta bleed na borda direita em viewports desktop (1440×900) e mobile (375×812)

#### Scenario: Test detecta regressão de bleed amarelo
- **WHEN** alguém remove `overflow: hidden` do `#adminmenuwrap` e roda a suite
- **THEN** `node tests/visual/sidebar-overflow.mjs` SHALL falhar com mensagem indicando "yellow bleed detected at x=N"

#### Scenario: Test passa com fix aplicado
- **WHEN** CSS corrigido está em vigor
- **THEN** `node tests/visual/sidebar-overflow.mjs` SHALL terminar com exit code 0
- **AND** output SHALL incluir "sidebar boundary clean" em cada viewport testado
