## 1. CSS variables + reset base

- [x] 1.1 Em `assets/admin.css`, definir bloco `.loomi-studio-wrap { --loomi-black: #000000; --loomi-yellow: #FBD603; --loomi-white: #ffffff; --loomi-gray-100: #f5f5f5; --loomi-gray-300: #d0d0d0; --loomi-gray-600: #6b6b6b; --loomi-radius: 6px; --loomi-shadow: 0 1px 3px rgba(0,0,0,.08); font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }`
- [x] 1.2 Confirmar que nenhum hex hardcoded (`#000000`, `#FBD603`, `#ffffff`) aparece fora do bloco de variáveis

## 2. Loomi header customizado

- [x] 2.1 Em `Loomi_Settings_Page::render()`, adicionar `<div class="loomi-header">` antes do `<h1>` atual, contendo SVG wordmark inline + "Studio Setup" label + versão à direita
- [x] 2.2 SVG inline com `<text>` "loomi" (lowercase bold) + `<circle>` amarelo no canto (acento)
- [x] 2.3 Esconder o `<h1>` original via CSS (`.loomi-studio-wrap > .wrap > h1 { display: none }`) — manter no markup pra acessibilidade
- [x] 2.4 CSS do header: background `var(--loomi-black)`, padding 24px, white text, flexbox layout (logo à esquerda, versão à direita)

## 3. Tabs flat com underline amarelo

- [x] 3.1 CSS `.loomi-studio-wrap .nav-tab-wrapper { border-bottom: 1px solid var(--loomi-gray-300); margin: 24px 0 0; padding: 0; background: transparent; }`
- [x] 3.2 CSS `.loomi-studio-wrap .nav-tab { background: transparent; border: none; border-bottom: 3px solid transparent; color: var(--loomi-gray-600); font-weight: 500; margin: 0 4px -1px 0; padding: 12px 16px; box-shadow: none; }`
- [x] 3.3 CSS `.loomi-studio-wrap .nav-tab:hover { color: var(--loomi-black); background: transparent; }`
- [x] 3.4 CSS `.loomi-studio-wrap .nav-tab.nav-tab-active { color: var(--loomi-black); border-bottom-color: var(--loomi-yellow); background: transparent; }`

## 4. Botão primário (Salvar)

- [x] 4.1 CSS `.loomi-studio-wrap .button-primary { background: var(--loomi-black); border: 2px solid var(--loomi-black); color: var(--loomi-white); box-shadow: none; text-shadow: none; padding: 8px 24px; border-radius: var(--loomi-radius); transition: background .15s, color .15s; font-weight: 600; }`
- [x] 4.2 CSS `.loomi-studio-wrap .button-primary:hover, .loomi-studio-wrap .button-primary:focus { background: var(--loomi-yellow); color: var(--loomi-black); border-color: var(--loomi-black); outline: none; }`

## 5. Checkboxes customizados

- [x] 5.1 CSS reset `.loomi-studio-wrap input[type="checkbox"] { appearance: none; -webkit-appearance: none; width: 18px; height: 18px; border: 2px solid var(--loomi-black); border-radius: 3px; background: var(--loomi-white); vertical-align: middle; cursor: pointer; position: relative; margin: 0 6px 0 0; }`
- [x] 5.2 CSS checked `.loomi-studio-wrap input[type="checkbox"]:checked { background: var(--loomi-yellow); border-color: var(--loomi-black); }`
- [x] 5.3 CSS checkmark via pseudo `.loomi-studio-wrap input[type="checkbox"]:checked::after { content: ""; position: absolute; left: 4px; top: 0; width: 5px; height: 10px; border: solid var(--loomi-black); border-width: 0 2px 2px 0; transform: rotate(45deg); }`
- [x] 5.4 CSS focus `.loomi-studio-wrap input[type="checkbox"]:focus, .loomi-studio-wrap input[type="checkbox"]:focus-visible { outline: 2px solid var(--loomi-yellow); outline-offset: 2px; }`

## 6. Notices scopados

- [x] 6.1 CSS `.loomi-studio-wrap .notice { border-left-width: 4px; background: var(--loomi-gray-100); box-shadow: var(--loomi-shadow); margin-bottom: 16px; }`
- [x] 6.2 CSS `.loomi-studio-wrap .notice-info, .loomi-studio-wrap .notice.notice-info.inline { border-left-color: var(--loomi-yellow); }`
- [x] 6.3 Manter `notice-error` (vermelho semântico) e `notice-success` (verde semântico) sem override — só info muda pra amarelo Loomi

## 7. Inputs (text, color, number)

- [x] 7.1 CSS `.loomi-studio-wrap input[type="text"], .loomi-studio-wrap input[type="number"], .loomi-studio-wrap input[type="email"], .loomi-studio-wrap input[type="url"], .loomi-studio-wrap input.loomi-color-field { border: 1px solid var(--loomi-gray-300); border-radius: var(--loomi-radius); padding: 8px 12px; box-shadow: none; font-family: inherit; transition: border-color .15s, outline .15s; }`
- [x] 7.2 CSS focus `.loomi-studio-wrap input[type="text"]:focus, ...rest { border-color: var(--loomi-black); outline: 2px solid var(--loomi-yellow); outline-offset: -1px; box-shadow: none; }`
- [x] 7.3 Tipografia geral `.loomi-studio-wrap .form-table { font-size: 14px; } .loomi-studio-wrap .form-table th { font-weight: 600; color: var(--loomi-black); padding-top: 18px; padding-bottom: 18px; }`

## 8. Preset "Aplicar branding Loomi"

- [x] 8.1 Em `Tab_Login::render()`, adicionar `<button type="button" id="loomi-apply-brand" class="button">Aplicar branding Loomi</button>` abaixo do color picker
- [x] 8.2 No script JS existente em `Settings_Page::render()`, adicionar handler: `$('#loomi-apply-brand').on('click', function(e){ e.preventDefault(); $('#loomi-bg-color').val('#000000').trigger('change'); if (window.wpColorPicker) $('#loomi-bg-color').wpColorPicker('color', '#000000'); });`

## 9. Testes — BrandUITest

- [x] 9.1 Criar `tests/integration/BrandUITest.php`
- [x] 9.2 `test_loomi_header_renders_in_settings_page`: simular render do Settings_Page, assert HTML contém `<div class="loomi-header">` + `<svg class="loomi-logo">` + "Studio Setup"
- [x] 9.3 `test_admin_css_enqueued_only_on_plugin_page`: chamar `Loomi_Settings_Page::enqueue_assets('settings_page_loomi-studio-setup')` → assert `wp_style_is('loomi-studio-admin', 'enqueued')` é true; depois `enqueue_assets('dashboard')` → assert false
- [x] 9.4 `test_css_has_brand_variables`: ler `assets/admin.css` e assert que contém `--loomi-black: #000000` e `--loomi-yellow: #FBD603`
- [x] 9.5 `test_apply_brand_button_present_in_login_tab`: render Tab_Login, assert HTML contém `id="loomi-apply-brand"`

## 10. Sync + lint + suite

- [x] 10.1 Lint PHP em arquivos alterados (Settings_Page, Tab_Login)
- [x] 10.2 Sync `assets/admin.css` + arquivos PHP pro container docker
- [x] 10.3 Sync `BrandUITest.php` pro container
- [x] 10.4 Rodar `bash tests/run.sh` — esperado 108 anteriores + 4 novos = **112 testes passando**

## 11. Validação visual (cURL + manual)

- [x] 11.1 cURL `http://localhost:8089/wp-admin/options-general.php?page=loomi-studio-setup` (com cookie de admin) → assert HTML contém `loomi-header`, `loomi-logo`, `Studio Setup`
- [x] 11.2 cURL pra outras admin pages → assert HTML NÃO contém `loomi-header` (scope OK)
- [x] 11.3 (Manual, opcional) Abrir no browser e validar visualmente: tabs com underline amarelo, botão preto com hover amarelo, checkboxes amarelos quando marcados
- [x] 11.4 (Manual, opcional) Testar acessibilidade: tab navigation (focus visível em cada elemento), screen reader anuncia checkboxes corretamente

## 12. README + ZIP

- [x] 12.1 Adicionar seção "🎨 UI" no README ou atualizar Configuração com nota sobre brand Loomi (preto + amarelo, system fonts, scope estrito)
- [x] 12.2 Atualizar tabela de Recursos com nova linha "UI brand Loomi (preto + amarelo, scoped)"
- [x] 12.3 Rebuild ZIP de produção
- [x] 12.4 Confirmar tamanho ZIP <45 KB (admin.css cresce ~3 KB)
