# One BA Auctions (current)

WooCommerce auction product type with an AJAX 4-step frontend. Registration now consumes **points** (granted by membership products); bidding/claiming remain WooCommerce product based.

## Core behavior
- **Auction product meta:** registration points, bid product, required participants, pre-live timer, live timer, status, timestamps.
- **Lifecycle:** registration → pre-live (auto when lobby fills) → live (timer resets on each bid) → ended (last bid wins; leading bidder cannot rebid).
- **Membership & points:** membership is required to view/participate. Membership products grant points on order completion (stored in `wp_auction_user_points`). Registration deducts points instantly—no checkout for registration.
- **Bidding:** bid fee = bid product price; bids logged; live timer resets per bid.
- **Claim:** winner claim adds total bid fees to checkout; claim order tagged with `_oba_claim_auction_id`; claimed status shows with order link when completed.
- **Data tables:** participants, bids, winners, audit log, user points (+ ledger).
- **Frontend:** single template with 4 step cards, lobby %, timers, history, winner/loser blocks, T&C modal, membership lock overlay, and “Your points” display.
- **Admin (1BA menu):** All Auctions list with status filter and “registered/required” counts; auction detail shows status, winner, claim/order, end time, totals (registration points/value and bids/savings), participant log with inline removal; Audit Log; Settings (General/Emails/Translations); Memberships screen to toggle membership and edit points.
- **Settings:** default timers, poll interval, T&C text, login link, status-info HTML, email sender, translations overrides, email templates, point value (for money equivalent).
- **Ops/reliability:** cron expiry check, manual status actions, CLI tools for expiry/end/list; admin “End now”.

## Quick test checklist
1) Create auction: set registration points value, bid product, required participants, timers, product cost, status=registration.
2) Ensure user has membership + points (complete an order for a membership product with points meta).
3) Register: accept T&C, click register → points deducted, user added to participants, lobby updates.
4) Fill lobby → pre-live auto start → live auto start after countdown.
5) Live: place bids, timer resets, history updates, leading bidder blocked.
6) Let timer expire → ended, winner row created.
7) Claim as winner → checkout with bid total → complete order → admin detail shows claimed “Yes #order — completed”.
8) Admin detail: view status/winner/claim, participants (ID/name/email) with remove action; Memberships page shows membership flag and points; Settings tabs load without warnings; profit totals reflect points and cost; ended view shows savings vs. regular price.
