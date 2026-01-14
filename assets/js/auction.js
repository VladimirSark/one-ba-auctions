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

	function formatDurationShort(seconds) {
		const total = Math.max(0, parseInt(seconds || 0, 10));
		const mins = Math.floor(total / 60);
		const secs = total % 60;
		if (mins > 0) {
			return `${mins}m ${secs}s`;
		}
		return `${secs}s`;
	}

	function formatTimeStamp(ts) {
		const d = new Date(ts);
		if (Number.isNaN(d.getTime())) return ts;
		return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
	}

	function cleanMoney(val) {
		if (!val) return '';
		return val
			.toString()
			.replace(/<[^>]+>/g, '')
			.replace(/&nbsp;/g, ' ')
			.replace(/&euro;/gi, '€')
			.trim();
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

	function openLogin() {
		if (obaAuction.login_url) {
			window.open(obaAuction.login_url, '_blank', 'noopener');
		}
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
	const liveFee = (state.data.live_join_points_plain ?? state.data.live_join_points ?? '').toString().trim();
	const allowLiveJoin = !!state.data.allow_live_join;
	const canJoinLive = !!state.data.can_join_live;
	const hasEnoughLive = !!state.data.has_enough_points_for_live_join;
	const isLiveStage = status === 'live';
	const showLiveJoinCta = isLiveStage && allowLiveJoin && !state.data.user_registered;
	const isGuest = !state.data.is_logged_in;
	const autobidWindowLeft = Number(state.data.autobid_window_seconds_left || 0);
	const autobidWindowMinutes = Number(state.data.autobid_window_minutes || state.data.autobid_window_selected || 0);
	const autobidOnLabel = obaAuction.i18n?.autobid_on_button || 'Aut. statymas įjungtas';
	const autobidBtnLabel = autobidWindowLeft > 0
		? `${autobidOnLabel} (${formatDurationShort(autobidWindowLeft)})`
		: autobidOnLabel;

		if (isGuest) {
			$('.oba-guest-banner').show();
			$('.oba-col-right').addClass('oba-guest-blur');
			$('.oba-explainer').addClass('oba-guest-blur');
			$('.oba-membership-overlay, .oba-points-overlay').hide();
		} else {
			$('.oba-guest-banner').hide();
			$('.oba-col-right').removeClass('oba-guest-blur');
			$('.oba-explainer').removeClass('oba-guest-blur');
		}
		if (showLiveJoinCta) {
			const cta = obaAuction.i18n?.live_join_cta || 'Participate in auction';
			const label = liveFee ? `${cta} (${liveFee} ${suffix})` : cta;
			regBtn.text(label);
			regBtn.prop('disabled', !canJoinLive || !hasEnoughLive);
			$('.oba-terms').show();
			$('.oba-live-terms').show();
			$('.oba-registered-note').hide();
			$('.oba-not-registered').show();
			if (!canJoinLive) {
				const msg = !hasEnoughLive
					? (obaAuction.i18n?.points_low_title || 'Not enough points to continue.')
					: (obaAuction.i18n?.membership_required || 'Membership required to register.');
				showAlert(msg);
			} else {
				clearAlert();
			}
		} else if (status !== 'registration' && !state.data.user_registered) {
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
			$('.oba-live-terms').hide();
			$('.oba-registered-note').hide();
			$('.oba-not-registered').show();
			$('.oba-registered').hide();
			$('.oba-pending-banner').hide();
			$('.oba-autobid').hide();
			$('.oba-autobid-config').prop('disabled', true).hide();
		}

		const bidBtn = $('.oba-bid');
	if (showLiveJoinCta) {
		const cta = obaAuction.i18n?.live_join_cta || 'Participate in auction';
		const label = liveFee ? `${cta} (${liveFee} ${suffix})` : cta;
		bidBtn.text(label);
		bidBtn.prop('disabled', !canJoinLive || !hasEnoughLive);
	} else if (state.data.autobid_enabled) {
		bidBtn.prop('disabled', true).text(autobidBtnLabel);
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

		// Claimed summary (global view when winner has claimed)
		const winnerBlock = state.data.winner || {};
		const claimedSummary = $('.oba-claimed-summary');
		if (status === 'ended' && winnerBlock.claimed) {
			$('.oba-claimed-winner').text(winnerBlock.anonymous_name || '—');
			$('.oba-claimed-bids').text(winnerBlock.total_bids || 0);
			const valText = cleanMoney(winnerBlock.total_value_fmt || winnerBlock.total_value || '');
			$('.oba-claimed-value').text(valText || '—');
			const savedText = cleanMoney(winnerBlock.saved_amount_fmt || winnerBlock.saved_amount || '');
			$('.oba-claimed-saved').text(savedText || '—');
			const endedText = winnerBlock.ended_at ? (obaAuction.i18n?.ended_at || 'Ended') + ': ' + winnerBlock.ended_at : '';
			$('.oba-claimed-ended').text(endedText);
			claimedSummary.show();
			$('.oba-winner-claim').hide();
			$('.oba-loser').hide();
		} else {
			claimedSummary.hide();
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
				if (!winnerBlock.claimed) {
					$('.oba-loser').show();
				}
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

		// Autobid UI (V2 minimal) - only show if registered and server says autobid feature is enabled for auction.
		$('.oba-autobid-setup').each(function () {
			const setup = $(this);
			const auctionAutobidEnabled = !!state.data.autobid_allowed_for_auction;
			if (auctionAutobidEnabled) {
				setup.show();
				const enabled = !!state.data.autobid_enabled;
				renderInlineAutobidTotal(setup);
				updateAutobidWindowUI(enabled);
				const windowMinutes = Number(state.data.autobid_window_minutes || state.data.autobid_window_selected || 0);
				const windowSeconds = Number(state.data.autobid_window_seconds_left || 0);
				if (!enabled && windowMinutes) {
					setSelectedAutobidWindow(windowMinutes);
				}
				if (setup.attr('data-phase') === 'registration') {
					const title = setup.find('.oba-autobid-left h4');
					if (title.length) {
						if (enabled) {
							const base = obaAuction.i18n?.autobid_set_for || 'Autobid is set for:';
							const windowForLabel = windowMinutes || (windowSeconds ? Math.ceil(windowSeconds / 60) : 0);
							const label = windowForLabel ? `${base} ${windowForLabel}m` : (obaAuction.i18n?.autobid_set || 'Autobid is set');
							title.text(label);
						} else {
							title.text(obaAuction.i18n?.autobid_title || 'Autobid');
						}
					}
				}
			} else {
				setup.hide();
			}
		});
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
		if (state.data && !state.data.is_logged_in) {
			openLogin();
			return;
		}
		const termsBlocks = $('.oba-terms, .oba-live-terms');
		const termsChecked = $('.oba-terms-checkbox').is(':checked');
		if (obaAuction.terms_text && !termsChecked) {
			termsBlocks.addClass('oba-terms-error');
			showToast(obaAuction.i18n?.accept_terms || 'Please accept the terms to continue.', true);
			return;
		}
		termsBlocks.removeClass('oba-terms-error');

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
		if (state.data && !state.data.is_logged_in) {
			openLogin();
			return;
		}
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
		if (state.data && !state.data.is_logged_in) {
			openLogin();
			return;
		}
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

	$(document).on('click', '.oba-bid, .oba-register, .oba-claim, .oba-autobid-toggle, .oba-autobid-toggle-btn, .oba-autobid-enable, .oba-autobid-disable', (e) => {
		if (state.data && !state.data.is_logged_in) {
			e.preventDefault();
			openLogin();
			return false;
		}
		return true;
	});

	$(document).on('click', '.oba-bid', (e) => {
		e.preventDefault();
		const canLiveJoinNow = state.data
			&& state.data.status === 'live'
			&& state.data.allow_live_join
			&& !state.data.user_registered
			&& state.data.can_join_live;
		if (canLiveJoinNow) {
			register();
			return;
		}
		bid();
	});

$(document).on('click', '.oba-autobid-enable', function (e) {
	e.preventDefault();
	const maxInput = $(this).closest('.oba-autobid-setup').find('.oba-autobid-max');
	$('#oba-autobid-max-amount').val(maxInput.val());
	toggleAutobid(true);
});

$(document).on('click', '.oba-autobid-disable', function (e) {
	e.preventDefault();
	toggleAutobid(false);
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
			$('.oba-terms, .oba-live-terms').removeClass('oba-terms-error');
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

	$(poll);
	setInterval(poll, parseInt(obaAuction.poll_interval, 10) || 3000);

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
	const enabled = !!state.data?.autobid_enabled;
	const limitless = false;
	$('.oba-autobid-switch').prop('checked', enabled);
	const toggleText = $('.oba-toggle-text');
	if (toggleText.length) {
		toggleText.text(enabled ? (obaAuction.i18n?.on || 'On') : (obaAuction.i18n?.off || 'Off'));
	}
	const bidCost = Number(state.data?.bid_cost || 0);
	const amount = limitless ? 0 : (Number(state.data?.autobid_max_spend || 0) || ((state.data?.autobid_max_bids || 0) * bidCost));
	const count = limitless ? 0 : (bidCost ? Math.floor(amount / bidCost) : 0);
	const stateLabel = $('.oba-autobid-state');
	if (stateLabel.length) {
		if (enabled && limitless) {
			const text = obaAuction.i18n?.autobid_limitless_label || 'Unlimited autobid';
			stateLabel.text(text);
		} else if (enabled && amount > 0) {
			const valText = count ? `${formatMoney(amount)} (${count} bids)` : formatMoney(amount);
			stateLabel.text(valText);
		} else {
			stateLabel.text(obaAuction.i18n?.off || 'OFF');
		}
	}
}

function renderInlineAutobidTotal($block) {
	const input = $block.find('.oba-autobid-max');
	const totalEl = $block.find('.oba-autobid-total-inline');
	if (!input.length || !totalEl.length) return;

	if (state.data?.autobid_limitless || Number(state.data?.autobid_max_bids) === 0) {
		totalEl.text('');
		return;
	}

	const bidCost = Number(state.data?.bid_cost || 0);
	if (!bidCost) {
		totalEl.text('');
		return;
	}

	const rawAmount = parseFloat(input.val());
	const fallbackAmount = Number(state.data?.autobid_max_spend || 0) || Number(state.data?.autobid_max_bids || 0) * bidCost;
	const amount = Number.isFinite(rawAmount) && rawAmount > 0 ? rawAmount : fallbackAmount;
	const count = Math.max(0, Math.floor(amount / bidCost));
	if (!amount || !count) {
		totalEl.text('');
		return;
	}
	totalEl.text(`${formatMoney(amount)} = ${count} bids`);
}

	function getSelectedAutobidWindow() {
		const btn = $('.oba-autobid-window-btn.is-active').first();
		return btn.length ? parseInt(btn.data('minutes'), 10) || 0 : 0;
	}

	function setSelectedAutobidWindow(minutes) {
		$('.oba-autobid-window-btn').removeClass('is-active');
		$('.oba-autobid-window-btn').each(function () {
			if (parseInt($(this).data('minutes'), 10) === minutes) {
				$(this).addClass('is-active');
			}
		});
		state.data = state.data || {};
		state.data.autobid_window_selected = minutes;
	}

	function updateAutobidWindowUI(enabled) {
		$('.oba-autobid-window-remaining').text('');
	}

	function openAutobidWindowModal() {
		setSelectedAutobidWindow(10);
		$('.oba-autobid-window-overlay, .oba-autobid-window-modal').css('display', 'flex');
	}

	function closeAutobidWindowModal() {
		$('.oba-autobid-window-overlay, .oba-autobid-window-modal').hide();
	}

function toggleAutobid(enable) {
	const windowMinutes = getSelectedAutobidWindow();
		if (enable && (!windowMinutes || windowMinutes <= 0)) {
			$('.oba-autobid-window-btn').addClass('oba-terms-error');
			showToast(obaAuction.i18n?.autobid_select_window || 'Select a time window to enable autobid.', true);
			return;
		}
		$('.oba-autobid-window-btn').removeClass('oba-terms-error');
		if (enable) {
			const cost = obaAuction.autobid_cost_points || 0;
			const tmpl = obaAuction.i18n?.autobid_confirm
				|| 'Autobid will charge {cost} points and will be enabled for {minutes} minutes in live stage. Proceed?';
			const message = tmpl
				.replace('{cost}', cost)
				.replace('{minutes}', windowMinutes || 0);
		// eslint-disable-next-line no-alert
		if (!window.confirm(message)) {
			return;
		}
	}
		const maxBids = 0;
		const spend = 0;
		const limitless = false;
		const maxBidsDefault = 1000000; // large cap to satisfy server requirement when using time windows.
		$.post(
			obaAuction.ajax_url,
			{
				action: 'auction_toggle_autobid',
				auction_id: obaAuction.auction_id,
				nonce: obaAuction.nonce,
				enable: enable ? 1 : 0,
				max_bids: maxBidsDefault,
				max_spend: spend,
				limitless: limitless ? 1 : 0,
				window_minutes: windowMinutes,
			},
			(response) => {
				if (response && response.success) {
					state.data = state.data || {};
					state.data.autobid_enabled = response.data.autobid_enabled;
					state.data.autobid_remaining_bids = response.data.autobid_remaining_bids;
					state.data.autobid_window_seconds_left = response.data.autobid_window_seconds_left;
					state.data.autobid_window_ends_at = response.data.autobid_window_ends_at;
					state.data.autobid_window_minutes = response.data.autobid_window_minutes;
					state.data.autobid_max_spend = response.data.autobid_max_spend;
					state.data.autobid_max_bids = response.data.autobid_max_bids;
					state.data.autobid_limitless = response.data.autobid_limitless;
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
			const bidCost = Number(state.data?.bid_cost || 0);
			const val = state.data?.autobid_max_spend
				|| ((state.data?.autobid_max_bids || 1) * bidCost);
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

$(document).on('click', '.oba-autobid-toggle-btn', function (e) {
	e.preventDefault();
	const enable = !state.data?.autobid_enabled;
	if (enable) {
		openAutobidWindowModal();
	} else {
		toggleAutobid(false);
	}
});

	$(document).on('click', '.oba-autobid-overlay, .oba-autobid-close', (e) => {
		e.preventDefault();
		closeAutobidModal();
	});

	$(document).on('click', '.oba-autobid-window-cancel, .oba-autobid-window-overlay', (e) => {
		e.preventDefault();
		closeAutobidWindowModal();
	});

	$(document).on('click', '.oba-autobid-window-confirm', (e) => {
		e.preventDefault();
		const minutes = getSelectedAutobidWindow();
		if (!minutes) {
			$('.oba-autobid-window-btn').addClass('oba-terms-error');
			return;
		}
		closeAutobidWindowModal();
		toggleAutobid(true);
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
	const setup = $(this).closest('.oba-autobid-setup');
	const inlineVal = parseFloat(setup.find('.oba-autobid-max').val());
	if (Number.isFinite(inlineVal) && inlineVal > 0) {
		const modalInput = $('#oba-autobid-max-amount');
		if (modalInput.length) {
			modalInput.val(inlineVal).data('dirty', true);
		}
	}
	// Intercept enable to show window modal.
	if (enable) {
		$(this).prop('checked', false);
		openAutobidWindowModal();
	} else {
		toggleAutobid(false);
	}
});

$(document).on('input change', '.oba-autobid-max', function () {
	renderInlineAutobidTotal($(this).closest('.oba-autobid-setup'));
});

$(document).on('input change', '#oba-autobid-max-amount', function () {
	const amount = parseFloat($(this).val()) || 0;
	const bidCost = Number(state.data?.bid_cost || 0);
	const count = bidCost ? Math.floor(amount / bidCost) : 0;
	const total = count * bidCost;
	const totalEl = $('.oba-autobid-total-modal');
	$(this).data('dirty', true);
	if (totalEl.length) {
		if (!bidCost || !amount || !count) {
			totalEl.text('');
		} else {
			totalEl.text(`${formatMoney(amount)} = ${count} bids`);
		}
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
				const bidCost = Number(state.data?.bid_cost || 0);
				const fallback = state.data?.autobid_max_spend
					|| ((state.data?.autobid_max_bids || 1) * bidCost);
				input.val(fallback || '');
			}
		}
		updateAutobidUI();
	});

	buildPackLinks();
	closeCreditModal();

$(document).on('click', '.oba-autobid-window-btn', function (e) {
	e.preventDefault();
	const minutes = parseInt($(this).data('minutes'), 10) || 0;
	setSelectedAutobidWindow(minutes);
});

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
