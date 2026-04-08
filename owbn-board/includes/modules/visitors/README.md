# visitors module

Cross-chronicle character travel log. When a visiting character participates in a session at another chronicle, the host staff records it and both chronicles see the entry.

## What It Does

- **Hosting chronicle staff** (`chronicle/{slug}/hst` or `chronicle/{slug}/staff`) creates a visit entry after a session
- Entry fields: player (WP user), character name (free text), home chronicle slug, visit date, notes
- Both the hosting chronicle and the player's home chronicle see the entry
- Player themselves also sees their own visit record
- Notification fires to the player's home chronicle staff via **owbn-notifications** (blocking dependency — module stays disabled until that plugin exists)

## Data Model

- **Unit of tracking:** character, associated to player. A player can have multiple visit entries for different characters or different sessions.
- **Character field:** free text (NOT a picker from a character registry). The hosting chronicle may not have authoritative access to the player's character sheet; free text is simpler and works for any character.

## Rules

- **Append-only.** A visit entry cannot be edited after save. Corrections happen via new entries referencing the original.
- **Informational only.** Does NOT feed into Beyond Elysium XP, attendance tracking, or any other system. Just a record.

## Visibility

- **Hosting chronicle staff** — read + write (can create new entries)
- **Visiting player's home chronicle staff** — read
- **Visiting player themselves** — read (their own visits only)
- **Notes field** — visible to both chronicles' staff AND the player

## Dependencies

- **owbn-notifications** — required for the cross-chronicle notification delivery. Module scaffold can exist before that plugin, but the tile and notification dispatch should be disabled until the dependency is available.

## Open Questions (for later)

- None from initial scope discussion. Keep simple until real usage surfaces edge cases.
