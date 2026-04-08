# dues module

Chronicle dues tracking and payment. Players pay their chronicle dues via PayPal, and chronicle staff track who is paid-up, who is overdue, and who has been delinquent.

## What It Does

- Each chronicle configures its dues schedule (amount, frequency, payment recipient PayPal account)
- Players see their dues status for each chronicle they belong to — paid through date, next due date, amount
- Players pay via PayPal directly from the tile — the module generates a PayPal payment link or Smart Button
- PayPal IPN (Instant Payment Notification) / webhook updates the player's dues status automatically on successful payment
- Chronicle staff see a roster of players with paid/overdue/new status
- Manual adjustments — staff can mark a player paid (cash, offline transfer) or waive dues

## Chronicle Configuration

Per-chronicle settings (configured by CM or HST):

- **PayPal merchant email / business account** — where dues payments are sent
- **Amount** — dollars per billing period
- **Currency** — USD default, configurable (chronicles are global — EUR, BRL, GBP, etc.)
- **Frequency** — monthly, quarterly, annual, custom
- **Grace period** — days after due date before marked overdue
- **Waiver policy** — free text describing when dues can be waived (new players, financial hardship, etc.)

## Player View

- **My Dues** tile — lists each chronicle the player belongs to
- Each entry: chronicle name, amount, next due date, status (paid, due, overdue)
- "Pay Now" button opens PayPal Smart Button or redirects to PayPal checkout
- Payment history for the player (last 12 entries, older archived)

## Staff View

- **Dues Roster** tile — visible to chronicle CM/HST
- Lists all players in the chronicle with dues status
- Filter: all, paid, due, overdue, waived
- Manual actions: mark paid (with payment method and reference), waive for period, adjust amount
- Export to CSV for treasurer reporting

## PayPal Integration

- Per-chronicle PayPal credentials stored in chronicle config (NOT global — each chronicle has its own account)
- Uses PayPal's standard Checkout / Smart Buttons API (client-side), or Orders API for server-side capture
- On successful payment: IPN webhook OR PayPal API verification updates the player's dues record
- Failed payment / chargeback: staff gets notified, status reverts

**Security:** PayPal credentials are sensitive. Stored encrypted in chronicle config (using WP's secrets handling or env vars). Never exposed to players — only the merchant_id / business email in the public button.

## Data Model

- `owbn_board_dues_config` table — one row per chronicle with the config fields above
- `owbn_board_dues_payments` table:
  - `id`
  - `chronicle_slug`
  - `user_id`
  - `amount`
  - `currency`
  - `paid_at`
  - `covers_period_start`, `covers_period_end`
  - `payment_method` (paypal, cash, waived, adjusted)
  - `paypal_txn_id` (nullable)
  - `recorded_by` (staff user who manually recorded, or 0 for automatic PayPal)
  - `notes`

- `owbn_board_dues_status` — computed view / cache of current status per user per chronicle

## Permissions

- **Read own:** players see their own dues status and history
- **Read roster:** `chronicle/{slug}/cm`, `chronicle/{slug}/hst`
- **Write (mark paid / waive / adjust):** same as read roster
- **Configure:** `chronicle/{slug}/cm` (CMs typically handle treasury)
- **Delete payments:** exec only — payments are essentially financial records and shouldn't be casually deleted

## Dependencies

- **owbn-core** — for ASC role resolution and chronicle identification
- **PayPal account** per chronicle — operational dependency, not code
- **owbn-notifications** (optional) — for "dues coming due" reminders to players

## Open Questions

- **Refunds** — how are PayPal refunds reflected in the ledger? Automatically via webhook, or manual staff action?
- **Multi-chronicle membership** — a player in two chronicles pays separate dues to each. Clear.
- **Currency exchange** — if a chronicle charges in EUR and a player pays in USD, PayPal handles the conversion. The recorded amount should reflect what was actually received after conversion/fees.
- **Tax / 1099 reporting** — US chronicles may need tax reporting if dues exceed thresholds. Out of scope for the module, but CMs should export to their treasurer tools.
- **Legacy dues data** — importing historical dues records from spreadsheets chronicles already maintain. Probably via CSV import helper.
- **Anonymous payment** — can someone pay for another player (e.g., a sponsor)? Probably yes via the manual "mark paid" staff action.
- **Stripe / other gateways** — some chronicles may not use PayPal. v1 is PayPal-only; v2 could add gateway abstraction if demand exists.

## Out of Scope for v1

- Subscription billing (auto-renewal via PayPal subscriptions API) — probably v2
- Multi-currency reporting / conversion tracking
- Integration with accounting software
- Receipt generation beyond PayPal's default email receipt
- Tax calculation
