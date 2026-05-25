## ADDED Requirements

### Requirement: Admin menus can be hidden per site via multi-select
The plugin SHALL provide a toggle `hide_menus_enabled` and a multi-select field `hidden_menus` whose options are a pre-approved list of WordPress admin menu slugs (e.g., `edit.php`, `edit-comments.php`, `tools.php`, `themes.php`, `plugins.php`, `users.php`). When the toggle is on, each selected slug SHALL be removed from the admin sidebar.

#### Scenario: Admin hides Comments and Tools
- **WHEN** admin checks `edit-comments.php` and `tools.php`, sets toggle ON, saves
- **THEN** users affected by the hide rule no longer see "Comentários" or "Ferramentas" in the admin menu

#### Scenario: Toggle OFF restores menus
- **WHEN** `hide_menus_enabled` is `false`
- **THEN** all standard menus are visible regardless of `hidden_menus` content

### Requirement: Menu hiding applies only to non-administrators by default
The plugin SHALL apply the hide rule only to users WITHOUT the `manage_options` capability so that administrators always retain full access to the admin UI, unless a future explicit "hide for admins too" sub-toggle is added.

#### Scenario: Editor with hidden Posts menu
- **WHEN** `hidden_menus` contains `edit.php` and an editor (no `manage_options`) logs in
- **THEN** the editor does NOT see the Posts menu

#### Scenario: Administrator sees all menus
- **WHEN** `hidden_menus` contains `edit.php` and an administrator logs in
- **THEN** the admin still sees the Posts menu

### Requirement: Critical menus cannot be hidden
The plugin SHALL refuse to hide the Dashboard (`index.php`) and the plugin's own settings menu, regardless of what is selected in `hidden_menus`, to prevent locking users out of basic navigation.

#### Scenario: Admin tries to hide Dashboard
- **WHEN** `hidden_menus` is saved with `index.php` selected
- **THEN** the value is filtered out at save time OR ignored at render time, and the Dashboard remains visible

### Requirement: Hide rule applies at admin_menu priority 999
The plugin SHALL register the hide callback on `admin_menu` with priority `999` so it runs after most other plugins that add menus, ensuring removal succeeds.

#### Scenario: Plugin adds a menu before Loomi removes one
- **WHEN** another plugin registers a menu at default priority and Loomi has `edit.php` in `hidden_menus`
- **THEN** `edit.php` is still successfully removed at priority `999`
