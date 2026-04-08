# events module

Upcoming events marketing board. A place for chronicles, coordinators, and the org to announce upcoming events — conventions, special games, cross-chronicle gatherings, game days, online sessions, anniversaries, themed nights, and anything else members should know about.

This is a marketing / promotion tool. It's not the chronicle session calendar (that's owned by chronicle-manager + the calendar module). Events are discrete, one-time or time-bounded happenings that members can look forward to and plan around.

## What It Does

- Event organizers (chronicle staff, coordinators, exec) create event listings with promotional content
- Listings go through an **approval workflow** before being visible to members
- Approved listings appear on the dashboard and feed into the calendar module
- Members can browse upcoming events, RSVP or indicate interest, and see details
- Events are time-bounded — past events archive automatically
- Each event has its own landing view with full description, images, and links
- Organizers can **upload banner images** directly via the WordPress media library

## Event Listing Fields

- **Title** (required)
- **Tagline** — short hook for the tile display
- **Start date/time** (required)
- **End date/time** — for multi-day events
- **Timezone** — same picker as chronicle timezone
- **Location** — free text (physical address, venue name, "Online", Discord/Zoom URL)
- **Hosting entity** — chronicle, coordinator, or exec office (picker from ASC roles)
- **Event type** — Convention, Special Game, Game Day, Online Event, Anniversary, Themed Night, Other
- **Banner image** — hero banner uploaded via wp.media, stored as a WP attachment, used for promotional display on the tile and detail view
- **Description** — full WYSIWYG for promotional content (story, schedule, what to expect)
- **Registration URL** — optional external link for ticket sales, RSVPs, or signup forms
- **Registration fee** — optional, display-only (no payment processing — that's a separate concern)
- **Max attendees** — optional cap
- **Website URL** — optional link to the event's dedicated page or site
- **Social links** — optional Facebook, Discord, Twitter, etc.
- **Tags** — genre filters, audience targeting (new players, staff-only, etc.)

## RSVP / Interest (Optional)

Two levels of engagement for members:

- **Interested** — lightweight "I might come" click. No commitment.
- **Going** — harder commitment. May feed into attendee count for the organizer.

Stored per user per event. Organizer sees counts and (if opted in by the user) a list of attendees.

If the event uses an external registration URL (Eventbrite, Tabletop.events, etc.), the internal RSVP is optional — organizers can disable it per event.

## Tile Views

- **Upcoming Events** tile — visually rich card layout showing the next N upcoming events with featured image, title, tagline, date, location
- Click → full event detail view
- Filter: all events, only events in my region, only events I'm interested in / going to, by event type

## Organizer View

- **My Events** tile — for users with event creation permissions, shows their own listings (draft, upcoming, past) with RSVP counts
- Create / edit event form
- Analytics per event: views, interested count, going count

## Approval Workflow

Events go through an approval pipeline before members see them:

1. **Draft** — organizer is still working on the listing. Only the creator sees it.
2. **Pending Approval** — organizer submitted the listing and it's waiting for review.
3. **Approved** — approved by exec or a designated reviewer. Visible to members, appears on the calendar, RSVPs open.
4. **Rejected** — reviewer sent it back with optional feedback. Returns to draft state for edits.
5. **Cancelled** — organizer pulled the event. Kept in history but not shown on the calendar.
6. **Past** — automatic transition after the event's end date. Archived.

Reviewers (exec team + a designated `events_reviewer` role if configured) see a pending queue with approve/reject actions. Approvals are audited.

## Permissions

- **Read:** all authenticated users see **approved** events. Draft/pending events only visible to the creator and reviewers.
- **Create (draft):** chronicle HST/CM, coordinators, exec team
- **Submit for approval:** the creator moves their own draft to pending
- **Approve/reject:** exec team, admins, or users with the `owbn_board_events_review` capability
- **Edit/delete own:** the creator (before approval — after approval, edits require a new approval cycle)
- **Edit/delete any:** admins, exec
- **Upload banner:** any user with create permission (uses WP's standard media library upload capability)

## Calendar Integration

Events automatically contribute to the **calendar module** via the `owbn_board_calendar_events` filter:

- Each published event becomes a calendar entry
- Times converted from the event timezone to viewer's browser local time
- Click-through from calendar goes to the event detail view

## Data Model

- `owbn_event` CPT — WordPress custom post type for richness (featured image, categories, taxonomies, native edit UI)
- OR `owbn_board_events` table — if we want tighter control. CPT is probably simpler.

Likely CPT with custom meta for the structured fields:

- `_owbn_event_start_dt`
- `_owbn_event_end_dt`
- `_owbn_event_timezone`
- `_owbn_event_location`
- `_owbn_event_host_scope` (ASC role path of the hosting entity)
- `_owbn_event_type`
- `_owbn_event_registration_url`
- `_owbn_event_fee`
- `_owbn_event_max_attendees`
- `_owbn_event_website`
- `_owbn_event_social_links` (serialized array)

RSVP data:

- `owbn_board_event_rsvps` table:
  - `event_id`, `user_id`, `status` (interested, going), `created_at`
  - UNIQUE (event_id, user_id)

## Dependencies

- **owbn-core** — ASC role resolution for host scope and permissions
- **calendar module** (internal to owbn-board) — consumes events via the filter

## Open Questions

- **Global vs chronicle-scoped events** — does "all members see all events" work at scale? For now yes. If it becomes noisy, add filters by region, hosted by, or user interest tags.
- **Cross-posting** — should an event hosted by Chronicle A be auto-shared to Chronicle B's neighborhood? Probably via tags and opt-in feeds rather than automatic cross-posting.
- **Recurring events** — a chronicle that hosts a "monthly game day" could want recurrence like the session schedule has. V1 keeps events one-off; recurrence is v2.
- **Payment / registration** — explicitly NOT in scope. If a chronicle charges for an event, they use an external platform and link it. Internal payment would reuse the dues module's PayPal infrastructure if ever needed.
- **Post-event content** — should an event listing transition into a "past event" display with photos, recap, attendance summary? Nice-to-have; past events auto-archive for now.

## Out of Scope for v1

- Ticket sales / payment processing (use external or link to dues module later)
- Recurring event patterns (one-off events only)
- Guest/non-member RSVP (logged-in users only)
- Email blast to members for new events (depends on owbn-notifications later)
- Calendar sync (ICS export is a nice-to-have, probably v2)
- Map view for physical events
