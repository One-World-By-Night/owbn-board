# OWBN Board

The unified working dashboard for One World by Night. Every site's landing page becomes your workspace.

**Version:** 0.2.3
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

### Module Status

Status legend: ✅ built · 🟡 spec only, not built · 🔵 future state

**Core modules** (enabled by default on fresh install):

- ✅ **notebook** — Shared group notebook (TinyMCE, scoped by ASC role)
- ✅ **activity** — Activity feed aggregator (plugins contribute items via `owbn_board_activity_items` filter)
- ✅ **message** — Lightweight group chat scoped by ASC role
- ✅ **calendar** — Upcoming dates aggregator with built-in chronicle-session contributor, per-user filters (genre, day, session type), UTC→browser timezone conversion. Other plugins add events via the `owbn_board_calendar_events` filter.
- ✅ **search** — Universal search dispatcher with Cmd+K shortcut (providers contribute via filter)
- ✅ **pinned-links** — Personal bookmarks stored per user

**Communication & documentation modules:**

- ✅ **[newsletter](owbn-board/includes/modules/newsletter/README.md)** — link feed of published newsletter editions
- ✅ **[visitors](owbn-board/includes/modules/visitors/README.md)** — cross-chronicle character travel log
- ✅ **[sessions](owbn-board/includes/modules/sessions/README.md)** — chronicle session log (title, summary, staff notes, attendance, optional player sharing)
- ✅ **[resources](owbn-board/includes/modules/resources/README.md)** — articles (CPT) + curated links library
- ✅ **[handoff](owbn-board/includes/modules/handoff/README.md)** — persistent staff diary scoped by role group across transitions
- ✅ **[events](owbn-board/includes/modules/events/README.md)** — upcoming events marketing board with approval workflow, banner uploads, RSVPs, calendar integration, and `[owbn_events]` shortcode for embedding on any page (including public pages for logged-out visitors)
- ✅ **[errata](owbn-board/includes/modules/errata/README.md)** — recent bylaw changes feed with per-user time window (7/30/90 days), reads bylaw-clause-manager data

**Admin & launcher modules:**

- ✅ **[portals](owbn-board/includes/modules/portals/README.md)** — quick-access launcher tiles for archivist office (OAT), territory manager, and exec vote actions. Each tile shows live counts + deep links into the target plugin's admin screens when the plugin is installed locally. On sites that don't host the target tool, the tile instead redirects users to the correct OWBN site via SSO (reading hosts from owbn-core/owbn-archivist remote settings), so they land already authenticated.
- ✅ **[ballot](owbn-board/includes/modules/ballot/README.md)** — unified card-based ballot. Tile on the dashboard shows the first 6 open votes; `[owbn_ballot]` shortcode renders a full-page ballot with Submit All button that fires wp-voting-plugin's existing cast-ballot endpoint per vote. Supports FPTP radios, RCV rank dropdowns, change-vote flow, and graceful cross-site fallback to council.owbn.net.
- ✅ **[tile-access](owbn-board/includes/modules/tile-access/README.md)** — admin editor at **OWBN Board > Tile Access** for per-tile read/write role overrides and Share Level content scoping. Overrides extend the existing `owbn_board_layout` option so they survive when the module is disabled. Share Level lets one tile show many group-scoped views (e.g. a user with roles across seven chronicles/coordinator positions sees one notebook tile with a group selector that switches between all seven notebooks). Read and write evaluated independently — any matching role grants the permission, with no "lower role blocks higher role" semantics.

**Not yet built:**

- 🟡 **[dues](owbn-board/includes/modules/dues/README.md)** — chronicle dues tracking and PayPal payment
- 🟡 **[metrics](owbn-board/includes/modules/metrics/README.md)** — platform health dashboard (web team only)
- 🟡 **[i18n](owbn-board/includes/modules/i18n/README.md)** — pt/BR ↔ en/US terminology glossary
- 🟡 **[npcs](owbn-board/includes/modules/npcs/README.md)** — recurring NPC roster with rich profiles
- 🟡 **[downtime](owbn-board/includes/modules/downtime/README.md)** — between-game action submission and resolution
- 🟡 **notifications-inbox** — UI wrapper around owbn-notifications (requires that plugin to exist)

**Future state:**

- 🔵 **[mediation](owbn-board/includes/modules/mediation/README.md)** — dispute/misconduct intake and tracking (sensitive, restricted access, deferred until design conversation with stakeholders)

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

## Deployment Notes

### Per-site tile scoping

owbn-board tiles do **not** have a hardcoded `sites` filter by default. A tile registered as "available" will render on every OWBN site unless an admin disables it via **OWBN Board > Layout** on that site. This is deliberate — it gives admins flexibility — but it also means the default behavior is "show everywhere" which may not match each site's purpose.

The intended deployment pattern is:

| Site | Typical enabled tiles | Typical disabled tiles |
|---|---|---|
| **players.owbn.net** | calendar, events (public), newsletter, errata, pinned-links, search | portals, ballot admin views, handoff, tile-access admin |
| **archivist.owbn.net** | portals (archivist — local), notebook, message, activity, search | chronicle session log, territory manager portal (remote) |
| **chronicles.owbn.net** | portals (territory — local), notebook, message, sessions, visitors, handoff, calendar, activity | ballot admin, archivist portal (remote) |
| **council.owbn.net** | portals (exec votes — local), ballot, handoff, notebook (exec scope via share level), activity, calendar | chronicle-specific tiles |
| **support.owbn.net** | resources, search, pinned-links, notebook (support staff) | chronicle-specific tiles, ballot admin |
| **www.owbn.net** (future) | events (public shortcode only, not the full dashboard), calendar (public) | **do NOT put [owbn_board] on the front page** — use a marketing page and expose only public shortcodes |

The `[owbn_board]` shortcode should generally live at `/dashboard` (the default `url_path`), not the site's homepage, on public-facing sites like players and www. This keeps the homepage available for marketing / login / public event listings while still giving logged-in users a dedicated workspace URL.

### Notebook Share Level and multi-site consistency

The notebook module stores rows keyed by `role_path` with `site_id = 0` — meaning **notebooks are cross-site**. A user's chronicle notebook follows them from chronicles.owbn.net to council.owbn.net to any other site where the tile is enabled. This is by design: staff should be able to reference their notes wherever they're working.

If you want per-site isolated notebooks, that's not currently supported and would require a schema change to include site_id in the uniqueness key.

### Default tile role patterns

Several tiles (notably **notebook**) default to staff-only role patterns like `chronicle/*/cm`, `chronicle/*/hst`, `chronicle/*/staff`. These are **intentionally** narrower than `chronicle/*/*` to exclude `player`/`approved` tiers from staff tools by default. Admins who want per-tier versions (e.g. a "player notebook" for a chronicle) can broaden the patterns via **OWBN Board > Tile Access** on a per-tile, per-site basis — the registered defaults are overridden only for tiles where the admin has explicitly edited the access card.

## Changelog

### 0.2.3

- **portals refactor (round 3a)**: archivist + territory tiles now call `owc_oat_get_dashboard_counts()` / `owc_oat_get_recent_activity()` (owbn-archivist) and `owc_get_territories()` (owbn-core) instead of probing local DB tables. Counts on the archivist tile are now per-user (Assigned to me / My submissions / Watching) instead of site-wide totals. Tiles render the same content on every site regardless of whether OAT or territory-manager is installed locally.
- exec-votes portal still uses local probes — pending wpvp wrapper layer in round 3b.

### 0.2.2

- **Fixed: calendar "Every Other" recurrence used a sliding anchor.** Same chronicle showed different "every other" dates depending on when the user loaded the calendar, because the parity was reset to the first matching weekday in the requested window. Recurrence math now uses an epoch-anchored parity (`floor(timestamp / 7 days) % 2`) so dates are stable across all viewers and timezones. Canonical implementation moved to `owbn-chronicle-manager` (>=2.14.0); owbn-board keeps a fallback copy for sites without chronicle-manager.
- **Fixed: message tile feeds were siloed per exact role.** Chronicle CM, HST, staff, and player tiers each saw a different feed because the scope was keyed by raw role path. Now uses a shared group key (top + second segment, e.g. `chronicle/mckn`, `coordinator/sabbat`, `exec/hc`) so anyone in the same chronicle/office sees the same feed regardless of tier.
- **Fixed: message tile hard-depended on notebook's role picker.** Disabling the notebook module broke the message tile. Message now resolves its scope key independently with no cross-module dependency.
- **Fixed: handoff add_section accepted any handoff_id from POST.** A user with scope X could pass a handoff_id from scope Y and add a section to it. Handler now verifies the handoff row's stored scope matches the claimed scope before writing.
- **Fixed: `[owbn_ballot election_id=X]` ignored the parameter.** Any admin using the filter got ALL open votes, potentially exposing unrelated council votes on a private election page. Now reads `oeb_election_sets.positions`, extracts the vote ids, and filters wpvp_votes accordingly.

### 0.2.1

- **Fixed: tile-access save silently disabled tiles.** Saving any access override (read/write/share-level) for a tile that had no prior layout entry caused `save_site_layout` to default `enabled=false`, making the tile vanish from the dashboard. Now `tile_access_save_config` seeds the layout entry from the tile's registration when no prior entry exists, preserving `enabled=true`.
- **Fixed: `exec/*` role pattern matched no exec users.** The pattern `exec/*` expanded to a 2-segment regex `#^exec/[^/]+$#`, but OWBN exec roles are 3-segment (`exec/hc/coordinator`, `exec/archivist/coordinator`, etc.). Six tiles using this pattern (notebook, message, handoff, calendar, search, activity) were invisible to all exec users. All occurrences updated to `exec/*/*`.
- **Fixed: ballot Submit All button silently failed.** The ballot tile's Submit All flow POSTed `OWBN_BOARD.nonce` (an `owbn_board` nonce) to wp-voting-plugin's `wpvp_cast_ballot` endpoint, which validates with the `wpvp_public` nonce action. Every vote cast through the owbn-board UI failed at wpvp's nonce check. owbn-board now localizes a `wpvp_nonce` (created with `wp_create_nonce('wpvp_public')`) and the JS uses that for cast-ballot calls. Multi-eligible-role users still need to use the wpvp native ballot until role-selection UI is added — the JS now surfaces wpvp's actual error message instead of a generic "Vote failed".

### 0.2.0

- **New module: tile-access** — admin editor at **OWBN Board > Tile Access** for per-tile read/write role overrides and Share Level content scoping. Overrides extend the existing `owbn_board_layout` option so they survive when the module is disabled (only the editor UI disappears; the stored overrides keep being enforced by core).
- **Share Level** lets a single tile render many group-scoped views via a group selector. A user with roles across multiple chronicles/coordinator positions sees one notebook tile that switches between all their group notebooks without a page reload. Read and write are evaluated independently — any matching role grants the permission, with no "lower role blocks higher role" semantics.
- **Notebook tile** is the first consumer — honors Share Level when set (group-keyed `role_path`, multi-group selector) and falls back to the legacy best-role picker otherwise. Existing notebooks remain readable whenever Share Level is unset.
- **Layout save AJAX** now merges incoming deltas with the stored layout instead of replacing wholesale, so drag-to-reorder no longer wipes per-tile access overrides.
- **Upgrade migration**: a one-time `owbn_board_tile_access_migrated` flag auto-adds the tile-access module to the enabled list on the first pageload after upgrade, without being re-added after an admin disables it.

### 0.1.0

- Full rewrite from the deprecated v0.9.0. New tile-based architecture with internal module system.
- Built-in modules: notebook, activity, message, calendar, search, pinned-links.
- Calendar aggregates chronicle sessions with per-user filters and timezone-aware display.
- Added modules: newsletter, visitors, sessions, resources, handoff, events, errata, portals, ballot.

## License

GPL-2.0-or-later
