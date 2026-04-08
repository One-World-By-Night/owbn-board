# OWBN Board

The unified working dashboard for One World by Night. Every site's landing page becomes your workspace.

Version: 0.1.0
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

## License

GPL-2.0-or-later
