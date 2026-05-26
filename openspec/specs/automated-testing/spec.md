## ADDED Requirements

### Requirement: PHPUnit + WP-PHPUnit dev dependencies
The plugin SHALL declare `phpunit/phpunit ^9.6`, `wp-phpunit/wp-phpunit ^6.7`, and `yoast/phpunit-polyfills ^2.0` as `require-dev` in `composer.json`. These dependencies SHALL NOT be included in production releases (ZIP build excludes `vendor/`).

#### Scenario: Composer install creates vendor directory
- **WHEN** a developer runs `composer install`
- **THEN** `vendor/` is created containing phpunit, wp-phpunit, and polyfills binaries

#### Scenario: Production ZIP excludes test dependencies
- **WHEN** the production ZIP is built
- **THEN** the archive does NOT contain `vendor/`, `composer.json`, `composer.lock`, `phpunit.xml.dist`, or `tests/`

### Requirement: Bootstrap file loads WP test framework and plugin
The plugin SHALL provide `tests/bootstrap.php` that loads WP-PHPUnit's includes/functions.php, hooks the plugin load on `muplugins_loaded`, then loads WP-PHPUnit's bootstrap.

#### Scenario: Bootstrap loads cleanly
- **WHEN** PHPUnit starts up with `--bootstrap tests/bootstrap.php`
- **THEN** WordPress fully initializes, the plugin is loaded, and `class_exists('Loomi_SVG')` returns true before any test runs

### Requirement: Base test case provides plugin-aware utilities
The plugin SHALL provide an abstract `Loomi_TestCase` extending `WP_UnitTestCase` with helper methods: `set_settings(array)`, `login_as(string $role)`, and automatic teardown that clears `Settings_Repository` cache and deletes `Plugin::OPTION_KEY` option in `setUp()`.

#### Scenario: setUp clears settings cache and option
- **WHEN** a test class extending `Loomi_TestCase` starts a test
- **THEN** `Settings_Repository::clear_cache()` was called and `get_option(Plugin::OPTION_KEY)` returns false before the test body runs

#### Scenario: Helper login_as creates and authenticates user
- **WHEN** a test calls `$this->login_as('editor')`
- **THEN** a new user with role `editor` is created via the WP_UnitTest factory and `wp_get_current_user()->ID` matches the new user

### Requirement: SVG sanitizer test coverage
The plugin SHALL provide `SvgSanitizerTest` exercising at minimum these payloads: clean SVG (passes), `<script>` tag (removed), `on*` event handler (removed), `javascript:` href (removed), `<style>` element with `url(javascript:...)` (removed), XXE entity (rejected or neutered), billion-laughs entity expansion (rejected or neutered), `<foreignObject>` (removed), `data:image/svg+xml` href (removed), `data:image/png` href (kept), malformed XML (rejected with error message).

#### Scenario: Script tag is removed
- **WHEN** the sanitizer processes an SVG containing `<script>alert(1)</script>`
- **THEN** the output does NOT contain `<script` or `alert(1)`

#### Scenario: Malformed XML is rejected
- **WHEN** the upload prefilter processes a file with content `<svg broken xml<<<`
- **THEN** `Loomi_SVG::sanitize_on_upload($file)` returns the file array with a non-empty `error` key

### Requirement: Login slug routing test coverage
The plugin SHALL provide `LoginSlugRoutingTest` covering: `/wp-login.php` direct → 404, `/wp-login.php?reauth=1` → 404, `/wp-login.php?action=logout` while logged in → pass-through, `/wp-login.php` POST → pass-through, `/studio-access/` GET → serves login form, custom slug with redirect query → preserved.

#### Scenario: wp-login.php returns 404 for unauthenticated GET
- **WHEN** an unauthenticated user requests `/wp-login.php`
- **THEN** `gate_wp_login()` triggers `render_not_found()` which sets status 404

#### Scenario: Custom slug serves login form
- **WHEN** a request to `/studio-access/` is processed via `maybe_serve_login()`
- **THEN** `wp-login.php` is included internally and the login form HTML is output

### Requirement: Login URLs helper test coverage
The plugin SHALL provide `LoginUrlsTest` exercising `Login_URLs::build()` with: no arguments, action only, action + redirect_to, action + reauth flag, empty slug fallback.

#### Scenario: Build with action and redirect
- **WHEN** `Login_URLs::build('logout', ['redirect_to' => 'https://example.com'])` is called with slug `studio-access`
- **THEN** the result contains `action=logout` and `redirect_to=https%3A%2F%2Fexample.com` query args

### Requirement: Loomi Client role test coverage
The plugin SHALL provide `RoleTest` covering: role creation on activation, presence of expected capabilities, absence of forbidden capabilities (`manage_options`, `install_plugins`, etc.), role removal on uninstall with user reassignment to `subscriber`, editable_roles filter hides role when toggle off.

#### Scenario: Role has reduced capabilities
- **WHEN** the role `loomi_client` is created
- **THEN** `get_role('loomi_client')->capabilities` does NOT include any of the 19 forbidden caps defined in `Loomi_Role::FORBIDDEN_CAPS`

#### Scenario: Uninstall reassigns users to subscriber
- **WHEN** a user has role `loomi_client` and the plugin is uninstalled
- **THEN** after `Loomi_Role::remove()`, the user has role `subscriber` and `loomi_client` no longer exists

### Requirement: Hide menus test coverage
The plugin SHALL provide `HideMenusTest` covering: toggle off → menus all visible, toggle on + editor user → selected menus hidden, toggle on + admin user → all menus visible (admin always sees everything), blacklisted slugs (`index.php`, `options-general.php`) never hidden.

#### Scenario: Editor sees hidden menus when toggle is on
- **WHEN** `hide_menus_enabled=true`, `hidden_menus=['edit-comments.php']`, and an editor user runs `admin_menu`
- **THEN** the global `$menu` array does NOT contain an entry with slug `edit-comments.php`

### Requirement: Duplicator test coverage
The plugin SHALL provide `DuplicatorTest` covering: duplicate page with featured image (`_thumbnail_id` meta preserved), duplicate with ACF-style meta (string + array), duplicate copies taxonomy assignments, duplicate creates draft status, original post unchanged.

#### Scenario: Duplicate preserves featured image
- **WHEN** a page with `_thumbnail_id=42` is duplicated
- **THEN** the new draft also has `_thumbnail_id=42`

### Requirement: Settings Repository test coverage
The plugin SHALL provide `SettingsRepositoryTest` covering: defaults returned when option missing, individual `get()` calls hit cache (no extra DB queries), `get_bool()` coerces string "false" to bool false (regression test for the bug fixed earlier), `get_bool()` coerces "0", "1", string types correctly, `clear_cache()` forces reload.

#### Scenario: get_bool coerces string "false"
- **WHEN** `update_option(Plugin::OPTION_KEY, ['login_slug_enabled' => 'false'])` runs (CLI-style write)
- **AND** `Settings_Repository::get_bool('login_slug_enabled')` is called
- **THEN** the result is strict `false` (not truthy)

### Requirement: Settings Sanitizer test coverage
The plugin SHALL provide `SettingsSanitizerTest` covering: invalid hex color rejected, valid hex color accepted, reserved slug (`wp-admin`, `admin`) rejected with settings_error, blacklisted menu slug filtered out, unknown menu slug filtered out, integer attachment ID parsed.

#### Scenario: Reserved slug is rejected
- **WHEN** the sanitizer receives `['login_slug' => 'wp-admin']`
- **THEN** the returned array's `login_slug` matches the previously stored value (not 'wp-admin')
- **AND** `get_settings_errors(Plugin::OPTION_KEY)` returns at least one error

### Requirement: Wordfence check test coverage
The plugin SHALL provide `WordfenceCheckTest` covering all 3 states: `absent` when plugin file missing, `installed_inactive` when present but not in active_plugins, `active` when present and active. Plus: notice renders for admin, notice hidden for `loomi_client`, install button hidden when user lacks `install_plugins`.

#### Scenario: State is 'absent' when file missing
- **WHEN** `WP_PLUGIN_DIR/wordfence/wordfence.php` does NOT exist
- **THEN** `Loomi_Wordfence_Check::get_state()` returns `'absent'`

### Requirement: Updater test coverage
The plugin SHALL provide `UpdaterTest` covering: offline endpoint returns null gracefully (no warnings, under 3s), mock 200 response with valid JSON injects update entry, malformed JSON discarded, untrusted package URL host rejected, `plugins_api` serves changelog from sections.

#### Scenario: Untrusted package URL rejected
- **WHEN** the remote returns a `download_url` pointing to a different host than `LOOMI_STUDIO_UPDATE_SERVER`
- **THEN** `inject_update()` does NOT add an entry to the transient response

### Requirement: Single-command test runner
The plugin SHALL provide `tests/run.sh` (or equivalent) that creates the test database if missing, ensures `vendor/` exists, and runs the full PHPUnit suite inside the docker stack with one shell command.

#### Scenario: Developer runs all tests
- **WHEN** a developer runs `bash tests/run.sh` from the repo root
- **THEN** all 10 test classes execute and pass within 20 seconds (assuming no failures)

### Requirement: Documentation in README
The plugin's `README.md` SHALL contain a "Running tests" section with: composer install command, test database setup, command to run full suite, command to run a single test class, link to WP-PHPUnit docs.

#### Scenario: New developer onboards
- **WHEN** a developer reads README → Running tests section
- **THEN** they can install dependencies, run the suite, and interpret pass/fail output without external research
