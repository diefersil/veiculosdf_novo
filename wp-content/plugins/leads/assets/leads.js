(function () {
	'use strict';

	var config = window.LeadsFrontend;
	if (!config) return;

	function send(action, values, keepalive) {
		var payload = new URLSearchParams();
		payload.set('action', action);
		payload.set('nonce', config.nonce);
		Object.keys(values || {}).forEach(function (key) { payload.set(key, values[key]); });
		return fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			keepalive: !!keepalive,
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: payload.toString()
		}).then(function (response) { return response.json(); }).then(function (response) {
			if (!response || !response.success) {
				throw new Error(response && response.data && response.data.message ? response.data.message : 'Não foi possível concluir. Tente novamente.');
			}
			return response.data;
		});
	}

	document.querySelectorAll('[data-cv-track-phone]').forEach(function (link) {
		link.addEventListener('click', function () {
			send('leads_track_event', { vehicle_id: config.vehicleId, channel: 'phone' }, true).catch(function () {});
		});
	});

	var modal = document.querySelector('[data-cv-whatsapp-modal]');
	var form = modal && modal.querySelector('[data-cv-whatsapp-form]');
	if (!modal || !form) return;

	var error = modal.querySelector('[data-cv-lead-error]');
	var recordId = form.querySelector('[name="record_id"]');
	var clickPromise = Promise.resolve(0);
	var previousFocus = null;

	function closeModal() {
		modal.hidden = true;
		modal.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('mm-lead-modal-open');
		if (previousFocus) previousFocus.focus();
	}

	document.querySelectorAll('[data-cv-whatsapp-trigger]').forEach(function (link) {
		link.addEventListener('click', function (event) {
			event.preventDefault();
			previousFocus = link;
			recordId.value = '';
			error.hidden = true;
			modal.hidden = false;
			modal.setAttribute('aria-hidden', 'false');
			document.body.classList.add('mm-lead-modal-open');
			modal.querySelector('input[name="name"]').focus();
			clickPromise = send('leads_track_event', { vehicle_id: config.vehicleId, channel: 'whatsapp' }).then(function (data) {
				recordId.value = data.record_id || '';
				return data.record_id || 0;
			}).catch(function () { return 0; });
		});
	});

	modal.addEventListener('click', function (event) {
		if (event.target.closest('[data-cv-modal-close]')) closeModal();
	});
	document.addEventListener('keydown', function (event) {
		if (!modal.hidden && event.key === 'Escape') closeModal();
	});

	form.addEventListener('submit', function (event) {
		event.preventDefault();
		var button = form.querySelector('button[type="submit"]');
		var popup = window.open('about:blank', '_blank');
		button.disabled = true;
		button.textContent = 'Aguarde…';
		error.hidden = true;
		clickPromise.then(function (registeredId) {
			recordId.value = recordId.value || registeredId || '';
			return send('leads_submit_whatsapp', {
				vehicle_id: form.querySelector('[name="vehicle_id"]').value,
				record_id: recordId.value,
				name: form.querySelector('[name="name"]').value,
				email: form.querySelector('[name="email"]').value,
				phone: form.querySelector('[name="phone"]').value
			});
		}).then(function (data) {
			if (popup) {
				popup.location.href = data.whatsapp_url;
				closeModal();
			} else {
				window.location.href = data.whatsapp_url;
			}
		}).catch(function (reason) {
			if (popup) popup.close();
			error.textContent = reason.message || 'Não foi possível enviar. Tente novamente.';
			error.hidden = false;
			button.disabled = false;
			button.textContent = 'Falar pelo WhatsApp';
		});
	});
}());
