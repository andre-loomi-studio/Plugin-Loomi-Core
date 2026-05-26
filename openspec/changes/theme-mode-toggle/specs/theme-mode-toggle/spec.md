## ADDED Requirements

### Requirement: Theme setting with 3 valid values
The plugin SHALL provide a setting `loomi_theme` accepting only the values `'dark'`, `'light'`, or `'auto'`. The default value SHALL be `'dark'`. The setting SHALL be persisted in the existing `Plugin::OPTION_KEY` option.

#### Scenario: Default value on fresh install
- **WHEN** the plugin is freshly activated
- **THEN** `Settings_Repository::get('loomi_theme')` returns `'dark'`

#### Scenario: Invalid value rejected
- **WHEN** the sanitizer receives `loomi_theme = 'rainbow'`
- **THEN** the value is rejected and the previous valid value is kept (or `'dark'` if no previous value); a settings error is added

#### Scenario: Each valid value persisted
- **WHEN** the admin saves `loomi_theme = 'light'` then loads the option
- **THEN** the stored value is `'light'`
- **AND** the same for `'auto'`

### Requirement: Body class reflects current theme
The plugin SHALL add a class `loomi-theme-<value>` (where `<value>` is one of `dark`, `light`, `auto`) to the `<body>` element on every admin page via the `admin_body_class` filter.

#### Scenario: Dark mode renders class
- **WHEN** `loomi_theme` is `'dark'` and an admin page renders
- **THEN** the body element has class `loomi-theme-dark`

#### Scenario: Class updates after save
- **WHEN** admin changes the setting to `'light'` and reloads any admin page
- **THEN** the body class changes to `loomi-theme-light`

### Requirement: CSS variables drive all colored properties
The plugin's admin CSS files (`assets/admin.css` and `assets/admin-global.css`) SHALL use CSS custom properties (variables) for all theme-dependent colors. No hardcoded hex values for background, text, border, or surface colors SHALL appear outside the variable definition blocks. The accent yellow (`#FBD603`) MAY remain hardcoded since it does not change with theme.

#### Scenario: No hardcoded dark bg outside variables
- **WHEN** the CSS files are grepped for `#000000` or `#000;` or `rgba(0, 0, 0`
- **THEN** matches appear only inside `:root`, `body.loomi-theme-*`, or `@media (prefers-color-scheme:)` variable definition blocks

### Requirement: Light mode applies inverted palette
When `loomi_theme = 'light'`, the plugin SHALL apply a light palette: white background (`#ffffff`), dark text (`#0a0a0a`), light gray borders. The yellow accent SHALL remain `#FBD603`. All text/bg combinations SHALL meet WCAG AA contrast (≥ 4.5:1 for normal text).

#### Scenario: Light theme bg is white
- **WHEN** `loomi_theme = 'light'` and the settings page renders
- **THEN** the computed background color of `.loomi-studio-wrap` resolves to `#ffffff` (or equivalent light shade)

#### Scenario: Contrast meets WCAG AA in light mode
- **WHEN** light theme is active
- **THEN** text color `#0a0a0a` on background `#ffffff` has contrast ratio ≥ 18:1 (well above AA threshold)

### Requirement: Auto mode follows system color scheme
When `loomi_theme = 'auto'`, the plugin SHALL apply dark colors by default but SHALL switch to light colors when `@media (prefers-color-scheme: light)` matches the user's OS/browser preference.

#### Scenario: Auto + OS dark = renders dark
- **WHEN** `loomi_theme = 'auto'` and the user's OS prefers dark mode
- **THEN** the panel renders with dark colors (same as `loomi_theme = 'dark'`)

#### Scenario: Auto + OS light = renders light
- **WHEN** `loomi_theme = 'auto'` and the user's OS prefers light mode
- **THEN** the panel renders with light colors

### Requirement: Theme toggle UI in settings panel
The plugin SHALL render a theme selector in the Dashboard tab of the settings panel with three options (Dark / Light / Auto). The selector SHALL submit the chosen value to `loomi_theme`. Layout SHALL be a segmented control or radio group, clearly labeled.

#### Scenario: 3 options rendered
- **WHEN** the Dashboard tab renders
- **THEN** the HTML contains 3 input elements (radio or equivalent) with values `'dark'`, `'light'`, `'auto'`

#### Scenario: Currently active option marked
- **WHEN** `loomi_theme = 'light'` and the tab renders
- **THEN** the radio with value `'light'` is `checked`

### Requirement: Welcome widget on WP Dashboard respects theme
The `loomi_welcome_widget` rendered at `/wp-admin/index.php` SHALL invert its colors when light theme is active: white background, dark text, light borders. Yellow accent and CTA button keep yellow + black text.

#### Scenario: Light theme widget bg
- **WHEN** `loomi_theme = 'light'` and the dashboard page renders
- **THEN** the widget's container has a light/white background instead of black

### Requirement: Behavioral parity — full test suite passes
The plugin SHALL pass its full WP-PHPUnit test suite after the theme toggle change. New tests SHALL be added for theme value validation, default, body class application.

#### Scenario: Suite passes with theme toggle changes
- **WHEN** `bash tests/run.sh` is executed
- **THEN** all existing 114 tests plus the new theme tests pass with 0 failures
