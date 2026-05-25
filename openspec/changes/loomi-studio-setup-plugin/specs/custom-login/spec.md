## ADDED Requirements

### Requirement: Custom login visual styling can be toggled per site
The plugin SHALL provide a toggle `custom_login_enabled` in the settings page. When enabled, the `wp-login.php` screen SHALL receive custom styling injected via `login_enqueue_scripts`. When disabled, the screen SHALL render with WordPress default styles.

#### Scenario: Toggle ON applies custom styles
- **WHEN** `custom_login_enabled` is `true` and a visitor opens `wp-login.php`
- **THEN** the rendered HTML contains a `<style>` block with the custom background color and logo

#### Scenario: Toggle OFF restores default
- **WHEN** `custom_login_enabled` is `false` and a visitor opens `wp-login.php`
- **THEN** no Loomi `<style>` block is injected and the page renders with WP defaults

### Requirement: Login background color is configurable
The plugin SHALL provide a color picker field `custom_login_bg_color` that defaults to `#000000`. The configured color SHALL be applied to `body.login { background: ... }` when the custom login is enabled.

#### Scenario: Admin sets blue background
- **WHEN** admin sets `custom_login_bg_color` to `#0044ff` and saves
- **THEN** `wp-login.php` renders with `body.login` background `#0044ff`

#### Scenario: Invalid color is sanitized
- **WHEN** admin submits a non-hex value like `red; background:url(evil)`
- **THEN** the value is rejected by `sanitize_hex_color` and the previous value is kept

### Requirement: Login logo is configurable via Media Library
The plugin SHALL provide a media picker field `custom_login_logo_id` (attachment ID). When set, the logo SHALL replace the WordPress logo on `wp-login.php` at 320×120 px, centered, with `background-size: contain` and a 60px bottom margin.

#### Scenario: Admin selects a logo
- **WHEN** admin picks an image from Media Library and saves
- **THEN** `wp-login.php` renders with `.login h1 a` showing the selected image's URL as background

#### Scenario: No logo set falls back to WP logo
- **WHEN** `custom_login_logo_id` is `0` or empty
- **THEN** the WordPress default logo is rendered

#### Scenario: Logo attachment deleted
- **WHEN** the attachment referenced by `custom_login_logo_id` is deleted from Media Library
- **THEN** the login screen falls back to the WP default logo (no broken image)

### Requirement: Logo link points to site home
The plugin SHALL filter `login_headerurl` to return `home_url()` when custom login is enabled, so clicking the logo on the login page navigates to the site front page (not wordpress.org).

#### Scenario: Visitor clicks login logo
- **WHEN** `custom_login_enabled` is `true` and a visitor clicks the logo on `wp-login.php`
- **THEN** the browser navigates to the site's home URL

### Requirement: Logo title attribute reflects site name
The plugin SHALL filter `login_headertext` to return the site name from `get_bloginfo('name')` when custom login is enabled, replacing the default "Powered by WordPress" text.

#### Scenario: Visitor hovers logo
- **WHEN** `custom_login_enabled` is `true` and a visitor hovers the logo
- **THEN** the title attribute shows the site name from `get_bloginfo('name')`
