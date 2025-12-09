# Architecture Decision Log

## Points + membership model
- **Decision:** Require membership to view/participate; store points in `wp_auction_user_points`; membership products grant points on order completion and set `_oba_has_membership`.
- **Why:** Replace the previous checkout/credits flows with a lightweight balance tied to memberships.
- **Alternatives:** Continue pay-to-register via checkout or legacy credits wallet. Rejected to reduce friction and duplicated carts.
- **Consequences:** Registration uses point deduction only; membership flag/points must be kept in sync; points ledger/table required on activation.

## Registration without Woo checkout
- **Decision:** Registration deducts `_registration_points` directly via AJAX after validation (login, membership, T&C, status).
- **Why:** Avoid cart/checkout for registration and keep lobby updates immediate.
- **Alternatives:** Add registration product to cart. Rejected to prevent pending orders and user confusion.
- **Consequences:** No Woo order exists for registration; refunds are not applicable; validation errors handled in AJAX responses.

## Admin point-value calculator
- **Decision:** Store a single “point value” in settings and auto-calculate registration monetary value in the auction editor based on points × required participants.
- **Why:** Give admins quick visibility into real-world cost/profit without reintroducing checkout products.
- **Alternatives:** Keep registration fee products. Rejected because registration is fully point-based now.
- **Consequences:** Estimations rely on the configured point value; update it to match business rules.

## Claim recognized by completed claim order
- **Decision:** Mark auction as claimed when a completed order exists with `_oba_claim_auction_id`; store `wc_order_id` in winners.
- **Why:** Align claimed status with paid orders.
- **Alternatives:** Treat any order meta as claimed. Rejected because on-hold/pending orders are not final.
- **Consequences:** Admin detail looks up completed orders; claim button remains until completion.

## Consolidate admin under 1BA menu
- **Decision:** Use a single “1BA Auctions” menu for lists, detail, settings, audit, and memberships.
- **Why:** Reduce clutter from legacy menus after pivots.
- **Alternatives:** Keep dual menus. Rejected to avoid confusion and deprecated redirects.
- **Consequences:** Old menu links removed; new slugs start with `oba-1ba-*`.

## Auction data in custom tables
- **Decision:** Store participants, bids, winners, audit (and points) in dedicated tables; timers remain in post meta.
- **Why:** Indexed writes for bidding/participants and simple meta for timers.
- **Alternatives:** Postmeta/custom post types for bids. Rejected for performance.
- **Consequences:** Activation must run migrations; direct SQL usage required.

## Winner = last valid bid
- **Decision:** Winner is the user in the last bid by sequence when live expires.
- **Why:** Matches spec; simplest deterministic rule.
- **Alternatives:** Highest-price or proxy bidding. Not required.
- **Consequences:** Timing matters; bid lock prevents current leader repeats.

## State transitions via polling + cron
- **Decision:** Advance pre_live→live and end live auctions during AJAX polling and via minute cron/manual end.
- **Why:** Prevent stuck auctions when no users are connected.
- **Alternatives:** WebSockets/background workers. Deferred for now.
- **Consequences:** Worst-case ~1 minute lag; depends on WP-Cron.

## AJAX shape and nonce use
- **Decision:** `admin-ajax.php` endpoints with nonce `oba_auction`; GET for state, POST for mutations; enforce login for bid/register/claim.
- **Why:** Standard WP pattern compatible with localized scripts.
- **Alternatives:** REST endpoints. Ajax chosen for speed in Woo templates.
- **Consequences:** Same-origin; JSON matches frontend mapping.

## Admin controls, logging, and CLI
- **Decision:** Keep admin pages for status actions, participants, audit log, settings, memberships; CLI for listing/ending/resetting.
- **Why:** Operational visibility and support.
- **Alternatives:** Minimal UI. Rejected to avoid SQL reliance.
- **Consequences:** More surfaces to maintain; audit must stay in sync with actions.
