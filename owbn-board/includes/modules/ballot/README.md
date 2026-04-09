# ballot module

Unified ballot display for OWBN elections. Shows all open votes (one card per vote) on a single page so eligible voters can mark all their selections and submit everything at once. Built on top of wp-voting-plugin + owbn-election-bridge — reads their data, delegates the actual vote casting to their existing AJAX endpoint.

This was originally specced in the owbn-election-bridge TODO. Moving it into owbn-board as a module makes it accessible on any site where the board is installed, not just council.

## What It Does

- Shows every currently-open vote (or all votes in a specific election set) as a **card**
- Each card displays: voting type badge, title, short description, open/close dates, candidates with optional "View application" links, and vote controls appropriate to the voting type
- **Submit All** button at the bottom fires the existing `wpvp_cast_ballot` AJAX endpoint once per card that has a selection
- Checkmark appears on each card as its vote is accepted
- Counter shows "X of Y remaining" as votes are cast
- Works for logged-out users in read-only mode (no vote controls, "Log in to vote" prompt)

## Card States

Five states a card can be in, all handled in the same renderer:

1. **Scheduled, apps open** — application window is active, voting hasn't started. Shows candidates (if any), "Apply for this position" link, "Voting opens {date}" note, no vote controls.
2. **Scheduled, apps closed** — application window closed, voting not yet open. Shows final candidate list, "Voting opens {date}" note, no vote controls.
3. **Open, not voted** — voting in progress, user eligible, hasn't cast a ballot yet. Shows radio buttons (FPTP) or rank dropdowns (RCV/STV/etc).
4. **Open, already voted** — voting in progress, user has cast a ballot. Shows their selection, "Voted" badge, "Change Vote" button to modify.
5. **Closed** — voting has ended. Shows candidates and results link, no controls.

## Voting Type Support

- **FPTP (singleton)** — radio buttons
- **RCV / IRV** — rank dropdowns (1st, 2nd, 3rd choice pickers)
- **Sequential RCV** — same as RCV but with multiple seat slots
- **STV** — same as RCV
- **Condorcet** — rank dropdowns
- **Consent Agenda** — objection checkbox only
- **Disciplinary** — FPTP-style radio buttons (Guilty / Not Guilty / Abstain)

Voting type is auto-detected from `wp_wpvp_votes.voting_type`. Controls render appropriately per type.

## Abstain + Reject All

Every vote in OWBN's wp-voting-plugin has Abstain and Reject All Candidates as permanent options. The ballot module respects this — those always appear in the voting controls even though they aren't "candidates" in the election-bridge sense.

## Candidate Detail Modal

Clicking a candidate name opens their full application post in an 80%-wide modal overlay. User reads the application, closes modal, stays on the ballot page. AJAX-loads the post content so there's no navigation away from the ballot. Keeps the voting flow uninterrupted.

## Submit Flow

The "Submit All Votes" button does a client-side JS loop:

1. Collects selections from each card that has one
2. Fires `wpvp_cast_ballot` AJAX endpoint sequentially per card
3. Updates each card with a checkmark as it completes
4. Counter shows "X of Y remaining"
5. If any cards have no selection, confirmation prompt: "You have X unvoted positions. Submit anyway?"
6. Only submits cards that have selections — skipped positions are left unvoted

## Viewing Modes

Handled by checking user state:

- **Eligible voter** (has the AccessSchema role gated by the vote's `allowed_roles`) — full vote controls + Submit All button
- **Logged in, not eligible** — candidates visible, View Application links work, no vote controls, no Submit button
- **Logged out** — same as not eligible, plus "Log in to vote" prompt redirecting to WP login

## Tile + Shortcode

- **Tile** (`ballot:all-open`) — shows ALL currently-open votes across OWBN, limited to 6 cards, with a "View full ballot →" link
- **Shortcode** (`[owbn_ballot]`) — full-page version suitable for a dedicated ballot page, with all cards and the Submit All button. Also supports `election_id="X"` to scope to a specific election set.

The shortcode is the primary interface; the tile is a quick-glance version on the dashboard.

## Dependencies

- **wp-voting-plugin** — source of truth for votes. Ballot module reads `wp_wpvp_votes` and delegates to `wpvp_cast_ballot` AJAX.
- **owbn-election-bridge** — optional. If installed, the shortcode can scope to an election set via `election_id`, and candidate cards link to election-bridge application detail posts.
- **owbn-core** — for ASC role resolution to determine voter eligibility.

## Graceful Fallback

- No wp-voting-plugin installed → tile shows "wp-voting-plugin is not installed"
- No open votes → tile shows "No open votes"
- Election-bridge not installed → still works, just without the election grouping and application detail modal

## Shortcode Attributes

| Attribute | Default | Purpose |
|-----------|---------|---------|
| `election_id` | 0 | Scope to a specific election set from owbn-election-bridge |
| `limit` | 0 (all) | Max number of vote cards to render |
| `show_closed` | false | Include closed votes (read-only, for reference) |

## Out of Scope for v1

- Editing vote options from the ballot view (that's wp-voting-plugin's job)
- Results display (link to wp-voting-plugin's results page)
- Email notifications (owbn-notifications later)
- Candidate comparison tables
- Ballot printing / PDF export
- Changing vote type on the fly
