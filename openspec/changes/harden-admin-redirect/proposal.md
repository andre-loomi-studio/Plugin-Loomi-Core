## Why

A feature `login-slug` foi pensada pra esconder o endpoint de login (`/wp-login.php` → 404, `/studio-access/` → form), mas tem **uma falha de segurança real**: o redirect padrão do WP quando um usuário não-autenticado acessa `/wp-admin/` agora aponta pro slug customizado, vazando o segredo:

```
$ curl -I https://site.loomi.studio/wp-admin/
HTTP/1.1 302 Found
Location: https://site.loomi.studio/studio-access/?reauth=1&redirect_to=...
                                     ^^^^^^^^^^^^^^^^^^^
                                     LEAK no header Location
```

Qualquer scanner automatizado (bots, ferramentas de pentest, WP fingerprinters) descobre o slug com **1 request HTTP**. O objetivo da slug — esconder o endpoint de login de varredura — é parcialmente anulado.

A mesma classe de problema existe em outros pontos onde o WP gera URLs de login (`wp_login_url()` em mensagens de erro, headers de redirect em REST API quando não autenticado, links em emails de comentário "logue para responder", etc.). Cada um desses é um leak potencial.

## What Changes

- **`/wp-admin/` unauthenticated → 404** (mesmo tratamento que `/wp-login.php`) em vez de 302 pra slug. Admin precisa ir direto pro slug, evita o leak.
- **Remover filtro `login_url`** quando a request vem de um contexto que pode estar sendo scaneado (ex: `auth_redirect` pra `/wp-admin/`). Em outros contextos (link de "Login" no front-end, formulários intencionais), o filtro continua aplicando — porque aí o slug já é visível pra um humano logado.
- **Estratégia opt-in**: novo toggle `hide_admin_endpoint` no painel (default ON), porque tem trade-off de UX (admin que digitar `/wp-admin/` no browser também leva 404 e precisa lembrar do slug).
- **Disclaimer** explicando o trade-off no painel de settings.
- Suite WP-PHPUnit: novo `AdminEndpointSecurityTest` validando que `/wp-admin/` retorna 404 sem `Location: /studio-access/` no header quando toggle ligado.
- Atualizar README com seção "Security model" explicando a defesa em camadas (slug + admin endpoint hardening + Wordfence).

## Capabilities

### New Capabilities
- `admin-endpoint-hardening`: bloqueio do leak da slug via `/wp-admin/` (e outros endpoints que o WP usa pra redirecionar para login), com toggle opt-in pra balancear segurança vs UX.

### Modified Capabilities
<!-- A capability existente `login-slug` (na change arquivada loomi-studio-setup-plugin) tem seu comportamento estendido: hoje protege `/wp-login.php`, depois desta change também protege `/wp-admin/` quando o toggle estiver ligado. -->

## Impact

- **Arquivos alterados**:
  - `includes/support/class-settings-repository.php` — adicionar default `hide_admin_endpoint => true`.
  - `includes/support/class-settings-sanitizer.php` — adicionar coerção bool pro novo toggle.
  - `includes/modules/class-loomi-login.php` — novo hook `init` que detecta `/wp-admin/` unauthenticated e retorna 404 (igual `gate_wp_login`); condicionar os 5 URL filters ao toggle.
  - `includes/settings/tabs/class-tab-slug.php` — adicionar toggle "Esconder /wp-admin/" + disclaimer.
  - `tests/integration/AdminEndpointSecurityTest.php` — **novo**.
  - `tests/integration/LoginSlugRoutingTest.php` — atualizar/adicionar cenários cobrindo o novo gate.
  - `README.md` — seção "Security model" explicando defesa em camadas.
- **Sem mudança de banco**: apenas um novo bool dentro da option existente.
- **Risco de UX**: admin que digite `/wp-admin/` no browser leva 404, precisa lembrar do slug (`/studio-access/`). Pode confundir admins que tinham bookmark de wp-admin. Mitigação: documentar no README + manter toggle desligável.
- **Compatibilidade**: nenhuma quebra. Toggle off mantém comportamento atual (302).
- **Performance**: zero — só mais uma checagem de `is_user_logged_in()` no `init` (rápido).
- **Riscos de regressão**:
  - **Login flow normal**: usuário acessa `/studio-access/`, faz POST, é redirecionado pra `/wp-admin/`. Como ele está logado agora, o gate de `/wp-admin/` não dispara — flow continua funcionando.
  - **Logout**: WP redireciona pra `/wp-login.php?loggedout=true` por default. Já 404'amos isso hoje; depois logout, pode mostrar tela de "Você saiu" rapidamente antes do 404 — aceitável.
  - **REST API / AJAX**: requests autenticadas com cookie passam; sem cookie WP responde 401, sem redirect. Não afetado.
