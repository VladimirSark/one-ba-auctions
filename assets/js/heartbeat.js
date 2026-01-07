(function () {
	if (typeof window === 'undefined' || typeof fetch === 'undefined' || !window.obaHeartbeat) {
		return;
	}

	const url = window.obaHeartbeat.ajax_url;
	const nonce = window.obaHeartbeat.nonce;
	const interval = parseInt(window.obaHeartbeat.interval, 10) || 15000;
	let timer = null;

	const body = () => `action=oba_tick_heartbeat&nonce=${encodeURIComponent(nonce)}`;

	function ping() {
		fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body(),
		}).catch(() => {
			/* silent */
		});
	}

	function start() {
		if (timer) {
			return;
		}
		ping();
		timer = setInterval(ping, interval);
	}

	function stop() {
		if (timer) {
			clearInterval(timer);
			timer = null;
		}
	}

	if (typeof document !== 'undefined' && typeof document.addEventListener === 'function') {
		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'visible') {
				start();
			} else {
				stop();
			}
		});
	}

	start();
})();
