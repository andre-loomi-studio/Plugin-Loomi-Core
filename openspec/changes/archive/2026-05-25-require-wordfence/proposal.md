## Why

Cada site WordPress da Loomi precisa ter uma camada de segurança mínima (firewall, bloqueio de IPs maliciosos, scan de malware) — e Wordfence é o padrão escolhido pela agência. Hoje, instalar Wordfence em cada site novo é uma etapa manual separada da instalação do `loomi-studio-setup`, com risco de ser esquecida. Acoplar essa exigência ao Loomi Studio Setup garante que **todo site Loomi nasce com Wordfence ativo** — sem precisar de checklist humano.

## What Changes

- Novo módulo `Loomi_Wordfence_Check` carregado em `admin_init` que detecta se `wordfence/wordfence.php` está instalado e ativo.
- Admin notice persistente (não-dismissível) na área administrativa enquanto Wordfence estiver ausente/inativo, contendo:
  - Botão **"Instalar Wordfence agora"** quando o plugin não está instalado (acionando `Plugin_Upgrader` contra `wp.org/plugins/wordfence`).
  - Botão **"Ativar Wordfence"** quando está instalado mas inativo.
- Endpoint admin protegido por nonce + capability `install_plugins` que executa o download/install/activate em uma única ação.
- Adicionar header `Requires Plugins: wordfence` no `loomi-studio-setup.php` (WP 6.5+ usa nativamente; em 6.0-6.4 fica como hint sem efeito — nosso check próprio cobre essas versões).
- Sem bloqueio de ativação do Loomi (decisão consciente — admins sem `install_plugins` ainda conseguem configurar o resto do Loomi enquanto pedem pro host instalar Wordfence).

## Capabilities

### New Capabilities
- `wordfence-dependency`: detecção do estado do Wordfence + admin notice persistente + one-click install/activate via wp.org repository.

### Modified Capabilities
<!-- Nenhuma capability existente muda comportamento; este é um add-on independente. -->

## Impact

- **Novo arquivo**: `includes/modules/class-loomi-wordfence-check.php`.
- **Edição em `loomi-studio-setup.php`**: adicionar `require_once` do novo módulo, chamar `Loomi_Wordfence_Check::init()` no bootstrap, adicionar header `Requires Plugins: wordfence`.
- **Edição em `README.md`**: documentar dependência (seção Instalação).
- **Sem mudança em banco**: nenhuma option/transient novo (estado é derivado de `is_plugin_active`).
- **Dependência externa runtime**: `wp.org/plugins/wordfence` durante o one-click install (timeout de 30s do `Plugin_Upgrader`). Em redes air-gapped o botão falha graciosamente; admin precisa instalar manualmente.
- **Compatibilidade**: WP ≥ 6.0 (header `Requires Plugins:` ignorado em 6.0-6.4, comportamento garantido pelo nosso check próprio).
- **Performance**: zero impacto no front-end; check só roda em `admin_init` (uma chamada `is_plugin_active` é O(1)).
