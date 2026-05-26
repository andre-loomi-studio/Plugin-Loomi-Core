## 1. Settings_Repository + Sanitizer

- [x] 1.1 Adicionar `'loomi_theme' => 'dark'` em `Settings_Repository::defaults()`
- [x] 1.2 NÃO adicionar em `BOOL_FIELDS` (é enum, não bool)
- [x] 1.3 Criar constante `Settings_Repository::THEME_VALUES = ['dark', 'light', 'auto']`
- [x] 1.4 Em `Settings_Sanitizer::sanitize()`, adicionar validação: se `loomi_theme` não está em `THEME_VALUES`, manter valor anterior + `add_settings_error`

## 2. Body class via admin_body_class filter

- [x] 2.1 Em `Loomi_Settings_Page::register()`, adicionar `add_filter('admin_body_class', [__CLASS__, 'filter_admin_body_class'])`
- [x] 2.2 Implementar método: pega `Settings_Repository::get('loomi_theme', 'dark')`, retorna `$classes . ' loomi-theme-' . $theme`

## 3. CSS variables refactor — admin.css (painel)

- [x] 3.1 Criar bloco de variables BASE no topo (sem theme): `--loomi-accent`, `--loomi-accent-strong`, `--loomi-radius`, `--loomi-radius-sm`, `--loomi-shadow-sm`, `--loomi-shadow`, etc. (props que não mudam com tema)
- [x] 3.2 Criar bloco de variables THEME-DEPENDENT: `--loomi-bg`, `--loomi-bg-elevated`, `--loomi-text`, `--loomi-text-muted`, `--loomi-text-secondary`, `--loomi-border`, `--loomi-border-strong`, `--loomi-hover`
- [x] 3.3 Definir valores DARK em `body.loomi-theme-dark .loomi-studio-wrap, body.loomi-theme-auto .loomi-studio-wrap`
- [x] 3.4 Definir valores LIGHT em `body.loomi-theme-light .loomi-studio-wrap`
- [x] 3.5 Definir valores LIGHT-via-auto em `@media (prefers-color-scheme: light) { body.loomi-theme-auto .loomi-studio-wrap { ... } }`
- [x] 3.6 Substituir TODAS as referências hex em admin.css por var() (bg, fg, border)
- [x] 3.7 Manter `#FBD603` hardcoded (accent não muda) ou definir como `--loomi-accent`

## 4. CSS variables refactor — admin-global.css (sidebar/topbar/widget)

- [x] 4.1 Adicionar mesmo bloco de variables no topo do arquivo, escopado em `body.loomi-theme-*`
- [x] 4.2 Sidebar: trocar `#000` por `var(--loomi-bg)`, items `#c4c4c4` por `var(--loomi-text-muted)`, hover `rgba(255,255,255,0.05)` por `var(--loomi-hover)`
- [x] 4.3 Active items mantêm fundo amarelo + texto preto (sem var, hardcode)
- [x] 4.4 Topbar: `#000` → `var(--loomi-bg)` ou similar
- [x] 4.5 Welcome widget: bg, text, divider, stat cards — todos via var
- [x] 4.6 Buttons primary: mantêm amarelo (não mudam com tema)
- [x] 4.7 Notices: bg amarelo claro tem que adaptar em light (talvez `#FFFBEB` já é claro mas verificar contraste)

## 5. UI do toggle na tab Dashboard

- [x] 5.1 Em `Tab_Dashboard::render()`, antes do `loomi-welcome`, adicionar um bloco "Tema do painel" com 3 radio buttons
- [x] 5.2 Layout segmented control: 3 botões com border, texto "Dark", "Light", "Auto"
- [x] 5.3 Active option destacada (bg amarelo + text preto)
- [x] 5.4 Form submit envia `loomi_theme = 'dark|light|auto'`
- [x] 5.5 CSS pro segmented control no admin.css

## 6. Testes — ThemeToggleTest

- [x] 6.1 Criar `tests/integration/ThemeToggleTest.php`
- [x] 6.2 `test_default_theme_is_dark`: vanilla → `Settings_Repository::get('loomi_theme')` === `'dark'`
- [x] 6.3 `test_each_valid_value_persisted` (dark, light, auto)
- [x] 6.4 `test_invalid_value_rejected_by_sanitizer`
- [x] 6.5 `test_admin_body_class_includes_theme`: chama filter manualmente, assert que retorna string com `loomi-theme-<value>`
- [x] 6.6 `test_tab_dashboard_renders_3_radios`: render Tab_Dashboard, assert 3 inputs radio com values certos

## 7. Sync + suite + manual

- [x] 7.1 Lint PHP em arquivos alterados
- [x] 7.2 Sync container
- [x] 7.3 Reset option (`wp option delete`) pra forçar default novo
- [x] 7.4 Rodar suite: **121 testes passando, 256 assertions** (114 prévios + 7 novos do ThemeToggleTest)
- [x] 7.5 cURL test: setar `loomi_theme = 'light'`, abrir painel → assert `<body class="... loomi-theme-light">`

## 8. README + ZIP

- [x] 8.1 Adicionar seção "Theme" no README explicando dark/light/auto + tabela de valores + comportamento
- [x] 8.2 Atualizar tabela de Recursos com linha "Theme dark/light/auto (configurável)"
- [x] 8.3 Rebuild ZIP (README excluído da distribuição — convenção WP, plugin runtime não precisa)
- [x] 8.4 Tamanho ZIP: **45.8 KB** (<50 KB ✓)
