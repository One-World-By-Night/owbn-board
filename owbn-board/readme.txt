=== OWBN Board ===
Contributors: greghacke
Tags: dashboard, workspace, owbn, larp
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.3.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Unified working dashboard for One World by Night. Every site's landing page becomes a user workspace with interactive tiles.

== Description ==

OWBN Board is the single landing page for every OWBN site. Not a directory of links -- an interactive dashboard where staff read, write, vote, post, log, query, and manage OWBN activity without leaving the page.

Tiles are self-contained dashboard components contributed by any OWBN plugin. Per-site layouts control which tiles appear where, and accessSchema role patterns control who sees what.

Built-in tiles include a shared group notebook, quick message feed, activity aggregator, calendar, notifications inbox, universal search, and pinned links. Plugin-provided tiles cover OAT inbox, votes, elections, chronicle management, territory updates, bylaws, and support tickets.

== Installation ==

1. Upload `owbn-board` to `/wp-content/plugins/`
2. Activate via the Plugins menu
3. Visit OWBN Board > Layout to configure tiles for your site
4. Set the board as your site's front page or custom URL

== Requirements ==

- WordPress 5.8+
- PHP 7.4+
- owbn-core (accessSchema client wrappers)

== Changelog ==

= 0.3.2 =
- F5: calendar per-user "my chronicles only" default filter. New `chronicles_mode` filter (`mine` default, `all` opt-out) added to the filter panel. In `mine` mode, the calendar narrows session events to chronicles where the user has any ASC chronicle role. Users with no chronicle roles (exec/coordinator-only) fall through to `all` automatically so their tile isn't empty. Existing users see the narrower view on upgrade.

= 0.3.1 =
- F4: in-place DOM updates after message post, visitor add, and pin add. Replaces location.reload() with targeted prepend/append so scroll position and other tile state are preserved. Server AJAX responses now carry the render-ready payload (display_name, time_label, formatted visit date, etc.).

= 0.3.0 =
- F1: user-configurable tile size. Per-user size override via a small dropdown in the tile header. Stored in user_meta, applied after site layout override, persists across sessions. 9 size choices (1x1 to 3x3).

= 0.2.11 =
- Calendar save_filters intersects genres against owbn_genre_list. Sessions share_with_players dead flag removed from form + save path (DB column left for forward compat). Activity + search filter hooks carry contract comments about self-enforcing user scoping. README adds deployment notes for events disable caveats and public-shortcode transparency (events / ballot / resources intentionally public).

= 0.2.10 =
- defensive lows: save_site_layout tightens cache race window; tile-access rejects share_level on non-supporting tiles; message_post rejects wildcard role_path; visitors validates home_chronicle_slug against owc_get_chronicles; dead visitors_delete removed; pinned-links returns 400 at 50-pin cap (no silent eviction); newsletter validates cover_image_id as image MIME.

= 0.2.9 =
- UX + security polish: ballot tile renamed "Your Ballot" -> "Open Votes"; dead "Change vote" button + handler removed. Events save_post enforces ASC role check server-side (UI gate was insufficient). Events approval page strips shortcodes before rendering pending content. Resources CPT blocks REST API writes from non-admins via rest_pre_insert_owbn_resource filter.

= 0.2.8 =
- multi-chronicle determinism: handoff/sessions/visitors sort chronicle scopes alphabetically for stable primary selection. Sessions admin gets a chronicle picker. Sessions + visitors tiles show "(+N other chronicles)" hint for multi-chronicle staff.

= 0.2.7 =
- activity + search modules disabled by default and flagged "(Pending Development)" in labels and tile titles. Both had zero contributors/providers wired. Hook contracts stay intact for future use.

= 0.2.6 =
- events full lockdown on chronicles: CPT, admin metabox, approval queue, save_post hook, RSVP AJAX, and schema install all gated to site_slug='chronicles'. Tile, shortcode, and calendar contributor on every site read via owc_events_* cross-site wrappers (owbn-core 1.5.0) + /events/* gateway endpoints (owbn-gateway 1.4.0). RSVP on non-chronicles sites is an SSO-wrapped link to the event permalink.

= 0.2.5 =
- errata refactor: tile reads bylaw clause data through owc_bylaws_* cross-site wrappers (owbn-core 1.4.0) via /bylaws/clauses/recent gateway endpoint (owbn-gateway 1.3.0). Recent bylaw changes fetch from council.owbn.net on every site instead of falling back to "Bylaws are not available on this site". Wrapper returns normalized arrays.

= 0.2.4 =
- ballot + portals exec-votes refactor: both tiles read wpvp data through owc_wpvp_* cross-site wrappers (owbn-core 1.3.0) via gateway endpoints (owbn-gateway 1.2.0). Vote counts, open vote lists, and ballot card data now fetch from council.owbn.net on every site instead of falling back to "go elsewhere" notices. Wrapper returns normalized arrays. Vote casting (Submit All) still requires the user be on a site where wpvp is locally installed.

= 0.2.3 =
- portals refactor: archivist + territory tiles use owc_oat_get_dashboard_counts / owc_oat_get_recent_activity / owc_get_territories cross-site wrappers instead of probing local DB tables. Archivist counts are now per-user (Assigned to me / My submissions / Watching) instead of site-wide totals. Tiles render the same on every site regardless of whether OAT or territory-manager is installed locally.
- exec-votes portal still uses local probes (pending wpvp wrappers in next round).

= 0.2.2 =
- Fixed: calendar Every Other recurrence used a sliding anchor; same chronicle showed different dates depending on when the user loaded the calendar. Now uses epoch-anchored parity, stable across all viewers. Canonical math moved to owbn-chronicle-manager 2.14.0; owbn-board falls back to a local copy on sites without it.
- Fixed: message tile feeds were siloed per exact role (cm/hst/staff/player saw separate feeds). Now uses a shared group key (top + chronicle/office) so a chronicle's group chat is actually shared across tiers.
- Fixed: message tile hard-depended on notebook's role picker. Now resolves its own scope key.
- Fixed: handoff add_section accepted any handoff_id from POST. Handler now verifies the handoff row's stored scope matches the claimed scope.
- Fixed: [owbn_ballot election_id=X] silently ignored the parameter and returned all open votes. Now reads oeb_election_sets.positions, extracts the vote ids, filters wpvp_votes accordingly.

= 0.2.1 =
- Fixed: tile-access save silently disabled tiles. Saving any access override for a tile that had no prior layout entry caused save_site_layout to default enabled=false, hiding the tile. tile_access_save_config now seeds the entry from the tile registration when no prior entry exists.
- Fixed: exec/* role pattern matched no exec users. The 2-segment pattern was invisible to OWBN's 3-segment exec roles (exec/hc/coordinator, etc.). Six tiles affected (notebook, message, handoff, calendar, search, activity). All updated to exec/*/*.
- Fixed: ballot Submit All passed the wrong nonce to wp-voting-plugin's cast-ballot endpoint. Now localizes wpvp_nonce via wp_create_nonce('wpvp_public') and the JS uses it for cast-ballot calls. Multi-eligible-role users still need the wpvp native ballot until role-selection UI is added.

= 0.2.0 =
- Added tile-access module: admin editor at OWBN Board > Tile Access for per-tile read/write role overrides and Share Level content scoping. Share Level lets a single tile render many group-scoped views with a group selector (e.g. one notebook tile that switches between multiple chronicles/coordinator positions). Permission overrides survive when the module is disabled because they live in the existing owbn_board_layout option and are enforced in core/permissions.php. Notebook tile is the first consumer — honors Share Level when set and falls back to the legacy best-role picker otherwise.
- Layout save AJAX now merges incoming deltas with the stored layout instead of replacing wholesale, so drag-to-reorder no longer wipes access overrides.

= 0.1.0 =
- Full rewrite from the deprecated v0.9.0. New tile-based architecture with internal module system. Built-in modules: notebook, activity, message, calendar, search, pinned-links. Calendar aggregates chronicle sessions with per-user filters and timezone-aware display.
