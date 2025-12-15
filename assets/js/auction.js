(function ($) {
	const state = { data: null };
	let membershipPrompt = false;

	const statusLabels = {
		registration: obaAuction.i18n?.step1_short || '1. Registration',
		pre_live: obaAuction.i18n?.step2_short || '2. Time to Live',
		live: obaAuction.i18n?.step3_short || '3. Live',
		ended: obaAuction.i18n?.step4_short || '4. End',
	};

	function formatTime(seconds) {
		const total = Math.max(0, parseInt(seconds || 0, 10));
		const mins = Math.floor(total / 60);
		const secs = total % 60;
		if (mins > 0) {
			return `${mins}:${secs.toString().padStart(2, '0')}`;
		}
		return `${secs}s`;
	}

	function formatTimeStamp(ts) {
		const d = new Date(ts);
		if (Number.isNaN(d.getTime())) return ts;
		return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
	}

	function showAlert(message) {
		if (!message) return;
		$('.oba-alert-error').text(message).show();
	}

	function showToast(message, isError = false) {
		const toast = $('.oba-toast');
		toast.text(message || '').removeClass('oba-error');
		if (isError) toast.addClass('oba-error');
		toast.fadeIn(150, () => {
			setTimeout(() => toast.fadeOut(200), 1500);
		});
	}

	function clearAlert() {
		$('.oba-alert-error').hide().text('');
	}

	function poll() {
		$.getJSON(
			obaAuction.ajax_url,
			{
				action: 'auction_get_state',
				auction_id: obaAuction.auction_id,
				nonce: obaAuction.nonce,
			},
			(response) => {
				if (!response || !response.success) {
					return;
				}
				state.data = response.data;
				clearAlert();
				render();
			}
		);
	}

	function render() {
		if (!state.data) return;

		const status = state.data.status;
		const unlocked = !!state.data.registration_unlocked;
		const productCost = parseFloat($('.oba-auction-wrap').data('product-cost') || 0);
		updateAutobidUI();
		const pointsBalance = Number(state.data.user_points_balance || 0);
		const bidderStatusCard = $('.oba-bidder-status-card');
		if (bidderStatusCard.length) {
			const isWinning = !!state.data.user_is_winning;
			const labelEl = bidderStatusCard.find('.oba-bidder-status-pill');
			labelEl.text(isWinning ? (obaAuction.i18n?.you_leading_custom || obaAuction.i18n?.you_leading || 'Leading') : (obaAuction.i18n?.outbid_label || 'Outbid'));
			bidderStatusCard.css({
				background: isWinning ? '#ecfdf3' : '#fef2f2',
				color: isWinning ? '#166534' : '#991b1b',
				borderColor: isWinning ? '#bbf7d0' : '#fecdd3',
			});
		}

		updateStepBar(status);
		updatePhaseCards(status);
		applyMembershipLocks(status);

		$('.oba-lobby-bar span').css('width', `${state.data.lobby_percent}%`);
		const lobbyLabel = obaAuction.i18n?.lobby_progress || 'Lobby progress';
		$('.oba-lobby-count').text(`${lobbyLabel}: ${state.data.lobby_percent}%`);

		$('.oba-prelive-seconds').text(formatTime(state.data.pre_live_seconds_left));
		updateBar('.oba-prelive-bar span', Number(state.data.pre_live_seconds_left), Number(state.data.pre_live_total));

		$('.oba-live-seconds').text(formatTime(state.data.live_seconds_left));
		updateBar('.oba-live-bar span', Number(state.data.live_seconds_left), Number(state.data.live_total));

		$('.oba-user-bids').text(state.data.user_bids_count);
		const userCost = (state.data.user_cost_plain || state.data.user_cost_formatted || state.data.user_cost || '').toString().replace(/&nbsp;/g, ' ').replace(/&euro;/g, '€');
		$('.oba-user-cost').text(userCost);
		const regBtn = $('.oba-register');
		const regText = obaAuction.i18n?.register_cta || obaAuction.i18n?.register || 'Register & Reserve Spot';
		const suffix = obaAuction.i18n?.points_suffix || 'pts';
		const fee = (state.data.registration_fee_plain ?? state.data.registration_fee_formatted ?? state.data.registration_fee ?? '').toString().trim();
		regBtn.text(`${regText}${fee ? ` (${fee} ${suffix})` : ''}`);
		if (status !== 'registration' && !state.data.user_registered) {
			regBtn.prop('disabled', true).text(obaAuction.i18n?.registration_closed || 'Registration closed');
			$('.oba-not-registered').hide();
			$('.oba-registered').hide();
			showAlert(obaAuction.i18n?.registration_closed || 'Registration closed');
			$('.oba-autobid').hide();
		} else if (!unlocked) {
			regBtn.prop('disabled', true);
			showAlert(obaAuction.i18n?.membership_required || 'Membership required to register.');
			$('.oba-autobid').hide();
		} else if (state.data.user_registered) {
			regBtn.addClass('oba-registered').prop('disabled', true);
			$('.oba-terms').hide();
			$('.oba-registered-note').show();
			$('.oba-not-registered').hide();
			$('.oba-registered').text(obaAuction.i18n?.registered || 'Registered').show();
			$('.oba-pending-banner').hide();
			$('.oba-autobid').show();
			$('.oba-autobid-config').prop('disabled', false).show();
		} else {
			regBtn.removeClass('oba-registered').prop('disabled', false);
			$('.oba-terms').show();
			$('.oba-registered-note').hide();
			$('.oba-not-registered').show();
			$('.oba-registered').hide();
			$('.oba-pending-banner').hide();
			$('.oba-autobid').hide();
			$('.oba-autobid-config').prop('disabled', true).hide();
		}

		const bidBtn = $('.oba-bid');
		if (state.data.autobid_enabled) {
			const autoText = obaAuction.i18n?.autobid_on_button || 'Autobid ON';
			bidBtn.prop('disabled', true).text(autoText);
		} else if (state.data.can_bid) {
			bidBtn.prop('disabled', false).text(obaAuction.i18n?.bid_button || 'Place bid');
		} else {
			const btnText = state.data.user_is_winning ? (obaAuction.i18n?.you_leading_custom || obaAuction.i18n?.you_leading || 'You are leading') : (obaAuction.i18n?.cannot_bid || 'Cannot bid');
			bidBtn.prop('disabled', true).text(btnText);
		}

		const historyList = $('.oba-history');
		historyList.empty();
		(state.data.history || []).slice(0, 5).forEach((row) => {
			const time = formatTimeStamp(row.time);
			const totalText = (row.total_bids_value_formatted || row.total_bids_value || '').toString().replace(/&nbsp;/g, ' ').replace(/&euro;/g, '€');
			const autoIcon = row.is_autobid ? '<span class="oba-history-auto" aria-label="Autobid">(autobid)</span>' : '';
			historyList.append(`<li><span>${row.name} ${autoIcon}</span><span>${time}</span><span class="oba-history-value">${totalText}</span></li>`);
		});

		if (state.data.error_message) {
			showAlert(state.data.error_message);
		} else {
			clearAlert();
		}

		if (state.data.success_message) {
			$('.oba-success-banner').text(state.data.success_message).show();
		} else {
			$('.oba-success-banner').hide();
		}

		if (state.data.wc_order_id) {
			$('.oba-claim-status').show().text(`Claimed. Order #${state.data.wc_order_id}`);
			$('.oba-claim').prop('disabled', true);
		} else {
			$('.oba-claim-status').hide();
			$('.oba-claim').prop('disabled', false);
		}

		if (state.data.current_user_is_winner) {
			$('.oba-winner-claim').show();
			const claimText = (state.data.claim_amount || '').toString().replace(/&nbsp;/g, ' ').replace(/&euro;/g, '€');
			$('.oba-claim-amount').text(claimText);
			const stats = state.data.winner_stats || {};
			$('.oba-win-bids-count').text(stats.bid_count || 0);
			const valueText = (stats.bid_value_plain || stats.bid_value_fmt || stats.bid_value || '').toString().replace(/&nbsp;/g, ' ').replace(/&euro;/g, '€');
			$('.oba-win-bids-value').text(valueText || '0');
			const bidValNum = typeof stats.bid_value_num !== 'undefined' ? Number(stats.bid_value_num) : parseFloat(stats.bid_value_plain || stats.bid_value || 0);
			if (productCost && bidValNum >= 0) {
				const saved = Math.max(0, productCost - bidValNum);
				$('.oba-win-save-value').text(formatMoney(saved));
				$('.oba-win-save .oba-save-prefix').text(obaAuction.i18n?.win_save_prefix || 'You saved around');
				$('.oba-win-save .oba-save-suffix').text(obaAuction.i18n?.win_save_suffix || 'from regular price in other stores.');
				$('.oba-win-save').show();
			} else {
				$('.oba-win-save').hide();
			}
			if (state.data.claim_pending) {
				$('.oba-claim').prop('disabled', true).text(obaAuction.i18n?.registration_pending || 'Pending approval');
				$('.oba-claim-status').text(obaAuction.i18n?.registration_pending || 'Pending approval').show();
			} else {
				$('.oba-claim').prop('disabled', false).text(obaAuction.i18n?.claim_button || 'Claim now');
				$('.oba-claim-status').hide();
			}
			$('.oba-loser').hide();
		} else {
			$('.oba-winner-claim').hide();
			if (status === 'ended') {
				$('.oba-loser').show();
				$('.oba-lose-bids-count').text(state.data.user_bids_count || 0);
				const loseValue = (state.data.user_cost_plain || state.data.user_cost_formatted || state.data.user_cost || '').toString().replace(/&nbsp;/g, ' ').replace(/&euro;/g, '€');
				$('.oba-lose-bids-value').text(loseValue || '0');
				const costNum = typeof state.data.user_cost_num !== 'undefined' ? Number(state.data.user_cost_num) : parseFloat(state.data.user_cost_plain || 0);
				if (productCost && costNum >= 0) {
					const saved = Math.max(0, productCost - costNum);
					$('.oba-lose-save-value').text(formatMoney(saved));
					$('.oba-lose-save .oba-save-prefix').text(obaAuction.i18n?.lose_save_prefix || 'If you win, you would save around');
					$('.oba-lose-save .oba-save-suffix').text(obaAuction.i18n?.lose_save_suffix || 'from regular price in other stores.');
					$('.oba-lose-save').show();
				} else {
					$('.oba-lose-save').hide();
				}
			}
		}

		updateCreditPill(pointsBalance, state.data.membership_active);
	}

	function updatePhaseCards(status) {
		const order = ['registration', 'pre_live', 'live', 'ended'];
		$('.oba-phase-card').each((_, el) => {
			const step = $(el).data('step');
			const idx = order.indexOf(step);
			const cur = order.indexOf(status);
			const iconWrap = $(el).find('.oba-phase-icon');
			const labelEl = $(el).find('.oba-phase-label');
			$(el).removeClass('is-active is-complete is-collapsed');
			iconWrap.removeClass('icon-check icon-lock icon-up icon-down');
			let iconState = 'lock';
			if (idx < cur || (step === 'registration' && state.data.user_registered && status !== 'registration')) {
				$(el).addClass('is-complete');
				iconState = 'check';
				$(el).addClass('is-collapsed');
			} else if (idx === cur) {
				$(el).addClass('is-active');
				iconState = 'up';
			} else {
				$(el).addClass('is-collapsed');
				iconState = 'lock';
			}
			if (step === 'registration' && state.data.user_registered && status === 'registration') {
				$(el).addClass('is-complete');
				if (state.data.lobby_percent >= 100) {
					$(el).addClass('is-collapsed');
					iconState = 'check';
				} else {
					$(el).removeClass('is-collapsed');
					iconState = 'up';
				}
			}
			if (step === 'ended' && status === 'ended') {
				$(el).removeClass('is-collapsed').addClass('is-active');
				iconState = 'up';
			}
			if (!$(el).hasClass('is-active') && !$(el).hasClass('is-complete') && iconState !== 'lock') {
				iconState = 'down';
			}
			iconWrap.addClass(`icon-${iconState}`);
			if (labelEl.length) {
				const map = {
					registration: obaAuction.i18n?.step1_label || 'Registration',
					pre_live: obaAuction.i18n?.step2_label || 'Countdown to Live',
					live: obaAuction.i18n?.step3_label || 'Live Bidding',
					ended: obaAuction.i18n?.step4_label || 'Auction Ended',
				};
				labelEl.text(map[step] || labelEl.text());
			}
		});
	}

	function updateStepBar(status) {
		const order = ['registration', 'pre_live', 'live', 'ended'];
		const cur = order.indexOf(status);
		$('.oba-step-pill').each((_, el) => {
			const step = $(el).data('step');
			const idx = order.indexOf(step);
			$(el).removeClass('is-active is-complete');
			if (idx < cur) {
				$(el).addClass('is-complete');
			} else if (idx === cur) {
				$(el).addClass('is-active');
			}
			const label = $(el).find('.label');
			const desc = $(el).find('.desc');
			const map = {
				registration: { label: obaAuction.i18n?.step1_label || 'Registration', desc: obaAuction.i18n?.step1_desc || 'Join the lobby with points.' },
				pre_live: { label: obaAuction.i18n?.step2_label || 'Countdown to Live', desc: obaAuction.i18n?.step2_desc || 'Short pre-live timer.' },
				live: { label: obaAuction.i18n?.step3_label || 'Live Bidding', desc: obaAuction.i18n?.step3_desc || 'Bid, reset timer, compete.' },
				ended: { label: obaAuction.i18n?.step4_label || 'Auction Ended', desc: obaAuction.i18n?.step4_desc || 'Claim or view results.' },
			};
			if (label.length && map[step]) {
				label.text(map[step].label);
			}
			if (desc.length && map[step]) {
				desc.text(map[step].desc);
			}
		});
	}

	function updateBar(selector, remaining, total) {
		if (!total) {
			$(selector).css('width', '0%');
			return;
		}
		const percent = Math.max(0, Math.min(100, (remaining / total) * 100));
		$(selector).css('width', `${percent}%`);
	}

	function buildMembershipButtons(container) {
		const links = obaAuction.membership_links || [];
		const labels = obaAuction.membership_labels || [];
		container.empty();
		for (let i = 0; i < 3; i++) {
			const urlRaw = (links[i] || obaAuction.login_url || '#').toString().trim();
			const labelRaw = (labels[i] || '').toString().trim();
			const btnLabel = labelRaw || `Membership ${i + 1}`;
			container.append(`<a href="${urlRaw}" target="_blank" rel="noopener">${btnLabel}</a>`);
		}
		if (!container.children().length) {
			container.append('<p style="margin:0;">' + (obaAuction.i18n?.membership_required || 'Membership required to register.') + '</p>');
		}
	}

	function applyMembershipLocks(status) {
		const hasMembership = !!state.data.membership_active;
		const unlocked = !!state.data.registration_unlocked;
		const layoutOverlay = $('.oba-membership-overlay');
		const pointsOverlay = $('.oba-points-overlay');
		pointsOverlay.hide();
		if (!unlocked) {
			buildMembershipButtons(layoutOverlay.find('.oba-membership-links'));
			layoutOverlay.css('display', 'flex');
			$('.oba-phase-card').addClass('is-collapsed');
		} else {
			layoutOverlay.hide();
		}

		// If user is unlocked/registered but points are too low to register (balance < fee), show prompt to top-up.
		const balance = Number(state.data.user_points_balance || 0);
		const fee = Number(state.data.registration_fee_plain || state.data.registration_fee || 0);
		if (unlocked && fee && balance < fee) {
			buildMembershipButtons(pointsOverlay.find('.oba-membership-links'));
			pointsOverlay.css('display', 'flex');
		}
	}

	function register() {
		if (obaAuction.terms_text && !$('.oba-terms-checkbox').is(':checked')) {
			$('.oba-terms').addClass('oba-terms-error');
			return;
		}
		$('.oba-terms').removeClass('oba-terms-error');

		$.post(
			obaAuction.ajax_url,
			{
				action: 'auction_register_for_auction',
				auction_id: obaAuction.auction_id,
				nonce: obaAuction.nonce,
				accepted_terms: $('.oba-terms-checkbox').is(':checked') ? 1 : 0,
			},
			(response) => {
				if (response && response.success) {
					if (response.data && response.data.redirect_url) {
						window.location = response.data.redirect_url;
						return;
					}
					if (response.data && response.data.cart_url) {
						window.location = response.data.cart_url;
						return;
					}
					if (response.data && response.data.state) {
						state.data = response.data.state;
					}
					state.data = response.data;
					clearAlert();
					showToast(obaAuction.i18n?.registration_success_custom || obaAuction.i18n?.registered || 'Registered');
					render();
				} else if (response && response.message) {
					showAlert(response.message);
					showToast(response.message, true);
					if (response.code === 'not_logged_in') {
						showLoginHint();
					}
				} else {
					const msg = obaAuction.i18n?.registration_fail_custom || obaAuction.i18n?.login_required || obaAuction.i18n?.registration_fail || 'Please log in to register.';
					showAlert(msg);
					showToast(msg, true);
					showLoginHint();
				}
			}
		).fail(() => {
			const msg = obaAuction.i18n?.registration_fail_custom || obaAuction.i18n?.login_required || obaAuction.i18n?.registration_fail || 'Please log in to register.';
			showAlert(msg);
			showToast(msg, true);
			showLoginHint();
		});
	}

	function bid() {
		const btn = $('.oba-bid');
		if (btn.prop('disabled')) return;

		$.post(
			obaAuction.ajax_url,
			{
				action: 'auction_place_bid',
				auction_id: obaAuction.auction_id,
				nonce: obaAuction.nonce,
			},
			(response) => {
				if (response && response.success) {
					state.data = response.data;
					clearAlert();
					showToast(obaAuction.i18n?.bid_placed_custom || obaAuction.i18n?.bid_placed || 'Bid placed');
					render();
				} else if (response && response.message) {
					showAlert(response.message);
					showToast(response.message, true);
				} else {
					showToast(obaAuction.i18n?.bid_failed_custom || obaAuction.i18n?.bid_failed || 'Bid failed', true);
				}
			}
		).fail(() => {
			showToast(obaAuction.i18n?.bid_failed_custom || obaAuction.i18n?.bid_failed || 'Bid failed. Check connection and try again.', true);
		});
	}

	function claim() {
		if (!state.data || state.data.status !== 'ended' || !state.data.current_user_is_winner) {
			return;
		}
		submitClaim();
	}

	function openClaimModal() {
		// Deprecated: direct checkout flow now.
	}

	function closeClaimModal() {
		$('.oba-modal-overlay').hide();
		$('.oba-claim-modal').hide();
		$('.oba-claim-error').hide().text('');
	}

	function openInfoModal() {
		$('.oba-info-overlay, .oba-info-modal').show();
	}

	function closeInfoModal() {
		$('.oba-info-overlay, .oba-info-modal').hide();
	}

	function submitClaim() {
		$.post(
			obaAuction.ajax_url,
			{
				action: 'auction_claim_prize',
				auction_id: obaAuction.auction_id,
				nonce: obaAuction.nonce,
			},
			(response) => {
				if (response && response.success && response.data.redirect_url) {
					showToast(obaAuction.i18n?.claim_started_custom || obaAuction.i18n?.claim_started || 'Claim started');
					window.location = response.data.redirect_url;
					return;
				}
				if (response && response.data && response.data.cart_url) {
					window.location = response.data.cart_url;
					return;
				}
				if (response && response.data && response.data.redirect_url) {
					window.location = response.data.redirect_url;
					return;
				}
				if (response && response.message) {
					showAlert(response.message);
					showToast(response.message, true);
				} else {
					showToast(obaAuction.i18n?.claim_failed_custom || obaAuction.i18n?.claim_failed || 'Claim failed. Please try again.', true);
				}
			}
		).fail(() => {
			showToast(obaAuction.i18n?.claim_failed_custom || obaAuction.i18n?.claim_failed || 'Claim failed. Please try again.', true);
		});
	}

	$(document).on('click', '.oba-register', (e) => {
		e.preventDefault();
		register();
	});

	$(document).on('click', '.oba-bid', (e) => {
		e.preventDefault();
		bid();
	});

	$(document).on('click', '.oba-admin-end-now', (e) => {
		e.preventDefault();
		if (!confirm('End auction now?')) return;
		$.post(
			obaAuction.ajax_url,
			{
				action: 'auction_place_bid',
				auction_id: obaAuction.auction_id,
				nonce: obaAuction.nonce,
				force_end: 1,
			},
			() => {
				poll();
			}
		);
	});

	$(document).on('click', '.oba-claim', (e) => {
		e.preventDefault();
		claim();
	});

	$(document).on('click', '.oba-terms-link', (e) => {
		e.preventDefault();
		$('.oba-terms-modal, .oba-terms-overlay').show();
	});

	$(document).on('click', '.oba-terms-close, .oba-terms-overlay', () => {
		$('.oba-terms-modal, .oba-terms-overlay').hide();
	});

	$(document).on('change', '.oba-terms-checkbox', () => {
		if ($('.oba-terms-checkbox').is(':checked')) {
			$('.oba-terms').removeClass('oba-terms-error');
		}
	});

	$(document).on('click', '.oba-modal-overlay, .oba-claim-cancel', () => {
		closeClaimModal();
	});

	$(document).on('click', '.oba-claim-confirm', (e) => {
		e.preventDefault();
		submitClaim();
	});

	$(document).on('click', '.oba-pill-status', (e) => {
		e.preventDefault();
		openInfoModal();
	});

	$(document).on('click', '.oba-info-overlay, .oba-info-close', (e) => {
		e.preventDefault();
		closeInfoModal();
	});

	setInterval(poll, obaAuction.poll_interval);
	$(poll);

	function buildPackLinks() {
		const container = $('.oba-credit-links');
		if (!container.length) return;
		container.empty();
		const links = (obaAuction.pack_links || []).filter(Boolean);
		const labels = obaAuction.pack_labels || [];
		links.forEach((url, idx) => {
			const label = labels[idx] || `Pack ${idx + 1}`;
			container.append(`<a href="${url}" target="_blank" rel="noopener">${label}</a>`);
		});
	}

	function buildCreditModal() {
		const modal = $('.oba-credit-modal');
		const overlay = $('.oba-credit-overlay');
		if (!modal.length || !overlay.length) return;
		const container = modal.find('.oba-credit-options');
		container.empty();
		const links = [];
		const labels = [];
		if (!links.length) {
			container.append(`<p>${obaAuction.i18n?.buy_credits || 'Buy credits'}</p>`);
		} else {
			links.forEach((url, idx) => {
				const label = labels[idx] || `Plan ${idx + 1}`;
				container.append(`<a href="${url}" target="_blank" rel="noopener">${label}</a>`);
			});
		}
		overlay.show();
		modal.show();
	}

	function closeCreditModal() {
		$('.oba-credit-overlay, .oba-credit-modal').hide();
	}

	function updateCreditPill(balance, membershipActive = true) {
		const pts = $('.oba-user-points');
		if (pts.length) {
			pts.text(balance);
		}
		const pill = $('.oba-credit-amount');
		if (pill.length) {
			const pillWrap = pill.closest('.oba-credit-pill');
			if (!membershipActive) {
				pillWrap.hide();
			} else {
				pillWrap.show();
			}
			pill.text(balance);
			if (obaAuction.i18n?.points_label) {
				pillWrap.find('.oba-credit-label').text(obaAuction.i18n.points_label);
			}
		}
	}

	function formatMoney(val) {
		const num = Number(val) || 0;
		if (typeof wcSettings !== 'undefined' && wcSettings.currency) {
			const c = wcSettings.currency;
			try {
				return new Intl.NumberFormat(undefined, {
					style: 'currency',
					currency: c.currency_code || c.code || 'EUR',
					minimumFractionDigits: c.currency_minor_unit || obaAuction.currency_decimals || 2,
					maximumFractionDigits: c.currency_minor_unit || obaAuction.currency_decimals || 2,
				}).format(num);
			} catch (e) {
				return num.toFixed(2);
			}
		}
		const symbol = obaAuction.currency_symbol || '';
		const decimals = typeof obaAuction.currency_decimals !== 'undefined' ? obaAuction.currency_decimals : 2;
		return `${symbol}${num.toFixed(decimals)}`;
	}

	function updateAutobidUI() {
		if (!state.data) return;
		const box = $('.oba-autobid');
		if (!box.length) return;
		const enabled = !!state.data.autobid_enabled;
		const limitless = (state.data.autobid_max_bids || 0) === 0;
		$('#oba-autobid-enabled').prop('checked', enabled);
		const toggle = $('.oba-autobid-toggle');
		const maxInput = $('#oba-autobid-max-amount');
		const limitlessCheckbox = $('#oba-autobid-limitless');
		if (limitlessCheckbox.length) {
			limitlessCheckbox.prop('checked', limitless);
		}
		if (maxInput.length) {
			const currentVal = parseInt(maxInput.val(), 10);
			const modalOpen = $('.oba-autobid-modal').is(':visible');
			const dirty = maxInput.data('dirty');
			if (!modalOpen || (!dirty && !maxInput.is(':focus'))) {
				maxInput.val(limitless ? '' : (state.data.autobid_max_bids || currentVal || 1));
			}
			maxInput.prop('disabled', limitless);
		}
		const totalEl = $('.oba-autobid-total');
		if (totalEl.length) {
			const raw = maxInput.length ? parseInt(maxInput.val(), 10) || 0 : 0;
			const count = limitless ? 0 : (raw || state.data.autobid_max_bids || 1);
			const total = count * Number(state.data.bid_cost || 0);
			const text = limitless
				? (obaAuction.i18n?.autobid_limitless_label || 'Staying on top (no limit)')
				: (count ? `${count} × ${formatMoney(state.data.bid_cost)} = ${formatMoney(total)}` : `${formatMoney(Number(state.data.bid_cost || 0))}`);
			totalEl.filter('.oba-autobid-total-modal').text(text).show();
			totalEl.not('.oba-autobid-total-modal').text('').hide();
		}
		const remainingSeconds = 0;
		let status;
		const controls = $('#oba-autobid-enabled, .oba-autobid-toggle');
		if (state.data.status === 'ended') {
			status = obaAuction.i18n?.autobid_ended || 'Autobid is unavailable after the auction ends.';
			controls.prop('disabled', true);
		} else if (!state.data.user_registered && state.data.status !== 'registration') {
			status = obaAuction.i18n?.registration_closed || 'Registration is closed.';
			controls.prop('disabled', true);
		} else if (!state.data.user_registered) {
			status = '';
			controls.prop('disabled', true);
			$('.oba-autobid').hide();
			$('.oba-autobid-config').prop('disabled', true).hide();
			$('.oba-autobid-pill').hide();
		} else {
			const isRegistration = state.data.status === 'registration';
			$('.oba-autobid.registration-only').toggle(isRegistration);
			$('.oba-autobid.live-only').toggle(!isRegistration);
			$('.oba-autobid').show();
			controls.prop('disabled', false);
			const heading = $('.oba-autobid-title');
			if (heading.length) {
				if (enabled) {
					heading.text(obaAuction.i18n?.autobid_set_title || 'Autobid is set.');
					$('.oba-autobid-config').text(obaAuction.i18n?.autobid_edit || 'Edit autobid');
				} else {
					heading.text(obaAuction.i18n?.autobid_prompt_title || 'Would you like to set autobid?');
					$('.oba-autobid-config').text(obaAuction.i18n?.autobid_set || 'Set autobid');
				}
			}
			status = ''; // hide status text in blocks; pill covers state
			$('.oba-autobid-config').prop('disabled', false).show();
			const pill = $('.oba-autobid-pill');
			if (pill.length) {
				pill.show().removeClass('success danger');
				if (enabled) {
					pill.addClass('success').text(obaAuction.i18n?.autobid_on_badge || 'Autobid ON');
				} else {
					pill.addClass('danger').text(obaAuction.i18n?.autobid_off_badge || 'Autobid OFF');
				}
			}
			const liveCardValue = $('.oba-autobid-card .oba-autobid-value');
			const liveSwitch = $('.oba-autobid-switch');
			if (liveCardValue.length) {
				if (enabled) {
					const count = Number(state.data.autobid_max_bids || 0);
					const unit = Number(state.data.bid_cost || 0);
					const total = count && unit ? formatMoney(count * unit) : (obaAuction.i18n?.autobid_on_badge || 'On');
					const limitlessText = obaAuction.i18n?.autobid_limitless_label || 'Staying on top (no limit)';
					liveCardValue.text(limitless ? limitlessText : total);
				} else {
					liveCardValue.text(obaAuction.i18n?.autobid_off_badge || 'Off');
				}
			}
			if (liveSwitch.length) {
				liveSwitch.prop('checked', enabled);
				liveSwitch.prop('disabled', state.data.status === 'ended');
			}
		}
		const statusEl = $('.oba-autobid-status');
		statusEl.text(status).css('color', status ? '#000' : '');
		if (!status) {
			statusEl.hide();
		} else {
			statusEl.show();
		}
	}

	function toggleAutobid(enable) {
		if (enable) {
			const cost = obaAuction.autobid_cost_points || 0;
			const message = obaAuction.i18n?.autobid_confirm
				|| `Autobid will charge ${cost} points. Proceed?`;
			// eslint-disable-next-line no-alert
			if (!window.confirm(message)) {
				return;
			}
		}
		let maxBids = parseInt($('#oba-autobid-max-amount').val(), 10);
		if (!Number.isFinite(maxBids) || maxBids < 1) {
			maxBids = state.data.autobid_max_bids || 1;
		}
		const limitless = $('#oba-autobid-limitless').is(':checked');
		if (limitless) {
			maxBids = 0;
		}
		$('#oba-autobid-max-amount').val(maxBids).data('dirty', true);
		$.post(
			obaAuction.ajax_url,
			{
				action: 'auction_toggle_autobid',
				auction_id: obaAuction.auction_id,
				nonce: obaAuction.nonce,
				enable: enable ? 1 : 0,
				max_bids: maxBids,
				limitless: limitless ? 1 : 0,
			},
			(response) => {
				if (response && response.success) {
					state.data = state.data || {};
					state.data.autobid_enabled = response.data.autobid_enabled;
					state.data.autobid_remaining_bids = response.data.autobid_remaining_bids;
					state.data.autobid_window_seconds = response.data.autobid_window_seconds;
					state.data.autobid_remaining_seconds = response.data.autobid_remaining_seconds;
					state.data.autobid_max_spend = response.data.autobid_max_spend;
					state.data.autobid_max_bids = response.data.autobid_max_bids;
					state.data.user_points_balance = response.data.user_points_balance;
					$('#oba-autobid-max-amount').data('dirty', false);
					const canBidLocal = state.data.status === 'live'
						&& state.data.user_registered
						&& !state.data.user_is_winning
						&& !state.data.autobid_enabled;
					state.data.can_bid = canBidLocal;
					updateAutobidUI();
					showToast(obaAuction.i18n?.autobid_saved || 'Autobid updated');
				} else if (response && response.data && response.data.message) {
					showToast(response.data.message, true);
				}
			}
		).fail(() => {
			showToast(obaAuction.i18n?.autobid_error || 'Could not update autobid', true);
		});
	}

	function openAutobidModal() {
		const modal = $('.oba-autobid-modal');
		const overlay = $('.oba-autobid-overlay');
		if (!modal.length || !overlay.length) return;
		const maxInput = $('#oba-autobid-max-amount');
		if (maxInput.length) {
			maxInput.data('dirty', false);
			const val = state.data?.autobid_max_bids || 1;
			maxInput.val(val);
			maxInput.trigger('input');
		}
		overlay.show();
		modal.show();
	}

	function closeAutobidModal() {
		$('.oba-autobid-overlay, .oba-autobid-modal').hide();
	}

	$(document).on('click', '.oba-autobid-config', (e) => {
		e.preventDefault();
		openAutobidModal();
	});

	$(document).on('click', '.oba-autobid-overlay, .oba-autobid-close', (e) => {
		e.preventDefault();
		closeAutobidModal();
	});

	$(document).on('click', '.oba-autobid-toggle', function (e) {
		e.preventDefault();
		const current = $('#oba-autobid-enabled').is(':checked');
		const next = current ? 0 : 1;
		$('#oba-autobid-enabled').prop('checked', !!next);
		toggleAutobid(next);
		closeAutobidModal();
	});

	$(document).on('change', '.oba-autobid-switch', function () {
		const enable = $(this).is(':checked');
		toggleAutobid(enable);
	});

	$(document).on('input change', '#oba-autobid-max-amount', function () {
		const count = parseInt($(this).val(), 10) || 0;
		const total = count * Number(state.data?.bid_cost || 0);
		const totalEl = $('.oba-autobid-total-modal');
		$(this).data('dirty', true);
		if (totalEl.length) {
			const appliedCount = count || 1;
			const appliedTotal = appliedCount * Number(state.data?.bid_cost || 0);
			totalEl.text(count ? `${count} × ${formatMoney(state.data.bid_cost)} = ${formatMoney(total)}` : `${formatMoney(appliedTotal)}`);
		}
	});

	$(document).on('change', '#oba-autobid-limitless', function () {
		const checked = $(this).is(':checked');
		const input = $('#oba-autobid-max-amount');
		if (checked) {
			input.prop('disabled', true).val('');
		} else {
			input.prop('disabled', false);
			if (!input.val()) {
				input.val(state.data?.autobid_max_bids || 1);
			}
		}
		updateAutobidUI();
	});

	buildPackLinks();
	closeCreditModal();

	function updateLastRefreshed() {
		// Intentionally left blank; system time hidden to avoid user confusion.
	}

	function showLoginHint() {
		const cta = $('.oba-login-cta');
		if (cta.length) {
			if (obaAuction.login_url) {
				cta.attr('data-login-url', obaAuction.login_url);
				cta.find('a').attr('href', obaAuction.login_url);
			}
			cta.show();
			$('html, body').animate({ scrollTop: cta.offset().top - 40 }, 250);
			return;
		}
		const hint = $('.oba-login-hint');
		if (hint.length) {
			if (obaAuction.login_url) {
				hint.find('a').attr('href', obaAuction.login_url);
			}
			hint.show();
		}
	}

	function showMembershipLinks() {}

	// Disable old credit modal behavior; pill now only displays balance.
	$(document).off('click', '.oba-credit-pill');

	$(document).on('click', '.oba-share-btn', function (e) {
		e.preventDefault();
		const network = $(this).data('network');
		const url = window.location.href;
		if (network === 'facebook') {
			window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`, '_blank', 'noopener');
		} else if (network === 'instagram') {
			window.open(`https://www.instagram.com/?url=${encodeURIComponent(url)}`, '_blank', 'noopener');
		} else if (network === 'x') {
			window.open(`https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}`, '_blank', 'noopener');
		} else if (network === 'copy') {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url);
			} else {
				const temp = $('<input>');
				$('body').append(temp);
				temp.val(url).select();
				document.execCommand('copy');
				temp.remove();
			}
			showToast(obaAuction.i18n?.link_copied || 'Link copied');
		}
	});

	const tip = $('<div class="oba-tip"></div>').appendTo('body');
	$(document).on('mouseenter', '.oba-phase-icon', function () {
		const text = $(this).data('tip');
		if (!text) return;
		tip.text(text).show();
		const offset = $(this).offset();
		const width = $(this).outerWidth();
		const tipWidth = tip.outerWidth();
		tip.css({
			top: offset.top - tip.outerHeight() - 6,
			left: offset.left + (width / 2) - (tipWidth / 2),
		});
	});
	$(document).on('mouseleave', '.oba-phase-icon', () => {
		tip.hide();
	});
	$(document).on('click', '.oba-phase-icon', function (e) {
		const text = $(this).data('tip');
		if (!text) return;
		e.preventDefault();
	});
})(jQuery);
