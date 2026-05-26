## Why

A suite atual de validação do plugin Loomi Studio Setup é **ad-hoc**: shell scripts + `wp eval` + cURL rodados manualmente no docker stack. Funcionou pra validar 81/83 tasks no MVP, mas não é repetível por outra pessoa, não roda em CI, e o conhecimento de "como testar isso" vive na cabeça de quem desenvolveu. Próximo refactor (`solid-dry-refactor`) precisa de **rede de segurança automatizada** pra garantir paridade — e qualquer mudança futura idem.

A escolha já foi feita: **integration tests via WP-PHPUnit** (o framework oficial do WP para testes que carregam WP completo). Razões:
- Plugin é hook-heavy; testar sem WP loaded daria falsos positivos (mocks de WP frequentemente não refletem o comportamento real).
- WP-PHPUnit é o padrão da indústria; quem entra no projeto reconhece imediatamente.
- Bootstrap está documentado e estabilizado há anos.

Pré-requisito: change `solid-dry-refactor` aplicada antes — pós-refactor o código tem testable seams (Repository pattern, helpers puros como `Login_URLs::build()`, `Settings_Sanitizer::sanitize()`) que tornam testes muito mais simples.

## What Changes

- Adicionar `composer.json` com dev dependencies (`phpunit/phpunit ^9.6`, `wp-phpunit/wp-phpunit ^6.7`, `yoast/phpunit-polyfills ^2.0`).
- Adicionar `phpunit.xml.dist` com config padrão (test suite path, bootstrap, color output).
- Adicionar `tests/bootstrap.php` que carrega WP-PHPUnit + o plugin.
- Adicionar `tests/integration/` com **10 test classes** cobrindo cada módulo:
  - `SvgSanitizerTest` — 11 payloads (script, onload, XXE, billion-laughs, style, data:svg, foreignObject, etc.).
  - `DuplicatorTest` — duplicar page com meta + featured image + taxonomias.
  - `LoginSlugRoutingTest` — slug serve login, wp-login.php → 404, /wp-admin/ redirect.
  - `LoginUrlsTest` — `Login_URLs::build()` com várias combinações.
  - `RoleTest` — role criada, caps proibidas absent, uninstall remove + reatribui.
  - `HideMenusTest` — toggle on/off, admin x editor x cliente.
  - `WordfenceCheckTest` — `get_state()` nos 3 cenários (active/installed_inactive/absent).
  - `SettingsRepositoryTest` — defaults, cache, get_bool coercion.
  - `SettingsSanitizerTest` — feed inputs ruins, valida sanitização campo por campo.
  - `UpdaterTest` — mock `pre_http_request`, valida inject + fallback offline.
- Configurar serviço `phpunit` no `docker-compose.clean.yml` (reusa o container WP + monta vendor/).
- Adicionar `tests/run.sh` (ou Makefile target) com one-liner para rodar a suite.
- Atualizar `README.md` com seção "Running tests" + instruções de instalação local.
- Atualizar `.gitignore` para excluir `vendor/`, `composer.lock` opcional, `tests/tmp/`.

## Capabilities

### New Capabilities
- `automated-testing`: suite WP-PHPUnit cobrindo todos os módulos, bootstrap reprodutível via docker, comando único pra rodar (`tests/run.sh`), documentação no README.

### Modified Capabilities
<!-- Nenhuma capability comportamental muda. Esta change adiciona tooling. -->

## Impact

- **Arquivos novos**:
  - `composer.json`, `phpunit.xml.dist`
  - `tests/bootstrap.php`, `tests/run.sh`
  - `tests/integration/*.php` (10 test classes)
  - `tests/helpers/BaseTestCase.php` (utilitários comuns)
- **Arquivos alterados**:
  - `docker-compose.clean.yml` — adicionar volume pra `vendor/`, mount `tests/`
  - `README.md` — seção Running tests
  - `.gitignore` (criar se não existir) — `vendor/`, `tests/tmp/`
- **Tooling externo**: dependência de `composer` instalado localmente OU dentro do container Docker (já tem PHP — basta `curl` o phar). Sem mudanças no plugin propriamente dito.
- **Sem mudança no ZIP de produção**: `tests/`, `composer.json`, `phpunit.xml.dist`, `vendor/` ficam fora do ZIP (já estavam fora; agora documentar explicitamente).
- **Banco**: WP-PHPUnit usa o mesmo MySQL do stack docker, mas com schema separado (`wordpress_test`) — transactions rollback após cada test.
- **Tempo de execução**: estimado < 20 segundos para a suite completa (cada test classe ~1-3 testes; WP-PHPUnit reusa bootstrap entre tests).
- **Cobertura alvo**: linhas de código dos módulos principais. Sem alvo numérico rígido — foco em cobrir os 13 cenários de paridade do `solid-dry-refactor` + edge cases não cobertos pelos validation tests atuais.
- **Risco**: bootstrap do WP-PHPUnit pode ser frágil (versões de PHP, MySQL, WP precisam estar alinhadas) — mitigado por fixar versões no `composer.json`.
