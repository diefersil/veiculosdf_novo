(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		var panel = document.querySelector('.cv-account');
		if (!panel || !window.CVVehicleData) return;
		var selects = {};
		panel.querySelectorAll('.cv-account-term.cv-term-select').forEach(function (select) {
			selects[select.getAttribute('data-taxonomy')] = select;
		});
		function value(key) {
			if (key === 'tipo_veiculo') {
				var type = panel.querySelector('input[type="radio"][data-taxonomy="tipo_veiculo"]:checked');
				return type ? parseInt(type.value, 10) : 0;
			}
			return selects[key] ? parseInt(selects[key].value || '0', 10) : 0;
		}
		function selectedName(key) {
			var select = selects[key];
			return select && select.selectedOptions.length ? select.selectedOptions[0].textContent.trim() : '';
		}
		function syncTitle() {
			var title = panel.querySelector('input[name="cv_account_title"]');
			var manufactureYear = panel.querySelector('[name="cv_account_fields[ano_fabricacao]"]');
			if (!title) return;
			var parts = [selectedName('marca'), selectedName('modelo'), manufactureYear ? manufactureYear.value : ''].filter(Boolean);
			title.value = parts.join(' ');
		}
		function selected(select) {
			return Array.prototype.map.call(select.selectedOptions, function (option) { return parseInt(option.value, 10); });
		}
		function refresh(key, predicate) {
			var select = selects[key];
			if (!select) return;
			var old = selected(select);
			Array.prototype.slice.call(select.options).forEach(function (option) { if (option.value) option.remove(); });
			var search = key === 'versao' ? (panel.querySelector('[data-search-taxonomy="versao"]') || {}).value || '' : '';
			search = search.trim().toLocaleLowerCase('pt-BR');
			(window.CVVehicleData[key] || []).filter(function (term) {
				return predicate(term) && (!search || term.name.toLocaleLowerCase('pt-BR').indexOf(search) !== -1);
			}).forEach(function (term) {
				var option = document.createElement('option');
				option.value = String(term.id);
				option.textContent = term.name;
				option.selected = old.indexOf(term.id) !== -1;
				select.appendChild(option);
			});
		}
		function refreshOptionalTypes(type) {
			var list = panel.querySelector('.cv-account-option-list[data-taxonomy="opcionais"]');
			if (!list) return;
			var terms = window.CVVehicleData.opcionais || [];
			list.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
				var term = terms.filter(function (item) { return item.id === parseInt(checkbox.value, 10); })[0];
				var allowed = !!term && (!type || term.types.indexOf(type) !== -1);
				checkbox.disabled = !allowed;
				checkbox.closest('.cv-account-option').hidden = !allowed;
				if (!allowed) checkbox.checked = false;
			});
		}
		function filter() {
			var type = value('tipo_veiculo');
			refresh('marca', function (term) { return !type || term.types.indexOf(type) !== -1; });
			var brand = value('marca');
			refresh('modelo', function (term) { return !!brand && term.parent === brand && (!type || term.types.indexOf(type) !== -1); });
			var model = value('modelo');
			refresh('versao', function (term) { return !!model && term.parent === model && (!type || term.types.indexOf(type) !== -1); });
			refresh('estilo', function (term) { return !type || term.types.indexOf(type) !== -1; });
			refreshOptionalTypes(type);
			syncTitle();
		}
		panel.addEventListener('change', function (event) {
			if (event.target.matches('.cv-account-term')) filter();
		});
		var versionSearch = panel.querySelector('[data-search-taxonomy="versao"]');
		if (versionSearch) versionSearch.addEventListener('input', filter);
		var manufactureYear = panel.querySelector('[name="cv_account_fields[ano_fabricacao]"]');
		if (manufactureYear) manufactureYear.addEventListener('change', syncTitle);
		filter();
	});
}());
