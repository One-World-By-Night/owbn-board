# owbn-board Shortcodes & Elementor Reference

Three shortcodes ship with owbn-board: `[owbn_board]`, `[owbn_events]`, and `[owbn_ballot]`. All three work in the WordPress block editor, the Classic editor, and inside Elementor's **Text Editor** and **Shortcode** widgets. owbn-board does not ship dedicated Elementor widget classes — the shortcodes are the integration point.

## Quick reference

| Shortcode | Attributes | Public? | Typical use |
|---|---|---|---|
| `[owbn_board]` | none | login required | Full dashboard at `/dashboard` |
| `[owbn_events]` | `limit`, `id`, `host`, `show_rsvp`, `show_cta` | yes | Upcoming events on public or logged-in pages |
| `[owbn_ballot]` | `election_id`, `limit`, `show_closed` | yes (read), login for voting | Election ballot embedded on a council page |

---

## `[owbn_board]`

Renders the full role-gated tile dashboard — the same layout a user sees at `/dashboard`. This is the main shortcode for hosting the board somewhere other than its default URL.

- **Attributes**: none.
- **Logged-out**: shows a login-required message.
- **Logged-in**: runs `owbn_board_render()`, which enqueues assets and renders every tile the user has `read_roles` access to, honoring tile-access overrides, per-user tile state (collapse/pin/snooze), and the current site's layout.
- **Host page placement**: put it at `/dashboard` (the default `url_path`) on player-facing sites. **Do not place it on a public homepage** — anonymous visitors will see a login prompt where they expect marketing content. Use `[owbn_events]` for public-facing pages instead.

**Example**
```text
[owbn_board]
```

---

## `[owbn_events]`

Renders the upcoming-events list. Works for anonymous visitors (public read), so it's the right choice for a public events page on players.owbn.net or a future www.owbn.net.

### Attributes

| Attribute | Type | Default | Meaning |
|---|---|---|---|
| `limit` | int | `6` | Max events to show. Ignored when `id` is set. |
| `id` | int | `0` | Render a single event card by ID. Overrides `limit` and `host`. Only published events render. |
| `host` | string | `""` | Filter by host scope (e.g. `chronicle/mckn`). Ignored when `id` is set. |
| `show_rsvp` | bool | `true` | Show Interested / Going buttons for logged-in users. Logged-out users see a "Log in to RSVP" link. |
| `show_cta` | bool | `false` | Show a "Create an event →" link for users with event-create permissions. |

### Examples

```text
[owbn_events]
[owbn_events limit="12"]
[owbn_events id="42"]
[owbn_events host="chronicle/mckn" limit="20" show_rsvp="false"]
[owbn_events show_cta="true"]
```

### Behavior notes

- Events data lives on chronicles.owbn.net. On other OWBN sites, the shortcode reads via `owc_events_*` wrappers through the owbn-gateway `/events/*` endpoints.
- RSVP clicks work cross-site via the `/events/rsvp/set` and `/events/rsvp/get` gateway endpoints (F2, owbn-core 1.6.0 / owbn-gateway 1.5.0). Players on any OWBN site can RSVP directly without being bounced to chronicles.
- Permalinks point to chronicles.owbn.net; cross-site links are SSO-wrapped.
- **Public by design**: OWBN governance is transparent. Don't put sensitive details in event descriptions.

---

## `[owbn_ballot]`

Renders the full-page ballot with Submit All button. On council.owbn.net, writes hit the local wpvp tables; elsewhere they proxy through owbn-gateway `/wpvp/votes/cast` (F3, owbn-core 1.7.0 / owbn-gateway 1.6.0).

### Attributes

| Attribute | Type | Default | Meaning |
|---|---|---|---|
| `election_id` | int | `0` | Scope the ballot to a specific election (from `oeb_election_sets`). When set, only votes in that election's positions render. |
| `limit` | int | `0` | Max votes to show. `0` means no limit. |
| `show_closed` | bool | `false` | Include closed/archived votes alongside open ones. |

### Examples

```text
[owbn_ballot]
[owbn_ballot limit="10"]
[owbn_ballot election_id="7"]
[owbn_ballot election_id="7" show_closed="true"]
```

### Behavior notes

- Vote cards render for anonymous visitors in read-only mode (no Submit All button).
- Logged-in users see Submit All. Clicking it POSTs each selection through `owbn_board_ballot_cast` → `owc_wpvp_cast_ballot`, which dispatches locally on council or cross-site via gateway.
- Supports FPTP (singleton) and ranked (RCV, STV, sequential RCV, Condorcet). Consent and disciplinary vote types still require wp-voting-plugin's native ballot page (state transitions out of proxy scope).
- Multi-eligible-role users (e.g. a player holding multiple coordinator posts): the first Submit All attempt returns a `requires_role_selection` error; today they need to fall back to wp-voting-plugin's native ballot. A role picker UI is planned.
- **Public by design**: proposal titles and options are visible to anonymous visitors.

---

## Elementor integration

There is **no dedicated owbn-board Elementor widget**. Use Elementor's built-in widgets to embed the shortcodes:

1. **Shortcode widget** (simplest): Elementor → Elements → General → Shortcode. Paste the shortcode in the field.
2. **Text Editor widget**: Elements → General → Text Editor. Paste the shortcode inline with other text. Elementor runs `do_shortcode()` on the text.
3. **HTML widget**: Elements → General → HTML. Same behavior — shortcodes are processed.

### Elementor + `[owbn_board]`

The dashboard shortcode is styled for a full-width column. Drop it into a single-column section at 100% width. The board's own grid handles tile layout; don't wrap it in Elementor's grid or flex containers.

### Elementor + `[owbn_events]` / `[owbn_ballot]`

Both render as self-contained HTML lists. They work at any column width. You can combine them with other Elementor widgets in the same section (heading, image, text, etc.).

### Caching note

If you use Elementor with a page cache (WP Rocket, LiteSpeed, W3 Total Cache), be aware:

- `[owbn_board]` renders per-user content. Exclude pages containing this shortcode from page cache or they'll show one user's tiles to everyone.
- `[owbn_events]` and `[owbn_ballot]` RSVP/vote state is per-user; the initial card HTML is cacheable but the buttons' active state is not. On heavily-cached public sites, either live-refresh the button state via JS after page load, or exclude the page from cache.

### Planned work

A native Elementor widget (with an Elementor control panel exposing the shortcode attributes as form fields) is a potential future addition. For now the Shortcode widget covers the same ground with no extra maintenance burden.

---

## Common pitfalls

- **Nesting shortcodes inside the events approval queue**: the approval UI strips shortcodes from pending event bodies before rendering (E-003 fix). Don't expect `[owbn_events]` inside an event description to render — it will be stripped.
- **Mixing `[owbn_ballot election_id=X]` with `show_closed="true"`**: this shows all positions in that election regardless of stage. Useful for archival pages but not for active voting.
- **Putting `[owbn_board]` on www.owbn.net homepage**: that page typically targets anonymous visitors. Use `[owbn_events]` for public content instead.
- **Building a custom Elementor template that wraps `[owbn_board]` in a flex/grid container**: the board uses its own CSS grid. External flex containers may distort tile sizes. Keep the shortcode in a plain text/shortcode widget inside a single-column section.
