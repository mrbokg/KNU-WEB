/**
 * KNU cart store (contract C5).
 * localStorage key "knu_cart_v1": [{ slug, color, size, qty }].
 * Same slug + color + size are merged. Every change dispatches "knu:cart" on document.
 */
(function () {
	'use strict';

	var KEY = 'knu_cart_v1';
	var MAX_QTY = 10;
	var MAX_LINES = 20;
	var K = window.KNU || {};
	var P = K.products || {};
	var hasCatalog = Object.keys(P).length > 0;

	function keyOf(it) {
		return [it.slug, it.color || '', it.size || ''].join('|');
	}

	function clampQty(q) {
		q = parseInt(q, 10);
		if (!isFinite(q) || q < 1) return 0;
		return Math.min(q, MAX_QTY);
	}

	function isValid(it) {
		if (!it || typeof it.slug !== 'string' || !it.slug) return false;
		if (!hasCatalog) return true;
		var p = P[it.slug];
		if (!p) return false;
		var colors = p.colors || {};
		if (Object.keys(colors).length && !colors[it.color]) return false;
		var sizes = p.sizes || [];
		if (sizes.length) return sizes.indexOf(it.size) !== -1;
		return !it.size;
	}

	function sanitize(list) {
		var out = [];
		var index = {};
		(Array.isArray(list) ? list : []).forEach(function (raw) {
			if (!raw) return;
			var it = {
				slug: String(raw.slug || ''),
				color: String(raw.color || ''),
				size: String(raw.size || ''),
				qty: clampQty(raw.qty)
			};
			if (!it.qty || !isValid(it)) return;
			var k = keyOf(it);
			if (index[k] !== undefined) {
				out[index[k]].qty = Math.min(out[index[k]].qty + it.qty, MAX_QTY);
			} else if (out.length < MAX_LINES) {
				index[k] = out.length;
				out.push(it);
			}
		});
		return out;
	}

	function read() {
		try {
			var raw = window.localStorage.getItem(KEY);
			return sanitize(raw ? JSON.parse(raw) : []);
		} catch (e) {
			return [];
		}
	}

	var items = read();

	function persist() {
		try {
			window.localStorage.setItem(KEY, JSON.stringify(items));
		} catch (e) {
			/* storage unavailable: cart still works for this page view */
		}
	}

	function copy() {
		return items.map(function (it) {
			return { slug: it.slug, color: it.color, size: it.size, qty: it.qty };
		});
	}

	function priceOf(slug) {
		var p = P[slug];
		return p ? parseInt(p.price, 10) || 0 : 0;
	}

	function count() {
		return items.reduce(function (s, it) { return s + it.qty; }, 0);
	}

	function subtotal() {
		return items.reduce(function (s, it) { return s + priceOf(it.slug) * it.qty; }, 0);
	}

	function emit(action, item) {
		var detail = { items: copy(), count: count(), subtotal: subtotal(), action: action || 'sync', item: item || null };
		var ev;
		try {
			ev = new CustomEvent('knu:cart', { detail: detail });
		} catch (e) {
			ev = document.createEvent('CustomEvent');
			ev.initCustomEvent('knu:cart', false, false, detail);
		}
		document.dispatchEvent(ev);
	}

	function commit(action, item) {
		persist();
		emit(action, item);
	}

	function find(key) {
		for (var i = 0; i < items.length; i++) {
			if (keyOf(items[i]) === key) return i;
		}
		return -1;
	}

	var api = {
		key: keyOf,
		items: copy,
		count: count,
		subtotal: subtotal,
		price: priceOf,
		add: function (it) {
			var next = sanitize([{ slug: it.slug, color: it.color, size: it.size, qty: it.qty || 1 }])[0];
			if (!next) return false;
			var i = find(keyOf(next));
			if (i !== -1) {
				items[i].qty = Math.min(items[i].qty + next.qty, MAX_QTY);
			} else {
				if (items.length >= MAX_LINES) return false;
				items.push(next);
			}
			commit('add', next);
			return true;
		},
		setQty: function (key, qty) {
			var i = find(key);
			if (i === -1) return;
			var q = clampQty(qty);
			if (!q) {
				items.splice(i, 1);
			} else {
				items[i].qty = q;
			}
			commit('qty');
		},
		remove: function (key) {
			var i = find(key);
			if (i === -1) return;
			items.splice(i, 1);
			commit('remove');
		},
		clear: function () {
			items = [];
			commit('clear');
		},
		maxQty: MAX_QTY
	};

	window.addEventListener('storage', function (e) {
		if (e.key !== KEY) return;
		items = read();
		emit('sync');
	});

	window.KNUCart = api;
})();
