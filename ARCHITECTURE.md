# Architecture Overview

## Product Type & Meta
- WooCommerce product type `auction` (subclass of `WC_Product_Simple`).
- Meta fields: `_registration_fee_credits`, `_bid_cost_credits`, `_required_participants`, `_live_timer_seconds`, `_prelive_timer_seconds`, `_claim_price_credits`, `_auction_status`, `_pre_live_start`, `_live_expires_at`.
- Single-product template override `oba-single-auction.php` replaces add-to-cart with 4-step UI (registration → countdown → live bidding → ended).

## Credits System
- User wallet stored in `wp_auction_user_credits`.
- Credit packs: simple/virtual products marked `_is_credit_pack` with `_credits_amount`; credits added on `woocommerce_order_status_completed`.
- Credits service (`OBA_Credits_Service`) for balance read/add/subtract/set; logs to ledger.
- Ledger table `wp_auction_credit_ledger` records every credit change with reason/reference.
- “My Credits” endpoint in Woo account shows balance; admin can edit balances.

## Custom Tables
- `wp_auction_user_credits`: per-user balance.
- `wp_auction_participants`: registrations with fee and status.
- `wp_auction_bids`: bid log with sequence and reserved credits.
- `wp_auction_winners`: winner, totals, claim price, Woo order id.
- `wp_auction_audit_log`: admin actions trail.
- `wp_auction_credit_ledger`: credit transactions trail.

## Auction Flow
- States: `registration` → `pre_live` → `live` → `ended`.
- Registration: requires login, T&C (if set), deducts fee, inserts participant. When participant count reaches required, sets status `pre_live` and `pre_live_start`.
- Pre-live: countdown = `pre_live_start` + `prelive_timer_seconds`. On expiry, status -> `live`, sets `live_expires_at`.
- Live: bids require registration, login, sufficient credits, and not already leading. Each bid deducts bid cost, logs bid, resets live timer (`live_expires_at` = now + `live_timer_seconds`).
- End: when `live_expires_at` passed, status -> `ended`, winner = last bid. Non-winner bid credits refunded; winner credits consumed. Winner row inserted.
- Claim: winner only, status `ended`, and no existing order. Credits mode deducts claim price and creates paid order with payment method `auction_credits`; gateway mode creates payable order priced at claim credits.

## AJAX Endpoints
- `auction_get_state`: returns status, lobby percent, timers, user bids/cost, history (last 5), winner flags, claim info; also triggers expiry check.
- `auction_register_for_auction`: validates login/T&C/state/credits, registers user, deducts fee, returns state.
- `auction_place_bid`: validates login/state/registration/credits/not-leading, records bid, deducts credits, resets timer, returns state.
- `auction_claim_prize`: validates winner/ended/no order; handles credits or gateway order creation and redirects.

## WooCommerce Integration
- Product type registration and meta panels via Woo hooks.
- Single-product summary override to custom template for auction type.
- Order integration for credit packs (on completed orders).
- Claim flow creates Woo orders (paid via custom method or standard gateways) with user addresses hydrated.
- Admin bulk actions on Products list for starting live and force-ending auction products.

## Admin & Settings
- Custom Auctions menu: Auctions (status-filtered with counts and actions and per-status participant counts), Winners, User Credits (edit balances), Participants (filters by status/user, counts, bulk remove/restore/ban, CSV export), Audit Log (optionally filtered by user to show ledger), Settings (default timers, poll interval, T&C).
- Participant removal and bulk actions; manual “run expiry check now”.
- Settings stored in `oba_settings` option; defaults applied to timer meta and localized to frontend.

## Frontend UI/JS
- `assets/js/auction.js` polls state on interval; updates steps, timers, lobby (percent), history, status pill; disables bid when leading; shows toasts/errors/success banner with last-refreshed; handles registration (with T&C modal/checkbox), bid, claim modal (credits vs gateway).
- `assets.css/auction.css` styles 4-step UI, timers, history, modals, toasts.
- Localization: `ajax_url`, nonce, `auction_id`, `poll_interval`, `terms_text`.
- Credits display: optional pill now floats bottom-right on all pages (if enabled); shows balance, highlights when low, and opens a credit-pack modal on click (links/labels from settings). Inline pill removed. System time label is hidden. Ended step shows colored outcome cards (win/lose) without titles and includes a refund note for non-winners; registered users see a green lobby note. Status pill is clickable (info icon), keeps a short step label (1–4), and opens a steps modal with content from settings. All modals have elevated z-index and increased top gap (with admin-bar adjustment) to avoid being hidden under headers. Frontend layout: two-column product + phase cards, with 4-step explainer bar above; cards collapse when complete. Audit logging captures auction endings (winner, totals, trigger, last bid, refunds) and claims; admin “Ended Logs” page surfaces these entries with filtering. Email layer added with configurable sender; sends pre-live/live start, winner/loser end, claim, credit edit, and participant status notifications.
- Live auctions show quick credit pack links (from settings) when the user lacks credits.
- Credits pill balance updates via AJAX state.

## Reliability
- Cron `oba_run_expiry_check` scheduled every minute to end expired live auctions; manual admin trigger available; admin-only frontend “End now” button to force winner resolution.
- Bid endpoint enforces login; nonce validation on AJAX calls; WP-CLI commands for expiry, end/recalc, listing auctions by status (with live expiry), listing participants/bids/winners, ledger export, and resetting live timer.
- Admin auctions list shows participant counts and live expiry; participants page supports filters, pagination, CSV export, and bulk status changes (remove/restore/ban/restore-all).
