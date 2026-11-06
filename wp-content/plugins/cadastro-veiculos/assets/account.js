(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		var panel = document.querySelector('.cv-account');
		if (!panel || !window.CVVehicleData) return;
		var selects = {};
		panel.querySelectorAll('.cv-account-term').forEach(function (select) {
			selects[select.getAttribute('data-taxonomy')] = select;
		});
		function value(key) { return selects[key] ? parseInt(selects[key].value || '0', 10) : 0; }
		function selected(select) {
			return Array.prototype.map.call(select.selectedOptions, function (option) { return parseInt(option.value, 10); });
		}
		function refresh(key, predicate) {
			var select = selects[key];
			if (!select) return;
			var old = selected(select);
			Array.prototype.slice.call(select.options).forEach(function (option) { if (option.value) option.remove(); });
			(window.CVVehicleData[key] || []).filter(predicate).forEach(function (term) {
				var option = document.createElement('option');
				option.value = String(term.id);
				option.textContent = term.name;
				option.selected = old.indexOf(term.id) !== -1;
				select.appendChild(option);
			});
		}
		function filter() {
			var type = value('tipo_veiculo');
			refresh('marca', function (term) { return !type || term.types.indexOf(type) !== -1; });
			var brand = value('marca');
			refresh('modelo', function (term) { return !!brand && term.parent === brand && (!type || term.types.indexOf(type) !== -1); });
			var model = value('modelo');
			refresh('versao', function (term) { return !!model && term.parent === model && (!type || term.types.indexOf(type) !== -1); });
			['estilo', 'opcionais'].forEach(function (key) {
				refresh(key, function (term) { return !type || term.types.indexOf(type) !== -1; });
			});
		}
		panel.addEventListener('change', function (event) {
			if (event.target.classList.contains('cv-account-term')) filter();
		});
		filter();
	});
}());
