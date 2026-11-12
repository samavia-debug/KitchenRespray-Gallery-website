/* Respray Portfolio: gallery assistant chat panel. */
(function () {
	'use strict';

	var root = document.querySelector('[data-rp-assistant]');
	if (!root || !window.rpAssistant || !window.fetch) { return; }

	var openButton = root.querySelector('[data-rp-assistant-open]');
	var panel = root.querySelector('.rp-assistant-panel');
	var log = root.querySelector('[data-rp-assistant-log]');
	var form = root.querySelector('[data-rp-assistant-form]');
	var input = form.querySelector('input');
	var history = []; // {role, text}: kept only in this page, sent with each question for context.
	var busy = false;

	function toggle(open) {
		panel.hidden = !open;
		openButton.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (open) { input.focus(); } else { openButton.focus(); }
	}

	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) { node.className = className; }
		if (text) { node.textContent = text; }
		return node;
	}

	function addMessage(text, who) {
		var p = el('p', 'rp-assistant-msg rp-assistant-' + who, text);
		log.appendChild(p);
		log.scrollTop = log.scrollHeight;
		return p;
	}

	// Only same-site links and images returned by the server are shown.
	function sameSite(url) {
		try { return new URL(url, window.location.href).origin === window.location.origin; } catch (e) { return false; }
	}

	function addAnswer(data) {
		var wrap = el('div', 'rp-assistant-msg rp-assistant-bot');
		wrap.appendChild(el('p', '', data.reply || ''));
		if (data.cards && data.cards.length) {
			var cards = el('div', 'rp-assistant-cards');
			data.cards.forEach(function (card) {
				if (!sameSite(card.url)) { return; }
				var a = el('a');
				a.href = card.url;
				if (card.image && sameSite(card.image)) {
					var img = el('img');
					img.src = card.image;
					img.alt = card.title || '';
					img.loading = 'lazy';
					a.appendChild(img);
				}
				a.appendChild(el('span', '', card.title || 'View project'));
				cards.appendChild(a);
			});
			wrap.appendChild(cards);
		}
		if (data.link && data.link.url && sameSite(data.link.url)) {
			var link = el('a', 'rp-assistant-link', data.link.label || 'Open');
			link.href = data.link.url;
			wrap.appendChild(link);
		}
		log.appendChild(wrap);
		log.scrollTop = log.scrollHeight;
	}

	openButton.addEventListener('click', function () { toggle(panel.hidden); });
	root.querySelector('[data-rp-assistant-close]').addEventListener('click', function () { toggle(false); });
	root.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && !panel.hidden) { toggle(false); }
	});

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var text = input.value.trim();
		if (!text || busy) { return; }
		busy = true;
		input.value = '';
		addMessage(text, 'user');
		var waiting = addMessage('Looking…', 'bot');

		fetch(window.rpAssistant.endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ message: text, history: history.slice(-6) })
		})
			.then(function (response) {
				if (!response.ok) { throw new Error('Request failed'); }
				return response.json();
			})
			.then(function (data) {
				waiting.remove();
				addAnswer(data);
				history.push({ role: 'user', text: text });
				history.push({ role: 'assistant', text: data.reply || '' });
			})
			.catch(function () {
				waiting.textContent = 'Sorry, I could not answer just now. You can search all projects in the gallery.';
			})
			.then(function () { busy = false; });
	});
})();
