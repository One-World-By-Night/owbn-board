# OWBN Board

The unified working dashboard for One World by Night. Every site's landing page becomes your workspace.

Version: 1.0.0
Status: Active rewrite. Replaces the old v0.9.0 approach entirely.

## What It Does

Every OWBN site has a single landing page that IS the user's workspace for OWBN activity on that site. Not a links page, not a directory -- an interactive dashboard where you actually do things. Each site shows different content based on what that site is for, and each user sees different content based on who they are (their accessSchema roles).

A council member lands on council.owbn.net and sees their chronicle's shared notebook, pending votes they can cast, and a quick-message box for their team. A storyteller lands on chronicles.owbn.net and sees their chronicle's session log, pending player submissions in OAT, and the staff notebook for their game. Same plugin, completely different experience.

## How It Works

- Tiles are self-contained dashboard components
- Modules group related tiles, models, admin pages, and data
- Per-site layout controls which tiles appear on which site and in what order
- accessSchema role patterns control who sees what -- parent roles inherit child access
- Per-user customization lets people hide, pin, reorder, and snooze tiles
- Cross-site data via owbn-gateway so a council dashboard can show OAT inbox items from archivist

## Architecture

owbn-board is monolithic with a module system. Every tile belongs to a module. Core tiles (notebook, message, calendar, activity feed, search, pinned links) are built-in modules enabled by default. New LARP tools (sessions, downtime, visitors, etc.) are built as additional internal modules. Existing external OWBN plugins stay separate and contribute tiles via the public hook API.

### Built-in Modules (enabled by default)

- notebook -- Shared group notebook (TinyMCE, scoped by ASC role)
- activity -- Activity feed aggregator
- message -- Lightweight group chat
- calendar -- Upcoming dates aggregator
- search -- Universal search across OWBN data sources
- pinned-links -- Personal bookmarks

### Built-in Modules (disabled by default)

- notifications-inbox -- UI wrapper around owbn-notifications (requires that plugin to exist)

### LARP Modules (not yet built, enable as needed)

- visitors, npcs
- sessions, downtime
- conduct, diplomacy, safety
- packets, errata, canon, resources
- mentors, membership, conventions, newsletter, metrics, i18n, handoff

### External Plugins (stay separate, contribute tiles via hooks)

- owbn-client and its sub-plugins (owbn-core, owbn-entities, owbn-archivist, owbn-gateway, owbn-support)
- owbn-archivist-toolkit (OAT)
- wp-voting-plugin
- owbn-election-bridge
- owbn-chronicle-manager
- owbn-territory-manager
- bylaw-clause-manager
- beyond-elysium

## Tile Sizes

Tiles are placed in a 3-column grid. Each tile is sized width x height in grid cells. Allowed sizes: 1x1, 1x2, 1x3, 2x1, 2x2, 2x3, 3x1, 3x2, 3x3. The grid height grows to fit all tiles.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- owbn-core (for accessSchema client wrappers)

## License

GPL-2.0-or-later
