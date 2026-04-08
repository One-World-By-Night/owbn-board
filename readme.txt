# OWBN Board

The unified working dashboard for One World by Night. Every site's landing page becomes your workspace.

Version: 1.0.0
Status: Active rewrite. Replaces the old v0.9.0 approach entirely.

## What It Does

Every OWBN site has a single landing page that IS the user's workspace for OWBN activity on that site. Not a links page, not a directory -- an interactive dashboard where you actually do things. Each site shows different content based on what that site is for, and each user sees different content based on who they are (their accessSchema roles).

A council member lands on council.owbn.net and sees their chronicle's shared notebook, pending votes they can cast, and a quick-message box for their team. A storyteller lands on chronicles.owbn.net and sees their chronicle's session log, pending player submissions in OAT, and the staff notebook for their game. Same plugin, completely different experience.

## How It Works

- Tiles are self-contained dashboard components registered by any OWBN plugin
- Per-site layout controls which tiles appear on which site and in what order
- accessSchema role patterns control who sees what -- parent roles inherit child access
- Per-user customization lets people hide, pin, reorder, and snooze tiles
- Cross-site data via owbn-gateway so a council dashboard can show OAT inbox items from archivist
- TinyMCE notebook -- shared group notebook scoped to your role

## Built-in Tiles

- Shared Group Notebook -- collaborative notebook scoped by accessSchema role
- Quick Message -- lightweight group chat
- Activity Feed -- aggregated recent events from every plugin
- Calendar -- upcoming dates from every plugin
- Notifications Inbox -- wraps owbn-notifications
- Universal Search -- searches every data source in the OWBN ecosystem
- Pinned Links -- personal bookmarks

## Requirements

- WordPress 5.8+
- PHP 7.4+
- owbn-core (for accessSchema client wrappers)

## Architecture

owbn-board is monolithic -- new LARP tools are built as internal modules inside this plugin, not as separate plugins. Existing OWBN plugins stay external and register tiles via the public hook API. New tools go here.

External plugins (stay separate, contribute tiles via hooks):
- owbn-client and all its sub-plugins (owbn-core, owbn-entities, owbn-archivist, owbn-gateway, owbn-support) -- infrastructure layer owbn-board depends on
- owbn-archivist-toolkit (OAT) -- workflow engine and character registry
- wp-voting-plugin -- voting engine
- owbn-election-bridge -- coordinator elections
- owbn-chronicle-manager -- chronicle and coordinator directory
- owbn-territory-manager -- territory assignments
- bylaw-clause-manager -- bylaws
- beyond-elysium -- LARP character management (when built)

Internal modules live in includes/modules/{name}/ with their own tables, hooks, tiles, and admin pages. Admins enable/disable modules per site. If a module outgrows the monolith, it can be extracted later.

## Internal Modules (Roadmap)

LARP tools owbn-board will provide as it grows. Each is an internal module, not a separate plugin. Order and timing determined by community demand.

Character & Player Management: visitors, npcs
Session & Attendance: sessions, downtime
Governance & Safety: conduct, diplomacy, safety
Resources & Reference: packets, errata, canon, resources
Administration: mentors, membership, conventions, newsletter, metrics, i18n, handoff

## License

GPL-2.0-or-later
