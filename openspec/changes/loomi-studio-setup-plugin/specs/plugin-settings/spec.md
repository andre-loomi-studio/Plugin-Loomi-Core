## ADDED Requirements

### Requirement: Single settings page is exposed under Settings menu
The plugin SHALL register one admin page at `Configurações → Loomi Studio` (slug `loomi-studio-setup`) accessible at `options-general.php?page=loomi-studio-setup`, requiring `manage_options` capability.

#### Scenario: Administrator opens the menu
- **WHEN** an administrator navigates to `Configurações`
- **THEN** the submenu "Loomi Studio" is present and the page loads without errors

#### Scenario: Non-admin cannot access page
- **WHEN** a `loomi_client` user navigates directly to `?page=loomi-studio-setup`
- **THEN** WordPress denies access ("Sorry, you are not allowed to access this page.")

### Requirement: Settings are stored in a single autoloaded option
The plugin SHALL persist all settings in a single `wp_options` row with key `loomi_studio_setup_settings` and `autoload = yes`. The value SHALL be an associative array including all toggles and fields defined across capabilities.

#### Scenario: Settings load with a single query
- **WHEN** any request needs plugin settings during page render
- **THEN** the settings come from the WP `alloptions` cache (no extra DB query per request)

#### Scenario: Default values used on first load
- **WHEN** the option does not yet exist (fresh install)
- **THEN** `get_option('loomi_studio_setup_settings', $defaults)` returns the defaults array with all toggles `false` except always-on items

### Requirement: Form uses Settings API with sanitize callback
The plugin SHALL register the setting via `register_setting` with a `sanitize_callback` that validates each field type (booleans for toggles, hex color for colors, attachment ID integer for the logo, sanitize_title for the slug, array of whitelisted menu slugs for `hidden_menus`).

#### Scenario: Save submits all fields in one form
- **WHEN** an admin submits the settings form via the Settings API
- **THEN** the entire `loomi_studio_setup_settings` array is updated atomically through the sanitize callback

#### Scenario: Invalid field values are rejected
- **WHEN** an admin submits a malformed value (e.g., non-hex color, unknown menu slug)
- **THEN** the sanitize callback rejects that specific value, keeps the previous one, and adds a `settings_errors()` notice

### Requirement: Settings page renders as tabs without external JS framework
The plugin SHALL render the page using sectioned tabs (`<nav class="nav-tab-wrapper">`) — one tab per capability group (Login, Login Slug, Hide Menus, Client Role) — using only WordPress's bundled assets plus a single small `admin.css` file. No React, Vue, or jQuery beyond what WP already ships SHALL be added.

#### Scenario: Settings page loads on a minimal site
- **WHEN** the plugin is active on a site without any other plugins
- **THEN** the settings page renders correctly with no console errors and no extra script enqueued beyond WP defaults and `media-upload` for the logo picker

### Requirement: Asset enqueues are scoped to the plugin page only
The plugin SHALL enqueue its admin CSS (`assets/admin.css`) and the WP media uploader only on the screen where `$hook === 'settings_page_loomi-studio-setup'`, NOT globally in the admin.

#### Scenario: Visit another admin page
- **WHEN** an admin opens any page other than the Loomi settings (e.g., Dashboard, Posts)
- **THEN** `assets/admin.css` is NOT enqueued

#### Scenario: Visit the Loomi settings page
- **WHEN** an admin opens the Loomi settings page
- **THEN** `assets/admin.css` and `wp_enqueue_media()` are enqueued
