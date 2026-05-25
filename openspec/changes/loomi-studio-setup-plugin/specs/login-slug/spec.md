## ADDED Requirements

### Requirement: Login URL can be redefined via a custom slug
The plugin SHALL provide a toggle `login_slug_enabled` and a text field `login_slug` (default `studio-access`). When enabled, the URL `https://site.tld/<login_slug>/` SHALL serve the same content as `wp-login.php`.

#### Scenario: Custom slug serves the login form
- **WHEN** `login_slug_enabled` is `true` and `login_slug` is `studio-access`, and a visitor opens `/studio-access/`
- **THEN** the standard `wp-login.php` form is rendered

#### Scenario: Toggle OFF restores default
- **WHEN** `login_slug_enabled` is `false`
- **THEN** `/studio-access/` returns a 404 and `/wp-login.php` works normally

### Requirement: Direct access to wp-login.php is blocked for unauthenticated visitors
When `login_slug_enabled` is `true`, the plugin SHALL block direct GET requests to `wp-login.php` for visitors who are not authenticated, returning a 404 via `wp_die()` with status `404`.

#### Scenario: Unauthenticated visitor opens wp-login.php
- **WHEN** an unauthenticated visitor opens `/wp-login.php`
- **THEN** the response is HTTP 404 and the login form is not exposed

#### Scenario: Authenticated user opens wp-login.php
- **WHEN** an authenticated user (any role) opens `/wp-login.php` (e.g., from a bookmark)
- **THEN** the request is allowed (default WP behavior, may redirect to profile)

#### Scenario: Logout action passes through
- **WHEN** a request hits `/wp-login.php?action=logout` (with nonce)
- **THEN** the request is allowed and the user is logged out

#### Scenario: Password-reset flow passes through
- **WHEN** a request hits `/wp-login.php` with `action` in `[lostpassword, retrievepassword, rp, resetpass, postpass]`
- **THEN** the request is allowed and the appropriate form is shown

### Requirement: Slug is sanitized and validated
The plugin SHALL sanitize `login_slug` to a URL-safe slug (`sanitize_title`) on save, reject reserved values (`wp-admin`, `wp-login`, `admin`, `login`, empty string), and refuse to save if the sanitized value is empty.

#### Scenario: Admin enters slug with spaces
- **WHEN** admin enters `Studio Access` in the slug field and saves
- **THEN** the stored value is `studio-access`

#### Scenario: Admin enters reserved slug
- **WHEN** admin enters `wp-admin` or `admin` as the slug
- **THEN** the save is rejected with a settings error and the previous value is kept

### Requirement: Rewrite rules are flushed only when slug changes
The plugin SHALL flush rewrite rules via `flush_rewrite_rules(false)` ONLY when the `login_slug` value or the `login_slug_enabled` toggle actually changes between save operations, not on every options save.

#### Scenario: Slug value changes
- **WHEN** admin changes `login_slug` from `studio-access` to `secret-door`
- **THEN** `flush_rewrite_rules(false)` is called once

#### Scenario: Unrelated setting changes
- **WHEN** admin changes only `custom_login_bg_color`
- **THEN** `flush_rewrite_rules` is NOT called
