# The Core - Collectivity Management

Shared collectivity/business layer for The Core WordPress sites.

This plugin centralizes the reusable functional modules that should not live in a commune-specific child theme.

Current version: `1.0.1-beta.9`
Release channel: beta

Current scope:
- menu item subtext
- event agenda on regular posts
- shared transversal taxonomies
- generic map content and map widget
- site alerts
- procedures
- documents
- transports, including GTFS-backed schedules
- Elementor widgets and Elementor query integrations used by those modules

## Requirements

- WordPress
- Elementor
- Elementor Pro for `Loop Grid` and custom query IDs
- a recent Elementor build with Atomic Widgets / V4 enabled
- outbound HTTP access for GTFS downloads and Leaflet CDN assets
- PHP `ZipArchive` for the transport GTFS importer

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

Feature modules:
- `includes/features/class-thecore-collectivity-menu-subtext.php`
- `includes/features/class-thecore-collectivity-event-agenda.php`
- `includes/features/shared-taxonomies/`
- `includes/features/maps/`
- `includes/features/alerts/`
- `includes/features/procedures/`
- `includes/features/documents/`
- `includes/features/transports/`

## Start here

Read these files in order:
1. `docs/HANDOFF.md`
2. `docs/ELEMENTOR.md`
3. `docs/MAPS.md`
4. `docs/TRANSPORTS.md`

## Operational notes

- On plugin activation, the transport schedule tables are installed and the GTFS cron is scheduled.
- Transport schedules are stored in custom tables, not post meta.
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
