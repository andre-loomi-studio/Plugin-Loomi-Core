## Why

Hoje o painel do Loomi Studio Setup usa estilos default do WordPress admin: cinza neutro, azul `#2271b1` nos botões, tipografia padrão WP. Funciona, mas:

1. **Visualmente genérico** — clientes que entram no admin não conseguem distinguir "isto é da Loomi" de "isto é um plugin terceiro qualquer".
2. **Sem identidade visual** — Loomi é uma agência de design; ter o próprio plugin parecendo um plugin de WordCamp dos anos 2010 é dissonante.
3. **Sem reinforcement de marca** — admins que veem o plugin todo dia poderiam ter um tom Loomi como reminder.

A solução: **UI minimalista preto + amarelo Loomi** aplicada ao painel de configurações do plugin, mantendo conformidade total com convenções WP (acessibilidade, structure semantic, sem quebrar updates WP).

Paleta:
- **Preto** `#000000` — backgrounds, headers, typography principal
- **Amarelo Loomi** `#FBD603` — acentos, foco, botão primário, indicador de tab ativa
- Tons cinza neutros para texto secundário e borders sutis

Contraste WCAG: ambas combinações (#000 ↔ #FBD603) atingem **AAA** com ratio 11.97. Acessibilidade não é trade-off.

## What Changes

- **Header customizado** no topo do painel (`<div class="loomi-header">`) com logo wordmark "loomi" (SVG inline minimalista) + título + subtítulo, em background preto com acento amarelo.
- **Tabs reestilizadas**: removidas as bordas pesadas do `.nav-tab-wrapper` nativo; estilo flat, tab ativa com **underline amarelo de 3px**, hover sutil.
- **Botão primário "Salvar Alterações"**: preto com texto branco; hover/focus inverte para amarelo + texto preto.
- **Checkboxes customizados** (`.loomi-studio-wrap input[type=checkbox]`): borda 2px preta; quando checked, fundo amarelo + check preto.
- **Notice de info** (`<div class="notice notice-info inline">`): borda esquerda amarela `border-left: 4px solid #FBD603` substituindo o azul WP default — escopo apenas dentro do painel.
- **Form-table**: spacing aumentado (linhas mais respiradas), tipografia ligeiramente maior, labels em peso 600.
- **Inputs (text, color)**: borda 1px cinza claro, focus ring amarelo 2px (substitui o azul WP).
- **Sem Google Fonts**: usar `system-ui, -apple-system, "Segoe UI", sans-serif` para performance e consistência cross-OS.
- **Scope estrito**: todas as regras CSS scoped sob `.loomi-studio-wrap`. Zero leak pra outras telas do admin WP. Outros plugins não são afetados.
- **Preset "Aplicar branding Loomi"** opcional no Tab_Login: botão que pré-popula `custom_login_bg_color = #000000` (admin pode trocar pra qualquer cor depois) — útil pra setup rápido em sites Loomi.
- **Tests**: suite WP-PHPUnit ganha 2 testes confirmando que (a) `<div class="loomi-header">` é renderizado e (b) o CSS file novo é enqueuado na page hook correta.

## Capabilities

### New Capabilities
- `brand-ui`: identidade visual Loomi (preto + amarelo + tipografia system) aplicada de forma scoped ao painel de settings do plugin. Header customizado, botões, tabs, checkboxes, notices, inputs com tom Loomi.

### Modified Capabilities
<!-- Não muda nenhuma capability comportamental — refactor visual puro. -->

## Impact

- **Arquivos novos**:
  - `assets/loomi-logo.svg` — wordmark "loomi" em SVG (preto, scalable).
  - Tests novos em `BrandUITest.php` (~3 testes).
- **Arquivos alterados**:
  - `assets/admin.css` — riscar e reescrever com nova paleta + header + tabs + checkboxes (~150 linhas vs 6 atuais).
  - `includes/settings/class-settings-page.php` — adicionar `<div class="loomi-header">...</div>` antes do `<h1>` e wrapper class `.loomi-studio-wrap` (já existe, manter).
  - `includes/settings/tabs/class-tab-login.php` — adicionar botão "Aplicar branding Loomi" abaixo do color picker (JS handler que seta o campo).
  - `README.md` — screenshot ou nota descrevendo a nova UI.
- **Sem mudança no contrato externo**: hooks, filtros, option key, comportamento dos toggles — tudo idêntico.
- **Sem mudança de tamanho perceptível**: `admin.css` cresce ~3 KB, ZIP final +~4 KB. SVG logo ~1.5 KB.
- **Performance**: zero impacto runtime — CSS enqueueado APENAS na hook `settings_page_loomi-studio-setup` (já garantido).
- **Compatibilidade**:
  - WP ≥ 6.0 (sem mudança).
  - Funciona em dark mode (plugins de dark mode WP geralmente respeitam `prefers-color-scheme`; podemos adicionar query opcional pra inverter, mas não obrigatório nesta change).
  - Não conflita com plugins que modificam admin globalmente — nosso CSS é scoped sob `.loomi-studio-wrap` apenas.
- **Acessibilidade**: contraste #000/#FBD603 = ratio 11.97 (AAA tanto em normal quanto large text). Focus rings visíveis (2px amarelo) em todos os elementos focáveis. Sem dependência de cor para transmitir informação (checkboxes ainda têm o check ✓ além da cor).
- **Riscos**: visual muito agressivo poderia surpreender admins acostumados ao cinza WP. Mitigação: scope estrito — só o painel do plugin. Não muda toolbar, dashboard, ou outras telas. Admin "fora" do painel não percebe nada diferente.
