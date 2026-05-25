## ADDED Requirements

### Requirement: SVG MIME type is registered for media uploads
The plugin SHALL register `image/svg+xml` as an allowed MIME type for the WordPress Media Library so that users with `upload_files` capability can upload `.svg` files. This capability SHALL be always-on (no UI toggle).

#### Scenario: Editor uploads a clean SVG file
- **WHEN** a user with `upload_files` capability uploads a file with extension `.svg` and MIME `image/svg+xml`
- **THEN** WordPress accepts the upload and creates an attachment post

#### Scenario: SVG MIME stays registered after media filters from other plugins
- **WHEN** another plugin filters `upload_mimes` before or after the Loomi plugin
- **THEN** `image/svg+xml` remains in the final allowed list (Loomi hook runs at priority `99`)

### Requirement: Uploaded SVG content is sanitized before being stored
The plugin SHALL sanitize the contents of any uploaded `.svg` file BEFORE the file is moved to its final location in `wp-content/uploads/`. Sanitization SHALL strip `<script>` tags, all `on*` event-handler attributes, `xlink:href`/`href` values starting with `javascript:`, and any tag not in the allow-list.

#### Scenario: SVG with script tag is cleaned
- **WHEN** a user uploads an SVG containing `<script>alert(1)</script>`
- **THEN** the stored file no longer contains the `<script>` element
- **AND** the rest of the SVG (paths, shapes, styles) is preserved

#### Scenario: SVG with onload attribute is cleaned
- **WHEN** a user uploads an SVG with `<svg onload="alert(1)">`
- **THEN** the stored file no longer contains the `onload` attribute

#### Scenario: SVG with javascript: href is cleaned
- **WHEN** a user uploads an SVG with `<a xlink:href="javascript:alert(1)">`
- **THEN** the `xlink:href` attribute is removed or rewritten to `#`

#### Scenario: Malformed XML is rejected
- **WHEN** a user uploads a `.svg` file that is not valid XML
- **THEN** the upload is rejected with an error message returned via `wp_handle_upload_prefilter`

### Requirement: Media Library shows a preview for SVG attachments
The plugin SHALL provide a preview URL for SVG attachments in the JS attachment data so the Media Library grid view, attachment details modal, and featured-image picker render the SVG instead of a generic file icon.

#### Scenario: SVG appears in grid view
- **WHEN** an admin opens the Media Library in grid view containing SVG attachments
- **THEN** each SVG renders inline (via its `url`) as the thumbnail

#### Scenario: SVG is selectable as featured image
- **WHEN** an editor selects an SVG attachment in the featured image picker
- **THEN** the preview shows the SVG, not a placeholder icon

### Requirement: SVG capability cannot be disabled via UI
The plugin SHALL NOT expose any setting, toggle, or filter to disable SVG support. The capability SHALL be active for the entire lifetime that the plugin is active.

#### Scenario: No SVG toggle exists in the settings page
- **WHEN** an admin opens `Configurações → Loomi Studio`
- **THEN** no field related to SVG upload is rendered
