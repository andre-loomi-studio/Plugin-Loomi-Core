## ADDED Requirements

### Requirement: Brand palette via CSS variables
The plugin SHALL define CSS custom properties at the top of `assets/admin.css` for the brand palette, scoped under `.loomi-studio-wrap`. Required variables: `--loomi-black: #000000`, `--loomi-yellow: #FBD603`, `--loomi-white: #ffffff`. All color references in the file SHALL use these variables (no hardcoded hex outside the variable definitions).

#### Scenario: Variables defined and used
- **WHEN** the contents of `assets/admin.css` are inspected
- **THEN** the file contains `--loomi-black`, `--loomi-yellow`, `--loomi-white` definitions
- **AND** there are no hardcoded `#000000`, `#FBD603`, or `#ffffff` literals outside the variable definitions

### Requirement: Loomi header rendered at top of settings page
The plugin SHALL render a `<div class="loomi-header">` element at the top of the settings page (`Loomi_Settings_Page::render`), containing an SVG logo (the "loomi" wordmark) and the product name "Studio Setup". The header SHALL have black background and white text styling via the brand CSS.

#### Scenario: Header present in rendered HTML
- **WHEN** the settings page renders for an administrator
- **THEN** the HTML contains a `<div class="loomi-header">` element
- **AND** the element contains an `<svg>` with class `loomi-logo`
- **AND** the element contains the text "Studio Setup"

#### Scenario: Header is scoped within page wrapper
- **WHEN** the header is inspected
- **THEN** it is a child of `<div class="wrap loomi-studio-wrap">` (not a top-level element of wp-admin)

### Requirement: Brand styles are scoped to the plugin page only
The plugin's brand CSS rules SHALL be prefixed with `.loomi-studio-wrap` to ensure they do not leak to other admin pages. No CSS rule SHALL apply globally to `<body>`, `<input>`, `<button>`, or generic elements without the `.loomi-studio-wrap` prefix.

#### Scenario: Other admin pages unaffected
- **WHEN** an admin opens a page other than `Configurações → Loomi Studio` (e.g., Dashboard, Posts)
- **THEN** none of the brand CSS styles apply to that page

#### Scenario: All rules contain the wrapper selector
- **WHEN** `admin.css` is grepped for selectors
- **THEN** every non-variable rule starts with `.loomi-studio-wrap` (or `:root` for `:root`-level vars, if any)

### Requirement: Primary button uses brand interactivity
The settings page's primary submit button (rendered via WP's `submit_button()`) SHALL display with black background + white text in idle state, and SHALL invert to yellow background + black text on `:hover` and `:focus`.

#### Scenario: Idle state
- **WHEN** the button is not hovered or focused
- **THEN** its computed background color is `#000000` and text color is `#ffffff`

#### Scenario: Hover state
- **WHEN** the user hovers over the button
- **THEN** its computed background color is `#FBD603` and text color is `#000000`

### Requirement: Active tab is indicated by yellow underline
The nav-tab-wrapper SHALL be restyled: tabs are flat (no background), the active tab is indicated by a 3px yellow `border-bottom`. Inactive tabs use gray text; hover transitions text to black.

#### Scenario: Active tab styling
- **WHEN** a tab has the class `nav-tab-active`
- **THEN** its `border-bottom` is `3px solid #FBD603`

### Requirement: Custom checkbox styling
Checkboxes inside `.loomi-studio-wrap` SHALL be styled with `appearance: none`, 2px black border, 18px×18px size. When checked, background turns yellow (`#FBD603`) and an inset black checkmark (created via CSS pseudo-element) is shown. Focus state SHALL show a 2px yellow outline.

#### Scenario: Unchecked appearance
- **WHEN** a checkbox is not checked
- **THEN** its background is white and border is 2px black

#### Scenario: Checked appearance
- **WHEN** a checkbox is checked
- **THEN** its background is yellow `#FBD603` and a checkmark (`::after` pseudo) is visible

### Requirement: Accessibility — WCAG AAA contrast and focus visibility
All text/background color pairs in the brand UI SHALL meet **WCAG AAA contrast** (ratio ≥ 7:1 for normal text). All interactive elements SHALL have a visible focus indicator (yellow outline 2px). Color SHALL NOT be the only means of conveying state (e.g., active tab has both color change AND underline; checked checkbox has color AND checkmark glyph).

#### Scenario: Black on yellow contrast
- **WHEN** text `#000000` is shown on `#FBD603` background (button hover, checkbox check)
- **THEN** the contrast ratio is ≥ 11:1 (AAA)

#### Scenario: Focus ring present on tab navigation
- **WHEN** a user navigates tabs/checkboxes/inputs via keyboard (Tab key)
- **THEN** each focused element shows a visible 2px yellow outline

### Requirement: No external font dependencies
The brand UI SHALL use only system fonts (`system-ui, -apple-system, "Segoe UI", Roboto, sans-serif`). No Google Fonts, Adobe Fonts, or external font CDN SHALL be loaded.

#### Scenario: No external font requests
- **WHEN** the settings page loads in a browser
- **THEN** no HTTP request is made to fonts.googleapis.com, use.typekit.net, or similar font hosts

### Requirement: "Apply Loomi branding" preset in Tab_Login
The Tab_Login SHALL include a "Aplicar branding Loomi" button below the color picker that, on click, sets the `custom_login_bg_color` input value to `#000000`. The behavior SHALL be JavaScript-based (no form submit required).

#### Scenario: Click sets bg color
- **WHEN** an admin clicks the "Aplicar branding Loomi" button on the Custom Login tab
- **THEN** the `custom_login_bg_color` input's value becomes `#000000` (visible in the field; admin can still save or modify before saving)

### Requirement: Asset enqueueing remains scoped
The brand CSS file `assets/admin.css` SHALL continue to be enqueued ONLY on the settings page hook (`settings_page_loomi-studio-setup`). It SHALL NOT load on other admin pages.

#### Scenario: CSS not loaded on Dashboard
- **WHEN** an admin opens `/wp-admin/index.php` (Dashboard)
- **THEN** `assets/admin.css` is not in the page's `<link rel="stylesheet">` tags
