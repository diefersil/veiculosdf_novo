(function () {
	'use strict';

	document.querySelectorAll('[data-mm-gallery]').forEach(function (gallery) {
		var slides = Array.prototype.slice.call(gallery.querySelectorAll('[data-gallery-open]'));
		var lightbox = document.querySelector('[data-mm-lightbox]');
		if (!slides.length || !lightbox) return;

		var image = lightbox.querySelector('[data-lightbox-image]');
		var caption = lightbox.querySelector('[data-lightbox-caption]');
		var galleryCounter = gallery.querySelector('[data-gallery-counter]');
		var lightboxCounter = lightbox.querySelector('[data-lightbox-counter]');
		var thumbnails = Array.prototype.slice.call(lightbox.querySelectorAll('[data-lightbox-thumb]'));
		var activeIndex = 0;
		var previousFocus = null;

		function setSlide(index) {
			activeIndex = (index + slides.length) % slides.length;
			slides.forEach(function (slide, slideIndex) {
				slide.hidden = slideIndex !== activeIndex;
				slide.classList.toggle('is-active', slideIndex === activeIndex);
			});
			if (galleryCounter) galleryCounter.textContent = (activeIndex + 1) + ' / ' + slides.length;

			var activeSlide = slides[activeIndex];
			if (image) {
				image.src = activeSlide.getAttribute('data-full-image');
				image.alt = activeSlide.querySelector('img').alt;
			}
			if (caption) caption.textContent = 'Foto ' + (activeIndex + 1) + ' de ' + slides.length;
			if (lightboxCounter) lightboxCounter.textContent = (activeIndex + 1) + ' / ' + slides.length;
			thumbnails.forEach(function (thumbnail, thumbIndex) {
				var selected = thumbIndex === activeIndex;
				thumbnail.classList.toggle('is-active', selected);
				thumbnail.setAttribute('aria-current', selected ? 'true' : 'false');
			});
		}

		function openLightbox(index) {
			previousFocus = document.activeElement;
			setSlide(index);
			lightbox.hidden = false;
			document.body.classList.add('mm-lightbox-open');
			lightbox.querySelector('[data-lightbox-close]').focus();
		}

		function closeLightbox() {
			lightbox.hidden = true;
			document.body.classList.remove('mm-lightbox-open');
			if (previousFocus) previousFocus.focus();
		}

		gallery.addEventListener('click', function (event) {
			var openButton = event.target.closest('[data-gallery-open]');
			var stepButton = event.target.closest('[data-gallery-step]');
			if (openButton) openLightbox(parseInt(openButton.getAttribute('data-gallery-open'), 10) || 0);
			if (stepButton) setSlide(activeIndex + parseInt(stepButton.getAttribute('data-gallery-step'), 10));
		});
		lightbox.addEventListener('click', function (event) {
			var stepButton = event.target.closest('[data-lightbox-step]');
			var thumbButton = event.target.closest('[data-lightbox-thumb]');
			if (event.target === lightbox || event.target.closest('[data-lightbox-close]')) closeLightbox();
			if (stepButton) setSlide(activeIndex + parseInt(stepButton.getAttribute('data-lightbox-step'), 10));
			if (thumbButton) setSlide(parseInt(thumbButton.getAttribute('data-lightbox-thumb'), 10) || 0);
		});
		document.addEventListener('keydown', function (event) {
			if (lightbox.hidden) return;
			if (event.key === 'Escape') closeLightbox();
			if (event.key === 'ArrowRight') setSlide(activeIndex + 1);
			if (event.key === 'ArrowLeft') setSlide(activeIndex - 1);
		});
		setSlide(0);
	});
}());
