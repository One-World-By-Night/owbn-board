# OWBN Board

The unified working dashboard for One World by Night. Every site's landing page becomes your workspace.

**Version:** 1.0.0
**Status:** Active rewrite. Replaces the old v0.9.0 approach entirely.

## What It Does

Every OWBN site has a single landing page that IS the user's workspace for OWBN activity on that site. Not a links page, not a directory — an interactive dashboard where you actually do things. Each site shows different content based on what that site is for, and each user sees different content based on who they are (their accessSchema roles).

A council member lands on council.owbn.net and sees their chronicle's shared notebook, pending votes they can cast, and a quick-message box for their team. A storyteller lands on chronicles.owbn.net and sees their chronicle's session log, pending player submissions in OAT, and the staff notebook for their game. Same plugin, completely different experience.

## How It Works

- **Tiles** are self-contained dashboard components registered by any OWBN plugin
- **Per-site layout** controls which tiles appear on which site and in what order
- **accessSchema role patterns** control who sees what — parent roles inherit child access
- **Per-user customization** lets people hide, pin, reorder, and snooze tiles
- **Cross-site data** via owbn-gateway so a council dashboard can show OAT inbox items from archivist
- **TinyMCE notebook** — shared group notebook scoped to your role (`chronicle/mckn/staff`, etc.)

## Built-in Tiles

- **Shared Group Notebook** — collaborative notebook scoped by accessSchema role
- **Quick Message** — lightweight group chat
- **Activity Feed** — aggregated recent events from every plugin
- **Calendar** — upcoming dates from every plugin (votes, sessions, deadlines)
- **Notifications Inbox** — wraps owbn-notifications
- **Universal Search** — searches every data source in the OWBN ecosystem
- **Pinned Links** — personal bookmarks

## Plugin Integration

Any OWBN plugin can register a tile:

```php
add_action('owbn_board_register_tiles', function () {
    owbn_board_register_tile([
        'id'          => 'myplugin:my-tile',
        'title'       => 'My Tile',
        'read_roles'  => ['chronicle/*/cm', 'exec/*'],
        'write_roles' => ['chronicle/*/cm', 'exec/*'],
        'sites'       => ['council', 'chronicles'],
        'size'        => 'medium',
        'render'      => 'myplugin_render_my_tile',
    ]);
});
```

Plugins can also contribute to the activity feed, calendar, and universal search via filter hooks.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- owbn-core (for accessSchema client wrappers)

## Future Tiles & Roadmap

The board architecture enables many more tools as OWBN builds them. These are aspirational and not blocking:

### Character & Player Management
- **owbn-visitors** — cross-chronicle character travel
- **owbn-npcs** — recurring NPC roster

### Session & Attendance
- **owbn-sessions** — Post-Event Logs, attendance, XP awards
- **owbn-downtime** — between-game action submission and resolution

### Governance & Safety
- **owbn-conduct** — code of conduct reporting (sensitive, restricted access)
- **owbn-diplomacy** — chronicle pacts, alliances, ongoing conflicts
- **owbn-safety** — safety tools registry (X-card, lines/veils)

### Resources & Reference
- **owbn-packets** — genre packet distribution and versioning
- **owbn-errata** — rules updates feed
- **owbn-canon** — org-wide canon database
- **owbn-resources** — player and ST resource library

### Administration
- **owbn-mentors** — mentorship pairing
- **owbn-membership** — dues tracking
- **owbn-conventions** — convention tracking
- **owbn-newsletter** — org-wide announcements
- **owbn-metrics** — organizational health dashboard
- **owbn-i18n** — translation coordination
- **owbn-handoff** — coordinator transition tracking

Each of these becomes its own plugin when built, contributing tiles to the board without rewiring the dashboard.

## License

GPL-2.0-or-later
