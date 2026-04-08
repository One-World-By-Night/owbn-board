# i18n module

Terminology glossary for pt/BR ↔ en/US translation. Portuguese-speaking OWBN members contribute common terms they encounter in bylaws, game content, community jargon, and administrative language so that translations (automated or human) use consistent, correct phrasing.

This is **not** a full translation management system. OWBN already uses TranslatePress for page/post translation. This module fills a gap: capturing the domain-specific vocabulary that generic translators don't know — and getting it from native speakers who actually play.

## What It Does

- pt/BR native speakers log in and contribute glossary entries
- Each entry has the pt/BR term, the recommended en/US equivalent, context, and optional notes
- Entries are reviewed by a small editorial group (e.g., pt/BR translator leads) before being marked authoritative
- Translators, developers, and TranslatePress maintainers consult the glossary when translating content
- Export the glossary in formats that translation tools can consume (CSV, TMX, JSON)

## Why This Exists

OWBN has pt/BR chronicles. Bylaws, rules, announcements, and gameplay terminology are full of domain-specific language that:

- Generic machine translators mangle
- Professional translators unfamiliar with World of Darkness LARP misinterpret
- Volunteer translators need a shared reference to stay consistent

Rather than hoping each translator makes the same choices, we crowdsource the glossary from players who already know both languages and the game.

## Entry Fields

- **Source term (pt/BR)** (required)
- **Recommended translation (en/US)** (required)
- **Term type** — noun, verb, phrase, idiom, jargon, proper noun, game term
- **Context / domain** — where this term appears (bylaws, rules, in-character speech, admin communication, etc.)
- **Notes** — free text explaining nuance, gotchas, regional variations, why this translation was chosen
- **Examples** — one or more example sentences showing the term in use with suggested translations
- **Alternatives** — other acceptable translations if multiple are valid
- **Status** — submitted, under review, approved, deprecated
- **Submitter** — WP user who contributed the entry
- **Reviewers** — WP users who approved the entry
- **Revision history** — each edit captured for accountability

Reverse lookup works too — a translator going en/US → pt/BR can search by the English term.

## Permissions

- **Read:** all authenticated users (the glossary is a shared resource)
- **Submit:** any user with a pt/BR chronicle role OR a designated "translator" ASC role
- **Review/approve:** designated translator-lead role (configured via ASC, e.g., `i18n/pt_br/lead` or reused `exec/i18n/*`)
- **Edit approved entries:** translator leads only
- **Delete:** translator leads only (soft-delete with reason)

## Tile Views

- **Glossary browser** tile — search and filter glossary entries
- **My Submissions** tile (for contributors) — shows entries the user has submitted and their status
- **Review Queue** tile (for translator leads) — pending submissions awaiting approval

## Data Model

- `owbn_board_i18n_terms` table:
  - `id`
  - `source_term` (pt/BR)
  - `target_term` (en/US)
  - `term_type`
  - `context`
  - `notes` TEXT
  - `alternatives` TEXT (serialized)
  - `status` (submitted, under_review, approved, deprecated)
  - `submitted_by`, `submitted_at`
  - `reviewed_by`, `reviewed_at`
  - `updated_at`

- `owbn_board_i18n_term_examples` table:
  - `id`, `term_id`, `source_sentence`, `target_sentence`, `notes`

- `owbn_board_i18n_term_revisions` — revision history for each term (append-only), same pattern as notebook history

## Export Formats

- **CSV** — simple two-column export for spreadsheet-based translators
- **TMX** (Translation Memory eXchange) — industry standard format that CAT tools and TranslatePress can import
- **JSON** — structured export for custom tooling

Export is on-demand via a button in the admin view. No automatic sync to TranslatePress yet — v2 might add a "push approved terms to TranslatePress glossary" action once we understand TranslatePress's glossary API.

## Dependencies

- **owbn-core** — for ASC role resolution
- **TranslatePress** (optional) — not a hard dependency. If TranslatePress is active on a site, this module can optionally export terms into its glossary.

## Open Questions

- **Other language pairs** — the initial need is pt/BR ↔ en/US. Should the data model support arbitrary language pairs from day one (so Spanish, French, German can be added later)? Probably yes — the schema cost is trivial (add `source_lang` and `target_lang` columns) and it avoids a painful migration.
- **Authority on disputes** — what happens when two reviewers disagree on the correct translation? Need a tiebreaker rule or dispute process.
- **Integration with TranslatePress** — does TranslatePress have an API for adding glossary entries programmatically? Needs audit.
- **Bulk import from existing translation work** — if someone has a spreadsheet of terms already, can they import it? Probably yes via CSV import helper.
- **Who is the authority?** — the OWBN pt/BR translator lead is the authority on pt/BR terminology. This module assumes that role exists in the org; if not, establishing it is a prerequisite.

## Out of Scope for v1

- Full document translation workflow (that's TranslatePress's job)
- Machine translation integration (DeepL, Google Translate API)
- Quality scoring or voting on translations
- Contextual screenshots or media attachments
- Language pairs beyond pt/BR ↔ en/US (keep the schema flexible but the UI scoped)
