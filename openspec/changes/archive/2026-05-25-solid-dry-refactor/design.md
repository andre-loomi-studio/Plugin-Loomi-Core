## Context

O plugin Loomi Studio Setup foi desenvolvido em sprint rápido para entregar 8 capabilities. A estrutura atual:

```
includes/
├── class-loomi-settings.php       ← 340 linhas, god-class
├── class-loomi-updater.php
└── modules/
    ├── class-loomi-svg.php
    ├── class-loomi-login.php       ← 5 métodos quase idênticos de rewrite
    ├── class-loomi-admin-menu.php
    ├── class-loomi-role.php
    ├── class-loomi-duplicate.php
    └── class-loomi-wordfence-check.php
```

Estado funcional: **validado** (81/83 tasks no docker stack). Estado interno: acoplamento alto em `Settings`, duplicação em `Login`, padrão de bootstrap manual sem contrato.

Restrições:
- WP ≥ 6.0, PHP ≥ 7.4 (sem composer, sem autoload PSR-4 baseado em namespaces — manter `require_once`).
- Convenções WP: hooks via static methods, classes prefixadas `Loomi_*` (compatibilidade externa).
- Sem dependências runtime novas.

## Goals / Non-Goals

**Goals:**
- Cada classe com responsabilidade única e clara (SRP).
- Zero duplicação evidente em loops de URL rewrites e tab rendering (DRY).
- Contrato `Module` para módulos futuros nascerem alinhados (OCP).
- Constantes centralizadas (substituir magic strings).
- Mesma área de superfície externa: hooks, filtros, option key, ZIP layout, comportamento de runtime.

**Non-Goals:**
- Não adotar namespaces PHP (`namespace Loomi\Studio`) — adicionaria fricção de migração e exigiria composer ou autoload manual com mapping.
- Não introduzir DI container (ex.: `php-di`, `pimple`) — WP é hook-driven; uma static container global resolveria, mas não vale o ceremony.
- Não introduzir testes unitários nesta change — vale como follow-up; o refactor habilita, mas não cria a suite.
- Não fazer "Clean Architecture" (Domain / Application / Infrastructure layers) — não se encaixa em plugin WP, que é por natureza um adapter para o framework.
- Não migrar para PSR-12 / phpcs WP automaticamente — fora de escopo desta change.

## Decisions

### 1. Estrutura de pastas: separar contratos, value objects e implementações

```
includes/
├── class-plugin.php                    # constantes globais + bootstrap
├── contracts/
│   ├── interface-module.php           # contract pra todos os módulos
│   └── interface-settings-tab.php     # contract pra cada tab do painel
├── support/
│   ├── class-settings-repository.php   # read/write/cache de option
│   ├── class-settings-sanitizer.php    # sanitização Settings API
│   └── class-login-urls.php           # construção de URLs slug-based
├── settings/
│   ├── class-settings-page.php        # registra página, orquestra tabs
│   └── tabs/
│       ├── class-tab-login.php
│       ├── class-tab-slug.php
│       ├── class-tab-hide-menus.php
│       └── class-tab-client-role.php
└── modules/
    ├── class-loomi-svg.php             # implementam Module
    ├── class-loomi-login.php
    ├── class-loomi-admin-menu.php
    ├── class-loomi-role.php
    ├── class-loomi-duplicate.php
    └── class-loomi-wordfence-check.php
```

**Por quê?** Pastas refletem camada conceitual: contracts (interfaces), support (helpers), settings (admin UI), modules (hooks WP). Localidade — pra mudar um tab, vai-se em `settings/tabs/`. Pra entender o bootstrap, `Plugin` é o ponto único.

**Alternativa considerada:** namespace PSR-4 `Loomi\Studio\Settings\Tabs\Login`. Rejeitado: exige composer ou autoload manual com mapping; ganho marginal num plugin com ~15 arquivos.

### 2. Interface `Module` — contrato mínimo

```php
interface Loomi_Module {
    /**
     * Register hooks/filters. Called once during bootstrap (plugins_loaded).
     */
    public static function register() : void;
}
```

Cada módulo (`Loomi_SVG`, `Loomi_Login`, etc.) implementa. Bootstrap:

```php
$modules = [
    Loomi_SVG::class,
    Loomi_Login::class,
    Loomi_Admin_Menu::class,
    Loomi_Role::class,
    Loomi_Duplicate::class,
    Loomi_Wordfence_Check::class,
    Loomi_Settings_Page::class,
];
foreach ( $modules as $module ) {
    $module::register();
}
```

Vantagem: adicionar módulo novo = (1) criar arquivo implementando `Module`, (2) require_once, (3) adicionar à lista. Sem espalhar lógica de init.

**Alternativa considerada:** auto-discovery via `glob()` de `modules/*.php`. Rejeitada — ordem de carregamento importa (settings tem que carregar antes das tabs lerem); explícito > implícito aqui.

### 3. Split de `Loomi_Settings` (SRP)

| Antes | Depois |
|---|---|
| `Loomi_Settings::defaults()` | `Settings_Repository::defaults()` |
| `Loomi_Settings::all()` + cache | `Settings_Repository::all()` + cache |
| `Loomi_Settings::get($k)` | `Settings_Repository::get($k)` (mantém API) |
| `Loomi_Settings::sanitize($input)` | `Settings_Sanitizer::sanitize($input)` |
| `Loomi_Settings::register_page()` | `Settings_Page::register()` |
| `Loomi_Settings::render_page()` | `Settings_Page::render()` |
| `Loomi_Settings::render_login_tab()` etc | `Tab_Login::render($settings)` etc |
| `Loomi_Settings::enqueue_assets()` | `Settings_Page::enqueue_assets()` |

**Compatibilidade:** mantém `Loomi_Settings::get()` como alias estático para `Settings_Repository::get()` durante a transição. Após validação, módulos passam a chamar `Settings_Repository::get()` direto. Constantes (`OPTION_KEY`, `HIDEABLE_MENUS`, etc.) migram para `Plugin`.

**Por quê dividir tabs?** Cada tab tem ~30-60 linhas de HTML+PHP. Hoje os 4 estão concatenados num switch no `render_tab()`. Separar em classes com `Tab::slug()`, `Tab::label()`, `Tab::render($settings)` torna trivial adicionar/remover/reordenar tabs.

### 4. `Login_URLs` helper — DRY no Login

Antes (5 métodos quase idênticos):
```php
rewrite_login_url($url, $redirect, $force_reauth)
rewrite_logout_url($url, $redirect)
rewrite_logout_redirect($redirect_to, ...)
rewrite_lostpassword_url($url, $redirect)
rewrite_register_url($url)
```

Depois:
```php
class Login_URLs {
    public static function build( string $action = '', array $extra = [] ) : string {
        $url = home_url( '/' . trim( Settings_Repository::get( 'login_slug' ), '/' ) . '/' );
        if ( $action !== '' ) $url = add_query_arg( 'action', $action, $url );
        foreach ( $extra as $k => $v ) {
            if ( $v !== null && $v !== '' ) $url = add_query_arg( $k, rawurlencode( (string) $v ), $url );
        }
        return $url;
    }
}
```

Os 5 filtros no `Loomi_Login` viram:
```php
public static function filter_login_url( $url, $redirect, $force_reauth ) {
    return Login_URLs::build( '', [ 'redirect_to' => $redirect, 'reauth' => $force_reauth ? '1' : null ] );
}
public static function filter_logout_url( $url, $redirect ) {
    return wp_nonce_url( Login_URLs::build( 'logout', [ 'redirect_to' => $redirect ] ), 'log-out' );
}
// ... 3 mais, todos thin
```

Cada filtro tem 1-3 linhas. Lógica de URL building centralizada.

### 5. Classe `Plugin` — constantes centralizadas

```php
final class Plugin {
    const SLUG          = 'loomi-studio-setup';
    const VERSION       = LOOMI_STUDIO_VERSION;       // ainda de loomi-studio-setup.php
    const OPTION_KEY    = 'loomi_studio_setup_settings';
    const SETTINGS_PAGE = 'loomi-studio-setup';
    const TEXT_DOMAIN   = 'loomi-studio-setup';
    const NONCE_PREFIX  = 'loomi_';
    
    const WORDFENCE_FILE = 'wordfence/wordfence.php';
    const UPDATE_TRANSIENT = 'loomi_update_check';
    const UPDATE_TTL = 12 * HOUR_IN_SECONDS;
}
```

Substitui ocorrências espalhadas. Mudança futura (renomear slug, mudar TTL) vira edição em 1 lugar.

**Alternativa considerada:** consts dentro de cada classe que usa. Rejeitada pra magic strings cross-classe (option key é usado por Repository, Sanitizer e na hook `update_option_<key>` em Login).

### 6. Bool coercion como método público

Atualmente em `Settings::all()`:
```php
$merged[$bool_field] = filter_var( $merged[$bool_field], FILTER_VALIDATE_BOOLEAN );
```

Fica em `Settings_Repository::get_bool($key) : bool` — método público que módulos usam ao verificar toggles. Módulos param de chamar `Settings::get()` + comparar com `true`; passam a chamar `Settings_Repository::get_bool('login_slug_enabled')`. Mais expressivo + seguro.

### 7. Refactor é uma única mudança atômica

Não vamos fazer "fase 1: extrair Plugin, fase 2: split Settings, fase 3: Tabs". O risco de paridade é menor com **uma** mudança grande seguida de re-validação do que com várias mudanças pequenas onde cada uma esconde regressões parciais.

**Mitigação do risco da mudança grande:** ao final, **re-executar todos os 81 testes que já passaram** no docker stack. Diff de comportamento = 0. Lint PHP em todos os arquivos.

## Risks / Trade-offs

- **[Risco] Regressão funcional silenciosa** → Mitigação: re-executar suite completa de 81 tests (SVG sanitizer 11/11, role caps, hide menus via cURL, duplicate, custom login CSS injection, updater mock + offline, login slug 404 + redirect, install via ZIP). Se algum falhar, abortar e investigar.
- **[Risco] Quebra de API externa** se algum site customizou usando `Loomi_Settings::get()` diretamente → Mitigação: manter `Loomi_Settings` como classe deprecated com aliases estáticos para `Settings_Repository` durante esta versão. Próxima major remove.
- **[Risco] Cache estático espalhado** se múltiplas classes mantêm estado → Mitigação: apenas `Settings_Repository` tem cache estático; outras classes são stateless (chamam repository quando precisam de dados).
- **[Risco] Inflação de arquivos sem ganho proporcional** → Mitigação: limitar a ~6 arquivos novos + ~4 tabs (10 arquivos novos total). Cada um < 80 linhas. Se um ficar grande, é sinal de que ainda há SRP a aplicar.
- **[Trade-off] Plugin agora tem 19 arquivos PHP vs. 9 hoje** → Aceito: cada arquivo é coeso, < 100 linhas, fácil de localizar. Plugin de média escala em produção tipicamente tem 30-50 arquivos.
- **[Trade-off] `Loomi_Settings` legado mantido como alias** → Aceito por uma versão. Documentado como deprecated. Remove na próxima major.
- **[Trade-off] Sem testes unitários adicionados** → Aceito; o refactor habilita testes (classes injetáveis, sem estado fora do Repository), mas escrever a suite é outra change.

## Migration Plan

1. Branch local (ou trabalho direto se sem git).
2. Criar arquivos novos um por um, em ordem de dependência:
   - `class-plugin.php` (constantes)
   - `contracts/interface-module.php`
   - `contracts/interface-settings-tab.php`
   - `support/class-settings-repository.php`
   - `support/class-settings-sanitizer.php`
   - `support/class-login-urls.php`
   - `settings/tabs/class-tab-*.php` (4)
   - `settings/class-settings-page.php`
3. Refatorar módulos existentes para implementar `Module`.
4. Atualizar `loomi-studio-setup.php` bootstrap.
5. Sync arquivos pro container docker.
6. Rodar suite de 81 testes via wp-cli + curl.
7. Se tudo passar, rebuild ZIP.
8. Atualizar README com a nova estrutura de pastas (opcional, mas útil).

Rollback: descartar branch / git revert. Sem migração de schema.

## Open Questions

- Manter `Loomi_Settings` como alias deprecated por quanto tempo? Sugestão: 2 versões (1.0.x e 1.1.x), remover em 1.2.0.
- Vale a pena criar uma classe `Hooks` que registra cada hook com um nome legível (ex.: `Hooks::on_init($cb)`)? Pequeno benefício de legibilidade, custo de mais uma camada. Decisão atual: NÃO, manter `add_action()` direto.
- Adotar PSR-4 namespaces no futuro? Sim, mas em uma change futura quando outros plugins / contracts forem adicionados ao ecossistema Loomi.
