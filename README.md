# The Core - Collectivity Management

Shared collectivity/business layer for The Core WordPress sites.

This plugin centralizes the reusable functional modules that should not live in a commune-specific child theme.

Current version: `1.0.1-beta.51`
Release channel: beta

### Changes in 1.0.1-beta.51

- Create and edit native Elementor components from the editorial library. Communication staff manage library components through a dedicated capability. Published sections are available when composing articles.

### Changes in 1.0.1-beta.50

- Optional Publications module for municipal news. Communication staff can maintain reusable sections and compositions. Editors assemble approved sections and contributors fill a prepared composition in Gutenberg. Publishing follows WordPress permissions.
- Enable the `publications` module in the existing module registry. The workspace screen is `publications`. A site adapter can use `tccm_publication_manager_roles` to give a communication role library access without global administration rights.

### Changes in 1.0.1-beta.49

- Prepare hidden illustrations for PDF export in custom menu themes. Configure the default suppliers for new weeks and render each week’s own legend.

### Changes in 1.0.1-beta.48

- Give mobile school menus more room for dishes and display the day and date on a taller ribbon above each daily menu.

### Changes in 1.0.1-beta.47

- Add a weekly school menu guide to the private workspace for authorized menu editors. Sites can adapt the guide to their editor and approval workflow.

### Changes in 1.0.1-beta.46

- Keep the illustrated school menu at its intended layout width when Elementor’s frontend stylesheet is active.

### Changes in 1.0.1-beta.45

- Configure the French origin logos copied to the legend of each new school menu and its PDF. Ingredient-level French origin choices can be enabled independently.

### Changes in 1.0.1-beta.44

- Add a site setting for catering staff to choose a weekly menu design. It defaults to enabled. When disabled, design managers keep control and catering edits preserve the assigned design and its saved snapshot.
- Menu editors can use `TheCore_Collectivity_School_Menu_Themes::can_choose()` to show the selector. The assignment API enforces the same policy.

### Changes in 1.0.1-beta.43

- Record whether an AI-generated school-menu theme was saved as a draft, rejected during validation, or failed to save.
- Open the current user's monthly AI usage from the theme library or editor when the active The Core theme supports usage reports.

### Changes in 1.0.1-beta.42

- Add a school-menu theme editor with draft, preview, ZIP import and publication for authorized users.
- Let catering staff select a published design for a weekly menu. The menu keeps a snapshot of the selected design.
- Support optional AI-assisted design through the shared The Core theme service.

### Changes in 1.0.1-beta.41

- Separate current/upcoming school menus from a collapsed previous-weeks section, using the site's timezone and a Monday rollover without changing publication statuses.
- Reclassify cached front-end markup in the browser and show an honest empty state when no upcoming menu is published.
- Add an opt-in illustrated Autumn display to the Atomic widget and shortcode, reusing the approved composition with accessible HTML fallback and same-origin, published-only views.
- Show a computed finished-week label in the native menu list; keep explicit archived-week links, PDF and calendar exports available.
- Add regression checks for week boundaries, public visibility and illustrated views. Site-specific page assignments, cache integration and role policies remain outside this shared plugin.

### Changes in 1.0.1-beta.40

- Version the existing optional school-menu module: one private weekly post, ingredient-level food markers, an editing interface, an accessible table, an Atomic widget and calendar exports.
- Preserve the approved illustrated Autumn print composition and server-side PDF corrections, including continuous paint masks and foreground leaves; no change to existing menu data or site roles.
- Include the reusable PDF client, private configuration checks, bounded cache and scheduled preparation. The external Playwright service and its secrets are not shipped.
- Version the theme-authoring guide, v2 schema, five complete examples and their artwork. This does not add a WordPress theme designer or a per-week theme selector.
- Add standalone regression and bundle-integrity checks for the module and its theme-authoring kit.
- Start with [School menus](docs/SCHOOL_MENUS.md) and the [theme-authoring guide](docs/school-menus/THEME-GUIDE.md).

### Changes in 1.0.1-beta.39

- Add optional event programmes to the existing agenda module: structured days and activities, optional times or durations, locations and shared information.
- Include the WordPress editing interface, validation, persistence and accessible Atomic Elementor widget in Collectivity itself, without a new post type or site-specific MU-plugin.
- Keep overlapping activities, chronological ordering and native draft/save permissions; programmes without content render nothing.
- Use compact activity spacing on desktop and mobile while keeping days visually distinct.
- See [Event programmes](docs/EVENT_PROGRAMMES.md) for storage, widget setup, security and tests.

### Changes in 1.0.1-beta.38

- Extend the existing document library with native WordPress collections, list filtering and optional featured images.
- Add secondary consultation links, configurable labels and optional page counts without a site-specific data layer.
- Expose allowlisted document fields to native Elementor V4 loops through standard dynamic tags; rendering remains handled by Elementor.
- Preserve global order in `menu_order`, keep documents without an order in legacy queries, and validate metadata and public URLs.
- See [Document collections](docs/DOCUMENT_COLLECTIONS.md) for setup, permissions, native loop configuration and tests.

### Changes in 1.0.1-beta.37

- Add the disabled-by-default EcoTroc candidate PHP connector, pinned to the public `1.0.0-draft.5` package.
- Validate receipts, current acceptance state and qualified security refusals through the WordPress HTTP API, without automatic mutation retries or persistent private-response storage.
- Exercise all 54 public cases and four traces through the client; production BFF/authentication and plugin compatibility qualification remain open. See `docs/ECOTROC_CONNECTOR.md` for commands, results and limits.

### Changes in 1.0.1-beta.36

- Agenda queries and ongoing-event ordering now compare Unix timestamps without adding the site's timezone offset.
- Local event dates are parsed with seconds reset to zero; invalid dates and nonexistent DST times are rejected instead of silently normalized.
- Regression checks: `php tests/event-agenda-datetime-test.php` (standalone, no database required).

Current scope:
- menu item subtext
- event agenda on regular posts
- shared transversal taxonomies
- generic map content and map widget
- site alerts
- procedures
- documents
- school menus (disabled by default; see `docs/SCHOOL_MENUS.md`)
- transports, including GTFS-backed schedules
- Service-public / co-marquage import and display
- Elementor widgets and Elementor query integrations used by those modules
- EcoTroc candidate PHP connector (disabled by default; see `docs/ECOTROC_CONNECTOR.md`)

## Requirements

- WordPress
- Elementor
- Elementor Pro for `Loop Grid` and custom query IDs
- a recent Elementor build with Atomic Widgets / V4 enabled
- outbound HTTP access for GTFS downloads, Service-public/DILA downloads and Leaflet CDN assets
- PHP `ZipArchive` for the transport GTFS importer and Service-public ZIP importer

## Important boundaries

- This repository contains plugin code only.
- Several Elementor pages/templates were created directly in the WordPress database. They are not versioned in this repository.
- The search widget discussed earlier in the project is not part of this plugin. It lives in another plugin.
- A number of slugs, meta keys and hooks still use legacy `bellevue_*` names on purpose for backward compatibility.

## Architecture

Main bootstrap:
- `thecore-collectivity-management.php`
- `includes/class-thecore-collectivity-management.php`
- `includes/class-thecore-collectivity-elementor.php`
- `includes/class-thecore-collectivity-legacy-aliases.php`
- `includes/module-definitions.php`
- `includes/modules/`

Feature modules:
- `includes/features/class-thecore-collectivity-menu-subtext.php`
- `includes/features/class-thecore-collectivity-event-agenda.php`
- `includes/features/shared-taxonomies/`
- `includes/features/maps/`
- `includes/features/alerts/`
- `includes/features/procedures/`
- `includes/features/documents/`
- `includes/features/school-menus/`
- `includes/features/transports/`
- `includes/features/service-public/`

Historical modules are active by default. Module classes, hooks, cron callbacks, REST routes and Elementor assets are loaded only when their module is active. School menus are inactive by default, with a settings entry available to administrators. The EcoTroc candidate connector remains inactive by default; no plugin release is qualified against its pinned draft contract.

## Start here

Read these files in order:
1. `docs/HANDOFF.md`
2. `docs/MODULES.md`
3. `docs/ELEMENTOR.md`
4. `docs/MAPS.md`
5. `docs/TRANSPORTS.md`
6. `docs/SERVICE_PUBLIC.md`

## Operational notes

- On plugin activation, the transport schedule tables are installed and the GTFS cron is scheduled.
- On plugin activation, the Service-public tables are installed, the daily DILA sync cron is scheduled, and public rewrite rules are flushed.
- Module activation overrides are stored per site in `tccm_module_states` and can be finalized through `thecore_collectivity/module_enabled`.
- Transport schedules are stored in custom tables, not post meta.
- Service-public fiches are imported from official data.gouv/DILA ZIP resources into custom tables.
- The transport explorer widget expects mapped transport content in the admin before GTFS import can succeed.
- The map module stores both points and routes in the single CPT `tccm_map_item`.
- Map route geometry is stored as GeoJSON in post meta.

## Remote updates

- Source repository: `webinart/thecore-collectivity-management`
- Public distribution repository: `webinart/thecore-collectivity-management-updates`
- Distribution branch: `plugin-updates`
- Channels: `beta`, `prod`
- GitHub Actions secret expected in the source repository: `UPDATES_REPO_SSH_KEY`
- Publication helper script: `scripts/publish-plugin-updates.sh`

## Status

The plugin codebase is now documented enough for handoff, but there is still one important caveat:
- the recent Elementor V4/Atomic migrations on some database templates/pages were paused after editor instability; see `docs/HANDOFF.md`
