## ADDED Requirements

### Requirement: Plugin checks a Loomi-controlled update endpoint
The plugin SHALL query a JSON endpoint defined by the constant `LOOMI_STUDIO_UPDATE_SERVER` (default `https://updates.loomi.studio/loomi-studio-setup.json`) to determine if a newer version is available. The endpoint response SHALL contain at minimum `version`, `download_url`, `requires`, `tested`, `requires_php`, and a `sections` object.

#### Scenario: Update check fetches remote metadata
- **WHEN** WordPress runs its scheduled `wp_update_plugins` check
- **THEN** the plugin issues `wp_remote_get` against `LOOMI_STUDIO_UPDATE_SERVER` and parses the JSON response

#### Scenario: Endpoint unreachable
- **WHEN** the update server returns HTTP error or a network timeout (3s) occurs
- **THEN** the plugin silently skips the update check (no admin warnings, no PHP notices) and falls back to the cached value if any

### Requirement: Update check result is cached for 12 hours
The plugin SHALL cache the update endpoint response in a transient `loomi_update_check` with a TTL of 12 hours (`12 * HOUR_IN_SECONDS`). Subsequent checks within the TTL SHALL use the cached value rather than hitting the network.

#### Scenario: Repeated checks within 12h hit cache
- **WHEN** the update check ran successfully 1 hour ago and runs again
- **THEN** no HTTP request is made; the cached response is used

#### Scenario: Cache expires after 12h
- **WHEN** the transient is older than 12 hours
- **THEN** the next check refreshes by calling the endpoint

### Requirement: Available update is exposed to WP via update transient
The plugin SHALL hook `pre_set_site_transient_update_plugins` and inject an entry for itself when the remote `version` is strictly greater than the locally installed plugin version. The injected entry SHALL include `new_version`, `package` (download URL), `url`, `slug`, `plugin`, `tested`, `requires`, `requires_php`.

#### Scenario: New version available
- **WHEN** local version is `1.0.0` and remote version is `1.1.0`
- **THEN** the WP "Plugins → Updates" screen shows "Loomi Studio Setup" with an available update

#### Scenario: Local is up-to-date
- **WHEN** local version equals remote version
- **THEN** the plugin does NOT appear on the Updates screen

### Requirement: "View details" modal is populated from the endpoint
The plugin SHALL hook `plugins_api` and serve metadata for the plugin slug so that the "View details" modal in `Plugins → Updates` shows changelog, description, and version info from the endpoint's `sections` field.

#### Scenario: Admin clicks "View details"
- **WHEN** an admin clicks the "Ver detalhes" link for the plugin update
- **THEN** the modal renders the changelog and description received from the endpoint

### Requirement: Update install uses standard WP upgrader flow
The plugin SHALL NOT implement a custom installer. The standard `Plugin_Upgrader` flow SHALL download the `package` URL, replace the plugin directory, and trigger the standard activation hooks. After a successful upgrade, the plugin SHALL clear the `loomi_update_check` transient via `upgrader_process_complete`.

#### Scenario: Admin clicks "Update Now"
- **WHEN** an admin clicks the standard WP "Update Now" button for this plugin
- **THEN** WordPress downloads the package from `download_url`, installs it, and the cache transient is cleared

#### Scenario: Failed download rolls back
- **WHEN** the download URL is unreachable during upgrade
- **THEN** WordPress's upgrader rollback runs (standard behavior) and the plugin remains on the previous version

### Requirement: Update check does not block admin pageloads
The plugin SHALL use a 3-second timeout on `wp_remote_get` against the update server and SHALL NOT make synchronous HTTP calls outside the WP update transient lifecycle.

#### Scenario: Endpoint is slow
- **WHEN** the update endpoint takes 10+ seconds to respond
- **THEN** the plugin's update check aborts at 3s and the admin page is not delayed
