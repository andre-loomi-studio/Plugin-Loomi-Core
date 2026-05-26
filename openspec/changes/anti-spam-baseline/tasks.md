## 1. Settings_Repository — 5 defaults novos

- [x] 1.1 Adicionar 5 chaves em `Settings_Repository::defaults()` (todos `true`):
  - `anti_spam_enabled`
  - `anti_spam_honeypot`
  - `anti_spam_time_check`
  - `anti_spam_comment_lockdown`
  - `anti_spam_akismet_autoconfig`
- [x] 1.2 Adicionar as 5 chaves em `Settings_Repository::BOOL_FIELDS` (coerção genérica de bool)

## 2. Módulo Loomi_Anti_Spam

- [x] 2.1 Criar `includes/modules/class-loomi-anti-spam.php` implementando `Loomi_Module`
- [x] 2.2 `register()`: early return se `anti_spam_enabled === false`; senão registra os sub-features individualmente
- [x] 2.3 Sub-feature **Honeypot**:
  - Hook `login_form`, `register_form`, `comment_form_after_fields` → emite HTML do campo
  - Filter `authenticate` (priority 21) → se `$_POST['loomi_hp']` non-empty, retorna `WP_Error('honeypot', 'Detectado bot.')`
  - Filter `pre_comment_approved` → se honeypot preenchido, retorna `'spam'`
  - Filter `registration_errors` → adiciona erro se honeypot preenchido
- [x] 2.4 Sub-feature **Time check**:
  - Mesmos hooks de render injetam `<input type="hidden" name="loomi_t" value="<?php echo time(); ?>" />`
  - Mesmos filters validam: `time() - intval($_POST['loomi_t']) < 2` → rejeita
  - Faltando `loomi_t` no submit → tratar como bot (rejeita)
- [x] 2.5 Sub-feature **Comment lockdown**:
  - Em activation hook (chamado pelo plugin principal): `update_option('default_pingback_flag', 0)`, `update_option('default_ping_status', 'closed')`, `update_option('comment_moderation', 1)`, `update_option('comment_whitelist', 0)`
  - Filter `xmlrpc_methods` removendo `pingback.ping` e `pingback.extensions.getPingbacks`
  - Tudo só roda se `anti_spam_comment_lockdown` true
- [x] 2.6 Sub-feature **Akismet autoconfig**:
  - Action `admin_init` priority 99
  - Se `defined('LOOMI_AKISMET_KEY')` && `LOOMI_AKISMET_KEY` truthy && `is_plugin_active('akismet/akismet.php')`:
    - Se `get_option('wordpress_api_key') !== LOOMI_AKISMET_KEY`, `update_option('wordpress_api_key', LOOMI_AKISMET_KEY)`
- [x] 2.7 Helper privado `render_hidden_fields()` que junta honeypot HTML + timestamp HTML — reusado pelos 3 hooks de render

## 3. Tab_Anti_Spam

- [x] 3.1 Criar `includes/settings/tabs/class-tab-anti-spam.php` implementing `Loomi_Settings_Tab`
- [x] 3.2 `slug()` retorna `'anti-spam'`; `label()` retorna `__('Anti-Spam', ...)`
- [x] 3.3 `render()` mostra 5 checkboxes com descrições, agrupados visualmente
- [x] 3.4 Disclaimer no topo: "Proteção zero-config contra bots. Não exige reCAPTCHA, Akismet ou credencial externa. Combinado com Wordfence cobre >95% do spam genérico."
- [x] 3.5 Indicador visual do status do Akismet: se constante definida e plugin ativo, mostra "✓ Akismet configurado com a key central da Loomi"; senão, mostra texto orientando a definir `LOOMI_AKISMET_KEY` no `wp-config.php`

## 4. Bootstrap + Settings_Page

- [x] 4.1 `loomi-studio-setup.php`: `require_once` do novo módulo + da nova tab
- [x] 4.2 Adicionar `Loomi_Anti_Spam::class` ao array `$modules` (entre `Loomi_Duplicate` e `Loomi_Wordfence_Check`)
- [x] 4.3 Em `Loomi_Settings_Page::tabs()`, adicionar `new Tab_Anti_Spam()` como 5º item (após `Tab_Client_Role`)

## 5. Tests — AntiSpamTest

- [x] 5.1 Criar `tests/integration/AntiSpamTest.php` extending `Loomi_TestCase`
- [x] 5.2 `test_all_defaults_true_on_fresh_install`
- [x] 5.3 `test_master_switch_disables_all_subfeatures`
- [x] 5.4 `test_honeypot_rendered_in_login_form`
- [x] 5.5 `test_honeypot_rendered_in_comment_form`
- [x] 5.6 `test_login_rejected_when_honeypot_filled`
- [x] 5.7 `test_comment_marked_spam_when_honeypot_filled`
- [x] 5.8 `test_time_check_rejects_instant_submission`
- [x] 5.9 `test_time_check_passes_after_2s_delay`
- [x] 5.10 `test_time_check_treats_missing_timestamp_as_bot`
- [x] 5.11 `test_comment_lockdown_sets_options`
- [x] 5.12 `test_xmlrpc_pingback_removed_when_lockdown_on`
- [x] 5.13 `test_akismet_autoconfig_sets_option_when_constant_defined`
- [x] 5.14 `test_akismet_autoconfig_skipped_when_constant_undefined`
- [x] 5.15 `test_tab_renders_5_checkboxes`

## 6. Tests — Atualizar existentes

- [x] 6.1 Em `SettingsRepositoryTest::test_defaults_returned_when_option_missing`, adicionar asserts para os 5 novos defaults
- [x] 6.2 Em `HideMenusTest::test_tab_groups_core_and_cpts_separately` etc., conferir nada quebrou (5 tabs agora)

## 7. Sync + lint + suite

- [x] 7.1 Lint PHP em 2 arquivos novos + 3 alterados
- [x] 7.2 Sync pro container via `docker cp`
- [x] 7.3 Reset option pra forçar novos defaults
- [x] 7.4 `bash tests/run.sh` — esperado 92 anteriores + ~14 novos = ~106 testes passando

## 8. README + ZIP

- [x] 8.1 Adicionar seção "Anti-Spam" no README com tabela das 4 técnicas + nota explicando que não cobre contact form plugins
- [x] 8.2 Atualizar a tabela de "Recursos" do topo do README com nova linha "Anti-spam baseline (honeypot + time check + lockdown + Akismet autoconfig)"
- [x] 8.3 Atualizar seção "Security model" com 5ª linha "Anti-spam zero-config → bloqueia bots dumb sem CAPTCHA"
- [x] 8.4 Documentar constante `LOOMI_AKISMET_KEY` (uso + exemplo em wp-config.php)
- [x] 8.5 Rebuild ZIP de produção
- [x] 8.6 Confirmar tamanho ZIP <40 KB

## 9. Validação manual cURL no docker stack

- [x] 9.1 POST `/studio-access/` com `loomi_hp=spam` → falha autenticação (mesmo com creds válidas)
- [x] 9.2 POST `/studio-access/` com `loomi_t=<time()>` (delta 0s) → falha
- [x] 9.3 POST de comment com honeypot preenchido → comment marcado como spam (via DB check)
- [x] 9.4 Confirmar `default_pingback_flag = 0` na option DB
