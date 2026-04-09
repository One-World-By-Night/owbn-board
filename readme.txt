# OWBN Board

The unified working dashboard for One World by Night. Every site's landing page becomes your workspace.

Version: 0.2.1
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

### Module Status

Legend: [BUILT] built · [SPEC] spec only, not built · [FUTURE] future state

### Core Modules (enabled by default on fresh install)

- [BUILT] notebook -- Shared group notebook (TinyMCE, scoped by ASC role)
- [BUILT] activity -- Activity feed aggregator
- [BUILT] message -- Lightweight group chat
- [BUILT] calendar -- Upcoming dates aggregator with chronicle-session contributor, per-user filters, UTC to local conversion
- [BUILT] search -- Universal search dispatcher with Cmd+K shortcut
- [BUILT] pinned-links -- Personal bookmarks

### Communication & Documentation Modules

- [BUILT] newsletter -- link feed of published newsletter editions
- [BUILT] visitors -- cross-chronicle character travel log
- [BUILT] sessions -- chronicle session log (title, summary, notes, attendance, player sharing)
- [BUILT] resources -- articles (CPT) + curated links library
- [BUILT] handoff -- persistent staff diary scoped by role group across transitions
- [BUILT] events -- upcoming events marketing board with approval workflow, banner uploads, RSVPs, calendar integration, [owbn_events] shortcode for embedding on public pages
- [BUILT] errata -- recent bylaw changes feed with per-user time window (7/30/90 days), reads bylaw-clause-manager data

### Admin & Launcher Modules

- [BUILT] portals -- quick-access launcher tiles for archivist office (OAT), territory manager, and exec vote actions. Shows live counts and deep links when the tool is installed locally; on other sites the tile redirects via SSO to the correct OWBN host.
- [BUILT] ballot -- unified card-based ballot. Tile shows the first 6 open votes; [owbn_ballot] shortcode renders full-page ballot with Submit All button. Delegates vote casting to wp-voting-plugin's cast-ballot AJAX endpoint. Supports FPTP, RCV, change-vote.
- [BUILT] tile-access -- admin editor at OWBN Board > Tile Access for per-tile read/write role overrides and Share Level content scoping. Share Level lets a single tile show many group-scoped views (e.g. one notebook tile that switches between multiple chronicles and coordinator positions). Overrides extend the owbn_board_layout option and keep being enforced when the module is disabled.

### Not Yet Built

- [SPEC] dues -- chronicle dues tracking and PayPal payment
- [SPEC] metrics -- platform health dashboard (web team only)
- [SPEC] i18n -- pt/BR to en/US terminology glossary
- [SPEC] npcs -- recurring NPC roster with rich profiles
- [SPEC] downtime -- between-game action submission and resolution
- [SPEC] notifications-inbox -- UI wrapper around owbn-notifications (requires that plugin to exist)

### Future State

- [FUTURE] mediation -- dispute/misconduct intake and tracking (sensitive, restricted access, deferred)

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

## Changelog

### 0.2.1

- Fixed: tile-access save silently disabled tiles. Saving any access override (read/write/share-level) for a tile that had no prior layout entry caused save_site_layout to default enabled=false, hiding the tile from the dashboard. tile_access_save_config now seeds the entry from the tile registration so saving an override leaves the tile enabled.
- Fixed: exec/* role pattern matched no exec users. The 2-segment pattern was invisible to OWBN's 3-segment exec roles like exec/hc/coordinator. Six tiles affected (notebook, message, handoff, calendar, search, activity). All updated to exec/*/*.
- Fixed: ballot Submit All button silently failed because it passed the owbn_board nonce to wp-voting-plugin's cast-ballot endpoint, which validates with the wpvp_public nonce. Now localizes wpvp_nonce via wp_create_nonce('wpvp_public') and the JS uses that. The JS now also surfaces wpvp's actual error message instead of generic "Vote failed" so users can see when role selection is required (multi-eligible-role users still need the wpvp native ballot until role-selection UI is added).

### 0.2.0

- New module: tile-access -- admin editor at OWBN Board > Tile Access for per-tile read/write role overrides and Share Level content scoping. Overrides extend the existing owbn_board_layout option so they survive when the module is disabled (only the editor UI disappears; core continues enforcing the stored overrides).
- Share Level lets a single tile render many group-scoped views via a group selector. A user with roles across multiple chronicles/coordinator positions sees one notebook tile that switches between all their group notebooks without a page reload. Read and write are evaluated independently -- any matching role grants the permission, no "lower role blocks higher role" semantics.
- Notebook tile is the first consumer -- honors Share Level when set (group-keyed role_path, multi-group selector) and falls back to the legacy best-role picker otherwise. Existing notebooks remain readable whenever Share Level is unset.
- Layout save AJAX now merges incoming deltas with the stored layout instead of replacing wholesale, so drag-to-reorder no longer wipes per-tile access overrides.
- Upgrade migration: a one-time owbn_board_tile_access_migrated flag auto-adds the tile-access module to the enabled list on the first pageload after upgrade, without being re-added after an admin disables it.

### 0.1.0

- Full rewrite from the deprecated v0.9.0. New tile-based architecture with internal module system.
- Built-in modules: notebook, activity, message, calendar, search, pinned-links.
- Calendar aggregates chronicle sessions with per-user filters and timezone-aware display.
- Added modules: newsletter, visitors, sessions, resources, handoff, events, errata, portals, ballot.

## License

GPL-2.0-or-later
