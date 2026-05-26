## ADDED Requirements

### Requirement: Toggle `hide_admin_endpoint` in settings
The plugin SHALL add a boolean setting `hide_admin_endpoint` defaulting to `true`. The Tab_Slug page SHALL expose a checkbox to control it, with descriptive help text about the trade-off.

#### Scenario: Default is on for new installs
- **WHEN** the plugin is freshly activated
- **THEN** `Settings_Repository::get_bool('hide_admin_endpoint')` returns `true`

#### Scenario: Toggle persists when changed
- **WHEN** admin unchecks the toggle and saves
- **THEN** `Settings_Repository::get_bool('hide_admin_endpoint')` returns `false`

### Requirement: `/wp-admin/` returns 404 for unauthenticated visitors when toggle is on
The plugin SHALL register an `auth_redirect` action hook with priority `0` that intercepts unauthenticated access to `/wp-admin/*` and renders a 404 page (using the same `render_not_found()` helper as the login slug gate), preventing WordPress from issuing a 302 redirect that would leak the slug in the `Location` header.

#### Scenario: Anonymous GET to /wp-admin/ returns 404 without Location header
- **WHEN** an unauthenticated visitor sends `GET /wp-admin/`
- **THEN** the response status is 404
- **AND** the response does NOT include a `Location` header pointing to the custom slug

#### Scenario: Logged-in user accesses /wp-admin/ normally
- **WHEN** an authenticated user (any role) accesses `/wp-admin/`
- **THEN** the admin loads normally (200 status)

#### Scenario: Toggle off restores default redirect behavior
- **WHEN** `hide_admin_endpoint` is `false` and an unauthenticated visitor accesses `/wp-admin/`
- **THEN** WordPress responds with its default 302 redirect to the login URL (which goes through the `login_url` filter)

### Requirement: Hardening does not affect REST API or AJAX
The plugin's admin endpoint hardening SHALL NOT interfere with:
- REST API requests (URLs matching `/wp-json/*`)
- AJAX requests (`wp_doing_ajax()` returns true)
- WP-Cron requests (`wp_doing_cron()` returns true)

#### Scenario: REST request not 404'd
- **WHEN** an unauthenticated `GET /wp-json/wp/v2/posts` is sent
- **THEN** WordPress handles it normally (200 or 401, never 404 from our gate)

#### Scenario: AJAX request passes through
- **WHEN** an unauthenticated `POST /wp-admin/admin-ajax.php?action=heartbeat` is sent
- **THEN** WordPress handles it normally (admin-ajax does not require login by default)

### Requirement: Emergency override via `wp-config.php` constant
The plugin SHALL respect the constant `LOOMI_STUDIO_DISABLE_HARDENING` if defined. When defined and truthy, the admin endpoint hardening SHALL be disabled regardless of the toggle value, providing an escape hatch for admins locked out of the panel.

#### Scenario: Constant overrides toggle
- **WHEN** `define('LOOMI_STUDIO_DISABLE_HARDENING', true)` is set in `wp-config.php`
- **AND** `hide_admin_endpoint` is `true` in settings
- **THEN** unauthenticated `/wp-admin/` falls back to WP default redirect (does NOT 404)

### Requirement: Tab UI explains the trade-off
The plugin's login-slug tab SHALL render a description below the `hide_admin_endpoint` checkbox explaining: (a) what the toggle does (404 on `/wp-admin/`), (b) the security benefit (prevents slug leak via Location header), (c) the UX trade-off (admin must remember the custom slug).

#### Scenario: Description text present
- **WHEN** the login-slug tab renders
- **THEN** the HTML contains text mentioning "Location" or "header" (security) and "lembrar" or "trade-off" (UX)
