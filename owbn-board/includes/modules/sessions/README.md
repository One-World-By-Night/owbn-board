# sessions module

Event log per chronicle session. Staff (HST, CM, AST) collaboratively document what happened during a game night. Keep it light — a few free-form fields, not a heavyweight template.

## What It Does

- Chronicle staff create a session record for each game night
- Multiple staff can edit the same record (concurrent-edit lock pattern like the notebook module)
- Records are listed in reverse-chronological order in a tile
- Each record is scoped to the chronicle — visible to that chronicle's staff and optionally to the players

## Session Record Fields

Minimal. Not the kitchen-sink template in the reference example — that's a personal-use template, not what this module ships with.

- **Date** (required) — the date the session was played
- **Title** (optional) — a short headline (e.g., "The Hollow Shall Speak")
- **Summary** — free-form WYSIWYG, what happened at the session
- **Notes** — free-form WYSIWYG, staff-only observations, loose ends, follow-up items

Three editable fields total: Title, Summary, Notes. Keep it simple.

## Roster / Attendance (optional)

Each session record optionally has an attendance list — a free-text area where staff can list who showed up. No player picker, no XP integration, no formal structure.

If the chronicle wants more rigorous tracking later, staff can keep expanding the Notes field, or an org-wide attendance module can be added separately.

## Permissions

- **Read:** chronicle staff (`chronicle/*/hst`, `chronicle/*/cm`, `chronicle/*/staff`) see all sessions for their chronicle
- **Write:** same — staff can create/edit any session for their chronicle
- **Optional player visibility:** a per-session checkbox "Share with players" exposes Title + Summary (NOT Notes) to players in that chronicle via a separate read-only view. Off by default.

## Tile

- **Recent Sessions tile** — shows the last 5 sessions for the current user's chronicle with date + title. Click → full detail view.
- **Edit inline on the detail view** — using wp_editor() for Summary and Notes, same pattern as notebook.
- **"New Session" button** at the top of the tile for users with write access.

## Data Model

- `owbn_board_sessions` table:
  - `id`
  - `chronicle_slug` (identifies which chronicle this session belongs to)
  - `site_id` (for multisite scoping; 0 = cross-site)
  - `session_date` DATE
  - `title` VARCHAR(255)
  - `summary` LONGTEXT (HTML)
  - `notes` LONGTEXT (HTML)
  - `attendance` TEXT (free-form)
  - `share_with_players` TINYINT
  - `created_at`, `created_by`, `updated_at`, `updated_by`
  - `locked_by`, `locked_at` — concurrent-edit lock

- `owbn_board_session_history` table — revision history for Summary/Notes changes (append-only, last 20 per session), same pattern as the notebook module

## Dependencies

- **owbn-core** for ASC role resolution and chronicle data

## Out of Scope for v1

- Per-player attendance tracking with user pickers
- XP awards / integration with Beyond Elysium or any character sheet system
- Plot hook cross-references
- NPC cross-references (can be done by hand in the free-form fields)
- Session templates
- Import/export
- Calendar integration (that's what the chronicle session schedule + calendar module already do)

These can be added later as the module matures. Start with the simplest possible useful version.
