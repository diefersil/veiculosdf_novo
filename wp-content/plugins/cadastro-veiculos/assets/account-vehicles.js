(function () {
	'use strict';

	if (!window.CVAccountVehicles || !window.CVAccountVehicles.ajaxUrl) {
		return;
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('.js-cv-account-toggle');
		if (!button || button.disabled) {
			return;
		}

		event.preventDefault();
		var row = button.closest('li');
		var originalLabel = button.textContent;
		button.disabled = true;
		button.setAttribute('aria-busy', 'true');
		button.textContent = 'Aguarde…';

		var body = new URLSearchParams();
		body.set('action', 'cv_account_toggle_vehicle');
		body.set('vehicle_id', button.getAttribute('data-vehicle-id'));
		body.set('nonce', window.CVAccountVehicles.nonce);

		fetch(window.CVAccountVehicles.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (response) {
				if (!response || !response.success || !response.data || !response.data.html) {
					throw new Error(response && response.data && response.data.message ? response.data.message : window.CVAccountVehicles.errorMessage);
				}
				var template = document.createElement('template');
				template.innerHTML = response.data.html.trim();
				var updatedRow = template.content.firstElementChild;
				if (!updatedRow || !row) {
					throw new Error(window.CVAccountVehicles.errorMessage);
				}
				row.replaceWith(updatedRow);
			})
			.catch(function (error) {
				button.disabled = false;
				button.removeAttribute('aria-busy');
				button.textContent = originalLabel;
				window.alert(error.message || window.CVAccountVehicles.errorMessage);
			});
	});
}());
