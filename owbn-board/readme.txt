=== OWBN Board ===
Contributors: greghacke
Tags: dashboard, workspace, owbn, larp
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
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

= 1.0.0 =
- Full rewrite from v0.9.0. New tile-based architecture replacing the old Chronicle/Coordinator/Exec tools scaffolding.
