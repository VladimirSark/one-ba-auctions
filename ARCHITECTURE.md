# Architecture Overview (current)

## Product type & meta
- WooCommerce product type `auction`.
- Key meta: registration points, bid product ID, required participants, pre-live timer seconds, live timer seconds, status (`registration|pre_live|live|ended`), `pre_live_start`, `live_expires_at`.
- Single-product template (`templates/oba-single-auction.php`) replaces add-to-cart with a 4-step interface.

## Data tables
- `wp_auction_participants`: user registrations with status/timestamp.
- `wp_auction_bids`: bid log with sequence and bid value.
- `wp_auction_winners`: winner record with totals, claim price, `wc_order_id`.
- `wp_auction_audit_log`: admin/engine events.
- `wp_auction_user_points` (+ ledger if present): membership points balances.

## Auction flow
- Statuses: `registration` → `pre_live` → `live` → `ended`.
- Membership & points: membership required to view/participate. Membership products grant points on WC order completion. Registration deducts points immediately (no checkout) after validating login, membership, T&C, and status.
- Pre-live: starts when participants reach required; countdown = `pre_live_start` + pre-live seconds; on expiry -> `live` and `live_expires_at` set/reset.
- Live: bids require registration and not being current leader; each bid logs to `auction_bids` and resets live timer; timer expiry ends auction.
- Ended: winner = last bid. Winners row inserted; claim price = total bid value.
- Claim: winner triggers claim; adds bid total to checkout; claim order tagged with `_oba_claim_auction_id`; claimed considered when a completed order exists; `wc_order_id` stored/backfilled.

## AJAX/API
- `auction_get_state`: status, lobby %, timers, user bids/cost, history (last 5), winner flags, claim info, membership flag, user points balance; also checks expiry.
- `auction_register_for_auction`: validates login/T&C/status/membership; deducts registration points and inserts participant; returns updated state.
- `auction_place_bid`: validates login/registration/status/not-leading; logs bid, resets timer; returns state.
- `auction_claim_prize`: winner-only, ended, no existing claim order; prepares checkout with bid total; redirects to checkout.

## WooCommerce integration
- Product type/meta panels; single template override.
- Membership products: meta `_is_membership_plan_points=yes`, `_points_amount` grant points on order completion and flag membership.
- Bids priced from bid product; claim uses bid total.
- Claim orders carry meta `_oba_claim_auction_id` for tracking.
- Point value setting stored in options to estimate monetary equivalents.

## Frontend
- Assets: `assets/js/auction.js` (polling, actions), `assets/css/auction.css` (layout/styling).
- UI: 4-step cards (registration, pre-live, live, ended), lobby bar with %, timers, history list, toasts, T&C modal, winner/loser blocks, leading-bidder lockout, membership lock overlays, “Your points” display.

## Admin
- Menu: **1BA Auctions** with All Auctions (filter by status, participants registered/required), auction detail (status, winner, claim/order link, end time, totals, participant log with inline removal), Audit Log, Settings (General/Emails/Translations inline), Memberships screen (membership flag + points editor).
- Actions: start pre-live/live, manual winner entry, participant removal, claim status visibility.
- Settings: timers, poll interval, T&C text, login link, status-info HTML, email sender, translations overrides, email templates.

## Reliability
- Cron expiry check (minute) plus manual end/pre-live/live actions; admin “End now”.
- Claimed state tied to completed claim order; points stored in dedicated table.
- Login + nonce enforced on AJAX actions; membership/points checked server-side.
