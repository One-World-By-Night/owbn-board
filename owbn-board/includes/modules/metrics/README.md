# metrics module

Platform health dashboard for the OWBN web team. Internal tool — not player-facing, not exec-facing in the normal sense. Shows statistics about users, chronicles, participation, and platform usage so the web team can monitor the health of the organization and spot trends.

## Who Sees This

**Web team only.** Access is restricted to accessSchema roles matching `exec/web/*` (web coordinator, web assistant coordinators, web staff). Nobody else — not even other exec offices — sees this module by default.

Tile visibility is strictly gated by `read_roles: ['exec/web/*']`. If the web team wants to grant access to another role temporarily (e.g., giving exec leadership read access), they adjust the tile permissions rather than opening it broadly.

## What It Measures

### User Metrics
- Total registered users across all sites
- New user signups (7d, 30d, 90d, 365d)
- Active users (logged in within the window)
- Users by ASC role category (chronicle staff, coordinator, exec, player)
- Orphan users (no ASC roles assigned)

### Chronicle Metrics
- Total active chronicles, probationary, decommissioned
- Chronicles by genre, region, country
- Chronicle staff counts (HSTs, CMs, ASTs)
- Chronicles with/without recent activity (session scheduled in last 30 days, recent staff updates, etc.)
- Satellite chronicles vs primary

### Participation Metrics
- OAT entries submitted per period
- Vote participation rates on council votes
- Bylaw change frequency
- Territory updates per period
- Active support tickets

### Platform Usage
- Page views per site (from WP analytics if available)
- API requests to the owbn-gateway
- Cache hit/miss ratios
- Error rates (from WordPress error log aggregation if available)

## Tile Views

Metrics are usually small numeric cards with trend sparklines rather than full charts. Multiple small tiles on the web team dashboard, each focused on one category:

- **Users at a Glance** — total, new, active, orphan
- **Chronicle Health** — active count, recent activity rate, stale chronicles list
- **Participation** — OAT throughput, vote turnout, bylaw activity
- **Platform Health** — error rates, cache stats, API usage

Each card can expand to a larger detail view with a chart (via Chart.js or similar lightweight library) and historical breakdown.

## Data Sources

This module **does not generate its own data** — it reads from the other plugins:

- WordPress user table via `$wpdb` on each site (users, registration dates, last login)
- accessSchema role assignments via `owc_asc_get_all_roles()` and friends
- Chronicle data via `owc_get_chronicles()`
- OAT metrics via OAT's own API (if OAT exposes aggregate stats)
- wp-voting-plugin via its stats API
- bylaw-clause-manager via recent-changes endpoint
- owbn-support via ticket counts API

Many of these require the metrics module to make multiple cross-site API calls (via owbn-gateway) to build a unified org-wide view. Caching is essential.

## Data Model

No new tables — metrics are computed from existing plugin data. Caching:

- `owbn_board_metrics_cache` transient (or custom table if transient limits become an issue)
  - Keyed by metric name + site + window
  - Refreshed on a cron schedule (hourly for most metrics, daily for heavy aggregates)

## Refresh Strategy

- **Fast metrics** (counts of users, chronicles) — refreshed hourly via cron
- **Medium metrics** (participation rates, activity windows) — refreshed every 4 hours
- **Heavy metrics** (aggregated historical trends) — refreshed nightly
- **Manual refresh** button on each tile for the web team

## Permissions

- **Read:** `exec/web/*` only
- **Write:** n/a — this module is read-only
- **Configure:** `exec/web/coordinator` can adjust the cron schedule, pick which metrics to display, and set alert thresholds

## Dependencies

- **owbn-core** — for ASC role checks and cross-site client API
- **owbn-gateway** — for cross-site data aggregation
- Whichever OWBN plugins the specific metrics pull from (OAT, wp-voting-plugin, etc.)

## Out of Scope for v1

- Alerting / notifications on metric thresholds (e.g., "ticket backlog exceeds 50") — that's useful but adds complexity. V2.
- Per-user behavior tracking (privacy concerns, and GDPR-sensitive) — we stick to aggregate counts
- Exporting metrics to external tools (Grafana, Datadog) — out of scope unless there's a specific need
- A/B testing or experimentation framework
- Historical drill-down UI beyond simple trend sparklines

## Open Questions

- **Cross-site data aggregation performance** — querying 6 sites for user counts every hour adds load. Is the owbn-gateway the right layer, or should each site push metrics to a central store?
- **Which plugins expose metric APIs already?** Probably none — this module may need to add lightweight metric endpoints to OAT, wp-voting-plugin, etc., as it's built out. Track that cost.
- **Privacy boundary** — aggregates are fine. No individual user tracking beyond "did this user log in within the window."
- **Retention** — do we keep historical snapshots of metrics for trend analysis, or only the rolling windows? If historical, storage grows indefinitely. Start with rolling windows, add snapshots only if needed.
