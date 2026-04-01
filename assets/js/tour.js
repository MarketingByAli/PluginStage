(function ($) {
	'use strict';

	if (typeof Shepherd === 'undefined' || typeof pluginstageTour === 'undefined') {
		return;
	}

	$(function () {
		var steps = pluginstageTour.steps;
		if (!steps || !steps.length) {
			return;
		}

		var tour = new Shepherd.Tour({
			useModalOverlay: true,
			defaultStepOptions: {
				classes: 'pluginstage-shepherd',
				scrollTo: true,
				cancelIcon: { enabled: true }
			}
		});

		var last = steps.length - 1;

		steps.forEach(function (step, index) {
			var opts = {
				title: step.title || '',
				text: step.text || '',
				buttons: []
			};
			if (step.attachTo && step.attachTo.element && step.attachTo.on) {
				opts.attachTo = step.attachTo;
			}

			if (index > 0) {
				opts.buttons.push({
					text: pluginstageTour.back,
					secondary: true,
					action: function () {
						return tour.back();
					}
				});
			}

			if (index === last) {
				opts.buttons.push({
					text: pluginstageTour.done,
					action: function () {
						return tour.complete();
					}
				});
			} else {
				opts.buttons.push({
					text: pluginstageTour.next,
					action: function () {
						return tour.next();
					}
				});
			}

			if (index === 0) {
				opts.buttons.unshift({
					text: pluginstageTour.cancel,
					secondary: true,
					action: function () {
						return tour.cancel();
					}
				});
			}

			tour.addStep(opts);
		});

		tour.start();
	});
})(jQuery);
