/* Respray Photos: small progressive enhancements. Every page works without this file. */
(function () {
	'use strict';

	var doc = document;

	/* ---------- Mobile menu ---------- */
	var navToggle = doc.querySelector('[data-rpt-nav-toggle]');
	if (navToggle) {
		navToggle.addEventListener('click', function () {
			var nav = doc.getElementById('rpt-nav');
			var open = nav.classList.toggle('is-open');
			navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	}

	/* ---------- Toast + copy link ---------- */
	var toastTimer;
	function toast(message) {
		var el = doc.querySelector('[data-rp-toast]');
		if (!el) { return; }
		el.textContent = message;
		el.hidden = false;
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { el.hidden = true; }, 2600);
	}

	function copyText(text) {
		if (navigator.clipboard && window.isSecureContext) {
			// Some browsers refuse the modern method (e.g. inside in-app browsers); fall back.
			return navigator.clipboard.writeText(text).catch(function () { return copyLegacy(text); });
		}
		return copyLegacy(text);
	}

	function copyLegacy(text) {
		return new Promise(function (resolve, reject) {
			var area = doc.createElement('textarea');
			area.value = text;
			area.setAttribute('readonly', '');
			area.style.position = 'fixed';
			area.style.opacity = '0';
			doc.body.appendChild(area);
			area.select();
			try { doc.execCommand('copy') ? resolve() : reject(); } catch (e) { reject(e); }
			doc.body.removeChild(area);
		});
	}

	doc.addEventListener('click', function (e) {
		var button = e.target.closest('[data-rp-copy]');
		if (!button) { return; }
		e.preventDefault();
		var url = button.getAttribute('data-rp-copy') || window.location.href;
		copyText(url).then(function () {
			toast('Link copied. Paste it into a message to share.');
		}, function () {
			window.prompt('Copy this link:', url);
		});
	});

	/* ---------- Gallery filters: update results and the address without a reload ---------- */
	var form = doc.querySelector('[data-rp-filters]');
	var order = ['colour', 'service', 'style', 'finish', 'material', 'brand'];

	function buildUrl() {
		var groups = {};
		form.querySelectorAll('input[type=checkbox][name$="[]"]:checked').forEach(function (box) {
			var name = box.name.slice(0, -2);
			(groups[name] = groups[name] || []).push(encodeURIComponent(box.value));
		});
		var parts = [];
		order.forEach(function (name) {
			if (groups[name]) { parts.push(name + '=' + groups[name].join(',')); }
		});
		var q = form.querySelector('[name=q]');
		if (q && q.value.trim()) { parts.push('q=' + encodeURIComponent(q.value.trim())); }
		var ba = form.querySelector('[name=ba]');
		if (ba && ba.checked) { parts.push('ba=1'); }
		var year = form.querySelector('[name=completed]');
		if (year && year.value) { parts.push('completed=' + encodeURIComponent(year.value)); }
		return form.action.split('?')[0] + (parts.length ? '?' + parts.join('&') : '');
	}

	function syncForm(fresh) {
		if (!fresh) { return; }
		fresh.querySelectorAll('input, select').forEach(function (input) {
			var selector = input.type === 'checkbox'
				? 'input[type=checkbox][name="' + input.name + '"][value="' + input.value + '"]'
				: '[name="' + input.name + '"]';
			var mine = form.querySelector(selector);
			if (!mine) { return; }
			if (input.type === 'checkbox') { mine.checked = input.checked; } else { mine.value = input.value; }
		});
		var freshGroups = fresh.querySelectorAll('details.rp-filter');
		form.querySelectorAll('details.rp-filter').forEach(function (details, i) {
			if (!freshGroups[i]) { return; }
			details.querySelector('summary').innerHTML = freshGroups[i].querySelector('summary').innerHTML;
			details.toggleAttribute('data-active', freshGroups[i].hasAttribute('data-active'));
		});
	}

	function loadResults(url, push) {
		var results = doc.getElementById('rp-results');
		if (!results || !window.fetch || !window.DOMParser) { window.location.href = url; return; }
		results.classList.add('is-loading');
		var finalUrl = url;
		fetch(url, { credentials: 'same-origin' })
			.then(function (response) {
				if (!response.ok) { throw new Error('Request failed'); }
				finalUrl = response.url || url; // A typed search is redirected to its filter address.
				return response.text();
			})
			.then(function (html) {
				var page = new DOMParser().parseFromString(html, 'text/html');
				var fresh = page.getElementById('rp-results');
				if (!fresh) { throw new Error('No results in response'); }
				results.replaceWith(fresh);
				syncForm(page.querySelector('[data-rp-filters]'));
				var heading = doc.querySelector('[data-rp-heading]');
				var freshHeading = page.querySelector('[data-rp-heading]');
				if (heading && freshHeading) { heading.textContent = freshHeading.textContent; }
				doc.title = page.title;
				if (push) { window.history.pushState({ rp: true }, '', finalUrl); }
			})
			.catch(function () { window.location.href = url; });
	}

	if (form && doc.getElementById('rp-results')) {
		form.addEventListener('change', function (e) {
			if (e.target.matches('input[type=checkbox], select')) { loadResults(buildUrl(), true); }
		});
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			loadResults(buildUrl(), true);
		});
		window.addEventListener('popstate', function () { loadResults(window.location.href, false); });

		doc.addEventListener('click', function (e) {
			// Filter chips and "Clear filters".
			var nav = e.target.closest('[data-rp-nav]');
			if (nav) {
				e.preventDefault();
				loadResults(nav.href, true);
				return;
			}
			// Close an open filter list when clicking elsewhere.
			form.querySelectorAll('details.rp-filter[open]').forEach(function (details) {
				if (!details.contains(e.target)) { details.removeAttribute('open'); }
			});
		});
	}

	/* ---------- Load more: add the next page of projects under the current ones ---------- */
	doc.addEventListener('click', function (e) {
		var more = e.target.closest('[data-rp-more]');
		if (!more || !window.fetch || !window.DOMParser) { return; }
		var grid = doc.querySelector('#rp-results [data-rp-grid]');
		var wrap = doc.querySelector('#rp-results [data-rp-more-wrap]');
		if (!grid || !wrap) { return; }
		e.preventDefault();
		more.textContent = 'Loading…';
		fetch(more.href, { credentials: 'same-origin' })
			.then(function (response) { return response.text(); })
			.then(function (html) {
				var page = new DOMParser().parseFromString(html, 'text/html');
				var freshGrid = page.querySelector('#rp-results [data-rp-grid]');
				var freshWrap = page.querySelector('#rp-results [data-rp-more-wrap]');
				if (!freshGrid) { throw new Error('No projects in response'); }
				while (freshGrid.firstElementChild) { grid.appendChild(freshGrid.firstElementChild); }
				var next = freshWrap ? freshWrap.querySelector('[data-rp-more]') : null;
				wrap.innerHTML = '';
				if (next) { wrap.appendChild(next); }
			})
			.catch(function () { window.location.href = more.href; });
	});

	/* ---------- Before / after slider ---------- */
	doc.addEventListener('input', function (e) {
		var slider = e.target.closest('[data-rp-ba]');
		if (slider && e.target.type === 'range') {
			slider.style.setProperty('--pos', e.target.value + '%');
		}
	});

	/* ---------- Videos: load the player only when asked ---------- */
	doc.addEventListener('click', function (e) {
		var play = e.target.closest('[data-rp-embed]');
		if (!play) { return; }
		e.preventDefault();
		var frame = doc.createElement('iframe');
		frame.src = play.getAttribute('data-rp-embed');
		frame.title = play.getAttribute('aria-label') || 'Video';
		frame.allow = 'autoplay; fullscreen; picture-in-picture';
		frame.allowFullscreen = true;
		play.replaceWith(frame);
	});

	/* ---------- Photo lightbox ---------- */
	var box = doc.querySelector('[data-rp-lightbox]');
	var links = [];
	var index = 0;
	var opener = null;

	function show(i) {
		index = (i + links.length) % links.length;
		var img = box.querySelector('img');
		var thumb = links[index].querySelector('img');
		img.src = links[index].href;
		img.alt = thumb ? thumb.alt : '';
		var many = links.length > 1;
		box.querySelector('[data-rp-lightbox-prev]').hidden = !many;
		box.querySelector('[data-rp-lightbox-next]').hidden = !many;
	}

	function closeBox() {
		box.hidden = true;
		box.querySelector('img').src = '';
		doc.documentElement.style.overflow = '';
		if (opener) { opener.focus(); }
	}

	if (box) {
		doc.addEventListener('click', function (e) {
			var link = e.target.closest('a[data-rp-zoom]');
			if (link && link.getAttribute('href')) {
				e.preventDefault();
				links = Array.prototype.slice.call(doc.querySelectorAll('a[data-rp-zoom]'));
				opener = link;
				box.hidden = false;
				doc.documentElement.style.overflow = 'hidden';
				show(links.indexOf(link));
				box.querySelector('[data-rp-lightbox-close]').focus();
				return;
			}
			if (box.hidden) { return; }
			if (e.target.closest('[data-rp-lightbox-prev]')) { show(index - 1); }
			else if (e.target.closest('[data-rp-lightbox-next]')) { show(index + 1); }
			else if (e.target.closest('[data-rp-lightbox-close]') || e.target === box) { closeBox(); }
		});
		doc.addEventListener('keydown', function (e) {
			if (box.hidden) { return; }
			if (e.key === 'Escape') { closeBox(); }
			if (e.key === 'ArrowLeft') { show(index - 1); }
			if (e.key === 'ArrowRight') { show(index + 1); }
		});
	}
})();
