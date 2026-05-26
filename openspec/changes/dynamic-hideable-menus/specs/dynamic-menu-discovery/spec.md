## ADDED Requirements

### Requirement: Core HIDEABLE_MENUS reduced to 5 actually-visible menus
The plugin SHALL define `Settings_Repository::HIDEABLE_MENUS` with exactly these 5 entries — the menus that a default `editor` (and `loomi_client`) actually sees in the admin sidebar:
- `edit.php` → Posts
- `edit.php?post_type=page` → Páginas
- `edit-comments.php` → Comentários
- `upload.php` → Mídia
- `tools.php` → Ferramentas

The plugin SHALL NOT include `themes.php`, `plugins.php`, `users.php` in `HIDEABLE_MENUS`, because WordPress already hides those menus automatically for users without the corresponding capabilities (`switch_themes`/`edit_theme_options`, `activate_plugins`, `list_users`).

#### Scenario: Core list has exactly 5 entries
- **WHEN** `Settings_Repository::HIDEABLE_MENUS` is read
- **THEN** the array has exactly 5 entries

#### Scenario: Pages slug present
- **WHEN** `Settings_Repository::HIDEABLE_MENUS` is read
- **THEN** the array contains key `edit.php?post_type=page` with label `Páginas`

#### Scenario: Redundant slugs removed
- **WHEN** `Settings_Repository::HIDEABLE_MENUS` is read
- **THEN** the array does NOT contain `themes.php`, `plugins.php`, or `users.php`

#### Scenario: Defaults pre-mark all 5 core slugs
- **WHEN** `Settings_Repository::defaults()['hidden_menus']` is read on a fresh install
- **THEN** the array contains exactly the 5 core slugs from `HIDEABLE_MENUS`

### Requirement: Settings tab shows educational disclaimer
The plugin's hide-menus tab SHALL render an informational notice (`<div class="notice notice-info inline">` or equivalent) at the top of the field group, explaining that the list contains only menus visible to non-admin users and that WordPress already hides Plugins/Themes/Users/Settings via native capability gating.

#### Scenario: Disclaimer rendered when tab loads
- **WHEN** the hide-menus tab renders
- **THEN** the HTML contains a notice mentioning that WP already hides Plugins, Themes, Users, and Settings for users without the relevant capabilities

### Requirement: Dynamic discovery of public CPTs as hideable menus
The plugin SHALL provide a method `Settings_Repository::hideable_menus(): array` that returns an associative array of menu slugs to human-readable labels, combining the five hardcoded WordPress core menus with all public Custom Post Types currently registered on the site (filtered by `show_ui = true`, `show_in_menu = true`, `_builtin = false`).

#### Scenario: Returns core menus when no plugins register CPTs
- **WHEN** `Settings_Repository::hideable_menus()` is called on a vanilla WP install
- **THEN** the result contains exactly the five `HIDEABLE_MENUS` keys: `edit.php`, `edit.php?post_type=page`, `edit-comments.php`, `upload.php`, `tools.php`

#### Scenario: Adds CPT slug when post type is registered
- **WHEN** a post type `mock_cpt` is registered via `register_post_type` with `show_ui=true` and `show_in_menu=true`, then `Settings_Repository::hideable_menus()` is called
- **THEN** the result contains an entry with key `edit.php?post_type=mock_cpt` and value derived from the CPT's `labels->menu_name` (or `labels->name` as fallback)

#### Scenario: Does not add builtin CPTs
- **WHEN** `Settings_Repository::hideable_menus()` is called
- **THEN** the result does NOT contain `edit.php?post_type=post` or `edit.php?post_type=page` (built-in CPTs are excluded; `post` is already covered by `edit.php` core entry)

#### Scenario: Does not add CPTs hidden from menu
- **WHEN** a CPT is registered with `show_in_menu=false` or with `show_in_menu=string` (submenu of another)
- **THEN** the result does NOT contain an entry for that CPT

### Requirement: Result is memoized per-request
The plugin SHALL cache the result of `hideable_menus()` in a static class member for the duration of the request. `Settings_Repository::clear_cache()` SHALL invalidate this memo together with the main settings cache.

#### Scenario: Repeated calls do not re-query post types
- **WHEN** `Settings_Repository::hideable_menus()` is called twice in the same request
- **THEN** `get_post_types()` is invoked at most once

#### Scenario: clear_cache invalidates memo
- **WHEN** `Settings_Repository::clear_cache()` is called after `hideable_menus()`
- **AND** a new CPT is registered and `hideable_menus()` is called again
- **THEN** the new CPT appears in the result

### Requirement: Sanitizer validates against dynamic list
The plugin's `Settings_Sanitizer::sanitize()` method SHALL validate each value in the `hidden_menus` input against `Settings_Repository::hideable_menus()` (dynamic), not against the hardcoded `HIDEABLE_MENUS` constant. Unknown slugs SHALL still be filtered out. Blacklisted slugs SHALL still be refused.

#### Scenario: CPT slug accepted in hidden_menus
- **WHEN** a CPT `product` is registered and the user submits the settings form with `hidden_menus=['edit.php?post_type=product']`
- **THEN** the saved option contains `edit.php?post_type=product` in `hidden_menus`

#### Scenario: Slug for unregistered CPT filtered out
- **WHEN** the user submits `hidden_menus=['edit.php?post_type=fictional_cpt']` and no CPT named `fictional_cpt` is registered
- **THEN** the saved option does NOT contain that slug

### Requirement: Admin menu hider supports CPT slugs
The plugin's `Loomi_Admin_Menu::hide_menus()` method SHALL remove top-level CPT menus (`remove_menu_page('edit.php?post_type=<name>')`) for users without `manage_options`, when those slugs are present in `hidden_menus`.

#### Scenario: CPT menu hidden for editor when configured
- **WHEN** `hidden_menus` contains `edit.php?post_type=product` and an editor user triggers `admin_menu`
- **THEN** the global `$menu` no longer contains an entry for the `product` CPT

#### Scenario: CPT menu still visible for admin
- **WHEN** the same configuration but the current user has `manage_options`
- **THEN** the admin still sees the CPT menu

### Requirement: Settings UI renders core and CPT menus as distinct groups
The plugin's hide-menus tab SHALL render two visually distinct sections: one labeled "WordPress" containing the seven core menus, and one labeled "Custom Post Types" containing the dynamically discovered CPT menus (sorted alphabetically by label). When no CPTs are present, the CPT section SHALL show an informational message instead of empty checkboxes.

#### Scenario: Both groups rendered with CPTs present
- **WHEN** the tab renders on a site with two CPTs registered
- **THEN** the HTML contains a "WordPress" group with 7 checkboxes and a "Custom Post Types" group with 2 checkboxes

#### Scenario: Empty CPT group shows informational text
- **WHEN** the tab renders on a site with no public non-builtin CPTs
- **THEN** the HTML contains a "Custom Post Types" group with a `<p>` message like "Nenhum Custom Post Type encontrado neste site." and no checkboxes for CPTs

### Requirement: CPTs (non-core) are not pre-hidden by default
The plugin SHALL NOT include dynamically discovered (non-core) CPT slugs in `Settings_Repository::defaults()['hidden_menus']`. Admin SHALL opt in explicitly per CPT. Core menus (the 8 in HIDEABLE_MENUS) remain pre-marked.

#### Scenario: Fresh install, default hidden_menus
- **WHEN** the plugin is freshly activated
- **THEN** `Settings_Repository::defaults()['hidden_menus']` contains exactly the 5 core slugs from `HIDEABLE_MENUS` (including `edit.php?post_type=page`), no CPT slugs from other plugins
