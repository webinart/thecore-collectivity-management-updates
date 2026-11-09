# The Core - Collectivity Management

Shared collectivity/business layer for The Core WordPress sites.

This plugin centralizes the reusable functional modules that should not live in a commune-specific child theme.

Current version: `1.0.1-beta.38`
Release channel: beta

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
- `includes/features/transports/`
- `includes/features/service-public/`

Historical modules are active by default. Module classes, hooks, cron callbacks, REST routes and Elementor assets are loaded only when their module is active. The EcoTroc candidate connector remains inactive by default; no plugin release is qualified against its pinned draft contract.

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
