## ADDED Requirements

### Requirement: Plugin detects Wordfence presence and activation state
The plugin SHALL detect whether the Wordfence plugin (identified by file `wordfence/wordfence.php`) is installed and active on every admin pageload. It SHALL classify the state into one of three values: `active`, `installed_inactive`, `absent`.

#### Scenario: Wordfence is installed and active
- **WHEN** `is_plugin_active('wordfence/wordfence.php')` returns true
- **THEN** the state is `active` and no admin notice is rendered

#### Scenario: Wordfence is installed but not active
- **WHEN** the file `WP_PLUGIN_DIR/wordfence/wordfence.php` exists but `is_plugin_active(...)` returns false
- **THEN** the state is `installed_inactive` and the admin notice offers an "Ativar Wordfence" CTA

#### Scenario: Wordfence is not installed
- **WHEN** the file `WP_PLUGIN_DIR/wordfence/wordfence.php` does not exist
- **THEN** the state is `absent` and the admin notice offers an "Instalar Wordfence agora" CTA

### Requirement: Persistent admin notice while Wordfence dependency unmet
The plugin SHALL render a non-dismissible `notice-error` admin notice on every admin pageload until Wordfence is detected as `active`. The notice SHALL only be visible to users with the `activate_plugins` capability.

#### Scenario: Administrator sees notice
- **WHEN** an administrator (with `activate_plugins`) opens any admin page and the state is `absent` or `installed_inactive`
- **THEN** the notice is rendered at the top of the admin area

#### Scenario: Notice has no dismiss button
- **WHEN** the notice is rendered
- **THEN** it does NOT include the `is-dismissible` class and has no "X" close button

#### Scenario: Non-admin does not see notice
- **WHEN** a user without `activate_plugins` (e.g., `loomi_client`, `editor`) opens any admin page
- **THEN** the notice is NOT rendered

#### Scenario: Notice disappears once Wordfence is active
- **WHEN** Wordfence transitions to active (state becomes `active`)
- **THEN** the next admin pageload no longer renders the notice

### Requirement: One-click install + activate via admin-post handler
The plugin SHALL provide an admin-post handler at `admin-post.php?action=loomi_install_wordfence` that, given a valid nonce and `install_plugins` capability, downloads Wordfence from `wp.org`, installs it, and activates it. The handler SHALL redirect the user back to the page they came from with a status query arg.

#### Scenario: Admin clicks install button on a site without Wordfence
- **WHEN** an admin with `install_plugins` submits the form with a valid nonce
- **THEN** the handler calls `plugins_api('plugin_information', ['slug' => 'wordfence'])`, runs `Plugin_Upgrader::install()`, then `activate_plugin('wordfence/wordfence.php', '', false, true)`, and redirects back with `loomi_wf_status=ok`

#### Scenario: Admin clicks activate when Wordfence already installed
- **WHEN** the file exists but state is `installed_inactive` and admin submits the form
- **THEN** the handler skips install, calls `activate_plugin('wordfence/wordfence.php', '', false, true)`, and redirects with `loomi_wf_status=activated`

#### Scenario: Install fails (network error / wp.org unreachable)
- **WHEN** `Plugin_Upgrader::install()` returns a `WP_Error` (download/extract failure)
- **THEN** the handler redirects back with `loomi_wf_status=error&loomi_wf_msg=<urlencoded message>` and the next page renders a transient `notice-error` with the message

#### Scenario: Request without valid nonce
- **WHEN** a request hits the handler without a valid `_wpnonce`
- **THEN** the handler calls `wp_die()` and does not install or activate anything

#### Scenario: User lacks install_plugins capability
- **WHEN** a user with `activate_plugins` but NOT `install_plugins` submits the form
- **THEN** the handler calls `wp_die()` with a permission error and does not install

### Requirement: Install button is hidden when user cannot install plugins
The plugin SHALL render the install/activate button ONLY when the current user has the matching capability (`install_plugins` for install, `activate_plugins` for activate). Otherwise the notice SHALL show informational text directing the user to ask the site administrator.

#### Scenario: Editor with activate_plugins but no install_plugins
- **WHEN** state is `absent` and the user has `activate_plugins` but not `install_plugins`
- **THEN** the notice text is "Solicite ao administrador a instalação do Wordfence" and NO button is rendered

#### Scenario: Admin with both capabilities
- **WHEN** state is `absent` and user has both `install_plugins` and `activate_plugins`
- **THEN** the "Instalar Wordfence agora" button is rendered

### Requirement: Plugin header declares Wordfence dependency
The plugin file `loomi-studio-setup.php` SHALL include the header `Requires Plugins: wordfence` so that WordPress 6.5+ natively prevents activation when Wordfence is missing. For older WordPress versions (6.0-6.4), the header is ignored and the PHP-side check provides the equivalent UX.

#### Scenario: WP 6.5+ blocks activation without Wordfence
- **WHEN** running on WP 6.5+ and an admin tries to activate Loomi Studio Setup without Wordfence installed
- **THEN** WordPress refuses activation with a native "required plugins are missing" message

#### Scenario: WP 6.0-6.4 allows activation but shows notice
- **WHEN** running on WP 6.0-6.4 and Loomi Studio Setup activates without Wordfence
- **THEN** the plugin activates successfully AND the admin notice is rendered until Wordfence is installed and active
