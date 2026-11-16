(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		if (!window.CVHomeVehicleData) return;
		var brands = (CVHomeVehicleData.marca || []).filter(function (term) { return term.count > 0; });
		var models = CVHomeVehicleData.modelo || [];
		var versions = CVHomeVehicleData.versao || [];

		function startsWithModel(name, prefix) {
			if (name.toLocaleLowerCase('pt-BR').indexOf(prefix.toLocaleLowerCase('pt-BR')) !== 0) return false;
			var suffix = name.slice(prefix.length);
			return !suffix || /^[\s\p{P}]/u.test(suffix);
		}

		function rootModel(term) {
			return models.filter(function (candidate) {
				return candidate.parent === term.parent && startsWithModel(term.name, candidate.name);
			}).sort(function (a, b) { return a.name.length - b.name.length; })[0] || term;
		}

		function familyTerms(root) {
			return models.filter(function (term) { return term.parent === root.parent && startsWithModel(term.name, root.name); });
		}

		function familyHasVehicles(root) {
			return familyTerms(root).some(function (term) { return term.count > 0; });
		}

		function addOption(select, value, label, selected) {
			var option = document.createElement('option');
			option.value = String(value);
			option.textContent = label;
			option.selected = selected;
			select.appendChild(option);
		}

		document.querySelectorAll('.cv-home-search, .cv-home-sell form, .mm-archive-search').forEach(function (form) {
			var brand = form.querySelector('[data-taxonomy="marca"]');
			var model = form.querySelector('[data-taxonomy="modelo"]');
			var version = form.querySelector('[data-taxonomy="versao"]');
			if (!brand || !model) return;
			var useModelFamilies = form.matches('.cv-home-search, .mm-archive-search');

			function selectedRoot() {
				if (model.value.indexOf('family:') === 0) {
					var rootId = parseInt(model.value.slice(7), 10);
					return models.filter(function (term) { return term.id === rootId; })[0] || null;
				}
				var selectedId = parseInt(model.value || '0', 10);
				var selectedTerm = models.filter(function (term) { return term.id === selectedId; })[0];
				return selectedTerm ? rootModel(selectedTerm) : null;
			}

			function filterVersions() {
				if (!version) return;
				var current = version.value;
				Array.prototype.slice.call(version.options).forEach(function (option) { if (option.value) option.remove(); });
				var root = useModelFamilies ? selectedRoot() : null;
				var selectedModelId = useModelFamilies ? 0 : parseInt(model.value || '0', 10);
				var brandId = parseInt(brand.value || '0', 10);
				var allowedModelIds = root ? familyTerms(root).map(function (term) { return term.id; }) : [];
				if (!root && !selectedModelId && brandId) {
					allowedModelIds = models.filter(function (term) { return term.parent === brandId; }).map(function (term) { return term.id; });
				}
				versions.filter(function (term) {
					return root ? allowedModelIds.indexOf(term.parent) !== -1 : (selectedModelId ? term.parent === selectedModelId : (!brandId || allowedModelIds.indexOf(term.parent) !== -1));
				}).forEach(function (term) {
					addOption(version, term.id, term.name, current === String(term.id));
				});
			}

			function filterModelFamilies() {
				var currentRoot = selectedRoot();
				var currentValue = currentRoot ? 'family:' + currentRoot.id : '';
				var brandId = parseInt(brand.value || '0', 10);
				var flatList = form.matches('.cv-home-search');
				Array.prototype.slice.call(model.children).forEach(function (child) {
					if (child.value || child.tagName === 'OPTGROUP') child.remove();
				});
				brands.forEach(function (brandTerm) {
					if (brandId && brandId !== brandTerm.id) return;
					var familyRoots = models.filter(function (term) {
						return term.parent === brandTerm.id && rootModel(term).id === term.id && familyHasVehicles(term);
					});
					if (!familyRoots.length) return;
					if (flatList) {
						familyRoots.forEach(function (root) {
							addOption(model, 'family:' + root.id, root.name, currentValue === 'family:' + root.id);
						});
						return;
					}
					var group = document.createElement('optgroup');
					group.label = brandTerm.name;
					familyRoots.forEach(function (root) {
						addOption(group, 'family:' + root.id, root.name, currentValue === 'family:' + root.id);
					});
					model.appendChild(group);
				});
				var ungrouped = models.filter(function (term) {
					return !brands.some(function (brandTerm) { return brandTerm.id === term.parent; }) && rootModel(term).id === term.id && familyHasVehicles(term) && (!brandId || term.parent === brandId);
				});
				ungrouped.forEach(function (root) { addOption(model, 'family:' + root.id, root.name, currentValue === 'family:' + root.id); });
				filterVersions();
			}

			function filterExactModels() {
				var brandId = parseInt(brand.value || '0', 10);
				var current = model.value;
				Array.prototype.slice.call(model.options).forEach(function (option) { if (option.value) option.remove(); });
				models.filter(function (term) { return !brandId || term.parent === brandId; }).forEach(function (term) {
					addOption(model, term.id, term.name, current === String(term.id));
				});
			}

			function updateModels() {
				if (useModelFamilies) filterModelFamilies();
				else filterExactModels();
			}
			brand.addEventListener('change', updateModels);
			model.addEventListener('change', filterVersions);
			updateModels();
		});
	});
}());
