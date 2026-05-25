## ADDED Requirements

### Requirement: "Duplicar" row action appears on Posts and Pages listings
The plugin SHALL add a "Duplicar" action link to the row actions of both the Posts listing (`edit.php?post_type=post`) and the Pages listing (`edit.php?post_type=page`) for users with `edit_posts` capability. This SHALL be always-on (no UI toggle).

#### Scenario: Editor views Posts listing
- **WHEN** a user with `edit_posts` capability opens the Posts listing
- **THEN** each row shows a "Duplicar" link alongside Edit / Quick Edit / Trash / View

#### Scenario: Subscriber views Posts listing
- **WHEN** a user without `edit_posts` (e.g., subscriber) somehow opens the Posts listing
- **THEN** the "Duplicar" link does NOT appear

#### Scenario: Pages listing also shows action
- **WHEN** a user with `edit_pages` opens the Pages listing
- **THEN** each row shows a "Duplicar" link

### Requirement: Duplicate action creates a draft copy of the source
The plugin SHALL handle the "Duplicar" action by creating a new post of the same `post_type`, with `post_status = 'draft'`, title suffixed with " (cópia)", and copying `post_content`, `post_excerpt`, `post_author`, `post_parent`, `menu_order`, `comment_status`, `ping_status`.

#### Scenario: Duplicate a published page
- **WHEN** an editor clicks "Duplicar" on a published page titled "Sobre nós"
- **THEN** a new page is created with title "Sobre nós (cópia)", status `draft`, same content and excerpt

#### Scenario: Original is unchanged
- **WHEN** a duplicate is created
- **THEN** the original post's title, status, content, and metadata are unchanged

### Requirement: Duplicate copies meta, taxonomies, and featured image
The plugin SHALL copy ALL `post_meta` entries (including `_thumbnail_id`) from source to the duplicate, AND re-apply all taxonomy term assignments from source to the duplicate.

#### Scenario: Page with featured image and ACF fields is duplicated
- **WHEN** an editor duplicates a page with a featured image and 5 custom ACF fields
- **THEN** the duplicate has the same featured image AND the same 5 ACF field values

#### Scenario: Post with categories and tags is duplicated
- **WHEN** an editor duplicates a post in categories "News, Tutorials" and tags "wordpress, plugin"
- **THEN** the duplicate is assigned to the same categories and tags

### Requirement: Duplicate action is protected by nonce and capability checks
The plugin SHALL verify a nonce on every duplicate request and re-check `current_user_can('edit_post', $source_id)` before creating the duplicate, returning `wp_die()` on failure.

#### Scenario: Tampered request without valid nonce
- **WHEN** a request to the duplicate handler arrives without a valid `_wpnonce`
- **THEN** the handler calls `wp_die()` and no post is created

#### Scenario: User cannot edit source post
- **WHEN** a user lacking `edit_post` on the source ID triggers duplicate
- **THEN** the handler refuses and no post is created

### Requirement: Successful duplication redirects to the listing with notice
On success, the plugin SHALL redirect back to the same listing (`edit.php?post_type=...`) with a query arg `loomi_duplicated=1` and SHALL render an admin notice "Página/Post duplicado com sucesso."

#### Scenario: Successful duplicate flow
- **WHEN** the duplicate handler completes successfully
- **THEN** the browser is redirected to the listing URL with `loomi_duplicated=1`
- **AND** an admin notice with a success message is shown
