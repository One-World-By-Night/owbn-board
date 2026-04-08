# errata module

Recent bylaw changes feed. Shows clauses that have been added, amended, or removed in the last X days — a digestible "what changed" view for players, staff, and coordinators to stay current on bylaw updates without having to re-read the whole document.

## What It Does

- Reads bylaw data from **bylaw-clause-manager** (the authoritative source for OWBN's bylaws on council.owbn.net)
- Lists clauses that changed in a configurable time window (default: last 30 days)
- Shows what changed: clause added, text amended, removed, or re-categorized
- Links each change back to the full clause in the bylaws
- Shows which vote adopted the change (if bylaw-clause-manager has the vote_id metadata)

## Tile View

- **Recent Bylaw Changes** tile — reverse-chronological list, grouped by date
- Each entry: clause section number, clause title, change type (Added / Amended / Removed), timestamp, link to clause, link to adopting vote (if available)
- Filter: last 7 days / 30 days / 90 days / custom
- Click an entry → opens the clause detail in a modal or new page (depending on bylaw-clause-manager's display pattern)

## Data Source

Reads from bylaw-clause-manager via one of:

1. **Direct query** (if on the same site) — `get_posts()` on the bylaw clause post type with `date_query` on modified date
2. **Cross-site via owbn-gateway** — bylaw-clause-manager is on council.owbn.net, so other sites need to fetch bylaw data via REST. Requires bylaw-clause-manager to expose a "recent changes" endpoint OR the gateway to expose bylaw post meta.

bylaw-clause-manager doesn't currently expose itself through owbn-core's client API. When we build this module, we'll either:

- Add a bylaws contributor to owbn-core's client API (`owc_get_bylaws()` / `owc_get_bylaw_changes($since)`)
- Or register a filter/action in bylaw-clause-manager that the errata module reads from when they're on the same site

Option 1 is cleaner and consistent with the chronicle/coordinator/territory pattern.

## Permissions

- **Read:** all authenticated users — bylaws are org-wide reference material, everyone should see recent changes
- **Write:** n/a — this module is read-only, bylaw edits happen in bylaw-clause-manager itself

## Change Detection

Three approaches:

1. **Use `post_modified` date** — simplest, but captures any edit including typo fixes. Good enough for v1.
2. **Use a dedicated revision log** — bylaw-clause-manager tracks a separate "bylaw revision" custom post type or meta, with explicit change summaries. More work but cleaner display.
3. **Diff against prior revision** — compute the actual text diff between current and previous post revision. Expensive and hard to present in a small tile.

V1 goes with option 1. If bylaw-clause-manager later adds an explicit changelog structure, the errata module can upgrade to read from that.

## Dependencies

- **bylaw-clause-manager** — the authoritative source. The errata module is effectively a dashboard view on top of bylaw-clause-manager data.
- **owbn-core** — for cross-site data access if bylaws are fetched remotely.

## Open Questions

- **Vote reference** — bylaw-clause-manager already tracks vote metadata per clause. Does it expose this in a way the errata module can read? Needs an audit.
- **Multi-site bylaw display** — if we expose bylaws through owbn-core's client API, does the list response need to be heavy (full clause content) or light (section number + title + modified date only, with detail fetched on click)? Light is probably fine for the errata tile.
- **Changelog vs full bylaw view** — should clicking a change open the full bylaw at that clause, or just a popup showing the changed clause in isolation? Probably the full view, scrolled to that clause, so users can see context.
- **Stale cache handling** — bylaw changes aren't frequent. A 1-hour cache TTL is fine.

## Out of Scope for v1

- Side-by-side diff of old vs new text (complex UI, expensive)
- Subscription / alert on bylaw change (that's what the notifications module + owbn-notifications will handle later)
- Commenting on bylaw changes
- Filtering by bylaw section / topic / genre (v2 if demand)
- Export of change history
