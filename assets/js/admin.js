(function ($) {
	'use strict';

	function pad(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function tickCountdown() {
		var el = document.getElementById('pluginstage-countdown');
		if (!el || typeof pluginstageAdmin === 'undefined') {
			return;
		}
		if (!pluginstageAdmin.showCountdown || !pluginstageAdmin.nextReset) {
			el.textContent = '';
			return;
		}
		var ts = parseInt(pluginstageAdmin.nextReset, 10) * 1000;
		var now = Date.now();
		var diff = Math.floor((ts - now) / 1000);
		if (diff <= 0) {
			el.textContent = pluginstageAdmin.countdownLabel + ' —';
			return;
		}
		var h = Math.floor(diff / 3600);
		var m = Math.floor((diff % 3600) / 60);
		var s = diff % 60;
		el.textContent =
			pluginstageAdmin.countdownLabel +
			' ' +
			pad(h) +
			':' +
			pad(m) +
			':' +
			pad(s);
	}

	function initBannerBodyClass() {
		var b = document.getElementById('pluginstage-top-banner');
		if (b) {
			document.body.classList.add('pluginstage-has-banner');
		}
		if (document.getElementById('pluginstage-footer-bar')) {
			document.body.classList.add('pluginstage-has-footer');
		}
	}

	$(function () {
		initBannerBodyClass();
		tickCountdown();
		setInterval(tickCountdown, 1000);

		$('#pluginstage-banner-dismiss').on('click', function () {
			if (typeof pluginstageAdmin === 'undefined') {
				return;
			}
			$.post(pluginstageAdmin.ajaxUrl, {
				action: 'pluginstage_dismiss_banner',
				nonce: pluginstageAdmin.nonce
			}).done(function () {
				$('#pluginstage-top-banner').remove();
				document.body.classList.remove('pluginstage-has-banner');
			});
		});
	});
})(jQuery);
