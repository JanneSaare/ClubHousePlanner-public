(function () {
	'use strict';

	var cfg = window.chpCalendar || {};
	var i18n = cfg.i18n || {};
	var startOfWeek = typeof cfg.startOfWeek === 'number' ? cfg.startOfWeek : 1;
	var locale = cfg.locale || undefined;
	var slots = cfg.slots || {};
	var slotOrder = Object.keys(slots);

	function pad(n) {
		return (n < 10 ? '0' : '') + n;
	}

	function ymd(d) {
		return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
	}

	function el(tag, className, text) {
		var node = document.createElement(tag);
		if (className) node.className = className;
		if (text !== undefined) node.textContent = text;
		return node;
	}

	function fetchBookings(from, to, item) {
		var url = new URL(cfg.restUrl, window.location.href);
		url.searchParams.set('start', ymd(from));
		url.searchParams.set('end', ymd(to));
		if (item) url.searchParams.set('item', item);
		return fetch(url.toString(), { credentials: 'same-origin' }).then(function (res) {
			if (!res.ok) throw new Error('HTTP ' + res.status);
			return res.json();
		});
	}

	// Pre-fill the booking form of the same item (if present on the page) when a future day is clicked.
	function selectDay(date, item) {
		var form = item ? document.querySelector('.chp-form[data-item="' + item + '"]') : null;
		var scope = form || document;
		var input = scope.querySelector('[name="chp_date"]');
		if (input) {
			input.value = ymd(date);
		} else {
			// Free-time form: set the date and keep a time that was already chosen.
			input = scope.querySelector('[name="chp_start"]');
			if (!input) return;
			input.value = ymd(date) + 'T' + (input.value.slice(11, 16) || '10:00');
			input.dispatchEvent(new Event('change', { bubbles: true }));
		}
		// Keep the calendar where it is: only scroll if the field is not on screen.
		var box = input.getBoundingClientRect();
		var visible = box.top >= 0 && box.bottom <= (window.innerHeight || document.documentElement.clientHeight);
		if (!visible) input.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		input.focus({ preventScroll: true });
		input.classList.remove('chp-flash');
		void input.offsetWidth; // Restart the highlight if it is still running.
		input.classList.add('chp-flash');
	}

	function init(root) {
		var current = new Date();
		current.setDate(1);
		current.setHours(0, 0, 0, 0);

		var header = el('div', 'chp-cal__header');
		var prev = el('button', 'chp-cal__nav', '‹');
		var next = el('button', 'chp-cal__nav', '›');
		var title = el('h3', 'chp-cal__title');
		prev.type = next.type = 'button';
		prev.setAttribute('aria-label', i18n.prev || 'Previous');
		next.setAttribute('aria-label', i18n.next || 'Next');
		header.append(prev, title, next);

		var grid = el('div', 'chp-cal__grid');
		var status = el('p', 'chp-cal__status');
		root.append(header, grid, status);

		prev.addEventListener('click', function () {
			current.setMonth(current.getMonth() - 1);
			render();
		});
		next.addEventListener('click', function () {
			current.setMonth(current.getMonth() + 1);
			render();
		});

		function render() {
			var year = current.getFullYear();
			var month = current.getMonth();
			var first = new Date(year, month, 1);
			var last = new Date(year, month + 1, 0);
			var today = new Date();
			today.setHours(0, 0, 0, 0);

			title.textContent = first.toLocaleDateString(locale, { month: 'long', year: 'numeric' });
			status.textContent = '';

			fetchBookings(first, last, root.dataset.item)
				.then(function (bookings) {
					draw(bookings);
				})
				.catch(function () {
					draw([]);
					status.textContent = i18n.loadError || 'Error';
				});

			function draw(bookings) {
				var byDate = {};
				bookings.forEach(function (b) {
					(byDate[b.date] = byDate[b.date] || []).push(b);
				});
				Object.keys(byDate).forEach(function (d) {
					byDate[d].sort(function (a, b) {
						// Blocked days first, then the free-unit count, then the bookings.
						var rank = { unavailable: 0, availability: 1 };
						var ra = rank[a.status] === undefined ? 2 : rank[a.status];
						var rb = rank[b.status] === undefined ? 2 : rank[b.status];
						if (ra !== rb) return ra - rb;
						return slotOrder.indexOf(a.slot) - slotOrder.indexOf(b.slot);
					});
				});

				grid.textContent = '';

				for (var w = 0; w < 7; w++) {
					// 2023-01-01 was a Sunday (weekday 0).
					var wd = new Date(2023, 0, 1 + ((startOfWeek + w) % 7));
					grid.append(el('div', 'chp-cal__weekday', wd.toLocaleDateString(locale, { weekday: 'short' })));
				}

				var offset = (first.getDay() - startOfWeek + 7) % 7;
				for (var i = 0; i < offset; i++) {
					grid.append(el('div', 'chp-cal__day chp-cal__day--empty'));
				}

				for (var day = 1; day <= last.getDate(); day++) {
					var dayStart = new Date(year, month, day);
					var cell = el('div', 'chp-cal__day');
					cell.append(el('span', 'chp-cal__date', String(day)));

					if (dayStart < today) cell.classList.add('chp-cal__day--past');
					if (dayStart.getTime() === today.getTime()) cell.classList.add('chp-cal__day--today');

					(byDate[ymd(dayStart)] || []).forEach(function (b) {
						if (b.status === 'availability') {
							// Items with several units: how many are free at the busiest moment of the day.
							var label = (i18n.free || '{free}/{total}').replace('{free}', b.free).replace('{total}', b.total);
							var avail = el('span', 'chp-cal__event chp-cal__event--availability' + (b.free === 0 ? ' chp-cal__event--full-day' : ''), label);
							avail.title = label;
							cell.append(avail);
							return;
						}
						if (b.status === 'unavailable') {
							var text = b.text || i18n.unavailable || 'Not available';
							var blocked = el('span', 'chp-cal__event chp-cal__event--unavailable', text);
							blocked.title = text;
							cell.classList.add('chp-cal__day--unavailable');
							cell.append(blocked);
							return;
						}
						// Free-time bookings have no booking type; they show their hours instead.
						var slot = b.slot === 'free' ? { label: b.start + '–' + b.end } : (slots[b.slot] || { label: b.slot });
						var statusLabel = b.status === 'booked' ? i18n.booked : i18n.preliminary;
						var tag = el('span', 'chp-cal__event chp-cal__event--' + b.slot + ' chp-cal__event--' + b.status, slot.label);
						tag.title = slot.label + ' (' + statusLabel + ')';
						cell.append(tag);
					});

					if (dayStart >= today) {
						cell.tabIndex = 0;
						cell.setAttribute('role', 'button');
						(function (date) {
							cell.addEventListener('click', function () {
								selectDay(date, root.dataset.item);
							});
							cell.addEventListener('keydown', function (e) {
								if (e.key === 'Enter' || e.key === ' ') {
									e.preventDefault();
									selectDay(date, root.dataset.item);
								}
							});
						})(dayStart);
					}

					grid.append(cell);
				}
			}
		}

		render();
	}

	document.querySelectorAll('.chp-calendar').forEach(init);
})();
