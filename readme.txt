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

## Future Tiles & Roadmap

The board architecture enables many more tools as OWBN builds them. Aspirational, not blocking:

Character & Player Management: owbn-visitors, owbn-npcs
Session & Attendance: owbn-sessions, owbn-downtime
Governance & Safety: owbn-conduct, owbn-diplomacy, owbn-safety
Resources & Reference: owbn-packets, owbn-errata, owbn-canon, owbn-resources
Administration: owbn-mentors, owbn-membership, owbn-conventions, owbn-newsletter, owbn-metrics, owbn-i18n, owbn-handoff

Each of these becomes its own plugin when built, contributing tiles to the board without rewiring the dashboard.

## License

GPL-2.0-or-later
