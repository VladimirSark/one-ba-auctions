# Architecture Decision Log

## Auction data in custom tables
- **Decision:** Store credits, participants, bids, winners, audit, and ledger in custom tables.
- **Why:** WooCommerce postmeta is inefficient for high-write flows like bidding; tables enable indexed queries and clean separation.
- **Alternatives:** Store bids/participants in postmeta or custom post types. Rejected for performance/complexity.
- **Consequences:** Requires activation dbDelta migrations; developers must handle direct SQL and upgrades.

## Winner = last valid bid
- **Decision:** Determine winner as the user in the last bid by sequence number when live expires.
- **Why:** Simple lowest-latency rule matching spec; no proxy bidding/price increments.
- **Alternatives:** Highest-price or random tie-breaker. Not needed per spec.
- **Consequences:** Users must outbid by timing; race conditions mitigated by sequence numbers.

## Credit reservation model
- **Decision:** Deduct credits on each bid immediately; refund all non-winner bid credits at auction end; registration fee never refunded.
- **Why:** Simplifies wallet state; avoids holding balances across requests; matches spec of “reserved” then refunded.
- **Alternatives:** Pre-authorize without deducting; hold separate “reserved” bucket. Chosen method avoids dual balances.
- **Consequences:** Users need sufficient live balance before each bid; refunds only happen at end.

## Timers stored in meta
- **Decision:** Store `_pre_live_start` and `_live_expires_at` in post meta.
- **Why:** Lightweight persistence without extra tables; easy to query and adjust in admin.
- **Alternatives:** Separate timer table or transient storage. Meta chosen for simplicity.
- **Consequences:** Clock relies on server time; manual edits possible; ensure gmtime formatting.

## State transitions triggered by polling and cron
- **Decision:** Move pre_live→live and end live auctions during AJAX polling and via minute-level cron/manual trigger.
- **Why:** Avoid stuck auctions when no clients poll; keep logic simple without long-running daemons.
- **Alternatives:** WebSockets/push or background workers. Not needed yet; WP-Cron is acceptable.
- **Consequences:** Resolution within ~1 minute worst-case; reliant on WP-Cron availability.

## AJAX shape and nonce use
- **Decision:** Use `admin-ajax.php` endpoints with nonce `oba_auction`; GET for state, POST for mutations; enforce login for bid/claim/register.
- **Why:** Standard WP pattern; works with localized script data.
- **Alternatives:** REST API endpoints. Ajax chosen for speed with Woo templates.
- **Consequences:** Same-origin required; payloads limited to JSON shape defined in spec.

## Claim order handling
- **Decision:** Credits claim creates paid WC order at 0 EUR with custom payment method `auction_credits`; gateway claim creates payable order at claim price in EUR.
- **Why:** Fits WooCommerce fulfillment pipeline while supporting internal credits.
- **Alternatives:** Custom post for claims. Rejected to leverage Woo orders for shipping/records.
- **Consequences:** Order metadata needed for reporting; payment gateways handle gateway path.

## Admin controls and logging
- **Decision:** Provide admin pages for status actions, winners, credits, settings, and participant removal; log all admin actions and credit changes to audit/ledger tables.
- **Why:** Operational transparency and reversible actions; audit trail for support.
- **Alternatives:** Minimal admin UI. Chosen richer UI for clarity.
- **Consequences:** More surfaces to maintain; audit/ledger tables must be kept in sync on future changes.

## Participants management UI
- **Decision:** Surface participant counts, filters, CSV export, and bulk remove/restore in admin.
- **Why:** Ops needs to triage/remove/restore users quickly and export lists.
- **Alternatives:** SQL/manual edits only. Chosen UI for speed and safety.
- **Consequences:** Bulk actions flip status (not refunding fees); exports limited to filtered set.

## Frontend force-end for admins
- **Decision:** Add admin-only “End now” button using AJAX path that calls winner resolution.
- **Why:** Quick operational control without visiting admin pages.
- **Alternatives:** Admin-only cron/CLI only. Chosen to keep controls near live view.
- **Consequences:** Shares bid endpoint; must keep capability checks tight.

## WP-CLI listing
- **Decision:** Provide CLI to list auctions by status, inspect participants/bids, list winners, and reset live timer.
- **Why:** Fast inspection and control in ops scripts or during support.
- **Alternatives:** Rely on admin UI only. CLI chosen for automation.
- **Consequences:** Lists product titles/status and raw participant/bid/winner rows; no pagination; timer reset uses meta update.

## Product type override instead of custom endpoint pages
- **Decision:** Override single product summary for `auction` type instead of separate page template.
- **Why:** Keeps URL structure and Woo context; reuses product data and theme layout.
- **Alternatives:** Custom page template or shortcode. Override was quickest to integrate.
- **Consequences:** Theme hooks must be compatible; other plugins altering single summary may need adjustments.
