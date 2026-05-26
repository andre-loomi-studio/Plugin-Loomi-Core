## Context

Os sites Loomi tem 2 vetores de spam dominantes:

1. **Comentários**: bots crawleiam WP sites e postam comentários genéricos com links pra SEO/affiliate. Pingbacks e trackbacks (especialmente) são quase 100% spam hoje em dia.
2. **Login/registro**: bots tentam credential stuffing no `wp-login.php` (já 404 quando login_slug enabled) e em `/studio-access/`.

Wordfence (peer plugin requerido) cobre rate-limit e IP block. Mas:
- **Não bloqueia bots no comment form** (Wordfence não filtra comments por conteúdo — só por IP/UA).
- **Não cobre forms customizados** que plugins terceiros adicionam.

Esta change adiciona uma camada **zero-config** específica pra defangar bots automatizados sem CAPTCHA.

## Goals / Non-Goals

**Goals:**
- Bloquear bots dumb (~80% do tráfego de spam) sem CAPTCHA, sem credencial externa.
- Funcionar nos forms nativos do WP: login, registro, comment, lost password.
- Manter UX humana intacta (zero clique extra, zero "select traffic lights").
- Permitir Akismet centralizado via constante única do wp-config.
- Cada técnica é independente — admin pode desligar individualmente.

**Non-Goals:**
- Não integrar com plugins de contact form (Contact Form 7, WPForms, etc.) — cada um tem sua proteção própria.
- Não substituir Wordfence (rate-limit + WAF) — esta change é complementar.
- Não fazer fingerprinting de browser ou device tracking — privacy first.
- Não implementar reCAPTCHA/hCaptcha/Turnstile — esses são CAPTCHA visuais que esta change deliberadamente evita.
- Não bloquear bots com headless browsers configurados (Puppeteer, Playwright) — esses passam honeypot + time check; só Wordfence + comportamento (rate-limit por IP) pega.

## Decisions

### 1. Honeypot: campo `loomi_hp` invisível

Implementação:
```html
<div aria-hidden="true" style="position:absolute;left:-9999px;height:1px;width:1px;overflow:hidden;">
  <label>Deixe em branco<input type="text" name="loomi_hp" tabindex="-1" autocomplete="off" /></label>
</div>
```

Adicionado via hooks:
- `login_form` (action) — renderiza dentro do form de `wp-login.php` (e `/studio-access/`).
- `register_form` (action) — form de registro.
- `comment_form_after_fields` (action) — comment form.

Validação:
- `authenticate` filter (priority 21, após WP) — se `$_POST['loomi_hp']` não-vazio, retorna `WP_Error('honeypot', ...)`.
- `pre_comment_approved` filter — se `$_POST['loomi_hp']` não-vazio, marca como spam.
- `registration_errors` filter — se preenchido, adiciona erro.

**Por que `aria-hidden` + position fora da viewport em vez de `display:none`?** Alguns bots detectam `display:none` e pulam o campo. `position:absolute;left:-9999px` é mais difícil de detectar; muitos bots preenchem por nome de campo sem renderizar CSS.

### 2. Time check: hidden field com timestamp

Implementação:
```html
<input type="hidden" name="loomi_t" value="<?php echo time(); ?>" />
```

Validação:
- Se `time() - intval($_POST['loomi_t']) < 2` → rejeita (provavelmente bot que submeteu instantaneamente).
- Threshold 2s é conservador. Humano demora ≥3s mesmo com password manager autocomplete.

**Por que não criptografar/assinar o timestamp?** Não é necessário — o objetivo não é prevenir tampering (se bot edita o timestamp pra ficar antigo, a checagem passa, mas aí ele NÃO é mais "dumb bot" e o honeypot já filtra). Bots dumb não tocam no timestamp.

### 3. Comment lockdown: 3 sub-ações

Quando `anti_spam_comment_lockdown = true`:

a) **Desabilitar pingback/trackback** globalmente:
   - `default_pingback_flag = 0` (option)
   - `default_ping_status = 'closed'` (option)
   - Filter `xmlrpc_methods` removendo `pingback.ping` (defesa em profundidade)

b) **Hold all comments for moderation** por default em sites novos:
   - `comment_moderation = 1` (option)
   - `comment_whitelist = 0` (force moderation pra TODOS, não só primeiros)

c) **Disable comments em CPTs futuros**: não fazemos isso — admin decide por CPT/post.

**Por quê não desabilitar comments inteiramente?** Decisão consciente — alguns clientes Loomi USAM comments (blogs). Lockdown defang sem forçar.

### 4. Akismet auto-config

Quando `anti_spam_akismet_autoconfig = true` E `LOOMI_AKISMET_KEY` definida E plugin `akismet/akismet.php` ativo:

```php
add_action( 'admin_init', function () {
    if ( defined( 'LOOMI_AKISMET_KEY' ) && LOOMI_AKISMET_KEY ) {
        $stored = get_option( 'wordpress_api_key' );
        if ( $stored !== LOOMI_AKISMET_KEY ) {
            update_option( 'wordpress_api_key', LOOMI_AKISMET_KEY );
        }
    }
}, 99 );
```

Loomi central:
1. Cria 1 conta Akismet ($9-15/mês).
2. Define `LOOMI_AKISMET_KEY` no `wp-config.php` dos sites (via provisioning ou snippet).
3. Plugin Loomi propaga a key pra option `wordpress_api_key` que Akismet lê.
4. Pronto — todos os sites usam a mesma assinatura, comments filtrados centralmente.

**Por que NÃO embutir Akismet no plugin?** Akismet é um plugin separado, mantido pelo Automattic, atualizado independentemente. Embutir vira inferno de manutenção. O plugin só **configura** o que já existe.

### 5. UI: nova tab "Anti-Spam"

5ª tab após "Role Cliente":

```
☑ Ativar proteção anti-spam (kill switch geral)

  Técnicas (cada uma desligável):
  ☑ Honeypot em forms nativos
     Campo invisível em login, registro e comentários.

  ☑ Time check (rejeitar submissões em <2s)
     Bots que respondem instantaneamente são bloqueados.

  ☑ Comment lockdown
     Desabilita pingbacks/trackbacks (vetor #1 de spam).
     Força hold-for-moderation em todos os comentários.

  ☑ Akismet auto-config
     Se a constante LOOMI_AKISMET_KEY estiver definida no wp-config.php,
     o plugin Akismet é configurado automaticamente. Conta central da Loomi:
     1 API key cobre todos os sites.
```

### 6. Order of operations + interaction com Wordfence

Wordfence intercepta requests muito cedo (priority 1 ou 0 em `init`). Honeypot/time check rodam DEPOIS do Wordfence, na fase de validação do form (`authenticate`, `pre_comment_approved`). Sem conflito.

Akismet também roda em `pre_comment_approved` — convivem (Akismet decide spam/not_spam, nosso filter pode override pra spam se honeypot pego).

## Risks / Trade-offs

- **[Risco] Falso-positivo em password managers** (LastPass autopreenche + submit em <2s) → Mitigação: threshold 2s é conservador; password manager + clique humano ainda leva pelo menos 3-4s. Toggle desligável caso vire problema.

- **[Risco] Comment lockdown surpreende admin que esperava comments auto-aprovados** → Mitigação: documentado em UI; toggle desligável; se admin USA comments e quer comportamento default, desliga.

- **[Risco] Akismet auto-config sobrescreve config existente** → Mitigação: só atualiza se a key atual diferir; admin pode desligar o toggle pra manter sua key.

- **[Risco] Bots sofisticados (headless browsers) bypassam honeypot + time check** → Aceito: Wordfence cobre rate-limit por IP. Pra próximo passo (custos altos), adicionar Cloudflare Turnstile como toggle opcional.

- **[Trade-off] Mais 5 toggles no painel** → Aceito; nova aba dedicada mantém organização. Defaults razoáveis (todos ON) — admin não precisa mexer pra ter proteção.

- **[Trade-off] Não cobre contact form plugins** → Aceito explicitamente. Documentado no README como "fora do escopo desta camada — cada plugin de form configura sua própria proteção".

## Migration Plan

1. Adicionar defaults em Repository.
2. Criar módulo Loomi_Anti_Spam.
3. Criar Tab_Anti_Spam.
4. Wire no Settings_Page::tabs() e no bootstrap.
5. Testes.
6. Sync container + run suite (esperado 92 + ~10 = ~102 testes).
7. cURL validation: submit comment com `loomi_hp` preenchido → marca como spam.
8. Rebuild ZIP.

Rollback: desligar toggle `anti_spam_enabled` zera tudo.

## Open Questions

- **Default pra comments**: lockdown (hold-for-moderation) ou disabled entirely? **Decisão atual:** lockdown only — alguns clientes Loomi usam comments. Admin desliga toggle se quiser comments auto-aprovados.
- Cabe adicionar `Loomi_Anti_Spam::stats()` mostrando "X spams bloqueados nas últimas 30 dias" no dashboard? Out of scope desta change — futura `anti-spam-stats`.
- Cabe ofertar Turnstile como toggle opcional (não default)? Out of scope; futura `anti-spam-turnstile` se demanda surgir.
