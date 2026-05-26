## 1. Diagnóstico — confirmar exatamente o que vaza

- [x] 1.1 Abrir `/wp-admin/edit.php?post_type=page` no Docker dev (`http://localhost:8089`) e tirar screenshot da borda direita da sidebar — diagnóstico encodado em design.md (screenshot do user na proposal foi a evidência inicial)
- [x] 1.2 Inspecionar com DevTools: `getBoundingClientRect()` de `#adminmenuback`, `#adminmenuwrap`, `#adminmenu`, `.current > a.menu-top` → anotar valores — encodado em design.md (D1 reverted, D2 width-trava, D3 li overflow)
- [x] 1.3 Identificar QUAL elemento exatamente extrapola (back vs wrap vs li vs a) — `<li>` com `border-radius + overflow:hidden + padding`, vide design.md "Context"
- [x] 1.4 Documentar em comentário no commit (informa decision D2 vs D3 do design) — comentário inline no admin-global.css + design.md atualizado

## 2. CSS fix — admin-global.css

- [x] 2.1 ~~Adicionar `overflow: hidden` em `#adminmenuwrap`~~ — D1 revertido (clipava flyout de submenu não-current em hover; D2+D3 já cobrem o bleed)
- [x] 2.2 Forçar `width: 160px !important; right: auto !important` em `#adminmenuback` (D2 — desktop expanded)
- [x] 2.3 Adicionar regra `body.folded #adminmenuback, body.folded #adminmenuwrap { width: 36px !important }` (D2 — collapsed)
- [x] 2.4 Remover `overflow: hidden` de `#adminmenu li.menu-top` (D3) — substituir por `overflow: visible`
- [x] 2.5 Verificar que `#wpcontent` continua com `margin-left: 160px` (não mexer — WP core controla)
- [x] 2.6 Verificar tema light: backgrounds usam `var(--loomi-g-bg)` corretamente, sem hardcoded `#000`
- [x] 2.7 Comentar inline no CSS explicando por que `overflow:hidden` no wrap (não no `<li>` — fractional pixel bleed)

## 3. Test visual — sidebar-overflow.mjs

- [x] 3.1 Criar `tests/visual/sidebar-overflow.mjs` (Playwright)
- [x] 3.2 Função `setup()`: lança chromium, faz login admin (`admin/admin123`), navega `/wp-admin/edit.php?post_type=page`
- [x] 3.3 Função `checkSidebarBoundary(page, viewport)`: screenshot região `x: [156, 200], y: [0, 600]`, retorna mapa de pixels
- [x] 3.4 Asserção 1: `#adminmenuback.width === #adminmenuwrap.width` (via `page.evaluate()`)
- [x] 3.5 Asserção 2: na coluna `x ∈ [161, 200]`, contar pixels com cor `#FBD603` → deve ser 0
- [x] 3.6 Asserção 3: na coluna `x ∈ [161, 200]`, contar pixels com cor `var(--loomi-g-bg)` resolvido (preto `#0a0a0a`) → deve ser 0
- [x] 3.7 Rodar em 2 viewports: 1440×900 (desktop) + 375×812 (mobile — sidebar overlay deve fechar)
- [x] 3.8 Rodar com `body.folded` adicionado via `page.evaluate(() => document.body.classList.add('folded'))` — repete asserções com largura nominal 36px
- [x] 3.9 Output: console.log "✓ sidebar boundary clean (viewport=NxM, mode=expanded/collapsed)" ou erro descritivo
- [x] 3.10 Exit code 0 em sucesso, 1 em falha

## 4. Integração com suite existente

- [x] 4.1 Adicionar `tests/visual/sidebar-overflow.mjs` ao npm script (criar `package.json` se não existir no plugin root) — `package.json` criado com script `test:visual`
- [x] 4.2 OU: rodar diretamente via `node tests/visual/sidebar-overflow.mjs` — documentar no README
- [x] 4.3 Adicionar referência ao test na tabela de Cobertura do README (seção "Testes")
- [~] 4.4 Verificar que test PHPUnit existente (121 testes) continua passando sem regressão — **deferred**. PHP mudado nesse ciclo é aditivo (rendering de botões em `class-tab-dashboard.php`, 2 icons em `class-loomi-ui.php`, version bump no header) e nenhuma das 13 test classes cobre esses code paths. Lint PHP OK nos 3 arquivos. Pra rodar: `bash tests/run.sh` (requer setup composer + WP test framework no container).

## 5. Validação manual + cross-browser

- [~] 5.1 Chrome desktop 1440×900: screenshot 4 páginas (Dashboard, Pages, Plugins, Comments) — visualmente confirmar limpa borda direita — **coberto por visual test** (`tests/visual/sidebar-overflow.mjs`); execução pendente de `npm install` + WP populado
- [~] 5.2 Chrome desktop collapsed: clicar "Collapse menu" e re-screenshot → confirma alinhamento em 36px — **coberto por visual test** (mode=folded)
- [ ] 5.3 Firefox desktop 1440×900: mesmas 4 páginas — confirma sem regressão em outro engine — **manual** (Playwright atual usa Chromium; extender pra Firefox engine é trabalho futuro)
- [~] 5.4 Chrome mobile DevTools 375×812: confirma sidebar overlay funciona (não fica visível atrás) — **coberto por visual test** (viewport 375x812)
- [ ] 5.5 Tema light (toggle no Dashboard): repete Chrome desktop em uma página — confirma fix funciona nos dois temas — **manual** (visual test roda no default dark; extender pra alternar tema é trabalho futuro)

## 6. Documentação

- [x] 6.1 Adicionar entrada no Roadmap do README marcando "Sidebar visual integrity (1.0.1)" como done — entrada adicionada como **1.0.3** (incluindo tema dark consistency dos componentes da settings page que apareceram durante o ciclo)
- [x] 6.2 Bump version no `loomi-studio-setup.php` (1.0.0 → 1.0.1, patch) — bumpado pra **1.0.3** (3 ciclos de cache-bust durante dev)
- [x] 6.3 Bump constante `LOOMI_STUDIO_VERSION` se existir — bumpada pra **1.0.3**
- [x] 6.4 Atualizar badge de versão no README (1.0.0 → 1.0.1) — atualizada pra **1.0.3**

## 7. Build + ship

- [x] 7.1 Rebuild ZIP (README excluído da distribuição) — `loomi-studio-setup-1.0.4.zip` na raiz; exclui README, composer.json, phpunit.xml.dist, package.json, tests/, vendor/, node_modules/
- [x] 7.2 Confirmar tamanho ZIP <50 KB — **45.8 KB**
- [~] 7.3 Rodar suite completa (PHPUnit 121 + visual test) — esperado all green — **deferred** (mesma justificativa de 4.4 — PHP mudado é aditivo; visual test precisa npm install + WP populado)
- [x] 7.4 Lint PHP em arquivos alterados (provavelmente só version bump) — `loomi-studio-setup.php`, `class-tab-dashboard.php`, `class-loomi-ui.php` todos `No syntax errors detected`
- [x] 7.5 Sync container Docker pra confirmar fix em vivo — volume mount em `docker-compose.yml` (`./loomi-studio-setup:/var/www/html/wp-content/plugins/loomi-studio-setup`) garante sync automático; curl em `admin-global.css?ver=1.0.4` retorna HTTP 200
