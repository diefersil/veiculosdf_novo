(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		if (!window.CVHomeVehicleData) return;
		document.querySelectorAll('.cv-home-search, .cv-home-sell form').forEach(function (form) {
			var brand = form.querySelector('[data-taxonomy="marca"]');
			var model = form.querySelector('[data-taxonomy="modelo"]');
			if (!brand || !model) return;
			function filterModels() {
				var brandId = parseInt(brand.value || '0', 10);
				var current = model.value;
				Array.prototype.slice.call(model.options).forEach(function (option) { if (option.value) option.remove(); });
				(CVHomeVehicleData.modelo || []).filter(function (term) { return !brandId || term.parent === brandId; }).forEach(function (term) {
					var option = document.createElement('option');
					option.value = String(term.id);
					option.textContent = term.name;
					option.selected = current === option.value;
					model.appendChild(option);
				});
			}
			brand.addEventListener('change', filterModels);
			filterModels();
		});
	});
}());
