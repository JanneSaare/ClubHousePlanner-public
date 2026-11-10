(function () {
	'use strict';

	var cfg = window.chpForm || { streets: [], addresses: [] };
	var select = document.getElementById('chp_street');
	var wrap = document.querySelector('.chp-street-other');
	var input = document.getElementById('chp_street_other');
	var number = document.getElementById('chp_street_no');

	function update() {
		var other = select.value === cfg.otherStreet;
		wrap.hidden = !other;
		input.required = other;
		if (!other) input.value = '';
	}

	if (select && wrap && input) {
		select.addEventListener('change', update);
		update();
	}

	// House rules: open the rules page in a pop-up instead of leaving the form.
	var rulesLink = document.querySelector('.chp-rules-link');
	var i18n = cfg.i18n || {};

	function openRules(event) {
		if (typeof HTMLDialogElement === 'undefined' || !cfg.rulesUrl) return;
		event.preventDefault();

		var dialog = document.createElement('dialog');
		dialog.className = 'chp-modal';
		var close = document.createElement('button');
		close.type = 'button';
		close.className = 'chp-modal__close';
		close.textContent = '\u00d7';
		close.setAttribute('aria-label', i18n.close || 'Close');
		var title = document.createElement('h2');
		title.className = 'chp-modal__title';
		title.textContent = rulesLink.textContent;
		var body = document.createElement('div');
		body.className = 'chp-modal__body';
		body.textContent = i18n.loading || '';
		dialog.appendChild(close);
		dialog.appendChild(title);
		dialog.appendChild(body);
		document.body.appendChild(dialog);

		close.addEventListener('click', function () { dialog.close(); });
		dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
		dialog.addEventListener('close', function () { dialog.remove(); });
		dialog.showModal();

		fetch(cfg.rulesUrl)
			.then(function (res) { if (!res.ok) throw new Error(res.status); return res.json(); })
			.then(function (data) {
				title.textContent = data.title || title.textContent;
				body.innerHTML = data.content;
			})
			.catch(function () { body.textContent = i18n.loadError || ''; });
	}

	if (rulesLink) rulesLink.addEventListener('click', openRules);

	// Validation: show clear messages next to each invalid field instead of the browser's single tooltip.
	var form = document.querySelector('.chp-form');

	function labelFor(field) {
		if (field.type === 'radio') {
			var legend = document.getElementById('chp-slot-label');
			return legend ? legend.textContent.replace(/\s*\*\s*$/, '').trim() : '';
		}
		var label = field.id && document.querySelector('label[for="' + field.id + '"]');
		var node = label || field.closest('label');
		if (!node) return '';
		var clone = node.cloneNode(true);
		Array.prototype.forEach.call(clone.querySelectorAll('input, select, textarea'), function (el) { el.remove(); });
		return clone.textContent.replace(/\s*\*\s*$/, '').trim();
	}

	function messageFor(field) {
		var v = field.validity;
		if (v.valueMissing) {
			if (field.type === 'radio') return i18n.chooseOption || 'Please choose an option.';
			if (field.type === 'checkbox') return i18n.mustAccept || 'You must accept this to continue.';
			if (field.tagName === 'SELECT') return i18n.selectRequired || 'Please choose an option.';
			return i18n.required || 'Please fill in this field.';
		}
		if (v.typeMismatch && field.type === 'email') return i18n.invalidEmail || 'Please enter a valid email address.';
		if (v.patternMismatch) return field.title || i18n.invalidFormat || 'Please check the format.';
		if (v.rangeUnderflow) return i18n.pastDate || 'Please choose a date that is today or later.';
		if (v.badInput) return i18n.invalidFormat || 'Please check the format.';
		return field.validationMessage;
	}

	function errorHolder(field) {
		return field.type === 'radio' ? field.closest('[role="radiogroup"]') : field.closest('p') || field.parentNode;
	}

	function clearError(field) {
		var holder = errorHolder(field);
		var existing = holder && holder.querySelector('.chp-error');
		if (existing) existing.remove();
		holder && holder.classList.remove('chp-invalid');
		field.removeAttribute('aria-invalid');
		field.removeAttribute('aria-describedby');
	}

	function showError(field) {
		clearError(field);
		var holder = errorHolder(field);
		var msg = document.createElement('span');
		msg.className = 'chp-error';
		msg.id = 'chp-err-' + (field.id || field.name);
		msg.textContent = messageFor(field);
		holder.appendChild(msg);
		holder.classList.add('chp-invalid');
		field.setAttribute('aria-invalid', 'true');
		field.setAttribute('aria-describedby', msg.id);
		return msg.textContent;
	}

	function removeSummary() {
		var old = form.querySelector('.chp-error-summary');
		if (old) old.remove();
	}

	function validateForm(event) {
		removeSummary();
		var invalid = [];
		var seenRadios = {};
		Array.prototype.forEach.call(form.elements, function (field) {
			if (!field.willValidate || field.type === 'hidden') return;
			if (field.type === 'radio') {
				if (seenRadios[field.name]) return;
				seenRadios[field.name] = true;
			}
			if (field.checkValidity()) {
				clearError(field);
			} else {
				invalid.push({ field: field, message: showError(field) });
			}
		});
		if (!invalid.length) return;

		event.preventDefault();
		var summary = document.createElement('div');
		summary.className = 'chp-notice chp-notice--error chp-error-summary';
		summary.setAttribute('role', 'alert');
		summary.tabIndex = -1;
		var heading = document.createElement('strong');
		heading.textContent = i18n.summary || 'Please correct the following before sending:';
		var list = document.createElement('ul');
		invalid.forEach(function (item) {
			var li = document.createElement('li');
			var link = document.createElement('a');
			link.href = '#' + (item.field.id || '');
			link.textContent = (labelFor(item.field) || item.field.name) + ': ' + item.message;
			link.addEventListener('click', function (e) { e.preventDefault(); item.field.focus(); });
			li.appendChild(link);
			list.appendChild(li);
		});
		summary.appendChild(heading);
		summary.appendChild(list);
		form.insertBefore(summary, form.firstChild);
		summary.scrollIntoView({ behavior: 'smooth', block: 'center' });
		summary.focus({ preventScroll: true });
	}

	if (form) {
		form.noValidate = true; // Only when JS is available; otherwise native validation still applies.
		form.addEventListener('submit', validateForm);
		// Clear/refresh a field's error as soon as the user fixes it.
		['input', 'change'].forEach(function (type) {
			form.addEventListener(type, function (e) {
				var field = e.target;
				if (!field.willValidate || !field.classList) return;
				var holder = errorHolder(field);
				if (holder && holder.classList.contains('chp-invalid') && field.checkValidity()) clearError(field);
			});
		});
	}

	// Same rules as CHP_Settings::is_local_address() in PHP.
	function normalize(address) {
		return address.replace(/\s+/g, ' ').trim().toLowerCase().replace(/(\d) (\p{L})$/u, '$1$2');
	}

	function escapeRegExp(text) {
		return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	}

	function isLocal(address) {
		var normalized = normalize(address);
		var found = cfg.addresses.some(function (local) { return normalize(local) === normalized; });
		return found || cfg.streets.some(function (street) {
			return new RegExp('^\\s*' + escapeRegExp(street) + '(?=[\\s\\d,]|$)', 'iu').test(address);
		});
	}

	// Returns true (standard fee), false (other address) or null (address not complete yet).
	function addressState() {
		if (!number) return null;
		var street = select && select.value !== cfg.otherStreet ? select.value : (input ? input.value : '');
		var match = /^(\d{1,5})\s?(\p{L}?)$/u.exec(number.value.trim());
		street = street.trim();
		if (!street || !match) return null;
		return isLocal(street + ' ' + match[1] + match[2].toUpperCase());
	}

	var fees = document.querySelectorAll('.chp-slot__fee');

	function updateFees() {
		var state = addressState();
		Array.prototype.forEach.call(fees, function (el) {
			el.hidden = state === null;
			el.textContent = state === false ? cfg.contact : el.dataset.fee;
		});
	}

	[select, input, number].forEach(function (el) {
		if (el) {
			el.addEventListener('input', updateFees);
			el.addEventListener('change', updateFees);
		}
	});
	updateFees();
})();
