# newsletter module

OWBN already publishes a newsletter. This module is a link feed that surfaces newsletter editions on the dashboard so members don't miss them.

## What It Does

- Editorial staff add a new link entry whenever a newsletter edition is published
- Entries are simple: title, publication date, external URL to the edition, optional short description
- A tile on the dashboard shows the most recent few editions and an archive link for the rest
- Click → opens the edition in a new tab

## Entry Fields

- **Title** (required) — e.g., "OWBN Newsletter — March 2026"
- **Publication date** (required)
- **URL** (required) — external link to the published edition
- **Summary** (optional) — short blurb about what's in this edition
- **Cover image** (optional) — thumbnail for visual listing

## Tile View

- **Newsletter** tile — lists the last 5 editions with title, date, summary
- "View Archive" link at the bottom opens the full list
- Each entry is a click-through to the external URL

## Permissions

- **Read:** all authenticated users
- **Write:** exec team, newsletter editors (configured via ASC role — e.g., `exec/newsletter/*` or similar)
- **Manage archive:** same as write

## Data Model

Simple — a single custom table OR a WordPress CPT. Given the lightweight nature, probably:

- `owbn_board_newsletter_editions` table:
  - `id`
  - `title`
  - `published_at` DATE
  - `url` VARCHAR(500)
  - `summary` TEXT
  - `cover_image_id` (WP attachment ID, optional)
  - `created_at`, `created_by`

Or a CPT `owbn_newsletter_edition` if we want native WP listing UI for free.

## Dependencies

- **owbn-core** — ASC role resolution for editor permissions

## Future Growth

If OWBN ever wants to publish newsletters natively in the board (rather than linking out), this module can be extended to host full edition content. V1 is just the link feed.

## Out of Scope for v1

- Native newsletter authoring (WYSIWYG composition of editions within owbn-board)
- Email distribution (that's what the newsletter platform is already doing)
- Subscription management
- Per-user read/unread tracking
- Comments or reactions on editions
