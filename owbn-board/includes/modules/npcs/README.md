# npcs module

Recurring NPC roster. Chronicles and coordinators maintain rich character profiles for their recurring NPCs, track plot hooks, ongoing developments, resolved plots, roleplaying notes, and connections to other NPCs. Profiles can optionally link back to OAT's NPC records for single-source-of-truth authoritative data.

## What It Does

- **Staff roster view** — chronicle staff (`chronicle/*/hst`, `chronicle/*/staff`) see all NPCs associated with their chronicle
- **Coordinator roster view** — coordinators (`coordinator/*/*`) see all NPCs associated with their office
- **Add, edit, archive** NPC profiles from the tile
- **Optional OAT link** — an NPC in the roster can be bound to an OAT NPC record via UUID, in which case authoritative fields (name, clan, canonical faction, etc.) come from OAT and the local profile adds narrative layers on top
- **Independent mode** — NPCs without an OAT link live entirely in the board and are local to the chronicle/coordinator

## Profile Structure

Modeled on the existing `Sophia_Gries.md` style reference. Every NPC profile has these sections (all optional except name):

### Header (structured, same across all creature types)
- **Name** (required)
- **Title / Role** (free text, e.g., "Archbishop of Knoxville", "Theurge of the Silver Fangs", "Master of the Order of Hermes")
- **Portrait image** (upload or URL)
- **Creature Type** — select from org-maintained creature type list (Vampire, Werewolf, Mage, Changeling, Wraith, Hunter, etc.)

### Vitals (free-form key/value pairs)
Creature types have wildly different vitals — Vampires have Clan/Sire/Generation, Werewolves have Tribe/Breed/Auspice, Mages have Tradition/Essence/Arete, Changelings have Kith/Seeming/House. Rather than hardcoding fields per creature type, this section is a **repeating free-form list of label/value pairs** that the staff member fills in as appropriate.

Example for the Sophia (Vampire) case:
- Clan: Nosferatu Antitribu
- Sire: (unknown)
- Generation: 8
- Embrace: 2 November 1943
- Apparent Age: 25
- Nature: (free text)
- Demeanor: (free text)

Example for a Werewolf NPC:
- Tribe: Silver Fangs
- Breed: Homid
- Auspice: Philodox
- First Change: 1987
- Rank: Athro

### Path / Humanity / Morality (free-form)
Another repeating free-form label/value list:

Vampire example:
- Path: Path of Night, Rating 5
- Conscience: 7
- Self-Control: 8
- Courage: 5

Werewolf example:
- Honor: 4
- Glory: 6
- Wisdom: 3
- Rage: 7
- Gnosis: 5

Mage example:
- Avatar: 4
- Arete: 5
- Paradox: 2

The form accepts any label/value the user types. No forced schema — lets staff document whatever matters for that creature type.

### Character Overview
- **Player / Portrayed By** — which staff member portrays this NPC (WP user picker, optional)
- **Character Faction / Group**
- **Role / Title**
- **Current Status** — Alive, Deceased, Diablerized, Missing, Unknown, etc.
- **Backstory** — WYSIWYG, multi-paragraph

### Plot Hooks
Repeating group, each entry has:
- **Title**
- **Description**
- **First Introduced** — session/chapter reference
- **Current Status**
- **Actions Taken So Far**
- **Next Steps**
- **Involved NPCs or Factions**
- **State flag** — Unresolved, Ongoing Development, Resolved

### Recently Resolved Plots
Same as plot hooks but with:
- **Resolution Details**
- **Resolved In** — session/chapter reference
- **Impact on Character**

### Notes and Observations
- **Character Motivations** — free text, WYSIWYG
- **Roleplaying Notes** — voice, tone, emotional range, interactions, combat philosophy, ritualism, leadership style, theme statements (all free text subsections)
- **Potential Future Hooks** — bullet list
- **Important Relationships** — short descriptive list

### Key NPC Connections
Repeating group for cross-references:
- **NPC Name** (free text or picker to another NPC in the roster)
- **Role / Title**
- **Faction**
- **Relationship to Character**

### Metadata
- **Owner scope** — `chronicle/{slug}` or `coordinator/{slug}/{genre}` — who "owns" this NPC
- **Visibility** — private (owner scope only), shared within org (other chronicles can view), public
- **OAT link** — optional UUID pointing to OAT NPC record; if set, some fields become read-only mirrors of OAT data
- **Created by**, **Updated at**, **Updated by**

## Views

### Roster Tile
- Lists all NPCs the current user has access to based on their ASC roles
- Search / filter by creature type, faction, status, owner
- "Add NPC" button for users with write access to their own scope
- Click an NPC → opens detail view

### Detail View
- Full profile rendered from the stored data
- Edit button for users with write access
- "Link to OAT" action for users who can see OAT records (handled via owbn-archivist client)

### Add / Edit Form
- Tabbed admin form mirroring the profile structure
- WYSIWYG for all long-form fields
- Repeating groups for plot hooks, resolved plots, connections

## Permissions

- **Read:** users whose roles match the NPC's owner scope (e.g., `chronicle/mckn/*` for an MCKN-owned NPC), OR any user if visibility is `public`
- **Write:** `chronicle/{slug}/hst`, `chronicle/{slug}/cm`, `chronicle/{slug}/staff` for chronicle NPCs; `coordinator/{slug}/coordinator`, `coordinator/{slug}/sub-coordinator` for coordinator NPCs
- **Delete / archive:** HST or Coordinator only
- **OAT link:** users with `oat_manage_characters` or equivalent permission

## Data Model

- `owbn_board_npcs` table — one row per NPC, stores all simple fields + JSON blobs for structured sections
- `owbn_board_npc_attachments` — media library attachments scoped to an NPC (portraits, reference docs)
- NPC content stored as HTML (via `wp_editor()`) for the long-form fields — consistent with notebook module

## Dependencies

- **owbn-archivist-toolkit** (OAT) — optional. Required only for the "link to OAT NPC record" feature. Falls back to independent mode if OAT is not installed or the user lacks OAT access.
- **owbn-core** — for ASC role resolution

## Open Questions

- **Versioning of profiles** — do we want revision history like the notebook module? Probably yes for chronicle staff accountability, but adds cost.
- **Bulk import** — CSV or markdown import for chronicles with many NPCs already documented elsewhere? Deferred until real demand.
- **Cross-chronicle NPC sharing** — if two chronicles share an NPC (e.g., a traveling Archbishop), do they both get write access, or does one chronicle own it and the other references it?
- **Media storage** — portrait images go to the WP media library. Should they be site-scoped or network-wide on multisite? Probably site-scoped, with cross-site references via public URL.
