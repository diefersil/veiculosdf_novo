(function () {
	'use strict';

	function optionForTerm(term, selected) {
		var option = document.createElement('option');
		option.value = String(term.id);
		option.textContent = term.name;
		option.selected = selected;
		return option;
	}

	function selectedValue(select) {
		return select ? parseInt(select.value || '0', 10) : 0;
	}

	function selectedValues(select) {
		return select ? Array.prototype.map.call(select.selectedOptions, function (option) { return parseInt(option.value, 10); }) : [];
	}

	function filterVehicleFields() {
		var box = document.getElementById('cv_vehicle_data');
		if (!box || !window.CVVehicleData) return;
		var typeSelect = box.querySelector('[data-taxonomy="tipo_veiculo"]');
		var brandSelect = box.querySelector('[data-taxonomy="marca"]');
		var modelSelect = box.querySelector('[data-taxonomy="modelo"]');
		var versionSelect = box.querySelector('[data-taxonomy="versao"]');
		var typeId = selectedValue(typeSelect);
		var brandId = selectedValue(brandSelect);
		var modelId = selectedValue(modelSelect);

		function refresh(select, taxonomy, predicate) {
			if (!select) return;
			var old = selectedValues(select);
			var data = window.CVVehicleData[taxonomy] || [];
			while (select.options.length > (select.multiple ? 0 : 1)) select.remove(select.multiple ? 0 : 1);
			data.filter(predicate).forEach(function (term) {
				select.appendChild(optionForTerm(term, old.indexOf(term.id) !== -1));
			});
		}

		refresh(brandSelect, 'marca', function (term) { return !typeId || term.types.indexOf(typeId) !== -1; });
		brandId = selectedValue(brandSelect);
		refresh(modelSelect, 'modelo', function (term) {
			return !!brandId && (!typeId || term.types.indexOf(typeId) !== -1) && term.parent === brandId;
		});
		modelId = selectedValue(modelSelect);
		refresh(versionSelect, 'versao', function (term) {
			return !!modelId && (!typeId || term.types.indexOf(typeId) !== -1) && term.parent === modelId;
		});
		['estilo', 'opcionais'].forEach(function (taxonomy) {
			var select = box.querySelector('[data-taxonomy="' + taxonomy + '"]');
			refresh(select, taxonomy, function (term) { return !typeId || term.types.indexOf(typeId) !== -1; });
		});
	}

	function filterTermParentOptions() {
		var parentSelect = document.getElementById('cv_parent_term');
		var typeSelect = document.getElementById('cv_term_types');
		if (!parentSelect || !typeSelect || !window.CVVehicleData) return;
		var taxonomy = parentSelect.getAttribute('data-parent-taxonomy');
		var types = selectedValues(typeSelect);
		var selected = parentSelect.value;
		var known = {};
		(window.CVVehicleData[taxonomy] || []).forEach(function (term) { known[String(term.id)] = term; });
		Array.prototype.forEach.call(parentSelect.options, function (option) {
			if (!option.value) return;
			var parent = known[option.value];
			var allowed = !types.length || (parent && types.every(function (typeId) { return parent.types.indexOf(typeId) !== -1; }));
			option.hidden = !allowed;
			option.disabled = !allowed;
		});
		if (selected && parentSelect.options[parentSelect.selectedIndex].disabled) parentSelect.value = '';
	}

	function syncGallery() {
		var list = document.getElementById('cv_gallery_list');
		if (!list) return;
		var ids = Array.prototype.map.call(list.querySelectorAll('.cv-gallery-item'), function (item) { return item.getAttribute('data-id'); });
		document.getElementById('cv_gallery_ids').value = ids.join(',');
		var count = document.querySelector('.cv-gallery-count');
		if (count) count.textContent = ids.length + ' / 12';
	}

	function addGalleryItem(image) {
		var list = document.getElementById('cv_gallery_list');
		var id = String(image.id);
		if (list.querySelector('[data-id="' + id + '"]')) return;
		var item = document.createElement('li');
		item.className = 'cv-gallery-item';
		item.setAttribute('data-id', id);
		var drag = document.createElement('span');
		drag.className = 'cv-gallery-drag';
		drag.textContent = '↕';
		drag.setAttribute('aria-label', 'Arrastar para ordenar');
		var img = document.createElement('img');
		img.src = image.sizes && image.sizes.thumbnail ? image.sizes.thumbnail.url : image.url;
		img.alt = '';
		var remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'button-link-delete cv-gallery-remove';
		remove.textContent = 'Remover';
		item.appendChild(drag);
		item.appendChild(img);
		item.appendChild(remove);
		list.appendChild(item);
	}

	document.addEventListener('DOMContentLoaded', function () {
		var box = document.getElementById('cv_vehicle_data');
		if (box) {
			filterVehicleFields();
			box.addEventListener('change', function (event) {
				if (event.target.matches('.cv-term-select')) filterVehicleFields();
			});
		}
		filterTermParentOptions();
		document.addEventListener('change', function (event) {
			if (event.target.matches('#cv_term_types')) filterTermParentOptions();
		});

		var gallery = document.getElementById('cv_gallery_list');
		if (gallery && window.jQuery && jQuery.fn.sortable) {
			jQuery(gallery).sortable({ handle: '.cv-gallery-drag', update: syncGallery });
		}
		var addGalleryButton = document.getElementById('cv_gallery_add');
		if (addGalleryButton) {
			addGalleryButton.addEventListener('click', function (event) {
				event.preventDefault();
				if (!window.wp || !wp.media) return;
				var list = document.getElementById('cv_gallery_list');
				var frame = wp.media({ title: 'Selecionar fotos do veículo', button: { text: 'Usar fotos selecionadas' }, multiple: true, library: { type: 'image' } });
				var selection = frame.state().get('selection');
				Array.prototype.forEach.call(list.querySelectorAll('.cv-gallery-item'), function (item) {
					var attachment = wp.media.attachment(parseInt(item.getAttribute('data-id'), 10));
					attachment.fetch();
					selection.add(attachment);
				});
				frame.on('select', function () {
					var images = frame.state().get('selection').toJSON();
					var current = Array.prototype.map.call(list.querySelectorAll('.cv-gallery-item'), function (item) { return item.getAttribute('data-id'); });
					var unique = [];
					images.forEach(function (image) { if (unique.indexOf(String(image.id)) === -1) unique.push(String(image.id)); });
					list.innerHTML = '';
					unique.slice(0, 12).forEach(function (id) {
						var image = images.filter(function (entry) { return String(entry.id) === id; })[0];
						if (image) addGalleryItem(image);
					});
					if (unique.length > 12) window.alert('A galeria aceita no máximo 12 imagens.');
					if (window.jQuery && jQuery.fn.sortable) jQuery(list).sortable('refresh');
					syncGallery();
				});
				frame.open();
			});
		}
		document.addEventListener('click', function (event) {
			if (event.target.matches('.cv-gallery-remove')) {
				event.preventDefault();
				event.target.closest('.cv-gallery-item').remove();
				syncGallery();
			}
		});

		document.addEventListener('click', function (event) {
			if (event.target.matches('.cv-upload-term-image')) {
				event.preventDefault();
				if (!window.wp || !wp.media) return;
				var container = event.target.closest('.form-field, .form-table tr');
				var frame = wp.media({ title: 'Escolher imagem do termo', button: { text: 'Usar imagem' }, multiple: false, library: { type: 'image' } });
				frame.on('select', function () {
					var item = frame.state().get('selection').first().toJSON();
					container.querySelector('.cv-term-image-id').value = item.id;
					container.querySelector('.cv-term-image-preview').innerHTML = '<img src="' + item.url.replace(/&/g, '&amp;').replace(/"/g, '&quot;') + '" alt="" style="max-width:100px;height:auto">';
				});
				frame.open();
			}
			if (event.target.matches('.cv-remove-term-image')) {
				event.preventDefault();
				var container = event.target.closest('.form-field, .form-table tr');
				container.querySelector('.cv-term-image-id').value = '';
				container.querySelector('.cv-term-image-preview').innerHTML = '';
			}
		});
	});
}());
