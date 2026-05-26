## ADDED Requirements

### Requirement: Anti-spam toggle hierarchy
The plugin SHALL provide 5 boolean settings (all defaulting to `true`): `anti_spam_enabled` (kill switch), `anti_spam_honeypot`, `anti_spam_time_check`, `anti_spam_comment_lockdown`, `anti_spam_akismet_autoconfig`. When `anti_spam_enabled` is `false`, all sub-features SHALL be inactive regardless of individual toggle state.

#### Scenario: Defaults are all on for fresh install
- **WHEN** the plugin is freshly activated
- **THEN** all 5 anti-spam settings return `true` via `Settings_Repository::get_bool`

#### Scenario: Master switch off disables all sub-features
- **WHEN** `anti_spam_enabled` is `false` and `anti_spam_honeypot` is `true`
- **THEN** no honeypot field is rendered in login form HTML

### Requirement: Honeypot field rendered in native WP forms
When `anti_spam_enabled` and `anti_spam_honeypot` are both `true`, the plugin SHALL render an invisible field named `loomi_hp` in the WP login form, registration form, and comment form. The field SHALL be hidden via CSS (positioned off-screen) and marked `aria-hidden="true"` and `tabindex="-1"` and `autocomplete="off"` to prevent humans and accessibility tools from filling it accidentally.

#### Scenario: Login form contains honeypot
- **WHEN** the login form renders
- **THEN** the HTML output contains `name="loomi_hp"` inside a container with `aria-hidden="true"` and styled to be off-screen

#### Scenario: Comment form contains honeypot
- **WHEN** the comment form renders on a post
- **THEN** the HTML output contains `name="loomi_hp"` inside an off-screen container

### Requirement: Honeypot submission rejected
When a form submission arrives with a non-empty `loomi_hp` value, the plugin SHALL reject the submission via the appropriate WP filter (`authenticate` → `WP_Error`, `pre_comment_approved` → `'spam'`, `registration_errors` → error).

#### Scenario: Login with filled honeypot fails
- **WHEN** a POST to login arrives with `loomi_hp=anything`
- **THEN** authentication fails with a WP_Error (no successful login regardless of correct credentials)

#### Scenario: Comment with filled honeypot marked as spam
- **WHEN** a comment is submitted with `loomi_hp=link-spam-here`
- **THEN** the comment's approved status is `'spam'`

### Requirement: Time check rejects sub-2s submissions
When `anti_spam_time_check` is `true`, the plugin SHALL render a hidden `loomi_t` field with the current Unix timestamp and reject form submissions where `time() - intval($_POST['loomi_t']) < 2`.

#### Scenario: Bot submits instantly
- **WHEN** a comment is POSTed with `loomi_t` value equal to `time()` (delta = 0s)
- **THEN** the comment is rejected/marked spam

#### Scenario: Human submits after delay
- **WHEN** a comment is POSTed with `loomi_t` value 5 seconds in the past
- **THEN** the time check passes (other filters still apply)

#### Scenario: Missing timestamp treated as bot
- **WHEN** a POST arrives without `loomi_t` (bot stripped it)
- **THEN** the submission is rejected

### Requirement: Comment lockdown disables pingback and forces moderation
When `anti_spam_comment_lockdown` is `true`, the plugin SHALL set the WP options `default_pingback_flag=0`, `default_ping_status='closed'`, `comment_moderation=1`, `comment_whitelist=0` on activation/save, and filter `xmlrpc_methods` to remove the `pingback.ping` method.

#### Scenario: Pingback method removed from XML-RPC
- **WHEN** comment lockdown is on and a remote site attempts pingback via XML-RPC
- **THEN** the `pingback.ping` method is not exposed (returns error)

#### Scenario: Comments held for moderation by default
- **WHEN** lockdown is on and a visitor submits a comment
- **THEN** the comment's status is `0` (pending) not `1` (approved), regardless of WP defaults

### Requirement: Akismet auto-configured via constant
When `anti_spam_akismet_autoconfig` is `true` AND the constant `LOOMI_AKISMET_KEY` is defined AND the Akismet plugin (`akismet/akismet.php`) is active, the plugin SHALL ensure the WP option `wordpress_api_key` equals the constant value (updating only if it differs).

#### Scenario: Constant defined and Akismet active
- **WHEN** `LOOMI_AKISMET_KEY = 'abc123'` is defined in `wp-config.php`, Akismet plugin is active, and a request fires `admin_init`
- **THEN** `get_option('wordpress_api_key')` returns `'abc123'`

#### Scenario: Constant not defined
- **WHEN** the constant `LOOMI_AKISMET_KEY` is not defined
- **THEN** the plugin does NOT touch `wordpress_api_key` option

#### Scenario: Akismet plugin not active
- **WHEN** the constant is defined but Akismet plugin is not active
- **THEN** the plugin does NOT touch `wordpress_api_key` option (no-op until Akismet is activated)

### Requirement: Anti-Spam tab in settings page
The plugin SHALL add a 5th tab "Anti-Spam" to the settings page (`Tab_Anti_Spam` implementing `Loomi_Settings_Tab`) rendering all 5 toggles with descriptive help text. The tab SHALL appear after the "Role Cliente" tab.

#### Scenario: Tab renders all toggles
- **WHEN** the anti-spam tab renders
- **THEN** the HTML contains 5 checkboxes corresponding to the 5 settings, each with a label and help text describing the technique
