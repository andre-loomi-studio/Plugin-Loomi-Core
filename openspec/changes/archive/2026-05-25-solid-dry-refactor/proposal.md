## Why

O plugin Loomi Studio Setup cresceu de forma orgânica: 7 módulos foram adicionados rapidamente para entregar funcionalidade primeiro. Resultado funcional (81/83 tasks validadas), mas com débito interno que vai começar a doer no próximo ciclo de mudanças:

- `Loomi_Settings` (~340 linhas) carrega 7 responsabilidades distintas (defaults, cache, registro de página, sanitização, enqueue de assets, render do form, render de cada tab).
- 5 métodos quase idênticos em `Loomi_Login` para reescrever URLs WP (`login_url`, `logout_url`, `lostpassword_url`, `register_url`, `logout_redirect`) — todos fazem variações de `add_query_arg` sobre a slug.
- Coerção de booleanos espalhada (resolvida via `filter_var` em `Settings::all()`, mas a lógica é interna e não exposta).
- Magic strings (`'loomi-studio-setup'`, `'loomi_studio_setup_settings'`, `'wordfence/wordfence.php'`) repetidas.
- Pattern de bootstrap repetitivo: cada módulo tem `static init()` chamado manualmente em `loomi-studio-setup.php` — sem contrato comum.

Este refactor é **internal-only**: não muda comportamento, não muda specs, não muda o ZIP de release. Reaplica os 81 testes existentes ao final para garantir paridade. Objetivo: facilitar mudanças futuras (próximo módulo deve nascer com baixa fricção; testes unitários devem ser viáveis sem mocar WP inteiro).

## What Changes

- **SRP em `Loomi_Settings`**: dividir em quatro classes coesas:
  - `Settings_Repository` — leitura, cache, defaults, coerção de tipos.
  - `Settings_Sanitizer` — validação por campo (sanitize callback).
  - `Settings_Page` — registro da página, orquestração das tabs.
  - Interface `Settings_Tab` + 4 implementações (`Tab_Login`, `Tab_Slug`, `Tab_Hide_Menus`, `Tab_Client_Role`).
- **DRY nos URL rewrites do login**: extrair `Login_URLs::build($action, $args)`; os 5 filtros viram thin wrappers que chamam o helper.
- **Interface `Module`** com método `register(): void` — todos os módulos passam a implementar; bootstrap itera sobre uma lista.
- **Classe `Plugin`** com constantes centralizadas (slug, option key, version, paths) — substitui strings repetidas.
- **Garantia de paridade**: re-rodar os 81 cenários validados anteriormente após o refactor; lint PHP em todos os arquivos novos/alterados.
- **Sem mudanças nas specs existentes**: requirements de comportamento são exatamente os mesmos.

## Capabilities

### New Capabilities
- `module-architecture`: convenções de arquitetura interna do plugin — interface `Module`, contrato de bootstrap, separação Repository/Sanitizer/Page, helper `Login_URLs`, classe `Plugin` com constantes. Codifica decisões pra módulos futuros nascerem alinhados.

### Modified Capabilities
<!-- Nenhuma. As capabilities existentes (svg-upload, custom-login, login-slug, admin-menu-hider, loomi-client-role, post-duplication, plugin-settings, auto-update, wordfence-dependency) mantêm seus requirements de comportamento idênticos — só a implementação interna muda. -->

## Impact

- **Arquivos novos** (~6): `includes/contracts/interface-module.php`, `includes/contracts/interface-settings-tab.php`, `includes/class-plugin.php`, `includes/class-settings-repository.php`, `includes/class-settings-sanitizer.php`, `includes/class-settings-page.php`, `includes/class-login-urls.php`, `includes/tabs/` (4 arquivos).
- **Arquivos alterados** (~9): todos os módulos existentes adotam a interface `Module`; `loomi-studio-setup.php` orquestra via lista; `Loomi_Settings` deixa de existir como god-class (compatibilidade preservada via alias estático se necessário).
- **Sem mudança de banco**: mesma option, mesmo transient.
- **Sem mudança de comportamento**: hooks, filtros, e endpoints idênticos. Painel renderiza exatamente igual.
- **Sem mudança no ZIP**: tamanho similar (talvez ligeiramente maior por causa de mais arquivos), mas estrutura interna diferente. Sites com auto-update receberão a nova versão transparentemente.
- **Performance**: nula (mesma quantidade de hooks; classes minúsculas; autoload via require_once continua).
- **Compatibilidade**: WP ≥ 6.0, PHP ≥ 7.4 (mesmas restrições). PSR-12 ainda não obrigatório, mas naming consistente.
- **Risco**: regressão em refactor é o risco principal — mitigado por reaplicar a suite de 81 validações no docker stack antes de fechar.
