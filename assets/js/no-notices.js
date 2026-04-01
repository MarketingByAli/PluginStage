(function () {
	'use strict';

	function removeNotices() {
		var selectors = [
			'.notice',
			'.updated',
			'.error',
			'.update-nag',
			'.elementor-message',
			'.elementor-notice',
			'.elementor-admin-notice',
			'.e-notice',
			'.e-overview__go-pro',
			'.e-admin-top-bar-promotion',
			'[class*="elementor"][class*="notice"]',
			'[class*="elementor"][class*="promo"]',
			'[class*="elementor"][class*="promotion"]',
			'[class*="elementor"][class*="a11y"]',
			'[class*="elementor"][class*="ally"]',
			'[class*="e-a11y"]',
			'[class*="e-ally"]',
			'[class*="admin-notice"]',
			'[class*="admin_notice"]',
			'#welcome-panel',
			'.try-gutenberg-panel',
			'.jkit-notice',
			'.yoast-notice',
			'.woocommerce-message',
			'.jetpack-jitm-message'
		];

		var all = document.querySelectorAll(selectors.join(','));
		for (var i = 0; i < all.length; i++) {
			all[i].parentNode.removeChild(all[i]);
		}

		var wrap = document.querySelector('.wrap') || document.getElementById('wpbody-content');
		if (!wrap) return;

		var children = wrap.children;
		for (var j = children.length - 1; j >= 0; j--) {
			var el = children[j];
			var tag = el.tagName;
			if (tag !== 'DIV' && tag !== 'SECTION') continue;
			if (el.id || el.classList.length === 0) continue;

			var text = el.textContent || '';
			if (
				text.indexOf('Install now') !== -1 ||
				text.indexOf('Upgrade now') !== -1 ||
				text.indexOf('Go Pro') !== -1 ||
				text.indexOf('Learn more') !== -1 ||
				text.indexOf('accessibility statement') !== -1 ||
				text.indexOf('Starter Templates') !== -1
			) {
				el.parentNode.removeChild(el);
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', removeNotices);
	} else {
		removeNotices();
	}

	setTimeout(removeNotices, 500);
	setTimeout(removeNotices, 2000);

	var observer = new MutationObserver(function () {
		removeNotices();
	});
	observer.observe(document.body || document.documentElement, {
		childList: true,
		subtree: true
	});
})();
