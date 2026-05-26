## Why

A sidebar customizada Loomi tem artefato visual ("sobresalente") na borda direita: quando um item está ativo, o background preto da sidebar (`#adminmenuback`) e/ou o item ativo amarelo extendem alguns pixels para dentro da área de conteúdo principal, criando uma "saliência" visível que quebra o alinhamento limpo sidebar/conteúdo do design Linear/Vercel-style que tentamos replicar.

O bug aparece em **toda página do admin** (não apenas Loomi Studio) — é regressão visual do `admin-global.css` v1.0.0, agora visível em uso real conforme screenshot do usuário (página Pages).

## What Changes

- Clip a borda direita da sidebar (`#adminmenuback`, `#adminmenuwrap`) com `overflow: hidden` e/ou border-box width para que NADA passe da largura nominal de 160px (collapsed: 36px)
- Reset o `background-color` do `body.wp-admin` (fundo padrão WP) para a cor de conteúdo correta (`var(--loomi-g-content-bg)`) evitando que o preto da sidebar "sangra" caso o `wpcontent` left-margin esteja errado por 1-2px
- Garantir que `#wpcontent` left-margin = exatamente largura da sidebar (160px desktop / 36px collapsed / 0 mobile)
- Force item ativo (`.current`, `.wp-has-current-submenu`) a respeitar a largura do `li` pai (sem `margin-right` negativo, sem `width: calc(100% + Npx)`)
- Adicionar regression test visual (Playwright + pixelmatch) que captura sidebar + 8px da content area e compara com baseline; falha se houver background-color preto detectado fora dos 160px nominais

## Capabilities

### New Capabilities
- `sidebar-visual-integrity`: Garantias visuais da sidebar Loomi — sem overflow, sem bleeding, alinhamento sidebar/content pixel-perfect em todos os states (default, hover, current, collapsed, mobile)

### Modified Capabilities
*(nenhuma — `module-architecture` cobre a estrutura de módulos PHP, não a customização CSS de UI; criamos capability nova focada em integridade visual)*

## Impact

- **Affected files:**
  - `loomi-studio-setup/assets/admin-global.css` (selectors `#adminmenuback`, `#adminmenuwrap`, `#adminmenu li.current`, `#wpcontent`)
  - `loomi-studio-setup/tests/integration/` (test PHP que valida CSS gera classes corretas, opcional)
  - `loomi-studio-setup/tests/visual/sidebar-overflow.mjs` (test Playwright novo — captura screenshot e detecta pixel preto fora da bbox da sidebar)
- **Nenhuma mudança em PHP** — bug é puramente CSS
- **Risco:** baixo — overflow:hidden na sidebar não afeta funcionalidade (submenus já são absolutamente posicionados pelo WP core), apenas clipa artefato
- **Validation:** screenshot na página Pages + Plugins + Comments confirma alinhamento limpo + suite de testes (121) passa
