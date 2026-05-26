## Context

A change `login-slug` (arquivada em `loomi-studio-setup-plugin`) entregou:
- `/wp-login.php` → 404 para usuários não autenticados.
- `/studio-access/` → serve o form de login via `require ABSPATH . 'wp-login.php'; exit;`.
- 5 filtros (`login_url`, `logout_url`, `lostpassword_url`, etc.) que reescrevem URLs internas do WP pra usar o slug.

O problema é o último item: quando o WP precisa gerar um redirect (ex: `auth_redirect()` chamado por `wp-admin/admin.php`), ele usa `wp_login_url()` que passa pelo nosso filtro e devolve `/studio-access/?...`. WP envia HTTP 302 com `Location: /studio-access/?...` — e qualquer scanner que bata em `/wp-admin/` recebe o slug no header.

Defesas em camadas (defense in depth):
1. **Slug obscurecido** (já temos) — atacante precisa adivinhar o nome.
2. **Não vazar o slug em redirects** (esta change) — `/wp-admin/` não revela `/studio-access/`.
3. **Wordfence** (já requerido por outra change) — rate-limit + IP block em tentativas falhas.

Esta change cobre a camada 2.

## Goals / Non-Goals

**Goals:**
- `curl -I /wp-admin/` (sem cookie) → 404, sem `Location: /studio-access/` no header.
- Login flow normal (acesso direto a `/studio-access/`) continua funcionando.
- Logout funciona (mesmo que mostre 404 logo depois — comportamento aceitável).
- Admin tem opção de desligar o hardening se preferir o redirect-para-slug.
- Testes automatizados garantem o gate.

**Non-Goals:**
- Não bloquear `/wp-json/` ou outros endpoints REST — esses retornam 401 já, sem leak.
- Não esconder o fato de que o site é WP (impossível — meta tags, /wp-content/, etc.).
- Não esconder o slug de usuários logados (eles já sabem). Foco é proteger contra **scans não autenticados**.
- Não implementar IP-based rate-limit (esse é trabalho do Wordfence).

## Decisions

### 1. Gate em `/wp-admin/` para visitantes não autenticados

Hook em `admin_init` (que dispara antes de `auth_redirect()`):

```php
public static function gate_admin_endpoint() : void {
    if ( ! Settings_Repository::get_bool( 'hide_admin_endpoint' ) ) {
        return;
    }
    if ( is_user_logged_in() ) {
        return;
    }
    if ( wp_doing_ajax() || wp_doing_cron() || self::is_rest_request() ) {
        return;
    }
    // Unauthenticated GET to wp-admin/* → 404 sem revelar nada
    self::render_not_found();
}
```

`render_not_found()` é o mesmo helper já usado por `gate_wp_login()` (que carrega `404.php` do tema se existir, senão `wp_die` mínimo).

**Por quê hook `admin_init` e não mais cedo?** `admin_init` dispara em `wp-admin/admin.php`, exatamente onde `auth_redirect()` rodaria. Hook nele ANTES de WP fazer o redirect padrão. Em `admin_menu` seria tarde demais (auth_redirect já rodou).

Actually, the standard hook is `auth_redirect` itself which fires earlier. Let me check: WP's flow in wp-admin/admin.php is `wp_get_current_user()` then `auth_redirect()` which fires the `auth_redirect` action. Hooking on `auth_redirect` action with high priority (1) intercepts before WP redirects.

Re-decision: use `add_action('auth_redirect', [..., 'gate_admin_endpoint'], 0)` — fires before WP's own auth_redirect logic. If we kill the request here (wp_die), WP's redirect never runs.

### 2. Filtros `login_url`/`logout_url` permanecem ATIVOS

Não removo os filtros — eles são necessários pra:
- Link "Sair" no menu de usuário (`wp_logout_url`).
- Link "Esqueci minha senha" no form de login.
- Email de comment notification "Logue para responder".

Esses contextos não vazam pra scanner: só usuários logados (admin link sair) ou contextos triggered por humanos (clica em "esqueci senha" no form). O leak era do redirect AUTOMÁTICO em `/wp-admin/`, que é o que esta change bloqueia.

### 3. Toggle `hide_admin_endpoint` default ON

```php
'hide_admin_endpoint' => true,  // novo default
```

ON por default porque é a postura segura. Admin pode desligar se preferir UX de "digitar /wp-admin/ e ir pro login" — mas paga o preço de revelar o slug em scans.

### 4. Sanitizer + UI

Sanitizer trata como bool padrão (igual outros toggles).

UI: novo campo no `Tab_Slug` (mesma aba que tem o slug), abaixo do toggle existente:

```
☑ Ativar slug customizada
   Slug: [studio-access      ]

☑ Esconder /wp-admin/ também (recomendado)
   ⓘ Quando ativo, requests não autenticadas a /wp-admin/ retornam 404 em
     vez de redirecionar para a slug. Isso evita que scanners descubram
     a slug secreta inspecionando o header Location.
     Trade-off: admin precisa lembrar do slug — digitar /wp-admin/ no
     browser também levará a 404.
```

### 5. Testes

`AdminEndpointSecurityTest`:
- `test_wp_admin_returns_404_when_unauthenticated_and_hardening_on`
- `test_wp_admin_does_not_leak_slug_in_location_header`
- `test_wp_admin_works_normally_when_hardening_off`
- `test_wp_admin_passes_through_for_logged_in_user`
- `test_rest_api_request_not_affected`
- `test_ajax_request_not_affected`

A maioria via `$this->go_to('/wp-admin/')` + assert no current_screen ou no que `wp_die` produz. Para HTTP-level (`curl -I`), validar via `LoginSlugRoutingTest` integration (que já usa o stack docker).

### 6. README — seção "Security model"

Tabela de defesa em camadas:

| Camada | Função | Ataque que bloqueia |
|---|---|---|
| Custom slug | Endpoint não óbvio | Scanner que tenta `/wp-login.php` direto |
| Admin endpoint hardening | `/wp-admin/` → 404 | Scanner que infere slug via redirect |
| Wordfence (peer) | Rate-limit + IP block | Brute-force depois que slug é descoberto |
| Capabilities (loomi_client) | Sem acessar config | Privilege escalation pós-login |

## Risks / Trade-offs

- **[Risco UX] Admin esquece o slug e fica trancado** → Mitigação: documentar `wp eval-file` pra resetar option via WP-CLI; manter constante `LOOMI_STUDIO_DISABLE_HARDENING` que admin pode definir em `wp-config.php` pra desligar tudo de emergência. Toggle no painel também desliga.
- **[Risco] Plugins que esperam `/wp-admin/` responder normalmente** (ex: ferramentas que dão deep-link pra alguma página admin) → Mitigação: o hardening só dispara pra requests NÃO autenticadas; usuários com sessão válida continuam acessando normal.
- **[Risco] Logout mostra 404 em vez da tela "Você saiu"** → Aceitável; o cookie já foi limpo, segurança ok. Alternativamente, podemos hookar `wp_logout` pra redirecionar pra home antes do default redirect pro wp-login.
- **[Trade-off] Slug não pode ser deduzida via /wp-admin/ mas ainda pode ser deduzida por brute-force de slugs comuns** (`/admin/`, `/login/`, `/secret/`) — fora do escopo desta change, Wordfence cuida.

## Migration Plan

1. Aplicar code changes (Repository default + Login gate + Sanitizer + Tab).
2. Sync container, rodar suite — esperado 83 + 6 novos = ~89 testes.
3. Validar end-to-end via cURL: `curl -I /wp-admin/` retorna 404, sem Location pro slug.
4. Rebuild ZIP.
5. Update README.

Rollback: setting `hide_admin_endpoint => false` no DB devolve comportamento atual.

## Open Questions

- Devemos também hookar `wp_logout` pra mandar pra home em vez de wp-login.php? Sugestão: SIM, em uma futura change `loomi-logout-experience` se valer; nesta foca só na segurança.
- Cabe esconder `/wp-admin/` parcialmente (algumas subpáginas sim, outras não)? Resposta: NÃO, complexidade não compensa. Tudo-ou-nada.
- Cabe hooking `wp_safe_redirect` pra interceptar qualquer redirect que tenha o slug no destination? Resposta: NÃO nesta change — escopo é gate do `/wp-admin/`, não auditoria global de redirects.
