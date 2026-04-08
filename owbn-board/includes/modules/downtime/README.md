# downtime module

Player action / staff response system. Between game sessions, a player submits a downtime action ("I am doing X") for their character and the chronicle staff responds. Multi-turn thread if needed.

## What It Does

- **Player submits** a downtime action to their chronicle, specifying the character and describing what they're doing
- **Chronicle staff** (`chronicle/{slug}/hst`, `chronicle/{slug}/staff`) see the action in their queue and respond
- **Player sees the response** and can reply with follow-up questions or clarifications
- **Staff can continue the thread** until the action is resolved
- **Status tracking** so both sides know where things stand

## Thread Structure

Each downtime action is a thread with:

- **Submitter** (player)
- **Character** (free text — the character performing the action; not a picker because the character might not be in any registry)
- **Chronicle** (the chronicle the action is being submitted to — derived from the player's home chronicle or selectable if the player has roles in multiple chronicles)
- **Title** (optional short headline)
- **Initial action** — the opening message, free-form HTML via `wp_editor()`
- **Replies** — append-only list of messages, each with author, timestamp, and HTML body. Either player or staff can reply.
- **Status** — see below

## Status

- **Submitted** — player has just sent it, awaiting staff acknowledgment
- **In Progress** — staff has responded at least once, thread is active
- **Awaiting Player** — staff replied and is waiting on the player
- **Awaiting Staff** — player replied and is waiting on the staff
- **Resolved** — action is complete, no further replies expected
- **Cancelled** — player withdrew the action or staff declined it

Status transitions automatically on reply (player reply → Awaiting Staff, staff reply → Awaiting Player) but can be manually set by either party.

## Permissions

- **Read (own):** the submitting player always sees their own threads
- **Read (staff):** chronicle staff see all threads submitted to their chronicle
- **Read (private):** threads are NOT visible to other players in the chronicle, even if they share a chronicle — downtime actions are between that player and the staff
- **Write (reply):** only the submitter and chronicle staff can reply to a thread
- **Write (status change):** either party can change status; staff can always mark resolved/cancelled
- **Delete:** soft delete only — either the submitter (before staff has replied) or chronicle HST (anytime, with reason) can remove a thread

## Tile Views

- **Player tile ("My Downtime")** — lists the player's own threads across all chronicles they have submitted to. Shows status and last activity timestamp. Click → thread detail.
- **Staff tile ("Downtime Queue")** — lists threads submitted to the current chronicle, grouped by status. Priority filter: "Awaiting Staff" shown first so staff can see what needs their attention.
- **Thread detail view** — opens the full thread with reply form at the bottom.

## Notifications

Depends on owbn-notifications (not yet built). Once available:

- Player submits → all chronicle staff notified
- Staff replies → player notified
- Player replies → chronicle staff notified (but maybe deduped so all staff don't spam each other)
- Status change → both parties notified

Until owbn-notifications exists, the tile shows unread counts based on last-read timestamps stored in user meta, and email notifications are disabled.

## Data Model

- `owbn_board_downtime_threads` table:
  - `id`
  - `chronicle_slug`
  - `site_id`
  - `submitter_id` (WP user ID)
  - `character_name` (free text)
  - `title`
  - `status` (enum)
  - `created_at`, `updated_at`
  - `deleted_at` (soft delete)

- `owbn_board_downtime_messages` table:
  - `id`
  - `thread_id` FK
  - `user_id` (author)
  - `body` LONGTEXT (HTML)
  - `created_at`

- `owbn_board_downtime_read_state` table:
  - `user_id`
  - `thread_id`
  - `last_read_at`
  - UNIQUE (user_id, thread_id)

## Open Questions

- **Character picker vs free text** — should character be free text like sessions module, or should it integrate with OAT's character registry if that's available? Probably free text for v1 (matches visitors module pattern), with optional OAT link later.
- **Multi-chronicle submission** — if a player has roles in multiple chronicles (satellites, visiting play), can they pick which chronicle? Yes, but default to their primary home chronicle.
- **Staff-only notes** — should staff have a "private notes" field on the thread that the player can't see? Probably yes for v2. V1 just has the reply stream visible to both.
- **Deadlines / auto-resolve** — should threads have a "needs response by X date" field? Useful for tournaments and time-sensitive actions, but adds complexity. Defer until real demand.
- **Attachments** — can players attach files or images? Probably via the standard WP media library as wp_editor() allows, scoped to the thread.
- **Bulk actions by staff** — can staff mark multiple threads resolved at once? Nice-to-have for v2.

## Dependencies

- **owbn-core** — for ASC role resolution and chronicle identification
- **owbn-notifications** — optional but strongly recommended for notification delivery

## Out of Scope for v1

- XP integration (no rewards automatically applied for completed actions)
- Action categorization / tagging / templates
- Analytics for staff ("most active players this month")
- Integration with Beyond Elysium or any character sheet system
- Bulk import/export of historical downtime
