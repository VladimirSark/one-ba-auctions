# One BA Auctions (Work in Progress)

Credits-based auction system for WooCommerce with AJAX-driven 4-step frontend.

## Implemented (2025-11-20)
- Custom product type `auction` with meta: registration fee, bid cost, required participants, live/pre-live timers, claim price, status.
- DB tables created on activation: `wp_auction_user_credits`, `wp_auction_participants`, `wp_auction_bids`, `wp_auction_winners`.
- Credits wallet service; credit packs (simple/virtual products) grant credits on order completion; “My Credits” account endpoint.
- Auction frontend replaces add-to-cart with 4-step UI; AJAX state polling, registration, bidding.
- Auction engine: registration validation, bidding deduct/reserve credits, prevents same user from repeat leading bids, live timer reset, auto moves to pre-live when full, auto moves to live when pre-live countdown hits 0 (or when manually set live with no timer), auto end check, winner selection (last bid), refunds all non-winner reserved bid credits, inserts winners row, marks auction ended.
- Claim flow: winner can claim via credits (deducts claim price, creates paid WC order, stores `wc_order_id`) or gateway (creates payable order and redirects to checkout).
- Admin: Custom Auctions menu with status-filtered auction lists (registration/pre_live/live/ended), actions to start pre-live/live, force end, recalc winner; Winners list; User Credits page with inline balance editing. Settings page placeholder.
- Audit: Logs admin actions (start live/pre-live, force end, recalc winner, expiry runs, credit edits, participant removal, bulk actions) with an Audit Log admin page.
- Settings: default pre-live/live timers, poll interval, Terms text (required on registration if set).
- Frontend: Terms link opens modal showing configured T&C; acceptance required to register.
- UX: Terms checkbox surfaces a red highlight when not accepted; styled history/status/claim modal.
- Reliability: Cron expiry, manual admin expiry trigger, and WP-CLI commands (`oba auctions expire`, `oba auctions end --id=<auction_id>`, `oba auctions list`, participants/bids/winners/list, reset timer).
- Optional frontend credits pill (settings toggle) and shortcode `[oba_credits_balance]` to show current balance on any page; pill expands on hover to show credit pack links and highlights when balance is low; inline pill shown inside auction header (header pill hidden on auction pages).
- Live auctions show quick credit pack links (up to three URLs from settings) when the user lacks credits.

## Important Notes
- Registration credits are never refunded; bid credits are refunded to non-winners on auction end. Winner’s reserved bid credits remain consumed.
- Claim endpoint requires auction status `ended`, winner match, and no existing `wc_order_id`.
- Frontend shows claimed state and disables claim once an order is stored.
- Winner resolution is guarded to avoid double-processing if it already ran.
- Cron: scheduled minute-level expiry check runs `oba_run_expiry_check` to end live auctions even without frontend polling; manual “Run expiry check now” button in admin.

## Remaining Tasks / Gaps
- Remove participant and deeper admin actions not yet implemented.
- Reservation model is simple: credits are deducted on each bid and refunded on end for non-winners; no mid-auction unreserve.
- Error messaging/UX can be improved (e.g., modal for payment choice, better history display).
- Cron/on-request guard could be expanded beyond polling to end auctions.

## Quick Manual Test Checklist
- Activate plugin; confirm tables exist.
- Create auction product; verify meta saves; open single view and see 4-step UI.
- Create credit pack product, complete order, confirm balance updates in “My Credits”.
- Register for auction with credits; set status to live, place bids; observe timers/history/registered/bids counts updating via AJAX.
- Force end by setting `_live_expires_at` in the past while status is live; ensure status ends, non-winner credits are refunded, winners table row appears.
- Claim as winner: choose credits (balance decreases, order created, redirect to view order) or gateway (redirect to checkout; `wc_order_id` stored).
