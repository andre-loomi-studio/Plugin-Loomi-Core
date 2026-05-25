## ADDED Requirements

### Requirement: Loomi Client role is created on plugin activation
The plugin SHALL register a custom role `loomi_client` (display name "Cliente Loomi") on `register_activation_hook` with a reduced capability set: it SHALL inherit `editor` capabilities MINUS `edit_theme_options`, `manage_options`, `list_users`, `edit_users`, `delete_users`, `create_users`, `promote_users`, `install_plugins`, `activate_plugins`, `edit_plugins`, `update_plugins`, `delete_plugins`, `switch_themes`, `install_themes`, `edit_themes`, `update_themes`, `delete_themes`.

#### Scenario: Plugin is activated on a fresh site
- **WHEN** an admin activates the Loomi Studio Setup plugin
- **THEN** `get_role('loomi_client')` returns a role object with the reduced capability set

#### Scenario: Role already exists on re-activation
- **WHEN** the plugin is deactivated and re-activated
- **THEN** the role is not duplicated and its capabilities are reset to the canonical reduced set

### Requirement: Loomi Client role visibility can be toggled per site
The plugin SHALL provide a toggle `client_role_enabled` that controls whether `loomi_client` is selectable when creating/editing users. When off, the role is HIDDEN from the role dropdown but EXISTING users with that role retain it and can still log in.

#### Scenario: Toggle OFF hides role from dropdown
- **WHEN** `client_role_enabled` is `false` and an admin opens "Add New User"
- **THEN** `loomi_client` is not present in the role select

#### Scenario: Existing client user can still log in
- **WHEN** `client_role_enabled` is `false` and a user with role `loomi_client` logs in
- **THEN** login succeeds and the user's capabilities are unchanged

### Requirement: Role is removed on plugin uninstall, not deactivation
The plugin SHALL remove the `loomi_client` role only on `uninstall.php` (full uninstall), not on simple deactivation. Users currently assigned `loomi_client` at uninstall time SHALL be reassigned to `subscriber` to preserve their accounts.

#### Scenario: Plugin is deactivated
- **WHEN** an admin deactivates (but does not uninstall) the plugin
- **THEN** `get_role('loomi_client')` still returns the role

#### Scenario: Plugin is uninstalled with existing client users
- **WHEN** an admin uninstalls the plugin and there are 3 users with role `loomi_client`
- **THEN** the role is removed AND those 3 users now have role `subscriber`

### Requirement: Loomi Client cannot escalate privileges
The plugin SHALL ensure that `loomi_client` cannot perform any action that requires the removed capabilities, even through direct URL access.

#### Scenario: Client user opens /wp-admin/plugins.php
- **WHEN** a `loomi_client` user navigates to `/wp-admin/plugins.php`
- **THEN** WordPress returns "Sorry, you are not allowed to access this page."

#### Scenario: Client user opens /wp-admin/users.php
- **WHEN** a `loomi_client` user navigates to `/wp-admin/users.php`
- **THEN** WordPress denies access
