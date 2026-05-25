## Context

O `loomi-studio-setup` é o plugin interno que padroniza sites Loomi (custom login, role cliente, hide menus, etc.). Falta uma exigência cruzada: **Wordfence deve estar ativo em todos os sites**. A escolha já foi feita pela agência — Wordfence grátis (slug `wordfence` na wp.org), instalado como peer plugin, não como bibliotecа embutida.

Stack-alvo: WP ≥ 6.0, PHP ≥ 7.4. WP 6.5 introduziu o header `Requires Plugins:` que faz o gating nativo, mas precisamos suportar 6.0-6.4 também — por isso a checagem PHP-side complementar.

## Goals / Non-Goals

**Goals:**
- Detectar Wordfence ausente/inativo de forma confiável (chave: `wordfence/wordfence.php`).
- Exibir CTA não-dismissível no admin enquanto a dependência não estiver satisfeita.
- Permitir admin com `install_plugins` resolver com **um clique** (instala da wp.org + ativa).
- Manter o restante do Loomi Studio Setup funcionando mesmo sem Wordfence (não-bloqueante).

**Non-Goals:**
- Não exigir Wordfence Premium (out of scope — licença paga).
- Não validar configuração do Wordfence (firewall mode, country blocking, etc.) — só presença + ativo.
- Não desinstalar Wordfence se admin escolher remover — respeita a decisão (mas o notice volta).
- Não implementar TGM Plugin Activation library — overhead grande para 1 plugin.

## Decisions

### 1. Soft requirement, não hard-block

Loomi continua ativando normalmente mesmo sem Wordfence presente. O notice fica visível em **todas as telas do admin** até a dependência ser satisfeita, mas não trava nenhuma funcionalidade.

**Por quê?** Bloquear ativação tem efeitos colaterais ruins:
- Admin que não pode instalar plugins (filesystem readonly, gerenciado, etc.) fica sem nada.
- `register_activation_hook` rodando `deactivate_plugins(plugin_basename(__FILE__))` quebra fluxos de gerenciamento em massa.
- Wordfence pode ser instalado antes ou depois do Loomi — não importa a ordem.

**Alternativa considerada:** hard-block via deactivate_plugins na ativação. Rejeitada — ver acima.

### 2. Native `Requires Plugins:` header + custom PHP check

```php
// loomi-studio-setup.php header
* Requires Plugins:    wordfence
```

WP 6.5+ lê esse header e impede ativação se `wordfence/wordfence.php` não estiver presente. Em WP 6.0-6.4, o header é ignorado (sem erro) — nosso check PHP cobre.

**Combo dá:**
- WP 6.5+: WP nativo bloqueia ativação se Wordfence ausente. Nosso notice é redundante mas inofensivo.
- WP 6.0-6.4: Loomi ativa, nosso notice puxa o admin pra resolver.

### 3. Detecção via `is_plugin_active('wordfence/wordfence.php')`

Hardcode da path do plugin. Wordfence sempre instala em `wp-content/plugins/wordfence/wordfence.php` (slug imutável da wp.org). Nenhum filtro necessário.

Para detectar "instalado mas inativo": `file_exists( WP_PLUGIN_DIR . '/wordfence/wordfence.php' )`.

Três estados:
- **Ausente**: arquivo não existe → CTA "Instalar"
- **Instalado mas inativo**: arquivo existe, não ativo → CTA "Ativar"
- **Ativo**: tudo ok, nenhum notice

### 4. Notice via `admin_notices`, não-dismissível

```php
add_action( 'admin_notices', [ __CLASS__, 'render_notice' ] );
```

Sem botão "X" de dismiss. Volta a cada pageload até a dependência ser satisfeita — esse é o ponto.

Classe `notice-error` (vermelho) reforça urgência. Mostrado para usuários com `activate_plugins` (não polui visão de editores/clientes).

### 5. One-click install via admin-post handler

Endpoint próprio em `admin-post.php?action=loomi_install_wordfence`:

1. `check_admin_referer( 'loomi_install_wordfence' )` — nonce.
2. `current_user_can( 'install_plugins' )` — capability.
3. Se já instalado, pula direto pra activate.
4. Senão, instala via:
   ```php
   include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
   include_once ABSPATH . 'wp-admin/includes/plugin.php';
   include_once ABSPATH . 'wp-admin/includes/file.php';
   include_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

   $api = plugins_api( 'plugin_information', [ 'slug' => 'wordfence', 'fields' => [ 'sections' => false ] ] );
   $upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
   $result = $upgrader->install( $api->download_link );
   ```
5. Activate: `activate_plugin( 'wordfence/wordfence.php', '', false, true )` (silent flag true para não bloquear redirect).
6. Redirect de volta para `$_POST['_wp_http_referer']` com query arg `loomi_wf_status=ok|installed|activated|error`.
7. `admin_notices` lê o query arg e mostra success/erro transitório.

**Por que admin-post.php e não AJAX?** AJAX requer JS extra + UI de loading; admin-post é uma submissão simples de form que segue o flow padrão do WP (redirect + flash message). Mais simples, sem dependência de JS.

### 6. Sem AJAX, sem React, sem JS extra

O notice é HTML puro com um `<form action="admin-post.php" method="post">` contendo nonce + `action=loomi_install_wordfence`. Botão é `<button type="submit" class="button button-primary">`. Zero JS.

### 7. Sem auto-install na ativação

Tentar instalar Wordfence dentro de `register_activation_hook` é frágil:
- Filesystem credentials nem sempre disponíveis.
- Travamento de upgrade lock se outro update estiver rodando.
- Erro durante ativação pode deixar Loomi parcialmente ativo.

Decisão: deixar a instalação para o admin clicar no botão depois. Visivelmente claro, em controle do usuário, sem efeito colateral.

## Risks / Trade-offs

- **[Risco] wp.org indisponível durante o install** → Mitigação: `Plugin_Upgrader` já trata erros de download; o handler captura `WP_Error` e exibe notice transitório com mensagem clara. Admin pode tentar de novo ou instalar manualmente via `Plugins → Adicionar Novo`.

- **[Risco] Filesystem credentials prompt aparece em vez de instalar direto** → Mitigação: `request_filesystem_credentials()` retorna direct/ssh2/ftp dependendo do servidor; em hosts Loomi (Cloudways/WP Engine/Hostinger gerenciados), normalmente é `direct` e funciona sem prompt. Se vier ftp/ssh2, exibimos notice "instalar manualmente" como fallback.

- **[Risco] Admin com `activate_plugins` mas sem `install_plugins`** → Mitigação: o botão "Instalar Wordfence agora" só aparece se `current_user_can('install_plugins')`. Caso contrário, mostramos texto "peça ao administrador do site para instalar Wordfence" sem botão.

- **[Risco] Wordfence faz redirect próprio em alguns admin_notices (ex.: onboarding)** → Mitigação: ativamos com `$silent = true` em `activate_plugin()` para suprimir hooks de ativação que poderiam disparar redirect; admin chega na mesma tela onde clicou.

- **[Risco] WP 6.5+ bloqueia ativação do Loomi se Wordfence ausente (header `Requires Plugins:`)** → Mitigação consciente: esse é o comportamento desejado em 6.5+ — nativo, sem código nosso. Para WP < 6.5, o nosso notice cobre. Documentar no README.

- **[Trade-off] Notice persistente em todas as telas do admin pode irritar admin que deliberadamente não quer Wordfence** → Aceito: a dependência é política da Loomi, não opcional. Adicionar opt-out abriria caminho pra inconsistência entre sites.

## Migration Plan

- **Sites Loomi existentes que já têm o plugin ativo**: ao receber esta versão via auto-update (capability `auto-update` existente), o novo módulo carrega; sites que já têm Wordfence ativo não notam diferença; sites sem Wordfence verão o notice no próximo pageload do admin.
- **Sites novos**: instalam Loomi → notice aparece → admin clica "Instalar Wordfence agora" → ambos ativos em segundos.
- **Rollback**: desativar Loomi remove o notice (junto com tudo do Loomi). Wordfence continua ativo independentemente.

## Open Questions

- O notice deve ser mostrado também para `loomi_client` (role já criada pelo plugin)? Decisão padrão: NÃO — clientes não devem ver/agir sobre infra. Restringir ao `activate_plugins`.
- Após o admin instalar Wordfence, queremos pré-configurar algo (modo firewall, country blocking)? Out of scope desta change — pode virar uma future capability `wordfence-config` se valer.
- Quando WP 6.5 virar mínimo do nosso `Requires at least`, podemos remover o check próprio e confiar só no header nativo. Hoje vale manter ambos.
