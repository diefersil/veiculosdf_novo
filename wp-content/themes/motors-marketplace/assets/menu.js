(function () {
	'use strict';
	var button = document.querySelector('.mm-menu-toggle');
	var nav = document.getElementById('mm-primary-nav');
	if (!button || !nav) return;
	button.addEventListener('click', function () {
		var open = button.getAttribute('aria-expanded') === 'true';
		button.setAttribute('aria-expanded', open ? 'false' : 'true');
		nav.classList.toggle('is-open', !open);
	});
}());
