(function ($) {
	const state = { data: null };

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

		updateStepBar(status);
		updatePhaseCards(status);

		$('.oba-lobby-bar span').css('width', `${state.data.lobby_percent}%`);
		const lobbyLabel = obaAuction.i18n?.lobby_progress || 'Lobby progress';
		$('.oba-lobby-count').text(`${lobbyLabel}: ${state.data.lobby_percent}%`);

		$('.oba-prelive-seconds').text(formatTime(state.data.pre_live_seconds_left));
		updateBar('.oba-prelive-bar span', Number(state.data.pre_live_seconds_left), Number(state.data.pre_live_total));

		$('.oba-live-seconds').text(formatTime(state.data.live_seconds_left));
		updateBar('.oba-live-bar span', Number(state.data.live_seconds_left), Number(state.data.live_total));

		$('.oba-user-bids').text(state.data.user_bids_count);
		$('.oba-user-cost').text(state.data.user_cost);

		const regBtn = $('.oba-register');
		const regText = obaAuction.i18n?.register_cta || obaAuction.i18n?.register || 'Register & Reserve Spot';
		const fee = (state.data.registration_fee ?? state.data.registration_fee_credits ?? '').toString().trim();
		const creditsLabel = state.data.credits_label || obaAuction.i18n?.credit_plural || 'credits';
		const feeText = fee ? ` (${fee} ${creditsLabel})` : '';
		regBtn.text(`${regText}${feeText}`);
		if (state.data.user_registered) {
			regBtn.addClass('oba-registered').prop('disabled', true);
			$('.oba-terms').hide();
			$('.oba-registered-note').show();
			$('.oba-not-registered').hide();
			$('.oba-registered').show();
		} else {
			regBtn.removeClass('oba-registered').prop('disabled', false);
			$('.oba-terms').show();
			$('.oba-registered-note').hide();
			$('.oba-not-registered').show();
			$('.oba-registered').hide();
		}

		const bidBtn = $('.oba-bid');
		if (state.data.can_bid) {
			bidBtn.prop('disabled', false).text(obaAuction.i18n?.bid_button || 'Place bid');
		} else {
			const btnText = state.data.user_is_winning ? (obaAuction.i18n?.you_leading_custom || obaAuction.i18n?.you_leading || 'You are leading') : (obaAuction.i18n?.cannot_bid || 'Cannot bid');
			bidBtn.prop('disabled', true).text(btnText);
		}

		const historyList = $('.oba-history');
		historyList.empty();
		(state.data.history || []).slice(0, 5).forEach((row) => {
			const time = formatTimeStamp(row.time);
			historyList.append(`<li><span>${row.name}</span><span>${row.cost} cr</span><span>${time}</span></li>`);
		});

		if (state.data.error_message) {
			showAlert(state.data.error_message);
			showToast(state.data.error_message, true);
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
			$('.oba-claim-amount').text(state.data.claim_amount);
			$('.oba-loser').hide();
		} else {
			$('.oba-winner-claim').hide();
			if (status === 'ended') {
				$('.oba-loser').show();
			}
		}

		updateCreditPill(state.data.user_credits_balance);
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
				registration: { label: obaAuction.i18n?.step1_label || 'Registration', desc: obaAuction.i18n?.step1_desc || 'Join the lobby with credits.' },
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
		openClaimModal();
	}

	function openClaimModal() {
		$('.oba-modal-overlay').show();
		$('.oba-claim-modal').show();
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
		const method = $('input[name="oba-claim-method"]:checked').val() || 'credits';
		$.post(
			obaAuction.ajax_url,
			{
				action: 'auction_claim_prize',
				auction_id: obaAuction.auction_id,
				nonce: obaAuction.nonce,
				payment_method: method,
			},
			(response) => {
				if (response && response.success && response.data.redirect_url) {
					showToast(obaAuction.i18n?.claim_started_custom || obaAuction.i18n?.claim_started || 'Claim started');
					setTimeout(() => {
						window.location = response.data.redirect_url;
					}, 200);
					return;
				}
				if (response && response.message) {
					$('.oba-claim-error').text(response.message).show();
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
		const links = (obaAuction.pack_links || []).filter(Boolean);
		const labels = obaAuction.pack_labels || [];
		if (!links.length) {
			container.append(`<p>${obaAuction.i18n?.buy_credits || 'Buy credits'}</p>`);
		} else {
			links.forEach((url, idx) => {
				const label = labels[idx] || `Pack ${idx + 1}`;
				container.append(`<a href="${url}" target="_blank" rel="noopener">${label}</a>`);
			});
		}
		overlay.show();
		modal.show();
	}

	function closeCreditModal() {
		$('.oba-credit-overlay, .oba-credit-modal').hide();
	}

	function updateCreditPill(balance) {
		const pill = $('.oba-credit-pill');
		if (!pill.length) return;
		if (typeof balance === 'undefined' || balance === null) {
			balance = parseFloat(pill.data('balance') || 0);
		}
		pill.attr('data-balance', balance);
		const label = obaAuction.i18n?.credits_pill_label || 'Credits';
		pill.find('.oba-credit-balance').text(`${label}: ${balance}`);
		if (balance < 10) {
			pill.addClass('low');
		} else {
			pill.removeClass('low');
		}
	}

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

	$(document).on('click', '.oba-credit-pill', (e) => {
		if ($(e.target).is('a') || $(e.target).closest('a').length) {
			return;
		}
		e.preventDefault();
		buildCreditModal();
	});

	$(document).on('click', '.oba-credit-overlay, .oba-credit-close', (e) => {
		e.preventDefault();
		closeCreditModal();
	});

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
