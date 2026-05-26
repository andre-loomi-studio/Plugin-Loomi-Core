## Context

A feature "Esconder Menus" (capability `admin-menu-hider`) foi entregue com lista hardcoded de 7 menus core do WP. Funciona, mas não cobre CPTs que vêm de plugins instalados nos sites Loomi. Esta change estende a lista para ser dinâmica.

Stack: WP ≥ 6.0, PHP ≥ 7.4. Pós refactor solid-dry, a feature está dividida em:
- `Settings_Repository` (constantes + cache)
- `Settings_Sanitizer` (valida input do form)
- `Loomi_Admin_Menu` (executa `remove_menu_page` no hook `admin_menu`)
- `Tab_Hide_Menus` (UI do multi-select)

Esses 4 arquivos são os afetados.

## Goals / Non-Goals

**Goals:**
- Lista core reduzida a **5 menus que `editor`/`loomi_client` realmente vê** (remove redundância com gating nativo do WP).
- **Adicionar Páginas** como item core (faltava).
- Lista de "menus hideable" inclui CPTs públicos registrados no site, em tempo de render do painel.
- CPT label legível para humano (ex: "Produtos" não "product").
- **Disclaimer educativo** no painel explicando a lógica de visibilidade do WP.
- Sanitizer aceita CPT slugs válidos no momento do save.
- `Loomi_Admin_Menu::hide_menus()` remove CPT menus quando configurado.
- Cada CPT aparece com prefixo visual claro no multi-select pra admin distinguir do core.
- Performance: zero degradação perceptível.

**Non-Goals:**
- Não suportar CPTs como submenu de outro menu (`show_in_menu => 'string'`) — out of scope v1.
- Não tentar esconder menus de plugins NÃO derivados de CPT (ex: WooCommerce "Settings" menu próprio, Yoast "SEO" menu) — fora do escopo desta change.
- Não persistir uma "snapshot" da lista descoberta — sempre redescobrir a cada request (CPTs podem mudar entre instalações de plugins).
- Não auto-marcar novos CPTs como hidden — admin opta in explicitamente.

## Decisions

### 1. Método dinâmico `Settings_Repository::hideable_menus()` com memo

```php
private static $hideable_cache = null;

public static function hideable_menus() : array {
    if ( self::$hideable_cache !== null ) {
        return self::$hideable_cache;
    }

    $menus = self::HIDEABLE_MENUS;

    if ( function_exists( 'get_post_types' ) ) {
        $cpts = get_post_types(
            [ 'show_ui' => true, 'show_in_menu' => true, '_builtin' => false ],
            'objects'
        );
        foreach ( $cpts as $cpt ) {
            $slug = 'edit.php?post_type=' . $cpt->name;
            if ( isset( $menus[ $slug ] ) ) continue; // core takes precedence
            $label = $cpt->labels->menu_name ?? $cpt->labels->name ?? $cpt->name;
            $menus[ $slug ] = $label;
        }
    }

    self::$hideable_cache = $menus;
    return $menus;
}
```

Memo é por-request (static class member). `clear_cache()` zera ambos os caches.

**Por quê filtrar `_builtin => false`?** WP define `post` e `page` como builtin CPTs. `post` já está coberto pelo slug `edit.php` em HIDEABLE_MENUS (= edit.php?post_type=post). `page` é adicionado manualmente como 8º item em `HIDEABLE_MENUS` nesta change. Pular `_builtin` na descoberta dinâmica evita duplicação desses dois.

**Por quê `show_in_menu => true` e não permitir string?** CPTs com `show_in_menu = string` viram submenu de outro menu (ex: WooCommerce coloca Coupons em sub-item de Marketing). `remove_menu_page` não os remove — precisaria de `remove_submenu_page` com parent_slug correto. Out of scope v1.

### 2. Sanitizer valida contra lista dinâmica

```php
foreach ( $input['hidden_menus'] as $slug ) {
    if ( in_array( $slug, Settings_Repository::BLACKLISTED_MENUS, true ) ) continue;
    if ( ! array_key_exists( $slug, Settings_Repository::hideable_menus() ) ) continue;
    $out['hidden_menus'][] = $slug;
}
```

Mesma lógica, só troca a fonte. Timing: o sanitize roda em `options.php` submit, depois de `init` (onde plugins registram CPTs) — então `hideable_menus()` retorna lista completa.

### 3. `Loomi_Admin_Menu::hide_menus()` idem

Sem mudança estrutural — só troca const por método. Garante que CPTs marcados em `hidden_menus` sejam removidos via `remove_menu_page( $slug )`.

### 4. UI do tab — disclaimer + dois grupos

```php
public function render( array $s ) : void {
    $core_menus = Settings_Repository::HIDEABLE_MENUS;
    $all_menus  = Settings_Repository::hideable_menus();
    $cpt_menus  = array_diff_key( $all_menus, $core_menus );

    // Disclaimer no topo + <fieldset> "WordPress" (5 core) + <fieldset> "Custom Post Types" (N CPTs)
}
```

Visual:
```
☐ Ativar ocultação

ⓘ Esta lista contém apenas menus que usuários sem permissão de administrador
  (editores, clientes Loomi) normalmente veem. O WordPress já esconde
  automaticamente Plugins, Temas, Usuários e Configurações para usuários
  sem a capability correspondente.

WordPress
☑ Posts             (edit.php)
☑ Páginas           (edit.php?post_type=page)
☑ Comentários       (edit-comments.php)
☑ Mídia             (upload.php)
☑ Ferramentas       (tools.php)

Custom Post Types
☐ Produtos                 (edit.php?post_type=product)
☐ Pedidos                  (edit.php?post_type=shop_order)
☐ Templates Elementor      (edit.php?post_type=elementor_library)

(Vazio: "Nenhum Custom Post Type encontrado neste site.")
```

Disclaimer em `<div class="notice notice-info inline">` — visual leve, não-dismissível, mesmo padrão de UI do WP core. Se não há CPTs registrados, o segundo grupo mostra mensagem informativa em vez de checkboxes vazios.

### 5. Tabela `BLACKLISTED_MENUS` permanece estática

Os menus protegidos contra remoção (`index.php`, `options-general.php`) são intencionalmente estáticos. Nenhum CPT pode ter slug igual (CPTs são `edit.php?post_type=*`), então sem conflito.

### 6. Cache do menu list invalida quando

- A option é salva (Settings API) — cache do Repository já é limpo por `clear_cache()` chamado em `Settings_Sanitizer::sanitize`.
- Plugins ativam/desativam (mudam CPTs registrados) — neste caso o cache de UM request fica stale, mas o próximo request descobre novamente. Aceito.

### 7. Tests: registrar CPT mock + asserts

```php
// HideMenusTest::set_up()
register_post_type( 'mock_cpt', [
    'public' => true, 'show_ui' => true, 'show_in_menu' => true,
    'labels' => [ 'name' => 'Mock CPT', 'menu_name' => 'Mock' ],
] );
```

Testes assertam:
- `Settings_Repository::hideable_menus()` contém `edit.php?post_type=mock_cpt`.
- Sanitizer aceita esse slug em `hidden_menus`.
- `hide_menus()` remove o slug do menu global quando configurado.

`tear_down()` chama `unregister_post_type('mock_cpt')` pra não vazar entre tests.

## Risks / Trade-offs

- **[Risco] `get_post_types()` retorna lista vazia em contextos onde plugins não carregaram (ex: REST API early hooks)** → Mitigação: o tab só renderiza dentro de `wp-admin/options-general.php?page=loomi-studio-setup`, ponto onde `init` já firou e todos plugins estão loaded. Sanitizer roda no submit, mesma garantia.

- **[Risco] CPT registrado por um plugin desativado fica "fantasma" em `hidden_menus`** → Aceito: o sanitizer só drop slugs unknown ao salvar. Se o admin desativa o plugin DEPOIS de marcar CPTs, o slug persiste no DB mas o `remove_menu_page` no `hide_menus()` simplesmente falha silenciosamente (já lida com isso via `array_key_exists` check). Quando o plugin volta, a config funciona de novo automaticamente.

- **[Risco] Submenu CPTs (Woo "Coupons", etc.) aparecem na listagem mas não conseguem ser removidos** → Mitigação: filtro `_builtin => false, show_in_menu => true` já exclui submenu CPTs (WP devolve `show_in_menu` como bool quando é menu próprio). Documentar como known limitation no README.

- **[Risco] CPT com label vazio** → Mitigação: fallback em cascata `labels->menu_name ?? labels->name ?? cpt->name`.

- **[Trade-off] Lista pode mudar entre saves** (admin instala plugin novo entre abrir o tab e clicar Salvar) → Aceito: o sanitizer valida na hora do save com a lista atual. Se um CPT some entre render e save, esse slug é dropado.

## Migration Plan

1. Aplicar refactor em `Settings_Repository` (add method).
2. Trocar consumers (Sanitizer, Admin_Menu) pra usar método.
3. Atualizar Tab_Hide_Menus UI.
4. Atualizar/adicionar testes.
5. Sync container + rodar `bash tests/run.sh` — esperado 70 + novos = 76 testes passando.
6. Rebuild ZIP.

Rollback: reverter os 4 arquivos. `hidden_menus` option fica intacta (CPT slugs que estavam lá ficam ignorados como "unknown" — comportamento pré-mudança).

## Open Questions

- Devemos ordenar CPTs alfabeticamente ou na ordem de registro? Decisão: alfabético — mais previsível pra admin.
- Mostrar contador "(N CPTs encontrados)" no header do grupo? Decisão: NÃO, apenas o label do grupo é suficiente.
- Adicionar suporte a submenu CPTs (`remove_submenu_page`)? Decisão para v1: NÃO. Avaliar quando aparecer demanda real.
