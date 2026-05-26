## ADDED Requirements

### Requirement: Module contract via interface
The plugin SHALL define an interface `Loomi_Module` with a single static method `register(): void`. Every functional module (SVG handling, Login, Admin Menu, Role, Duplicate, Wordfence check, Settings page) SHALL implement this interface.

#### Scenario: Adding a new module
- **WHEN** a developer adds a new module to the plugin
- **THEN** they create a class implementing `Loomi_Module`, place it in `includes/modules/`, add a `require_once` to the bootstrap, and append the class name to the `$modules` array
- **AND** no other change is required to wire up its hooks

#### Scenario: All current modules implement the contract
- **WHEN** the plugin is loaded
- **THEN** `Loomi_SVG`, `Loomi_Login`, `Loomi_Admin_Menu`, `Loomi_Role`, `Loomi_Duplicate`, `Loomi_Wordfence_Check`, and `Loomi_Settings_Page` all implement `Loomi_Module`
- **AND** each module's `register()` is invoked exactly once during `plugins_loaded`

### Requirement: Centralized constants in `Plugin` class
The plugin SHALL provide a final class `Plugin` containing constants for shared identifiers: slug, option key, settings page slug, text domain, Wordfence plugin file, update transient key, update TTL. Modules SHALL reference these constants instead of duplicating string literals.

#### Scenario: Option key referenced consistently
- **WHEN** the codebase is grepped for the literal `'loomi_studio_setup_settings'`
- **THEN** it appears in at most one place — the `Plugin::OPTION_KEY` constant definition; all other references use `Plugin::OPTION_KEY`

#### Scenario: Wordfence file path centralized
- **WHEN** the codebase is grepped for `'wordfence/wordfence.php'`
- **THEN** it appears only in `Plugin::WORDFENCE_FILE`; the Wordfence check module reads from the constant

### Requirement: Settings persistence is separated from rendering
The plugin SHALL split the former `Loomi_Settings` god-class into at least three coherent classes:
- `Settings_Repository` — defaults, read/write, cache, boolean coercion.
- `Settings_Sanitizer` — Settings API `sanitize_callback`.
- `Settings_Page` — admin page registration, asset enqueueing, rendering orchestration.

No single class SHALL exceed 150 lines of code.

#### Scenario: Settings file count
- **WHEN** the `includes/` tree is listed
- **THEN** there are separate files for `class-settings-repository.php`, `class-settings-sanitizer.php`, and `class-settings-page.php`

#### Scenario: Repository exposes a typed boolean accessor
- **WHEN** a module needs to check a toggle (e.g., `login_slug_enabled`)
- **THEN** it calls `Settings_Repository::get_bool('login_slug_enabled')` which returns a strict `bool` regardless of how the value is stored

### Requirement: Each settings tab is its own class
The plugin SHALL define an interface `Loomi_Settings_Tab` with methods `slug(): string`, `label(): string`, `render(array $settings): void`. Each tab in the admin panel (Login, Login Slug, Hide Menus, Client Role) SHALL be a separate class implementing this interface, located in `includes/settings/tabs/`.

#### Scenario: Adding a new tab
- **WHEN** a developer adds a new tab
- **THEN** they create a class implementing `Loomi_Settings_Tab` in `includes/settings/tabs/`, register it in the `Settings_Page::tabs()` list
- **AND** the new tab appears in the admin panel with its label and rendered fields

#### Scenario: Tab classes are small and focused
- **WHEN** any tab class is inspected
- **THEN** it contains only the methods required by the interface plus optionally private helpers, and is under 80 lines

### Requirement: Login URL rewrites consolidated through a helper
The plugin SHALL provide a helper class `Login_URLs` with a single method `build($action, $extra_args): string` that constructs URLs to the custom login slug. The five filter callbacks in `Loomi_Login` (`login_url`, `logout_url`, `logout_redirect`, `lostpassword_url`, `register_url`) SHALL each be thin wrappers calling `Login_URLs::build()` with appropriate arguments. No single filter callback SHALL exceed 5 lines of body code.

#### Scenario: Login URL helper produces correct URL
- **WHEN** `Login_URLs::build('logout', ['redirect_to' => 'https://x'])` is called with slug `studio-access`
- **THEN** the returned URL is `https://<home>/studio-access/?action=logout&redirect_to=https%3A%2F%2Fx`

#### Scenario: Filter callbacks are thin
- **WHEN** the `filter_*_url` methods in `Loomi_Login` are inspected
- **THEN** each contains at most 5 lines of executable code, delegating URL construction to `Login_URLs::build()`

### Requirement: Behavioral parity after refactor
The plugin SHALL behave identically before and after the refactor. All previously validated scenarios (81 tasks in `loomi-studio-setup-plugin`) SHALL re-execute with the same results.

#### Scenario: SVG sanitizer suite still passes
- **WHEN** `test-svg-sanitizer.php` is executed after the refactor
- **THEN** 11/11 payloads still produce the correct sanitized output

#### Scenario: Login slug 404 + redirect flow still works
- **WHEN** an unauthenticated visitor hits `/wp-admin/`
- **THEN** they are redirected to `/studio-access/?reauth=1&redirect_to=/wp-admin/` (not to `/wp-login.php`)

#### Scenario: Wordfence one-click install still functional
- **WHEN** the install handler runs with a valid nonce and `install_plugins` capability
- **THEN** Wordfence is downloaded from wp.org and activated, with the notice disappearing afterward

#### Scenario: Backwards-compatible legacy class
- **WHEN** external code calls `Loomi_Settings::get('login_slug')`
- **THEN** the call returns the same value as `Settings_Repository::get('login_slug')`, with a `_deprecated_function()` notice triggered for awareness

### Requirement: Bootstrap is a small ordered list
The plugin SHALL bootstrap modules from an explicit, ordered list in `loomi-studio-setup.php`. Bootstrap code SHALL not contain per-module conditionals (e.g., `if (Settings::get('X')) { Module::init() }`); each module is responsible for its own internal toggle gating.

#### Scenario: Bootstrap simplicity
- **WHEN** the plugin's main file is inspected
- **THEN** the `plugins_loaded` callback contains a foreach loop over an ordered list of module class names, calling `::register()` on each

#### Scenario: Toggle gating is module-internal
- **WHEN** `custom_login_enabled` is false
- **THEN** `Loomi_Login::register()` still runs, but the login styling hooks (`login_enqueue_scripts`, `login_headerurl`, `login_headertext`) are conditionally added only when the toggle is true
