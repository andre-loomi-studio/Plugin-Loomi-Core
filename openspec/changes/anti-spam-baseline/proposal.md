## Why

Sites Loomi recebem spam recorrente em **comentários** (vetor #1 — pingbacks, trackbacks, comment forms abertos) e em **forms de login/registro** (credential stuffing, fake signups). Soluções clássicas:

- **reCAPTCHA**: Google credential por site, configuração tediosa, UX ruim (selecione semáforos), além de privacy concerns.
- **Akismet sozinho**: ótimo pra comments, mas precisa de API key e não cobre forms nativos (login, registro).
- **Plugins de captcha terceiros**: cada um com config própria, mais um plugin pra manter.

Esta change adiciona uma **baseline de proteção zero-config** ao Loomi Studio Setup, combinando 4 técnicas complementares que **não exigem nenhuma credencial externa** e funcionam sem CAPTCHA visual:

1. **Honeypot** — campo invisível nos forms nativos do WP (login, registro, comentário). Bot dumb preenche → rejeita.
2. **Time check** — submissão em <2s do render do form → bot.
3. **Comment lockdown** — desabilita pingback/trackback (vetor #1) + força hold-for-moderation por default.
4. **Akismet auto-config opcional** — se constante `LOOMI_AKISMET_KEY` definida em wp-config, plugin auto-ativa Akismet com essa key. Permite Loomi ter 1 conta Akismet central e propagar pra todos os sites.

Resultado esperado: **>95% do spam genérico** bloqueado sem reCAPTCHA, sem credencial por site, sem CAPTCHA visual chato.

## What Changes

- Novo módulo `Loomi_Anti_Spam` (implements `Loomi_Module`) com 4 sub-features ativáveis individualmente.
- Novo toggle `anti_spam_enabled` (default `true`) — kill switch geral.
- 4 sub-toggles (todos default `true`):
  - `anti_spam_honeypot` — adiciona campo `loomi_hp` invisível em login/registro/comment forms.
  - `anti_spam_time_check` — adiciona timestamp em hidden field; rejeita se delta < 2s.
  - `anti_spam_comment_lockdown` — disable_pingbacks=true + default_comment_status=closed para CPTs novos + comment_moderation=hold pra todos.
  - `anti_spam_akismet_autoconfig` — se `LOOMI_AKISMET_KEY` definida E plugin Akismet ativo, seta `wordpress_api_key` na option.
- Nova aba `Anti-Spam` no painel de settings (5ª tab) com os toggles + disclaimer explicando cada técnica.
- Suite WP-PHPUnit: `AntiSpamTest` cobrindo honeypot (bot/humano), time check, comment lockdown, Akismet autoconfig.
- README atualizado com seção "Anti-spam" explicando a estratégia.
- **Sem dependência externa**: Akismet permanece opcional. Plugin funciona sem nenhum config externo.

## Capabilities

### New Capabilities
- `anti-spam-baseline`: honeypot + time check em forms nativos WP, comment lockdown defaults, auto-config Akismet via constante.

### Modified Capabilities
<!-- Nenhuma capability existente muda. Esta change adiciona um módulo novo orthogonal aos existentes. -->

## Impact

- **Arquivos novos**:
  - `includes/modules/class-loomi-anti-spam.php` (~150 linhas).
  - `includes/settings/tabs/class-tab-anti-spam.php` (~60 linhas).
  - `tests/integration/AntiSpamTest.php` (~10 testes).
- **Arquivos alterados**:
  - `includes/support/class-settings-repository.php` — 5 defaults novos.
  - `includes/settings/class-settings-page.php` — adicionar `Tab_Anti_Spam` à lista de tabs.
  - `includes/support/class-settings-sanitizer.php` — automático (BOOL_FIELDS).
  - `loomi-studio-setup.php` — require_once dos 2 arquivos novos + add Loomi_Anti_Spam ao array de modules.
  - `README.md` — seção Anti-spam.
- **Sem dependência runtime**: tudo é PHP puro contra hooks WP (`comment_form_default_fields`, `login_form`, `register_form`, `pre_comment_approved`, `wp_authenticate`, etc.).
- **Performance**: zero impacto perceptível — honeypot é 1 string adicional no HTML; time check é 1 hidden input + 1 comparação no submit; comment lockdown muda options (já cached); Akismet config roda uma vez na ativação.
- **UX**: visitantes humanos **não veem nada** (honeypot escondido por CSS, time check transparente). Sem CAPTCHA pra resolver.
- **Risco**: pode causar **falso-positivo** em humanos muito rápidos (autopreenchimento de form via password manager talvez); mitigação: time check é desligável + threshold conservador (2s).
