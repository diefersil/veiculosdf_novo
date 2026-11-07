(function () {
	'use strict';

	document.querySelectorAll('.cv-account-tabs[role="tablist"]').forEach(function (tablist) {
		var tabs = Array.prototype.slice.call(tablist.querySelectorAll('[role="tab"]'));

		function activate(tab, moveFocus) {
			tabs.forEach(function (item) {
				var active = item === tab;
				var panel = document.getElementById(item.getAttribute('aria-controls'));
				item.setAttribute('aria-selected', active ? 'true' : 'false');
				item.setAttribute('tabindex', active ? '0' : '-1');
				item.classList.toggle('is-active', active);
				if (panel) {
					panel.hidden = !active;
				}
			});
			if (moveFocus) {
				tab.focus();
			}
		}

		tablist.addEventListener('click', function (event) {
			var tab = event.target.closest('[role="tab"]');
			if (tab && tabs.indexOf(tab) !== -1) {
				activate(tab, false);
			}
		});

		tablist.addEventListener('keydown', function (event) {
			var index = tabs.indexOf(document.activeElement);
			if (index === -1) {
				return;
			}
			var next = index;
			if (event.key === 'ArrowRight') {
				next = (index + 1) % tabs.length;
			} else if (event.key === 'ArrowLeft') {
				next = (index - 1 + tabs.length) % tabs.length;
			} else if (event.key === 'Home') {
				next = 0;
			} else if (event.key === 'End') {
				next = tabs.length - 1;
			} else {
				return;
			}
			event.preventDefault();
			activate(tabs[next], true);
		});
	});
}());
