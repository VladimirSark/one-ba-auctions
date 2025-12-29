# Developer Log

# Developer Log

## 2025-12-27 — Faster autobid cadence + shorter live extension
- **Summary:** Autobid cron now scheduled every ~10 seconds (self-heals any old 60s schedule); autobid-enabled auctions no longer force a 60s live timer—minimum live extension is 15s per bid. Removed the “skip if timer < 60s” guard so short timers work with frequent polling/cron.
- **Files/Classes:** `includes/class-plugin.php`, `includes/class-auction-engine.php`, `includes/class-autobid-service.php`.
- **How to test:** Ensure WP-Cron shows `oba_run_autobid_check` with “Every 10 seconds (OBA)”. Run a live auction with autobid enabled and a 10–20s live timer: verify bids still reset the timer to at least ~15s, autobids fire via cron/polling, and no “autobid_skipped_short_timer” logs appear.

# Developer Log

## 2025-12-22 — Live timer guards, no-bid restart, autobid UI polish
- **Summary:** Live timer now starts when entering live (no empty `_live_expires_at`); autobid cron will initialize a missing expiry once before finalizing. If a live auction hits expiry with zero bids, the live timer is restarted instead of ending without a winner. Autobid card UI simplified to a 3-column layout with a single toggle button (Enable/Disable) and a clear “Autobid set to” value display, aligned with other cards.
- **Files/Classes:** `includes/class-auction-engine.php`, `includes/class-plugin.php`, `templates/oba-single-auction.php`, `assets/js/auction.js`.
- **How to test:** Start auction → confirm live timer populated immediately; let live expire with no bids → timer restarts (logs `live_restart_no_bids`), auction stays live. Remove `_live_expires_at` and run autobid cron → it sets expiry once, otherwise finalizes. In UI, check autobid card shows amount/bid calc on left, single toggle in center, and “Autobid set to” value on right.

## 2025-12-21 — Autobid spend-based config & fairness tweaks
- **Summary:** Autobid setup now takes a spend amount (converted to max bids via bid cost) and shows “€X = N bids” in UI; editing while enabled no longer recharges points. Autobid auto-disables when max is consumed (users can re-enable with new spend). Autobids may place even if currently leading; if only the leading autobidder remains, autobid stops so the timer can expire. Backend returns `autobid_max_spend` in state/AJAX.
- **Why:** Make configuration clearer in currency terms, ensure bids distribute across autobidders, and prevent infinite self-bidding while still ending auctions.
- **Files/Classes:** `includes/class-autobid-service.php`, `includes/class-ajax-controller.php`, `includes/class-auction-engine.php`, `assets/js/auction.js`.
- **How to test:** Set bid price > 0; open auction, enter spend (e.g., €5 at €0.5/bid → 10 bids). Enable autobid, observe inline calculation. Let bids consume max; autobid should disable automatically and allow reconfigure. Run live auction with multiple autobidders and confirm round-robin bids place even when a user is leading, and auction ends when only the leader remains.

## 2025-12-19 — Autobid reminders, hardening, and stuck-live fallback
- **Summary:** Added configurable autobid reminder interval (minutes) with reminder email (`autobid_on_reminder`); reminders rate-limited per user/auction. Autobid check now skips current winner “already_leading” spam but finalizes if timer hit zero; `run_autobid_check` falls back to resolve winner if status stays live at 0s; added CLI `wp oba tick` to trigger autobid/expiry. Added self-heal to sync status to ended when `_oba_ended_at` exists.
- **Why:** Improve reliability when cron/polling is sparse and keep users informed about active autobid usage.
- **Files/Classes:** `includes/class-autobid-service.php`, `includes/class-plugin.php`, `includes/class-email.php`, `includes/class-settings.php`, `includes/class-admin.php`, `includes/class-auction-engine.php`, `assets/js/auction.js`.
- **How to test:** Set reminder minutes in settings; enable autobid before live, let cron run—check reminder emails show current bids/max. Force auctions to expiry with no clients; ensure `autobid_check_tick` or `wp oba tick` ends auction and logs `auction_finalized`/`auction_finalized_fallback`.

## 2025-12-12 — Autobid time-window with points cost & UI polish
- **Summary:** Autobid can be armed in registration/pre-live/live (blocked after ended); enabling charges a points cost and starts a window that begins at live; auto-disables when window expires; manual bids disabled while on; non-registered users cannot toggle after registration closes; history marks autobids; duplicate controls removed and status pill highlighted.
- **Why:** Prevent last-moment lockout for final registrants, monetize autobid activation, and clarify status.
- **Files/Classes:** `includes/class-activator.php` (autobid window columns), `includes/class-autobid-service.php`, `includes/class-ajax-controller.php`, `includes/class-settings.php`, `includes/class-admin.php`, `includes/class-frontend.php`, `assets/js/auction.js`, `assets/css/auction.css`, `templates/oba-single-auction.php`.
- **DB:** `wp_auction_autobid` gains `window_started_at`, `window_ends_at`.
- **Constraints/Assumptions:** Points charged on enable; no refunds; window starts when live; autobid firing still respects trigger threshold and registration.
- **How to test:** Enable autobid pre-registration, fill lobby, verify window starts in live and bids fire near timer end; confirm points deducted per enable; registration-closed users can’t toggle; status pill shows remaining; history shows `(autobid)`.

## 2025-12-09 — Points-based registration & membership gating
- **Summary:** Swapped registration to points instead of Woo orders; membership required to view/participate. Membership products grant points on completion; registration deducts points immediately via AJAX (no checkout). State now exposes `user_points_balance` and `membership_active`; frontend shows “Your points” and locks steps without membership. Points stored in `wp_auction_user_points`.
- **Why:** Replace the previous pay-to-register flow with a lightweight, wallet-like model tied to memberships.
- **Files/Classes:** `includes/class-activator.php` (points table), `includes/class-points-service.php`, `includes/class-points-order-integration.php`, `includes/class-auction-engine.php`, `includes/class-ajax-controller.php`, `assets/js/auction.js`, `templates/oba-single-auction.php`.
- **DB:** `wp_auction_user_points` (+ ledger table if present).
- **Constraints/Assumptions:** Membership flag `_oba_has_membership` required; registration cost stored as `_registration_points` on the auction product; bids/claims still use WooCommerce products.
- **How to test:** Complete order for membership product with points meta (grants points + membership flag), visit auction page, confirm membership lock overlay hidden, register (points deducted, participant added), lobby updates without checkout.
- **Known limits/TODO:** Points ledger display pending; ensure translations updated for points terminology.

## 2025-12-09 — Admin calculations & legacy cleanup
- **Summary:** Added “Point value” setting and auto-registration value preview (points × required participants) in auction editor; detail view shows registration points/value totals. Removed legacy product selectors (credit pack, claim product, registration product, membership plan/limit, credits amount) and unused registration fee totals.
- **Why:** Align admin tools with the points model and remove outdated product flags to avoid confusion.
- **Files/Classes:** `includes/class-settings.php`, `includes/class-admin.php`, `includes/class-product-type.php`, `includes/class-credits-order-integration.php`.
- **DB:** None (settings only).
- **How to test:** Set Point value in Settings → General; edit auction, adjust registration points/required participants, confirm value auto-updates; view All Auctions detail to see registration points/value totals; verify product edit screen no longer shows old selectors.

## 2025-12-10 — Savings UI & currency updates
- **Summary:** Added savings callouts in the ended state (winner/loser) using product cost vs. bid value, with translation keys; ensured currency symbol/decimals are localized to JS; removed credit-pack modal behavior from points pill; cleaned badge/column layout back to single-column focus.
- **Why:** Make value proposition clearer and keep UI consistent with the points model.
- **Files/Classes:** `templates/oba-single-auction.php`, `assets/js/auction.js`, `assets/css/auction.css`, `includes/class-frontend.php`.
- **DB:** None.
- **How to test:** End an auction; winner sees savings message with currency symbol; loser sees potential savings; translations for savings/points apply; points pill shows balance only.

## 2025-12-09 — Memberships admin screen (points)
- **Summary:** Added 1BA → Memberships page listing users with membership/points; inline form toggles membership flag and edits points balance.
- **Why:** Give admins a single place to manage membership status and balances after the points shift.
- **Files/Classes:** `includes/class-admin.php`, `includes/class-points-service.php`.
- **DB:** Reads `wp_auction_user_points`, usermeta `_oba_has_membership`.
- **Constraints/Assumptions:** Limit to ~200 rows; requires `manage_woocommerce`.
- **How to test:** Open 1BA → Memberships, adjust a user’s points and membership flag, save, and verify state updates on frontend.

## 2025-11-20 — Plugin skeleton, DB tables, product type, frontend override
- **Summary:** Created base plugin bootstrap, activation hooks, custom tables, WooCommerce `auction` product type with meta fields, and single-product template override with 4-step UI and polling script/styles.
- **Why:** Establish core structure for credits-based auction flow and data storage, replacing default add-to-cart UI.
- **Files/Classes:** `one-ba-auctions.php`, `includes/class-activator.php`, `includes/class-plugin.php`, `includes/class-product-type.php`, `templates/oba-single-auction.php`, `assets/js/auction.js`, `assets/css/auction.css`.
- **DB:** `wp_auction_user_credits`, `wp_auction_participants`, `wp_auction_bids`, `wp_auction_winners`.
- **Constraints/Assumptions:** Uses WooCommerce product meta; single-product override for `auction` type; AJAX polling drives UI.
- **How to test:** Activate plugin; create auction product with meta; visit single page and confirm 4-step layout shows instead of add-to-cart; verify tables created.
- **Known limits/TODO:** No claim handling yet (later added); minimal styling; no admin screens initially.

## 2025-11-20 — Credits system and credit packs
- **Summary:** Added credits wallet service, credit pack product meta, Woo order completion hook to add credits, “My Credits” account endpoint.
- **Why:** Provide purchasable credits and per-user balance required for registration/bidding.
- **Files/Classes:** `includes/class-credits-service.php`, `includes/class-credits-order-integration.php`.
- **DB:** Uses `wp_auction_user_credits`.
- **Constraints/Assumptions:** Credits stored as DECIMAL; credit packs are simple/virtual products marked with meta.
- **How to test:** Create credit pack product, complete order, confirm balance updates in “My Credits”.
- **Known limits/TODO:** No transaction ledger at this phase; admin edits absent initially.

## 2025-11-20 — Auction engine: registration, bidding, timers, winner resolution
- **Summary:** Implemented engine for registration (deduct fee), bidding (deduct/reserve), live timer reset per bid, pre-live auto start when full, live auto start after pre-live countdown, end detection, winner selection (last bid), and refund of all non-winner bid credits; prevents leading user from repeat bids.
- **Why:** Enforce auction lifecycle and credit reservation/refund rules.
- **Files/Classes:** `includes/class-auction-engine.php`, `includes/class-auction-repository.php`.
- **DB:** Uses participants, bids, winners tables.
- **Constraints/Assumptions:** Winner = last valid bid; registration fees non-refundable; bid credits deducted immediately and refunded only at end to non-winners.
- **How to test:** Register users to fill required participants (pre-live starts), wait for pre-live to reach zero (live starts), place bids (timer resets), let timer expire (ends, winner row created, refunds issued).
- **Known limits/TODO:** No mid-auction unreserve; manual intervention via admin if stuck.

## 2025-11-20 — AJAX API and polling frontend
- **Summary:** Added AJAX handlers for state, registration, bidding, claim (later), with JSON serialization; frontend polling updates steps, timers, lobby, history, winner state; T&C modal/checkbox gating registration; toasts for actions; claim modal choice.
- **Why:** Provide real-time UX without page reloads.
- **Files/Classes:** `includes/class-ajax-controller.php`, `assets/js/auction.js`, `templates/oba-single-auction.php`.
- **DB:** Reads/writes auction meta and custom tables.
- **Constraints/Assumptions:** Uses `admin-ajax.php`; polling interval from settings; relies on nonces and login for mutations.
- **How to test:** Open single auction page, watch `auction_get_state` polling, perform register/bid/claim, observe UI updates and AJAX responses.
- **Known limits/TODO:** Client-side error handling basic; no websocket/push; history limited to last 5 bids.

## 2025-11-20 — Claim flow and Woo order creation
- **Summary:** Implemented `auction_claim_prize` for winner: credits mode deducts claim price and creates paid WC order; gateway mode creates payable order and redirects; stores `wc_order_id`.
- **Why:** Allow winner to finalize purchase via credits or standard checkout.
- **Files/Classes:** `includes/class-ajax-controller.php`.
- **DB:** Writes to `wp_auction_winners` (wc_order_id).
- **Constraints/Assumptions:** Winner only; status must be `ended`; prevents double claim if order exists.
- **How to test:** End auction with winner, click Claim, choose credits (order paid, balance drops) or gateway (checkout redirect), confirm `wc_order_id` stored.
- **Known limits/TODO:** No gateway-specific validation; no “cancel claim” handling.

## 2025-11-20 — Admin UI, settings, audit log, ledger
- **Summary:** Added Custom Auctions admin menu with status-filtered auctions list + actions (start pre-live/live, force end, recalc); winners list; user credits editor; settings (default timers, poll interval, T&C text); manual expiry trigger; bulk live/force-end actions; participant removal form; audit log; credit ledger with per-user view.
- **Why:** Provide operational controls, observability, and compliance trail.
- **Files/Classes:** `includes/class-admin.php`, `includes/class-settings.php`, `includes/class-audit.php`, `includes/class-ledger.php`.
- **DB:** Added `wp_auction_audit_log`, `wp_auction_credit_ledger`.
- **Constraints/Assumptions:** Admin capability `manage_woocommerce`; ledger logs basic reasons; audit tied to current user.
- **How to test:** Use admin menu pages; run “Run expiry check now”; perform actions and verify audit entries; edit credits and view ledger entries; bulk actions on Products list for auction type.
- **Known limits/TODO:** Participant list UI missing; audit detail display minimal; settings not yet applied everywhere (e.g., defaults only for timers/poll/T&C).

## 2025-11-20 — Reliability guards and cron
- **Summary:** Scheduled minute-level cron + manual trigger to end expired live auctions without frontend polling; bid endpoint enforces login.
- **Why:** Avoid auctions stuck live when no clients are polling; tighten auth.
- **Files/Classes:** `includes/class-plugin.php`, `includes/class-admin.php`, `includes/class-ajax-controller.php`.
- **DB:** No schema change.
- **Constraints/Assumptions:** WP-Cron available; fallback is manual button.
- **How to test:** Set `live_expires_at` in past, wait for cron or click manual trigger, verify auction ends/winner resolves; attempt bid while logged out (should fail).

## 2025-11-21 — Admin participants tooling and status visibility
- **Summary:** Added participant counts per status on auctions and participants pages, filters by status/user, CSV export, and bulk remove/restore actions; auction list shows status counts and includes draft/pending auctions when type is `auction`.
- **Why:** Improve operational visibility and bulk management of participants across auctions.
- **Files/Classes:** `includes/class-admin.php`.
- **DB:** Uses `wp_auction_participants`.
- **Constraints/Assumptions:** Bulk remove/restore updates status; export limited to 500 rows in UI view.
- **How to test:** Go to Custom Auctions → Participants for an auction, verify counts, filter by status/user, export CSV, run bulk remove/restore and confirm statuses change; verify auction counts show in Auctions tabs.

## 2025-11-21 — Reliability controls: CLI list and admin end-now
- **Summary:** Added WP-CLI command to list auctions by status and a frontend admin-only “End now” button that forces winner resolution via AJAX.
- **Why:** Provide quick operational controls without relying on polling or admin pages.
- **Files/Classes:** `includes/class-admin.php`, `includes/class-ajax-controller.php`, `templates/oba-single-auction.php`, `assets/js/auction.js`.
- **DB:** No schema change.
- **Constraints/Assumptions:** CLI available for `wp oba auctions list --status=<state>`; end-now gated to `manage_woocommerce` users; uses existing winner resolution logic.
- **How to test:** Run CLI list command; load live auction as admin and click End now then verify auction ends and winner row appears.

## 2025-11-21 — Participant ops & additional CLI tools
- **Summary:** Added participant status counts and bulk remove/restore/ban with CSV export; CLI commands to list participants, bids, list auctions, and reset live timer.
- **Why:** Enhance ops visibility and quick interventions without manual SQL.
- **Files/Classes:** `includes/class-admin.php`.
- **DB:** Uses participants table; no schema change.
- **Constraints/Assumptions:** Bulk actions change status only (no refunds); CSV export limited to filtered set; CLI assumes WP-CLI context.
- **How to test:** Use Participants page to view counts, bulk actions, and export; run CLI commands `wp oba auctions participants --id=<id>`, `wp oba auctions bids --id=<id>`, `wp oba auctions reset-timer --id=<id> [--seconds=...]`.

## 2025-11-21 — Frontend credits pill and shortcode
- **Summary:** Added optional floating credits pill for logged-in users (toggled via settings) and a `[oba_credits_balance]` shortcode.
- **Why:** Surface user balance on the frontend (e.g., header) without theme edits.
- **Files/Classes:** `includes/class-settings.php`, `includes/class-admin.php`, `includes/class-frontend.php`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Pill controlled by setting; shortcode requires login; inline styles used for pill.
- **How to test:** Enable “Show credits balance in header” in settings, log in, and confirm pill shows; use shortcode in a page to display balance.

## 2025-11-21 — Quick credit pack links in live auctions
- **Summary:** Added three configurable credit pack links in settings; displayed in live auctions when the user lacks credits for bidding.
- **Why:** Let users quickly purchase credits mid-auction.
- **Files/Classes:** `includes/class-settings.php`, `includes/class-admin.php`, `includes/class-frontend.php`, `assets/js/auction.js`, `assets/css/auction.css`, `templates/oba-single-auction.php`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Links are external URLs; only shown in live state and when `has_enough_credits` is false.
- **How to test:** Set credit pack URLs in settings, start live auction with a user lacking credits, verify “Buy more credits” box shows links; hide when links empty or user has credits.

## 2025-11-21 — Interactive credits pill
- **Summary:** Expanded floating credits pill: shows balance, expands on hover with quick pack links, highlights when balance <10; updates via AJAX state; optional labels for packs.
- **Why:** Keep users aware of balance and offer immediate purchase without leaving auction.
- **Files/Classes:** `includes/class-settings.php`, `includes/class-admin.php`, `includes/class-frontend.php`, `includes/class-ajax-controller.php`, `assets/js/auction.js`, `assets/css/auction.css`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Pill only on auction product pages when enabled; hover to reveal links; low threshold hardcoded at 10 credits.
- **How to test:** Enable header pill, set pack URLs/labels, load auction page, hover pill to see links, watch balance update after bids/claims; verify low-balance highlighting below 10.

## 2025-11-21 — Inline banners, ledger CLI, participants restore-all
- **Summary:** Added inline success banner and last-refreshed indicator on auction page; CLI ledger export; participant restore-all bulk action.
- **Why:** Better UX feedback, ops visibility of ledger, faster participant recovery.
- **Files/Classes:** `templates/oba-single-auction.php`, `assets/js/auction.js`, `includes/class-ajax-controller.php`, `includes/class-admin.php`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Ledger export via WP-CLI; restore-all uses bulk status change.
- **How to test:** Observe banner and refreshed time after actions; run `wp oba ledger export [--user=<id>]`; use Participants bulk “Restore all removed”.

## 2025-11-21 — UX and ops polish (banners, expiry info, pagination)
- **Summary:** Show success messages for register/bid in state; last refreshed indicator; expose live expiry in state/admin and CLI; add participant pagination and restore-all; CLI ledger export and auctions list now includes expiry.
- **Why:** Improve feedback, visibility of timers, and manage large participant sets.
- **Files/Classes:** `templates/oba-single-auction.php`, `assets/js/auction.js`, `includes/class-ajax-controller.php`, `includes/class-admin.php`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Pagination per-page=100; CLI exports rely on WP-CLI `--format` for CSV.
- **How to test:** Register/Bid to see success banner; check last refreshed; view admin Auctions column for live expiry; run `wp oba auctions list --format=csv` and `wp oba ledger export`; paginate Participants via `p_page` query param and use restore-all.

## 2025-11-21 — Lobby percent display
- **Summary:** Lobby progress now shows percentage instead of count (still drives bar).
- **Why:** Clearer at-a-glance progress toward required participants.
- **Files/Classes:** `assets/js/auction.js`, `templates/oba-single-auction.php`.
- **DB:** No schema change.
- **How to test:** On registration step, confirm lobby text shows percent (e.g., 10%) and bar width matches.

## 2025-11-21 — Inline credits pill in auction header
- **Summary:** Moved the expanding credits pill inside the auction container/header so it no longer overlays content on mobile while keeping hover/low-balance behavior.
- **Why:** The floating pill covered UI on small screens; embedding it alongside status prevents obstruction.
- **Files/Classes:** `templates/oba-single-auction.php`, `assets/css/auction.css`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Only renders for logged-in users when header pill toggle is enabled; inline variant reuses pack links/labels and low-balance styling.
- **How to test:** Enable header pill in settings, open an auction product on mobile and desktop, verify pill appears within the auction header (not floating), expands on hover, and highlights below 10 credits.

## 2025-11-21 — Credit pack modal on click
- **Summary:** Replaced hover expansion of the inline credits pill with a click-triggered modal that lists the three configured credit pack links; hover reveal removed for the inline pill.
- **Why:** Prevent accidental overlay on mobile/desktop and provide clearer purchase options.
- **Files/Classes:** `assets/js/auction.js`, `assets/css/auction.css`, `templates/oba-single-auction.php`, `includes/class-frontend.php`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Uses existing pack links/labels from settings; modal appears on pill click (pill links still hover-expand on non-auction pages via floating pill).
- **How to test:** On an auction page, click the credits pill; modal opens showing configured pack buttons; close via X or overlay; pill no longer reveals links on hover; floating pill elsewhere still works.

## 2025-11-21 — Restrict credits pill to auction pages
- **Summary:** Disabled the floating/header credits pill on non-auction pages; credits display now only appears on auction products (inline) unless shortcode is used elsewhere.
- **Why:** Reduce UI clutter site-wide while keeping auction context focused.
- **Files/Classes:** `includes/class-frontend.php`.
- **DB:** No schema change.
- **Constraints/Assumptions:** `[oba_credits_balance]` shortcode remains available for other placements.
- **How to test:** Enable header pill in settings, visit non-auction pages (no pill), visit auction product (inline pill shows).

## 2025-11-21 — Floating pill removed; inline only
- **Summary:** Fully suppressed the floating/header pill rendering; auctions rely solely on the inline pill+modal inside the auction container (shortcode available if needed elsewhere).
- **Why:** User preference to keep credits UI within the auction block without global floating elements.
- **Files/Classes:** `includes/class-frontend.php`, `templates/oba-single-auction.php`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Inline pill only; shortcode still works anywhere.
- **How to test:** Enable header pill; confirm pill only shows in auction template and not elsewhere; modal still opens on click with pack links.

## 2025-11-21 — Ended state outcome styling
- **Summary:** Enhanced ended step visuals with win/lose outcome blocks (green win, red loss) with background/border emphasis (titles removed per UX request).
- **Why:** Make victory/defeat clearer and more celebratory/obvious.
- **Files/Classes:** `templates/oba-single-auction.php`, `assets/css/auction.css`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Outcome blocks respect existing show/hide logic.
- **How to test:** End an auction as winner and loser; observe green victory card with claim button vs red defeat card.

## 2025-11-21 — Refund note for losers
- **Summary:** Added a refund message to the loser view indicating reserved credits were refunded, with supporting styling.
- **Why:** Clarify credit resolution after losing an auction.
- **Files/Classes:** `templates/oba-single-auction.php`, `assets/css/auction.css`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Message shown only in loser block; refunds already processed server-side.
- **How to test:** Lose an auction and confirm the refund note appears under the defeat message.

## 2025-11-21 — Registered note in lobby
- **Summary:** Added a green registration note in Step 1 that appears once the user is registered.
- **Why:** Reassure registered users and prompt sharing to fill the lobby.
- **Files/Classes:** `templates/oba-single-auction.php`, `assets/js/auction.js`, `assets/css/auction.css`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Note toggles based on `user_registered` in AJAX state.
- **How to test:** Register for an auction; the green note should appear; unregister (or new user) hides it.

## 2025-11-21 — Status pill info modal
- **Summary:** Made the status pill clickable with an “i” icon that opens a modal describing the auction steps; content configurable via a rich text field in Settings (with defaults provided).
- **Why:** Give users quick onboarding to the 4-step flow without leaving the auction page.
- **Files/Classes:** `templates/oba-single-auction.php`, `assets/js/auction.js`, `assets/css/auction.css`, `includes/class-settings.php`, `includes/class-admin.php`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Modal content stored in settings as HTML; pill remains aligned with credits pill.
- **How to test:** Set content in Settings → Status info modal; on auction page click the status pill or info icon to open/close the modal; verify content renders.

## 2025-11-21 — Stable status labels with icons
- **Summary:** Status pill now keeps the “i” icon and uses short step labels (1–4) during updates instead of replacing the content.
- **Why:** Avoid flicker back to “Step 1...” and keep the info icon visible across states.
- **Files/Classes:** `templates/oba-single-auction.php`, `assets/js/auction.js`, `includes/class-frontend.php`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Localization keys added for short step labels.
- **How to test:** Switch auction through all states; pill should show “1. Registration/2. Time to Live/3. Live/4. End” with the “i” icon intact.

## 2025-11-21 — Modal spacing and layering
- **Summary:** Raised modal z-indexes and anchored them below the header (with top gap and constrained height) so they no longer hide under headers when scrolled to top.
- **Why:** Ensure claim/credit/info modals stay visible beneath fixed headers/admin bar.
- **Files/Classes:** `assets/css/auction.css`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Top gap set to 60px; max-height is `calc(100vh - 120px)` to keep content scrollable inside.
- **How to test:** Open claim/credit/info modals at top of page with admin bar enabled; they should appear below the header with a visible gap and remain above page content.

## 2025-11-21 — Modal top offset increase
- **Summary:** Increased modal top offset and max-height buffer (with admin-bar adjustment) so modals no longer tuck under sticky headers when scrolled to the top.
- **Why:** Previous gap was insufficient on some themes/header heights.
- **Files/Classes:** `assets/css/auction.css`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Default top 120px (152px with admin bar), max-height recalculated accordingly.
- **How to test:** Scroll to page top and open claim/credit/info modals; confirm they clear the header/admin bar.

## 2025-11-21 — Ended auction logs
- **Summary:** Added structured logging when auctions end and when claims are created, including winner, bids, credits consumed/refunded, trigger, last bid details; introduced an admin “Ended Logs” page to review these entries with filtering.
- **Why:** Provide operational visibility into auction endings and claims for auditing/support.
- **Files/Classes:** `includes/class-auction-engine.php`, `includes/class-ajax-controller.php`, `includes/class-audit.php`, `includes/class-admin.php`.
- **DB:** No schema change (reuses audit log table).
- **Constraints/Assumptions:** Logs record trigger (timer/admin/cli), totals, last bid, claim price, and order IDs; view limited to latest 200 (filter by auction ID).
- **How to test:** End an auction (timer/force), verify audit log row action `auction_end`; claim as winner and see `auction_claim` entry; view entries in Admin → Custom Auctions → Ended Logs (filter by auction ID).

## 2025-11-21 — Email notifications
- **Summary:** Added email sender settings and a mailer that sends notifications for pre-live start, live start, auction ended (winner/losers), credit balance edits, and participant status changes; claim emails sent on claim; includes reusable HTML template.
- **Why:** Keep participants and admins informed of key auction lifecycle events and account changes.
- **Files/Classes:** `includes/class-email.php`, `includes/class-settings.php`, `includes/class-admin.php`, `includes/class-auction-engine.php`, `includes/class-ajax-controller.php`, `includes/class-auction-repository.php`, `includes/class-plugin.php`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Uses WordPress mail; sender name/email configurable in Settings; notifications go to registered participants; claim emails re-use winner template.
- **How to test:** Reach required participants to trigger pre-live email; allow live start to send live email; end auction to send winner/loser emails; edit credits in admin and confirm credit update email; remove/ban participant and confirm status email.

## 2025-11-21 — Frontend translations admin page
- **Summary:** Added a Translations submenu to Custom Auctions allowing admins to override key frontend labels (steps, lobby progress, register CTA, bid button); values are localized to JS.
- **Why:** Provide an easy way to translate/override UI text without editing code or .po files.
- **Files/Classes:** `includes/class-settings.php`, `includes/class-admin.php`, `includes/class-frontend.php`, `assets/js/auction.js`, `templates/oba-single-auction.php`.
- **DB:** No schema change (stored in existing settings option).
- **Constraints/Assumptions:** Only select labels are exposed; defaults still use translation functions if overrides are empty.
- **How to test:** Go to Custom Auctions → Translations, set custom text for steps/lobby/register/bid, save, reload auction page to see updated labels.

## 2025-11-21 — Email templates admin page
- **Summary:** Added an Emails submenu to view/edit outgoing email subjects/bodies (pre-live, live, winner, loser, claim confirmation, credits edit, participant status) with token support; templates applied via the mailer.
- **Why:** Let admins customize customer emails without code changes.
- **Files/Classes:** `includes/class-settings.php`, `includes/class-admin.php`, `includes/class-email.php`.
- **DB:** No schema change (templates stored in settings).
- **Constraints/Assumptions:** Supported tokens: `{user_name}`, `{auction_title}`, `{auction_link}`, `{claim_price}`, `{bid_cost}`, `{balance}`, `{status}`, `{seconds}`.
- **How to test:** Update templates in Custom Auctions → Emails; trigger events (pre-live, live, end, claim, credits edit, participant status) and verify emails reflect custom subject/body.

## 2025-11-21 — Auction page redesign
- **Summary:** Rebuilt single auction template with two-column layout (product left, phase cards right), top 4-step explainer bar, collapsible phase cards, refreshed styling, and floating bottom-right credits pill (global) with modal links.
- **Why:** Improve clarity of the auction flow and separate product info from live controls while keeping credits accessible.
- **Files/Classes:** `templates/oba-single-auction.php`, `assets/css/auction.css`, `assets/js/auction.js`, `includes/class-frontend.php`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Auction logic unchanged; inline credits pill removed in favor of global floating pill; credit modal rendered via header except on auction page (template supplies it).
- **How to test:** Load an auction page on desktop/mobile—see step bar, product card, and phase cards with collapse/complete states; floating credits pill bottom-right opens buy links; registration/live/ended flows still function (register, bid, end, claim).

## 2025-11-21 — Login prompt on register
- **Summary:** Register action now redirects logged-out users to the login page (with return to auction) instead of silently failing.
- **Why:** Prevent confusion for anonymous users trying to register.
- **Files/Classes:** `assets/js/auction.js`, `includes/class-frontend.php`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Uses `wp_login_url` pointing back to current product page.
- **How to test:** While logged out, click Register; you should be sent to login and returned after authenticating.

## 2025-12-22 — Autobid UI cleanup (inline spend, toggle-only)
- **Summary:** Simplified autobid cards (registration + live): compact layout, removed gradient/status pill backgrounds, removed calculation text, and rely on a single toggle switch with inline EUR input. The inline amount now feeds the enable call so user-entered spend is honored instead of defaulting to 1 bid.
- **Why:** UI was cramped and misleading (status styling conflicting, value ignored on enable).
- **Files/Classes:** `templates/oba-single-auction.php`, `assets/js/auction.js`.
- **DB:** None.
- **Constraints/Assumptions:** User enters spend (EUR) not bid count; toggle sends that amount; status shows clean text without pill styling.
- **How to test:** Register, enter a spend in the inline field, toggle autobid ON → confirm state shows correct € value and server stores that amount; toggle OFF and ON again with a different amount to verify updates; check status text has no background/shadow.

## 2025-12-22 — Auction product supports inventory/virtual/downloadable
- **Summary:** Enabled WooCommerce inventory and shipping tabs for auction products and re-enabled virtual/downloadable flags (with core downloadable fields) by adding `show_if_auction` to relevant tabs/fields via filters + admin JS helper.
- **Why:** Auctions need stock management and virtual/downloadable behaviors like simple products.
- **Files/Classes:** `includes/class-product-type.php`.
- **DB:** None.
- **Constraints/Assumptions:** Woo core saves stock/virtual/downloadable meta; auction products inherit simple product stock semantics (typically stock=1, manage stock on).
- **How to test:** Edit an auction product → Inventory tab visible; set “Manage stock” + qty, save; toggle Virtual/Downloadable, add a file, save; confirm values persist and shipping behaves accordingly on checkout/claim.

## 2025-11-21 — Custom login/account link
- **Summary:** Added settings field for custom login/account URL used in logged-out registration prompts.
- **Why:** Let sites direct users to a specific login/signup page.
- **Files/Classes:** `includes/class-settings.php`, `includes/class-admin.php`, `includes/class-frontend.php`.
- **DB:** No schema change.
- **Constraints/Assumptions:** Falls back to default `wp_login_url` if empty.
- **How to test:** Set login URL in settings; log out and click Register to be directed to that URL.

## 2025-11-21 — Autobid proxy V2 (max bids, late firing, modal)
- **Summary:** Switched autobid to a max-bid-count proxy that triggers only in the last ~4s, removed time-window expiry, marked auto bids, and added a modal-based autobid configurator.
- **Why:** Prevent early burn and ensure fair triggering while letting users pre-set counts without polling overrides.
- **Files/Classes:** `includes/class-autobid-service.php`, `includes/class-ajax-controller.php`, `includes/class-frontend.php`, `assets/js/auction.js`, `assets/css/auction.css`, `templates/oba-single-auction.php`.
- **DB:** Autobid table already includes `max_bids`; `is_autobid` column required on bids table (previous migration).
- **Constraints/Assumptions:** Autobid only fires when live timer ≤ threshold and user isn’t leading; one auto-bid per tick; registration required to show UI.
- **How to test:** Register, open autobid modal, set max bids (>1), enable; during live, bids fire near 3–4s when outbid; autobid bids marked in history.

## 2025-11-21 — Autobid UI/UX refresh (cards, modal, pills)
- **Summary:** Added registration-stage autobid prompt with pill showing ON/OFF, modal input for max bids with EUR totals, single Set/Edit entry point, live legend cards for Autobid + Status (currency value and leading/outbid state with full-card color), and responsive 2x2 grid layout on desktop/stacked on mobile. Removed inline toggles/status clutter.
- **Why:** Simplify autobid setup, avoid polling overwrites, and surface state clearly next to live stats.
- **Files/Classes:** `templates/oba-single-auction.php`, `assets/js/auction.js`, `assets/css/auction.css`, `includes/class-frontend.php`, `includes/class-settings.php`, `includes/class-admin.php`.
- **DB:** None.
- **Constraints/Assumptions:** Users must register before seeing autobid UI; autobid toggle/enable handled via modal; status pill reflects state; legend Status card auto-updates win/lose colors.
- **How to test:** Register → see autobid prompt and pill; open modal, set bids, enable; live stage shows Autobid card with EUR total and toggle, Status card flips colors when leading/outbid; translations for new strings appear in Settings → Translations.

## 2025-11-21 — Autobid limitless mode + emails
- **Summary:** Added a “stay on top (no limit)” autobid option, updated modal UI, allowed max_bids=0 as limitless with reminders every 10 minutes, removed expiring-window emails, and added new emails for autobid on/off and limitless reminder with translation keys.
- **Why:** Support always-on proxy bidding without depletion and stop obsolete time-window messaging.
- **Files/Classes:** `includes/class-autobid-service.php`, `includes/class-ajax-controller.php`, `includes/class-email.php`, `includes/class-settings.php`, `includes/class-admin.php`, `includes/class-frontend.php`, `assets/js/auction.js`, `templates/oba-single-auction.php`.
- **DB:** None (reminders tracked in user meta).
- **Constraints/Assumptions:** Limitless mode sends reminder every 10 minutes while enabled; max_bids=0 treated as infinite and prioritized in tie-break; expiring emails removed from UI/tests.
- **How to test:** Enable autobid with “stay on top” checked → UI shows limitless text, autobid card shows no-limit value; receive autobid on/off emails; while limitless remains on for >10 minutes, reminder email sends; legacy expiring email no longer appears in settings/tests or sends.

## 2025-11-21 — Autobid fairness + background guard
- **Summary:** Added round-robin autobid selection (rotating pointer, up to 2 auto-bids per tick) so all enabled users get turns, and introduced a server-side autobid guard cron (now every ~1s with loopback pings) to keep autobids and expiry checks running even when no tabs are open. Moved live action buttons above history for better visibility.
- **Why:** Prevent only the first autobidders from firing and avoid auctions ending when no users are polling.
- **Files/Classes:** `includes/class-autobid-service.php`, `includes/class-plugin.php`, `templates/oba-single-auction.php`.
- **DB:** None (round-robin pointer stored as transient).
- **Constraints/Assumptions:** WP loopback to `wp-cron.php` must be allowed; otherwise, configure an external cron to hit `wp-cron.php` every second.
- **How to test:** Start a live auction with multiple autobidders (including limitless); verify bids rotate across users. Close all tabs—cron should continue bidding and end the auction correctly without premature timeout.
