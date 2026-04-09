# portals module

Quick-access launcher tiles that deep-link into other OWBN plugin admin screens. This module groups three thin portal tiles — no heavy data models of its own, just counts and action buttons that route users into the right place in OAT, wp-voting-plugin, and owbn-territory-manager.

## Tiles Included

### 1. Archivist portal (`portals:archivist`)

For the archivist office + web/admin execs. Shows pending/approved/denied OAT entry counts, 5 most recent entries with status, and quick links to the OAT dashboard and character registry.

- **Default read_roles:** `exec/archivist/*`, `exec/web/*`, `exec/admin/*`
- **Data source:** `wp_oat_entries` table (from owbn-archivist-toolkit)
- **Cross-site fallback:** if OAT isn't installed locally, the tile shows "OAT lives on archivist.owbn.net" and offers the same action buttons routed through SSO (via `owbn_board_tool_url()`) to the OAT host. Host URL comes from the `owc_oat_remote_url` option set by owbn-archivist.

### 2. Territory Manager portal (`portals:territory`)

For exec membership + web/admin + all coordinators. Shows total territory count, 5 most recently modified territories, and action buttons for Add Territory, Upload (bulk import), and Manage.

- **Default read_roles:** `exec/membership/*`, `exec/web/*`, `exec/admin/*`, `coordinator/*/*`
- **Data source:** `owbn_territory` CPT (from owbn-territory-manager)
- **Cross-site fallback:** if territory-manager isn't installed locally, the tile shows "Territories are managed on chronicles.owbn.net" and offers SSO-wrapped action buttons pointing at the chronicles host. Host URL comes from the `owc_territories_remote_url` option set by owbn-core.

### 3. Exec Vote Actions portal (`portals:exec-votes`)

For the HC and two assistant HCs only. Shows draft/open/closed vote counts, list of currently open votes with close dates, and action buttons for Create Vote, Build Election (owbn-election-bridge shortcut), Manage Votes, and Results.

- **Default read_roles:** `exec/hc/coordinator`, `exec/ahc1/coordinator`, `exec/ahc2/coordinator`
- **Data source:** `wp_wpvp_votes` table (from wp-voting-plugin)
- **Conditional links:** Build Election link only appears if owbn-election-bridge is installed
- **Cross-site fallback:** if wp-voting-plugin isn't installed locally, the tile shows "Votes are managed on council.owbn.net" and offers SSO-wrapped action buttons pointing at the council host. Host URL comes from the `owc_votes_remote_url` option set by owbn-core.

## Permissions Pattern

All three tiles are role-gated via the standard owbn-board `read_roles` mechanism. The tile still renders for users whose roles don't match — the tile registry filters it out of their dashboard entirely. An admin can override the default roles per-site via **OWBN Board > Layout** (see the role editor, coming in a later update).

## Architecture Notes

- **Option A, not B** — all three tiles live in owbn-board rather than being registered from each source plugin. Simpler, and owbn-board already treats OAT/wpvp/TM as "external plugins it knows about."
- **No custom tables** — this module is pure consumer of other plugins' data.
- **No AJAX, no JS** — plain links and static server-side renders. Counts are refreshed on each page load.

## Links Used

The module knows the admin URLs for each target plugin. If those URLs change upstream, this file needs updating:

| Target | URL |
|--------|-----|
| OAT dashboard | `admin.php?page=oat` |
| OAT character registry | `admin.php?page=oat-characters` |
| OAT entry detail | `admin.php?page=oat&entry_id={id}` |
| Territory new | `post-new.php?post_type=owbn_territory` |
| Territory import | `edit.php?post_type=owbn_territory&page=owbn-tm-import` |
| Territory list | `edit.php?post_type=owbn_territory` |
| Vote new | `admin.php?page=wpvp-new` |
| Vote list | `admin.php?page=wpvp` |
| Vote results | `admin.php?page=wpvp-results` |
| Vote edit | `admin.php?page=wpvp-edit&vote_id={id}` |
| Election Bridge | `admin.php?page=owbn-election-bridge` |

## Out of Scope

- Deep data integration (the portals don't try to render OAT entries or vote ballots inline — clicking a link takes you to the source plugin's UI)
- Custom permission models per tile (uses the shared owbn-board role resolver)
- Notifications or alerts (that's what owbn-notifications will handle when it exists)
