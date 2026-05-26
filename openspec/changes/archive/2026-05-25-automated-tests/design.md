## Context

Hoje a validação do plugin é manual via shell/wp-cli/cURL — sem checkpoint automatizável. Esta change introduz uma suite de testes de integração usando **WP-PHPUnit**, o framework oficial mantido pelo time core do WordPress.

Pré-requisito: `solid-dry-refactor` aplicado. Pós-refactor o código tem `Settings_Repository`, `Settings_Sanitizer`, `Login_URLs` — todos testáveis isoladamente. Sem o refactor, testes precisariam mockar a god-class `Loomi_Settings` em vez de exercitar seus pedaços puros.

Stack alvo:
- PHPUnit 9.6 (compat WP-PHPUnit + PHP 7.4-8.3)
- WP-PHPUnit 6.7 (cobre WP 6.0+)
- Yoast PHPUnit Polyfills (smoothie pra rodar em ambas 9.x e 10.x)
- MySQL 8 (reusa o container `loomi-clean-db`, schema separado `wordpress_test`)
- Composer instalado local OU baixado via container (phar)

Existem alternativas (WP_Mock, Brain Monkey, Pest com plugins) — descartadas em favor de WP-PHPUnit pelas razões já elencadas na proposal (hook-heavy + framework oficial + reconhecimento da comunidade).

## Goals / Non-Goals

**Goals:**
- Suite mínima cobrindo cada módulo do plugin (10 test classes), com 3-8 testes cada.
- Bootstrap reprodutível: `git clone` + `composer install` + `tests/run.sh` = suite rodando.
- Tempo total < 20s para encorajar execução frequente.
- Cobrir os 13 cenários de paridade do `solid-dry-refactor` (= rede de segurança automatizada pro refactor).
- Documentação no README sobre como rodar.

**Non-Goals:**
- Pixel-perfect visual testing (Playwright/Cypress) — fora de escopo, outra change se quiser.
- CI pipeline (GitHub Actions) — fora desta change; vai numa change futura `tests-ci` quando a suite local estabilizar.
- 100% de coverage métrica — alvo qualitativo (cobrir comportamento principal + edge cases conhecidos).
- Mutation testing, property-based testing — overkill pra plugin deste tamanho.
- Reescrever `test-svg-sanitizer.php` legado — vai ser substituído por `SvgSanitizerTest`, deletado depois.

## Decisions

### 1. WP-PHPUnit como framework, não WP_Mock

WP_Mock mockaria funções WP individualmente — adicionaria fricção (cada `add_action`, `get_option`, etc. precisa ser mockado explicitamente) e tem propensão a falsos positivos.

WP-PHPUnit carrega WP de verdade em mem mode, com DB transaction-rollback entre tests. Mais lento mas testa comportamento real (hooks fire de verdade, options persistem, queries reais).

**Alternativa considerada:** Pest com plugins de WP. Rejeitada — Pest é genial para apps PHP modernas, mas a documentação e adoção em WP plugin land ainda é menor que PHPUnit clássico.

### 2. Estrutura `tests/`

```
tests/
├── bootstrap.php              # carrega WP-PHPUnit + plugin
├── run.sh                     # one-liner para rodar
├── helpers/
│   └── BaseTestCase.php       # WP_UnitTestCase com utilitários do plugin
└── integration/
    ├── SvgSanitizerTest.php
    ├── DuplicatorTest.php
    ├── LoginSlugRoutingTest.php
    ├── LoginUrlsTest.php
    ├── RoleTest.php
    ├── HideMenusTest.php
    ├── WordfenceCheckTest.php
    ├── SettingsRepositoryTest.php
    ├── SettingsSanitizerTest.php
    └── UpdaterTest.php
```

10 test classes, ~50 testes totais. Todos sob `tests/integration/` porque todos exigem WP loaded (mesmo os "unit-like" pra `Login_URLs::build` precisam de `home_url()`, que é WP).

### 3. `tests/bootstrap.php` — padrão WP-PHPUnit + plugin load

```php
<?php
$wp_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';

require_once $wp_tests_dir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', function () {
    require dirname(__DIR__) . '/loomi-studio-setup.php';
});

require $wp_tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/helpers/BaseTestCase.php';
```

Bootstrap mínimo. O hook `muplugins_loaded` é onde WP-PHPUnit aceita carregar plugins antes do WP terminar de inicializar.

### 4. `BaseTestCase` — utilitários comuns

```php
abstract class Loomi_TestCase extends WP_UnitTestCase {
    protected function setUp() : void {
        parent::setUp();
        Settings_Repository::clear_cache();
        delete_option(Plugin::OPTION_KEY);
    }

    protected function set_settings(array $overrides) : void {
        $defaults = Settings_Repository::defaults();
        update_option(Plugin::OPTION_KEY, array_merge($defaults, $overrides));
        Settings_Repository::clear_cache();
    }

    protected function login_as(string $role) : int {
        $user_id = $this->factory->user->create(['role' => $role]);
        wp_set_current_user($user_id);
        return $user_id;
    }
}
```

Mantém cada test class focada no que está testando, sem repetir setup de option/user/cache.

### 5. Test database setup

WP-PHPUnit precisa de um schema MySQL separado pra não corromper o site de dev. Solução:

- Container Docker `loomi-clean-db` (MySQL 8.0) já tem o usuário `wordpress`.
- Bootstrap script (`tests/setup-db.sh`) cria schema `wordpress_test` com mesmo usuário.
- `wp-tests-config.php` aponta DB_NAME para esse schema.
- WP-PHPUnit usa transactions: cada teste roda dentro de um BEGIN/ROLLBACK, então o DB sempre fica limpo entre tests.

### 6. Como mocar `wp_remote_get` no `UpdaterTest`

Em vez de injetar HTTP client (que exigiria refatorar Updater), usar filter `pre_http_request` (padrão WP) — exatamente como já fizemos nos testes manuais:

```php
public function test_inject_update_with_mock_endpoint() : void {
    add_filter('pre_http_request', function ($pre, $args, $url) {
        if (str_contains($url, 'updates.loomi.studio')) {
            return ['response' => ['code' => 200], 'body' => json_encode([
                'version' => '9.9.9',
                'download_url' => 'https://updates.loomi.studio/x.zip',
                'sections' => ['changelog' => '...']
            ])];
        }
        return $pre;
    }, 10, 3);

    $transient = (object) ['response' => []];
    $result = Loomi_Updater::inject_update($transient);
    self::assertArrayHasKey('loomi-studio-setup/loomi-studio-setup.php', $result->response);
    self::assertSame('9.9.9', $result->response['loomi-studio-setup/loomi-studio-setup.php']->new_version);
}
```

Sem DI container, sem injection — só usar o que o WP oferece.

### 7. Como testar SVG sanitizer (file upload prefilter)

SVG sanitizer roda em `wp_handle_upload_prefilter` — pode ser exercitado direto:

```php
public function test_sanitizes_script_tag() : void {
    $tmp = tempnam(sys_get_temp_dir(), 'svg');
    file_put_contents($tmp, '<svg xmlns="..."><script>alert(1)</script></svg>');
    
    $file = ['name' => 'test.svg', 'tmp_name' => $tmp, 'type' => 'image/svg+xml', 'error' => 0, 'size' => 100];
    $result = Loomi_SVG::sanitize_on_upload($file);
    
    self::assertEmpty($result['error']);
    self::assertStringNotContainsString('<script>', file_get_contents($tmp));
    unlink($tmp);
}
```

Direto ao ponto. Sem mock de filesystem.

### 8. Como testar HTTP gates (login slug)

WP-PHPUnit oferece `$this->go_to($url)` que simula uma request. Combinado com `is_user_logged_in()` checks, dá pra exercitar o gate sem cURL.

Para casos onde precisamos validar HTTP status code, usar `WP_UnitTestCase`'s setup de `$_SERVER` direto e capturar saída via `ob_start`/`wp_die_handler` filter.

### 9. Como rodar — `tests/run.sh`

```bash
#!/usr/bin/env bash
set -e

# Garante DB de teste criado
docker exec loomi-clean-db mysql -uroot -prootpass -e "CREATE DATABASE IF NOT EXISTS wordpress_test;"

# Garante composer install ran
[ -d vendor ] || composer install

# Roda phpunit dentro do container WP (que tem PHP)
docker exec --user www-data loomi-clean-wp \
    php /var/www/html/wp-content/plugins/loomi-studio-setup/vendor/bin/phpunit \
    -c /var/www/html/wp-content/plugins/loomi-studio-setup/phpunit.xml.dist
```

One-liner: `bash tests/run.sh`. Saída colorida do PHPUnit padrão.

### 10. Composer config

```json
{
  "name": "loomi/loomi-studio-setup",
  "type": "wordpress-plugin",
  "require": {
    "php": ">=7.4"
  },
  "require-dev": {
    "phpunit/phpunit": "^9.6",
    "wp-phpunit/wp-phpunit": "^6.7",
    "yoast/phpunit-polyfills": "^2.0"
  }
}
```

Fixar `phpunit` 9.6 (não 10) pra compatibilidade WP-PHPUnit + PHP 7.4. Sem `autoload` (plugin usa `require_once` manual).

## Risks / Trade-offs

- **[Risco] WP-PHPUnit bootstrap brittle entre versões** → Mitigação: fixar `wp-phpunit/wp-phpunit ^6.7` (mesma minor do WP do stack); documentar troubleshooting comum no README (constantes em `wp-tests-config.php`, DB credentials).
- **[Risco] PHPUnit 9.x vs 10.x incompatibilidades de API** → Mitigação: usar `yoast/phpunit-polyfills` que abstrai as diferenças (`setUp()` vs `setUp(): void` etc.); fixar 9.6 nesta change.
- **[Risco] Testes flaky por estado compartilhado** → Mitigação: `BaseTestCase::setUp()` SEMPRE limpa cache + option; WP-PHPUnit transaction rollback handle o DB.
- **[Risco] Composer dependency hell em hosts gerenciados** → Mitigação: `vendor/` fica fora do ZIP de produção; dev-only deps; site cliente nunca toca composer.
- **[Risco] Tempo de execução crescer e desincentivar uso** → Mitigação: `phpunit --filter` permite rodar uma classe específica; orçamento <20s pra suite inteira atual.
- **[Trade-off] Mais arquivos no repo** (~13 arquivos novos) → Aceito; tooling tem valor proporcional à frequência de uso.
- **[Trade-off] Curva de aprendizado pra quem não conhece WP-PHPUnit** → Aceito; documentação no README cobre 80% dos casos. Restantes (configuração avançada) leem docs oficiais do WP-PHPUnit.

## Migration Plan

1. Aplicar `solid-dry-refactor` primeiro (pré-requisito).
2. Adicionar `composer.json` + rodar `composer install` localmente (cria `vendor/`).
3. Criar bootstrap + helpers + uma test class (`SvgSanitizerTest` é a mais simples) para validar setup.
4. Configurar `wordpress_test` database.
5. Rodar `tests/run.sh` e iterar até passar (1ª execução costuma falhar por config de DB / paths).
6. Adicionar as outras 9 test classes incrementalmente.
7. Documentar no README.
8. Adicionar `.gitignore` entries.

Rollback: descartar `tests/`, `composer.json`, `phpunit.xml.dist`. Plugin continua funcionando.

## Open Questions

- Devemos manter `test-svg-sanitizer.php` (script standalone na raiz do plugin) ou removê-lo após criar `SvgSanitizerTest`? Recomendação: remover, pois `SvgSanitizerTest` cobre os mesmos casos com melhor formato.
- Vale adicionar `phpcs` (WordPress Coding Standards) junto? Não nesta change — fora de escopo. Outra change futura `code-style-enforcement` se valer.
- Coverage report (HTML / Clover XML)? Não obrigatório, mas trivial habilitar: `phpunit --coverage-html coverage/`. Adicionar como instrução opcional no README.
