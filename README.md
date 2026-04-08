# OWBN Board

The unified working dashboard for One World by Night. Every site's landing page becomes your workspace.

**Version:** 0.1.0
**Status:** Active rewrite. Replaces the old v0.9.0 approach entirely.

## What It Does

Every OWBN site has a single landing page that IS the user's workspace for OWBN activity on that site. Not a links page, not a directory — an interactive dashboard where you actually do things. Each site shows different content based on what that site is for, and each user sees different content based on who they are (their accessSchema roles).

A council member lands on council.owbn.net and sees their chronicle's shared notebook, pending votes they can cast, and a quick-message box for their team. A storyteller lands on chronicles.owbn.net and sees their chronicle's session log, pending player submissions in OAT, and the staff notebook for their game. Same plugin, completely different experience.

## How It Works

- **Tiles** are self-contained dashboard components
- **Modules** group related tiles, models, admin pages, and data
- **Per-site layout** controls which tiles appear on which site and in what order
- **accessSchema role patterns** control who sees what — parent roles inherit child access
- **Per-user customization** lets people hide, pin, reorder, and snooze tiles
- **Cross-site data** via owbn-gateway so a council dashboard can show OAT inbox items from archivist
- **TinyMCE notebook** — shared group notebook scoped to your role (`chronicle/mckn/staff`, etc.)

## Architecture

owbn-board is **monolithic** with a **module system**. Every tile belongs to a module. Core tiles (notebook, message, calendar, activity feed, search, pinned links) are built-in modules enabled by default. New LARP tools (sessions, downtime, visitors, etc.) are built as additional internal modules. Existing external OWBN plugins stay separate and contribute tiles via the public hook API.

### Module Types

**Built-in modules** (enabled by default on fresh install):

- **notebook** — Shared group notebook (TinyMCE, scoped by ASC role)
- **activity** — Activity feed aggregator (plugins contribute items)
- **message** — Lightweight group chat
- **calendar** — Upcoming dates aggregator. Built-in chronicle-session contributor expands recurring sessions into concrete dates, converts chronicle-local times to each user's browser timezone, and supports per-user filters (genre, day, session type). Other plugins add events via the `owbn_board_calendar_events` filter.
- **search** — Universal search across all OWBN data sources
- **pinned-links** — Personal bookmarks

**Built-in modules** (disabled by default, enable when ready):

- **notifications-inbox** — UI wrapper around owbn-notifications (requires that plugin to exist)

**LARP modules** (not yet built, enable as needed):

- **[visitors](owbn-board/includes/modules/visitors/README.md)** — cross-chronicle character travel
- **[npcs](owbn-board/includes/modules/npcs/README.md)** — recurring NPC roster
- **[sessions](owbn-board/includes/modules/sessions/README.md)** — Post-Event Logs, attendance, XP awards
- **[downtime](owbn-board/includes/modules/downtime/README.md)** — between-game action submission and resolution
- **[mediation](owbn-board/includes/modules/mediation/README.md)** — dispute/misconduct intake and tracking (sensitive, restricted access) — **future state**
- **[errata](owbn-board/includes/modules/errata/README.md)** — recent bylaw changes feed
- **[resources](owbn-board/includes/modules/resources/README.md)** — player and ST resource library
- **[dues](owbn-board/includes/modules/dues/README.md)** — chronicle dues tracking and PayPal payment
- **[events](owbn-board/includes/modules/events/README.md)** — upcoming events marketing board
- **[newsletter](owbn-board/includes/modules/newsletter/README.md)** — link feed of published newsletter editions
- **[metrics](owbn-board/includes/modules/metrics/README.md)** — platform health dashboard (web team only)
- **[i18n](owbn-board/includes/modules/i18n/README.md)** — pt/BR ↔ en/US terminology glossary
- **[handoff](owbn-board/includes/modules/handoff/README.md)** — persistent staff diary for institutional knowledge across role transitions

### External Plugins (stay separate, contribute tiles via hooks)

These plugins are NOT absorbed into owbn-board. They register tiles through the public hook API:

- **owbn-client** and its sub-plugins (owbn-core, owbn-entities, owbn-archivist, owbn-gateway, owbn-support) — infrastructure layer that owbn-board depends on
- **owbn-archivist-toolkit** (OAT) — workflow engine and character registry
- **wp-voting-plugin** — voting engine
- **owbn-election-bridge** — coordinator elections
- **owbn-chronicle-manager** — chronicle and coordinator directory
- **owbn-territory-manager** — territory assignments
- **bylaw-clause-manager** — bylaws
- **beyond-elysium** — LARP character management (when built)

## Tile Sizes

Tiles are placed in a 3-column grid. Each tile is sized width×height in grid cells. Allowed sizes: `1x1, 1x2, 1x3, 2x1, 2x2, 2x3, 3x1, 3x2, 3x3`. The grid height grows to fit all tiles.

## Building a New Module

See `includes/modules/README.md` for the full module authoring guide. Short version:

1. Create `includes/modules/{name}/module.php` that calls `owbn_board_register_module([...])`
2. Add `schema.php`, `models.php`, `tiles.php`, and optionally `admin.php` as needed
3. The module registry auto-discovers the new module on plugin load
4. Admin enables it via **OWBN Board > Modules**

Modules are isolated: they own their tables, expose data via filters, and can be extracted to standalone plugins later if they outgrow the monolith.

## External Plugin Integration

Any OWBN plugin can register a tile via the public hook:

```php
add_action('owbn_board_register_tiles', function () {
    owbn_board_register_tile([
        'id'          => 'myplugin:my-tile',
        'title'       => 'My Tile',
        'read_roles'  => ['chronicle/*/cm', 'exec/*'],
        'write_roles' => ['chronicle/*/cm', 'exec/*'],
        'sites'       => ['council', 'chronicles'],
        'size'        => '2x2',
        'render'      => 'myplugin_render_my_tile',
    ]);
});
```

Plugins can also contribute to the activity feed, calendar, and universal search via filter hooks:

- `owbn_board_activity_items` — contribute recent events
- `owbn_board_calendar_events` — contribute upcoming dates
- `owbn_board_search_providers` — contribute a search provider

## Requirements

- WordPress 5.8+
- PHP 7.4+
- owbn-core (for accessSchema client wrappers)

## License

GPL-2.0-or-later
