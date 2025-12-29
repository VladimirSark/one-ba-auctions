# Changelog

## 2025-12-27
### Changed
- Autobid cron cadence increased to ~10s (self-heals legacy 60s schedule) and autobid-enabled timers now extend to a minimum of 15s instead of forcing 60s; short live timers no longer skip autobid runs.
- `auction_get_state` now triggers autobids when enabled, so frontend polling can fire autobids even if cron is delayed.
- Autobids are throttled to fire only when ≤4s remain on the timer and are guarded per-second to avoid multiple runs in the same second (poll + cron overlap).
- Autobid fire window widened to 15s and time-window skip logging removed to reduce noise and prevent missed bids when timers are frequently extended.

## 2025-12-22
### Changed
- Autobid cards (registration/live) simplified: compact inline spend input, single toggle switch, clean status text (no gradient/shadow), removed preset calc text, and enable now uses the user-entered EUR amount instead of defaulting to 1 bid.
- Auction products now expose WooCommerce inventory + shipping tabs and allow virtual/downloadable flags, using core stock/meta handling.

## 2025-12-09
### Added
- Points-based registration: auctions define a registration points cost; membership products grant points on order completion (stored in `wp_auction_user_points`); membership flag required to view/participate.
- Memberships admin screen (1BA → Memberships) to toggle membership flag and edit user points.
- AJAX state now returns `user_points_balance` and `membership_active`; frontend shows “Your points” and lock overlays when membership is missing.
- Settings now include “Point value” for money-equivalent calculations; auction edit shows auto-calculated registration value based on points × required participants.
### Changed
- Registration no longer creates WooCommerce orders; it deducts points immediately after validation.
- Claim flow unchanged: winner pays total bid value via checkout; claim order uses `_oba_claim_auction_id` meta; claimed status shows order link when completed.
- Emails/translations cleaned of credits references.
- Removed legacy product selectors (credit pack, claim product, registration fee product, membership plan, limits, credits amount); only bid product flag remains.
- Auction detail totals now use registration points/value and show winner-only bid value plus savings vs. cost; legacy fee totals removed; “All Auctions” menu item restored.
- Savings callouts added to ended state (winner/loser) using product cost; points labels/suffix and savings copy translatable; points pill no longer opens credit modal.
- Product cost stored on auction; profit calculations use points × participants – cost; money formatting now uses store currency.
### Fixed
- Removed legacy membership slots UI and related fatal redeclare from admin; membership management now matches points model.

## 2025-12-08
### Added
- 1BA Auctions admin menu with All Auctions list (status filter, participants registered/required) and auction detail (status, winner, claimed order link/status, end time, totals, participant log with inline removal).
- Inline participant actions within auction detail; Settings tabs (General/Emails/Translations) render inline under 1BA menu.
### Changed
- Removed previous credits/membership-slot flows; registration and claim previously used checkout products; claim status derived from completed claim orders.
### Removed
- Old Custom Auctions menu and credits UI/settings.

## 2025-11-20 (initial)
### Added
- Plugin bootstrap, activation hooks, custom tables (participants, bids, winners, audit log, legacy credits), `auction` product type + single-product 4-step UI, AJAX endpoints (state/register/bid/claim), claim flow, frontend polling and modals, admin menus for auctions/winners/credits/settings/audit, WP-CLI tools, and timer-driven auction engine with last-bid winner rule.
### Changed
- Prevent leading bidder repeat bids; live timer resets per bid; cron/manual expiry guard.
### Fixed
- Nested class declaration fatal resolved by isolating `WC_Product_Auction`.
