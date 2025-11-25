# Changelog

## 2025-11-20
### Added
- Initial plugin bootstrap and activation hooks.
- Custom tables: user credits, participants, bids, winners, audit log, credit ledger.
- WooCommerce `auction` product type with meta fields and single-product 4-step UI override.
- Credits system with credit pack products, Woo order completion credit grant, “My Credits” endpoint.
- Auction engine: registration, bidding, timers (pre-live/live), winner resolution, refunds to non-winners, prevent leading bidder repeats.
- AJAX endpoints: state, register, bid, claim.
- Claim flow: winner can pay via credits (paid order) or gateway (checkout order).
- Frontend polling, T&C modal/checkbox, claim modal, toasts, styled history/timers.
- Admin menu: auctions list/actions, winners list, user credits editor, settings (timers, poll interval, T&C), manual expiry check, participant removal form, bulk live/force-end actions.
- Audit log and credit ledger with admin views and per-user ledger view.
- WP-CLI commands for expiry check and end/recalc: `oba auctions expire`, `oba auctions end --id=<auction_id>`.
- WP-CLI command to list auctions by status: `oba auctions list [--status=registration|pre_live|live|ended]`.
- Frontend admin “End now” button to force-end a live auction; status counts on admin tabs; participants page filters, counts, CSV export, and bulk remove/restore.
- Participants page enhancements: status counts, bulk remove/restore/ban/restore-all, CSV export; CLI commands to list participants/bids, reset live timer, and list winners.
- Optional frontend credits pill (settings toggle) and shortcode `[oba_credits_balance]`.
- Quick credit pack links configurable in settings; shown in live auctions when user lacks credits.
- Interactive credits pill with hover expansion showing quick pack links; low-balance highlight.
- Added inline success banner and last-refreshed indicator; CLI ledger export; participant restore-all action.
- Auctions admin shows live expiry; participants paginated; CLI auctions list includes expiry; register/bid success banner; participant pagination and restore-all.
- Lobby display now shows percent instead of raw participant count.
- Register action redirects logged-out users to login (with return), avoiding silent failure.
- Settings allow custom login/account link used for logged-out registration prompts.

### Changed
- Default timers, poll interval, and T&C pulled from settings and applied to product meta/front-end.
- Live timer auto-starts when pre-live ends or status is set to live without timer.
- Frontend prevents repeated bids by current leader and disables bid button.
- Credits pill now renders inline inside the auction header (floating pill suppressed on auction pages) for better mobile usability; hover/low-balance behavior retained.
- Inline credits pill now opens a modal with credit pack links on click instead of showing links on hover (floating pill hover remains on non-auction pages).
- Floating/header credits pill rendering is disabled; credits UI now appears only inline within auction pages (use shortcode for other placements).
- Adjusted pill sizing: credits pill reduced on mobile and matched in height with the status pill for consistent layout.
- Inline credits pill now auto-fits its content (smaller width) and credit pack modal lists options in a vertical column.
- Removed status pill bottom margin for alignment with credits pill; hid system-time display to keep UI focused.
- Ended step now highlights outcomes with green win and red loss cards (background/border emphasis, no titles).
- Added refund note to loser view clarifying reserved credits were refunded.
- Added green registration note in Step 1 once the user is registered.
- Status pill now has an info icon; clicking it opens a configurable steps modal (content editable in Settings).
- Status pill uses fixed short labels (1–4) and retains the info icon across state updates.
- Modals (claim/credit/info) now sit below the header with increased z-index and height constraints to avoid being hidden when scrolled to top.
- Further increased modal top offset/max-height (with admin-bar adjustment) to prevent hiding under sticky headers.
- Added ended auction logging (winner, credits consumed/refunded, trigger, last bid) and a new “Ended Logs” admin page; claims are now logged with mode/order.
- Email notifications added: configurable sender; pre-live/live start emails to participants; winner/loser end emails; claim emails; credit edit emails; participant status (removed/banned/restored) emails.

### Fixed
- Prevented nested class declaration fatal by moving `WC_Product_Auction` to its own file.
- Login enforcement on bidding; reliability guards via cron/manual expiry to end auctions without polling.

### Removed
- Default Woo add-to-cart UI on auction products (replaced by custom flow).
