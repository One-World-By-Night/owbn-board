# resources module

Non-binding reference library for players and staff. Articles, sources, and curated links covering gameplay, participation, LARP etiquette, safety tools, rules primers, genre introductions, chronicle-running guides, and similar educational material. Explicitly non-canonical — this is help and guidance, not authoritative rules.

## What It Does

- Hosts **articles** (WordPress posts in a custom post type) written by org volunteers, coordinators, or community contributors
- Hosts **links** to external references, videos, articles, books, or community resources
- Tagged and categorized so players can browse by topic (new player guide, ST advice, safety, specific genre, etc.)
- Searchable via the board's universal search module
- Tile on the dashboard shows recent additions and a topic browser

## Content Types

### Articles
- WordPress custom post type, probably `owbn_resource_article`
- Standard WP post fields: title, body, author, excerpt, featured image, categories, tags
- Edited via WP admin (Gutenberg or classic editor)
- Can be marked "featured" to appear in the tile's spotlight slot

### Links
- Lightweight entries: title, URL, short description, category, tags
- Stored as a simpler CPT or custom table — no body text, just pointer metadata
- Can be annotated ("why this is useful", "who it's for")

## Permissions

- **Read:** all authenticated users
- **Write:** coordinators, exec team, or a designated "resource contributor" role
- **Edit/delete:** author + admins + exec team
- **Categorization:** admins manage the category taxonomy

## Tile View

- **Resource Library** tile
- Shows: featured articles, recently added entries, category browser (click a category to filter)
- Search box that filters within the resource library
- Click an article → opens the full post
- Click a link → opens the external URL in a new tab

## Data Model

- `owbn_resource_article` CPT — standard WP post type with taxonomies
- `owbn_board_resource_links` table OR `owbn_resource_link` CPT — simple link records
- Categories taxonomy shared between articles and links

## Dependencies

- **owbn-core** — for ASC role resolution (contributor-level permissions)
- None otherwise — this is a self-contained module

## Open Questions

- **Contribution workflow** — should there be a "suggest a resource" form for regular players, with staff moderation? Or is contribution limited to a designated role?
- **Versioning** — since this is non-binding reference material, are article edits tracked with revision history? Probably just use WP's built-in post revisions.
- **Multi-language** — if TranslatePress is active, articles get translated like any other post. Links probably need per-language URL overrides.
- **Rating / feedback** — should users be able to mark resources as helpful? Deferred — adds complexity for marginal value until there's a real content library.

## Out of Scope for v1

- Structured "learning path" curricula (e.g., "new player onboarding sequence")
- Gated content for paying members
- File attachments beyond WP media library
- Analytics on which resources are most viewed
