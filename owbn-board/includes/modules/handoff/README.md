# handoff module

A persistent "staff diary" scoped to a role group — the institutional knowledge current staff leave behind for whoever takes over next. HSTs writing down how they run their chronicle, CMs noting the quirks of their domain, coordinators documenting the unwritten rules of their genre. When the torch passes, the next person inherits context instead of starting from zero.

## What It Does

- Each role group (chronicle staff, chronicle HSTs, coordinator office, exec office) has its own handoff document
- Current staff add entries over time — tips, tools, ongoing projects, pitfalls, "here's what I wish I'd known when I started"
- Entries are organized by topical section, so a new staff member can skim by category rather than reading a wall of text
- Persists across role transitions — when a new HST takes over, they see everything the previous HSTs contributed
- Append-mostly — entries are rarely deleted, but can be edited for corrections or marked outdated

## Why It's Separate From the Notebook

The notebook module is for **current collaboration** — today's agenda, active plot hooks, shared to-dos. Its content is naturally transient.

Handoff is for **future readers** — things written specifically to help someone who isn't in the room yet. The intended audience is "whoever comes after me." Different purpose, different content, different expectations about longevity.

They're also scoped differently — the notebook is scoped to the current role holder set; handoff is scoped to the *role itself* regardless of who currently holds it.

## Scope / Ownership

Handoff docs are scoped by role group, NOT by the current individual:

- **chronicle/{slug}/hst** — the HST handoff for MCKN, for example. Continuous across all HSTs of MCKN over time.
- **chronicle/{slug}/staff** — the general staff handoff (AST and CM pool)
- **coordinator/{genre}/coordinator** — the coordinator office handoff
- **exec/{office}/coordinator** — the exec office handoff (for HC, AHC1, AHC2, Archivist, Web, etc.)

One handoff per role group. Not one per person. The document persists through role changes.

## Structure

Each handoff is a structured document with topical sections. Default sections (customizable per group):

- **How I run this** — general philosophy, pacing, approach
- **Tools I use** — spreadsheets, Discord bots, external services, templates
- **People to know** — key contacts inside and outside OWBN, with context
- **Ongoing priorities** — what's mid-stream that the next person should inherit
- **Pitfalls and lessons learned** — things I tried that didn't work, things I wish I'd known earlier
- **Current relationships** — state of play with neighboring chronicles, coordinators, exec
- **Standing commitments** — agreements, pacts, promises that carry over
- **Useful links** — documents, old emails, Discord channels, reference material
- **Open questions** — things I never got to resolve, left to my successor
- **What I'd do differently** — retrospective honesty

Sections are editable by the HST/CM/coordinator who owns the document. Within each section, entries are individual blocks with author attribution, timestamp, and optional tags.

## Entry Fields

Each entry within a section:

- **Author** (automatic from current user)
- **Date added** (automatic)
- **Title** (optional, for longer entries)
- **Body** — free-form HTML via `wp_editor()`
- **Tags** (optional) — for filtering ("crisis", "logistics", "tradition", "reminder")
- **Status** — current, outdated, superseded (editors can mark historical entries without deleting them)

## Permissions

- **Read:** current holders of the scope (e.g., current HST of MCKN reads MCKN's HST handoff)
- **Write (add entries):** same — current holders add to it during their tenure
- **Edit own entries:** each author can edit entries they wrote
- **Mark outdated / supersede:** any current holder of the scope
- **Delete entries:** rare — reserved for admins or exec in cases of confidentiality issues or gross error. Soft delete with reason.

When a new HST takes over MCKN, they automatically gain read/write access because they now hold `chronicle/mckn/hst`. The previous HST loses write access when the role is transferred (but their existing entries stay, attributed to them).

## Confidentiality Notes

- Handoff docs often contain sensitive information — personnel assessments, player concerns, conflict history, trust evaluations
- Permissions are strictly scoped to current role holders of the exact scope
- NOT shared across chronicles or up the hierarchy automatically (MCKN's HST handoff is not visible to exec unless the scope explicitly includes exec)
- Admins can override for investigation purposes, audited

## Tile Views

- **Handoff** tile — shows the handoff document for the user's highest-priority scope (e.g., if they're an HST, they see the HST handoff for their chronicle)
- Collapsible by section
- "Add entry" button at the top of any section
- Search within the handoff

If a user has multiple scopes (e.g., HST of one chronicle and CM of another), they see a selector at the top to pick which handoff they're viewing.

## Data Model

- `owbn_board_handoffs` table:
  - `id`
  - `scope` (ASC role pattern, e.g., `chronicle/mckn/hst`)
  - `site_id`
  - `title` (display title of the handoff — auto-generated or customizable)
  - `created_at`, `updated_at`

- `owbn_board_handoff_sections` table:
  - `id`
  - `handoff_id` FK
  - `label` (section name)
  - `sort_order`
  - `created_at`, `updated_at`

- `owbn_board_handoff_entries` table:
  - `id`
  - `section_id` FK
  - `author_id` (WP user)
  - `title`
  - `body` LONGTEXT (HTML)
  - `tags` TEXT
  - `status` (current, outdated, superseded)
  - `created_at`, `updated_at`
  - `deleted_at` (soft delete)

## Dependencies

- **owbn-core** — for ASC role resolution

## Open Questions

- **Template sections** — should each scope type have default sections pre-populated when first created, or start blank? Probably pre-populated templates so new groups aren't staring at an empty page.
- **Read access for successors before handoff** — an HST named as an incoming replacement but not yet in role — can they peek at the handoff before their ASC role activates? Probably no unless explicitly granted. The existing HST can export/share relevant parts manually if needed.
- **Cross-chronicle sharing** — could an HST of one chronicle share their handoff with an HST of another as peer reference? Probably via export / copy-paste, not a built-in feature. Keeps permissions clean.
- **Versioning of entries** — do we track edit history per entry? The notebook has revision history. Handoff probably should too for the longer timeframes involved.
- **Search across all handoffs** — exec might want to search handoffs they have access to. Low priority.

## Out of Scope for v1

- Export as PDF or formal transition package
- Handoff templates imported from other orgs
- Analytics on handoff usage (which sections get read most)
- Cross-scope linking ("see also the CM handoff for MCKN")
- Comments or discussion threads on individual entries
