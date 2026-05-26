## 1. Settings_Repository

- [x] 1.1 Adicionar `'hide_admin_endpoint' => true` em `Settings_Repository::defaults()`
- [x] 1.2 Adicionar `'hide_admin_endpoint'` em `Settings_Repository::BOOL_FIELDS`

## 2. Login module — novo gate

- [x] 2.1 Em `Loomi_Login::register()`, dentro do bloco `if ( Settings_Repository::get_bool('login_slug_enabled') )`, registrar `add_action('auth_redirect', [__CLASS__, 'gate_admin_endpoint'], 0)`
- [x] 2.2 Implementar `Loomi_Login::gate_admin_endpoint()` que:
  - Retorna early se `LOOMI_STUDIO_DISABLE_HARDENING` definida e truthy
  - Retorna early se `! Settings_Repository::get_bool('hide_admin_endpoint')`
  - Retorna early se `is_user_logged_in()`
  - Retorna early se `wp_doing_ajax()` ou `wp_doing_cron()` ou request é REST (`defined('REST_REQUEST')`)
  - Caso contrário: chama `self::render_not_found()` (helper já existe)
- [x] 2.3 Manter os 5 filtros `login_url`/`logout_url`/`logout_redirect`/`lostpassword_url`/`register_url` como estão (eles servem links visíveis para usuários logados — não vazam)

## 3. Settings_Sanitizer

- [x] 3.1 Confirmar que sanitizer já trata todos `BOOL_FIELDS` genericamente (sem ajuste necessário)

## 4. Tab_Slug — toggle + disclaimer

- [x] 4.1 Em `Tab_Slug::render()`, abaixo do toggle "Ativar slug customizada" e do campo de slug, adicionar nova linha de form-table com toggle `hide_admin_endpoint`
- [x] 4.2 Texto do label: "Esconder /wp-admin/ também (recomendado)"
- [x] 4.3 `<p class="description">` abaixo explicando: "Quando ativo, requests não autenticadas a /wp-admin/ retornam 404 em vez de redirecionar para a slug. Isso evita que scanners descubram a slug inspecionando o header Location. Trade-off: admin precisa lembrar do slug — digitar /wp-admin/ no browser também levará a 404."

## 5. Novo testes — AdminEndpointSecurityTest

- [x] 5.1 Criar `tests/integration/AdminEndpointSecurityTest.php` extending `Loomi_TestCase`
- [x] 5.2 `test_default_is_true`: vanilla → `Settings_Repository::get_bool('hide_admin_endpoint')` retorna true
- [x] 5.3 `test_gate_skipped_when_disabled`: toggle false, chama `gate_admin_endpoint()`, nada acontece (sem `exit`)
- [x] 5.4 `test_gate_skipped_when_logged_in`: toggle true, `wp_set_current_user(admin)`, chama gate, nada acontece
- [x] 5.5 `test_gate_skipped_for_ajax`: `define('DOING_AJAX', true)`, chama gate, nada acontece
- [x] 5.6 `test_gate_skipped_for_rest`: `define('REST_REQUEST', true)`, chama gate, nada acontece
- [x] 5.7 `test_constant_override_disables_hardening`: `define('LOOMI_STUDIO_DISABLE_HARDENING', true)` + toggle true + anonymous → gate retorna early
- [x] 5.8 `test_gate_triggers_404_for_anonymous`: toggle true + sem login + sem AJAX/REST → render_not_found dispara (verifica via output buffering ou status_header)

## 6. Atualizar testes existentes

- [x] 6.1 Em `SettingsRepositoryTest`, novo teste `test_hide_admin_endpoint_default_is_true`
- [x] 6.2 Em `SettingsRepositoryTest::test_defaults_returned_when_option_missing`, adicionar assert pra `hide_admin_endpoint`
- [x] 6.3 Em `LoginSlugRoutingTest`, adicionar `test_gate_admin_endpoint_method_exists` confirmando method existe
- [x] 6.4 Em `Tab_Slug`/`HideMenusTest` ou novo arquivo, validar que tab renderiza o disclaimer

## 7. Validação HTTP-level via cURL

- [x] 7.1 No docker stack, com toggle on: `curl -I http://localhost:8089/wp-admin/` → 404, **sem `Location:` apontando pra /studio-access/**
- [x] 7.2 Mesmo curl com toggle off: 302 com Location (comportamento padrão restaurado)
- [x] 7.3 cURL com cookie de admin logado: 200 (não bloqueia user válido)
- [x] 7.4 `curl /wp-json/wp/v2/posts`: response normal (REST não afetado)

## 8. Sync, lint, run suite

- [x] 8.1 Lint PHP em arquivos modificados
- [x] 8.2 Sync pro container
- [x] 8.3 Reset option pra forçar novo default
- [x] 8.4 `bash tests/run.sh` — esperado 83 anteriores + ~9 novos = ~92 testes passando

## 9. README + ZIP

- [x] 9.1 Adicionar seção "Security model" no README com tabela de defesa em camadas (slug + admin hardening + Wordfence + capabilities)
- [x] 9.2 Atualizar tabela de Segurança com nova linha "Admin endpoint leak via Location header → /wp-admin/ → 404 quando toggle on"
- [x] 9.3 Documentar a constante `LOOMI_STUDIO_DISABLE_HARDENING` na seção "Desenvolvimento" (escape hatch)
- [x] 9.4 Rebuild ZIP de produção
- [x] 9.5 Confirmar ZIP tem `class-loomi-login.php` atualizado
