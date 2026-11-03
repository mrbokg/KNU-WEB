/**
 * KNU landing UI: header, product options, cart drawer with 3-step checkout
 * (cart -> contacts & receiving -> payment), QR payment screen, mobile bar.
 * Contracts: C3 (window.KNU), C4 + C8 (REST), C5 (window.KNUCart from cart.js).
 */
(function () {
	'use strict';

	var K = window.KNU || {};
	var Cart = window.KNUCart;
	if (!Cart) return;

	var P = K.products || {};
	var I18N = K.i18n || {};
	var FEES = K.delivery || { pickup: 0, bishkek: 150, regions: 300 };
	var CONTACT = K.contact || {};
	var NBSP = ' ';
	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// vendor/qrcode.js sits next to this file; derive its URL from our own <script src>.
	var SELF_SRC = (document.currentScript && document.currentScript.src) || '';
	var QR_SRC = SELF_SRC ? SELF_SRC.replace(/main\.js(\?.*)?$/, 'vendor/qrcode.js') : '';

	/* ---------- helpers ---------- */

	function t(key, fallback) {
		if (I18N['js.' + key] !== undefined) return I18N['js.' + key];
		if (I18N[key] !== undefined) return I18N[key];
		return fallback !== undefined ? fallback : key;
	}

	function $(sel, root) { return (root || document).querySelector(sel); }
	function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

	function money(n) {
		var s = String(Math.round(n || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, NBSP);
		return s + NBSP + t('currency', 'сом');
	}

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function plural(n) {
		var m10 = n % 10, m100 = n % 100;
		if (m10 === 1 && m100 !== 11) return t('plural.one', 'товар');
		if (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) return t('plural.few', 'товара');
		return t('plural.many', 'товаров');
	}

	function fee(key) {
		var v = parseInt(FEES[key], 10);
		return isFinite(v) ? v : 0;
	}

	function feeLabel(key) {
		var v = fee(key);
		return v ? money(v) : t('cart.free', 'Бесплатно');
	}

	function product(slug) { return P[slug] || null; }

	function colorInfo(slug, color) {
		var p = product(slug);
		return (p && p.colors && p.colors[color]) || null;
	}

	function scrollToEl(el) {
		if (!el) return;
		el.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
	}

	var MISSING = "this.classList.add('is-missing')";

	/* ---------- header ---------- */

	var header = $('[data-header]');
	if (header) {
		var ticking = false;
		var onScroll = function () {
			if (ticking) return;
			ticking = true;
			window.requestAnimationFrame(function () {
				header.classList.toggle('is-scrolled', window.scrollY > 4);
				ticking = false;
			});
		};
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}

	// In-page anchors: smooth scroll (CSS) + move focus to the target for keyboard users.
	document.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('a[href*="#"]');
		if (!a) return;
		var href = a.getAttribute('href');
		var hash = href.slice(href.indexOf('#') + 1);
		var path = href.slice(0, href.indexOf('#'));
		if (!hash || (path && a.pathname !== window.location.pathname)) return;
		var target = document.getElementById(hash);
		if (!target) return;
		e.preventDefault();
		if (a.hasAttribute('data-drawer-link')) closeDrawer(true);
		scrollToEl(target);
		if (window.history && history.replaceState) history.replaceState(null, '', '#' + hash);
		if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
		target.focus({ preventScroll: true });
	});

	/* ---------- product cards ---------- */

	function setImg(img, src) {
		if (!img) return;
		if (!src) {
			img.classList.add('is-missing');
			img.removeAttribute('src');
			return;
		}
		if (img.getAttribute('src') === src) return;
		img.classList.remove('is-missing');
		img.classList.add('is-loading');
		var done = function () { img.classList.remove('is-loading'); };
		img.addEventListener('load', done, { once: true });
		img.addEventListener('error', done, { once: true });
		img.src = src;
	}

	$$('[data-product-form]').forEach(function (form) {
		var slug = form.getAttribute('data-slug');
		var card = form.closest('[data-product]') || form;
		var main = $('[data-gallery-main]', card);
		var thumbs = $$('[data-thumb]', card);
		var colorName = $('[data-color-name]', card);
		var hint = $('[data-hint]', form);
		var btn = $('[data-add]', form);
		var btnLabel = $('[data-add-label]', form);
		var needsSize = form.getAttribute('data-has-sizes') === '1';
		var active = 0;
		var resetTimer = null;

		function currentImages() {
			var checked = $('input[name="color"]:checked', form);
			if (checked && checked.getAttribute('data-images')) {
				try { return JSON.parse(checked.getAttribute('data-images')) || []; } catch (e) { return []; }
			}
			var hidden = $('input[name="color"]', form);
			var c = hidden ? colorInfo(slug, hidden.value) : null;
			return (c && c.images) || [];
		}

		function render() {
			var images = currentImages();
			if (active >= images.length) active = 0;
			setImg(main, images[active]);
			var checked = $('input[name="color"]:checked', form);
			var title = (product(slug) || {}).title || '';
			if (main && checked) main.alt = title + ', ' + checked.getAttribute('data-name');
			thumbs.forEach(function (th, i) {
				setImg($('img', th), images[i]);
				th.setAttribute('aria-pressed', i === active ? 'true' : 'false');
			});
		}

		thumbs.forEach(function (th) {
			th.addEventListener('click', function () {
				active = parseInt(th.getAttribute('data-thumb'), 10) || 0;
				render();
			});
		});

		form.addEventListener('change', function (e) {
			var el = e.target;
			if (el.name === 'color') {
				if (colorName) colorName.textContent = el.getAttribute('data-name') || '';
				render();
			} else if (el.name === 'size') {
				form.classList.remove('needs-size');
				if (hint) hint.textContent = '';
			}
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var colorEl = $('input[name="color"]:checked', form) || $('input[name="color"]', form);
			var sizeEl = $('input[name="size"]:checked', form);
			if (needsSize && !sizeEl) {
				form.classList.add('needs-size');
				if (hint) hint.textContent = t('product.pick_size', 'Выберите размер');
				var first = $('input[name="size"]', form);
				if (first) first.focus();
				return;
			}
			var ok = Cart.add({
				slug: slug,
				color: colorEl ? colorEl.value : '',
				size: needsSize && sizeEl ? sizeEl.value : '',
				qty: 1
			});
			if (!ok) return;
			btn.classList.add('is-done');
			btnLabel.textContent = t('product.added', 'Добавлено');
			clearTimeout(resetTimer);
			resetTimer = setTimeout(function () {
				btn.classList.remove('is-done');
				btnLabel.textContent = t('product.add', 'В корзину');
			}, 1800);
			showToast(t('toast.added', 'Добавлено в корзину'));
		});
	});

	/* ---------- toast ---------- */

	var toast = $('[data-toast]');
	var toastText = $('[data-toast-text]');
	var toastTimer = null;

	function showToast(msg) {
		if (!toast) return;
		toastText.textContent = msg;
		toast.hidden = false;
		window.requestAnimationFrame(function () {
			window.requestAnimationFrame(function () { toast.classList.add('is-visible'); });
		});
		clearTimeout(toastTimer);
		toastTimer = setTimeout(hideToast, 3200);
	}

	function hideToast() {
		if (!toast || toast.hidden) return;
		toast.classList.remove('is-visible');
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () { toast.hidden = true; }, 260);
	}

	if (toast) {
		toast.addEventListener('mouseenter', function () { clearTimeout(toastTimer); });
		toast.addEventListener('mouseleave', function () { toastTimer = setTimeout(hideToast, 1600); });
	}

	/* ---------- drawer & views ---------- */

	var drawer = $('[data-drawer]');
	var panel = $('[data-drawer-panel]');
	var drawerTitle = $('[data-drawer-title]');
	var backBtn = $('[data-back]');
	var stepper = $('[data-stepper]');
	var views = {};
	$$('[data-view]', drawer || document).forEach(function (v) { views[v.getAttribute('data-view')] = v; });
	var currentView = 'cart';
	var checkoutStep = 'contact';
	var pending = null; // order awaiting QR payment
	var lastFocus = null;
	var closeTimer = null;

	var form = $('[data-checkout]');
	var panels = {};
	$$('[data-panel]', form || document).forEach(function (p) { panels[p.getAttribute('data-panel')] = p; });

	function stepName() {
		if (currentView === 'cart') return 'cart';
		if (currentView === 'checkout') return checkoutStep;
		return '';
	}

	function updateChrome() {
		var step = stepName();
		var titles = {
			cart: t('cart.title', 'Корзина'),
			contact: t('contact.title', 'Контакты и получение'),
			payment: t('payment.title', 'Способ оплаты'),
			pay: t('pay.title', 'Оплата по QR'),
			success: t('success.title', 'Заказ принят')
		};
		drawerTitle.textContent = titles[step || currentView] || '';
		// The success screen has its own large heading; keep the dialog label for screen readers only.
		drawerTitle.classList.toggle('sr-only', currentView === 'success');
		backBtn.hidden = currentView !== 'checkout';
		if (stepper) {
			stepper.hidden = !step;
			var order = ['cart', 'contact', 'payment'];
			var idx = order.indexOf(step);
			$$('[data-step]', stepper).forEach(function (li, i) {
				li.classList.toggle('is-done', i < idx);
				li.classList.toggle('is-current', i === idx);
				if (i === idx) li.setAttribute('aria-current', 'step'); else li.removeAttribute('aria-current');
			});
		}
	}

	function showView(name) {
		currentView = name;
		Object.keys(views).forEach(function (k) { views[k].hidden = k !== name; });
		var body = views[name] && $('.drawer__body', views[name]);
		if (body) body.scrollTop = 0;
		updateChrome();
	}

	function showStep(step) {
		checkoutStep = step;
		Object.keys(panels).forEach(function (k) { panels[k].hidden = k !== step; });
		$('[data-next]', form).hidden = step !== 'contact';
		$('[data-submit]', form).hidden = step !== 'payment';
		$('[data-checkout-note]', form).hidden = step !== 'payment';
		if (step === 'payment') renderReview();
		var body = $('.drawer__body', form);
		if (body) body.scrollTop = 0;
		updateChrome();
	}

	function focusables() {
		return $$('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]):not([tabindex="-1"]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])', panel)
			.filter(function (el) { return el.offsetParent !== null || el === document.activeElement; });
	}

	function setInert(on) {
		$$('[data-inert-root]').forEach(function (el) {
			if (on) el.setAttribute('inert', ''); else el.removeAttribute('inert');
		});
	}

	function openDrawer(view) {
		if (!drawer) return;
		clearTimeout(closeTimer);
		hideToast();
		if (drawer.hidden) lastFocus = document.activeElement;
		// An unpaid QR order keeps its payment screen until "I paid" is pressed.
		if (pending && currentView === 'pay') view = 'pay';
		showView(view || 'cart');
		if (currentView === 'checkout') showStep(checkoutStep);
		drawer.hidden = false;
		document.body.classList.add('has-drawer');
		setInert(true);
		void panel.offsetWidth; // let the transition run
		drawer.classList.add('is-open');
		var f = focusables();
		(f[0] || panel).focus();
	}

	function closeDrawer(skipFocus) {
		if (!drawer || drawer.hidden) return;
		drawer.classList.remove('is-open');
		document.body.classList.remove('has-drawer');
		setInert(false);
		closeTimer = setTimeout(function () {
			drawer.hidden = true;
			if (currentView === 'success') showView('cart');
		}, reduceMotion ? 0 : 360);
		if (!skipFocus && lastFocus && lastFocus.focus) lastFocus.focus();
	}

	document.addEventListener('click', function (e) {
		if (!e.target.closest) return;
		if (e.target.closest('[data-cart-open]')) {
			e.preventDefault();
			openDrawer('cart');
		} else if (e.target.closest('[data-drawer-close]')) {
			closeDrawer();
		}
	});

	if (drawer) {
		drawer.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				e.preventDefault();
				closeDrawer();
				return;
			}
			if (e.key !== 'Tab') return;
			var f = focusables();
			if (!f.length) { e.preventDefault(); panel.focus(); return; }
			var first = f[0], last = f[f.length - 1];
			if (e.shiftKey && (document.activeElement === first || document.activeElement === panel)) {
				e.preventDefault(); last.focus();
			} else if (!e.shiftKey && document.activeElement === last) {
				e.preventDefault(); first.focus();
			}
		});
	}

	if (backBtn) {
		backBtn.addEventListener('click', function () {
			if (currentView !== 'checkout') return;
			if (checkoutStep === 'payment') {
				showStep('contact');
				var next = $('[data-next]', form);
				if (next) next.focus();
			} else {
				showView('cart');
				var b = $('[data-to-checkout]');
				if (b) b.focus();
			}
		});
	}

	/* ---------- cart rendering ---------- */

	var list = $('[data-cart-list]');
	var empty = $('[data-cart-empty]');
	var cartFoot = $('[data-cart-foot]');
	var subtotalEl = $('[data-cart-subtotal]');
	var countEls = $$('[data-cart-count]');
	var cartBtns = $$('.cart-btn');

	var ICON_MINUS = '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false"><path d="M2 7h10" stroke-linecap="round"/></svg>';
	var ICON_PLUS = '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false"><path d="M2 7h10M7 2v10" stroke-linecap="round"/></svg>';

	function itemMeta(it) {
		var c = colorInfo(it.slug, it.color);
		var parts = [];
		if (c && c.name) parts.push(c.name);
		if (it.size) parts.push(t('cart.size', 'Размер') + ' ' + it.size);
		return parts.join(', ');
	}

	function renderList(items) {
		list.innerHTML = items.map(function (it) {
			var p = product(it.slug) || {};
			var c = colorInfo(it.slug, it.color);
			var img = c && c.images && c.images[0] ? c.images[0] : '';
			var key = Cart.key(it);
			var title = p.title || it.slug;
			return '<li class="cart-item" data-key="' + esc(key) + '">' +
				'<div class="cart-item__img media" data-fallback="1925">' +
					(img ? '<img src="' + esc(img) + '" alt="" width="1200" height="1500" loading="lazy" onerror="' + MISSING + '">' : '') +
				'</div>' +
				'<div class="cart-item__info">' +
					'<p class="cart-item__title">' + esc(title) + '</p>' +
					'<p class="cart-item__meta">' + esc(itemMeta(it)) + '</p>' +
					'<div class="cart-item__row">' +
						'<div class="qty" role="group" aria-label="' + esc(t('cart.qty', 'Количество') + ': ' + title) + '">' +
							'<button class="qty__btn" type="button" data-act="dec" aria-label="' + esc(t('cart.dec', 'Уменьшить количество')) + '"' + (it.qty <= 1 ? ' disabled' : '') + '>' + ICON_MINUS + '</button>' +
							'<span class="qty__val" aria-live="polite">' + it.qty + '</span>' +
							'<button class="qty__btn" type="button" data-act="inc" aria-label="' + esc(t('cart.inc', 'Увеличить количество')) + '"' + (it.qty >= Cart.maxQty ? ' disabled' : '') + '>' + ICON_PLUS + '</button>' +
						'</div>' +
						'<button class="link-btn" type="button" data-act="remove">' + esc(t('cart.remove', 'Удалить')) + '</button>' +
					'</div>' +
				'</div>' +
				'<p class="cart-item__price">' + money(Cart.price(it.slug) * it.qty) + '</p>' +
			'</li>';
		}).join('');
	}

	if (list) {
		list.addEventListener('click', function (e) {
			var b = e.target.closest('[data-act]');
			if (!b) return;
			var li = b.closest('[data-key]');
			var key = li.getAttribute('data-key');
			var act = b.getAttribute('data-act');
			var it = Cart.items().filter(function (x) { return Cart.key(x) === key; })[0];
			if (!it) return;
			if (act === 'inc') Cart.setQty(key, it.qty + 1);
			if (act === 'dec') Cart.setQty(key, it.qty - 1);
			if (act === 'remove') Cart.remove(key);
			// Restore focus after re-render.
			var sameLi = $$('[data-key]', list).filter(function (n) { return n.getAttribute('data-key') === key; })[0];
			var target = sameLi && $('[data-act="' + act + '"]:not([disabled])', sameLi);
			if (!target && sameLi) target = $('[data-act="remove"]', sameLi);
			if (!target) target = $('[data-act]', list) || $('[data-drawer-link]') || panel;
			if (target && target.offsetParent !== null) target.focus(); else panel.focus();
		});
	}

	/* ---------- mobile bar ---------- */

	var mbarBtn = $('[data-mbar-btn]');
	var mbarLabel = $('[data-mbar-label]');

	if (mbarBtn) {
		mbarBtn.addEventListener('click', function () {
			if (Cart.count() > 0) {
				openDrawer('cart');
				return;
			}
			var target = document.getElementById('collection');
			if (target) scrollToEl(target);
			else window.location.href = (mbarBtn.getAttribute('data-home') || '/') + '#collection';
		});
	}

	/* ---------- checkout ---------- */

	var addressField = $('[data-address-field]');
	var alertBox = $('[data-form-alert]');
	var alertText = $('[data-form-alert-text]');
	var waFallback = $('[data-wa-fallback]');
	var submitBtn = $('[data-submit]');
	var nextBtn = $('[data-next]');
	var phoneInput = $('[data-phone]');
	var CONTACT_FIELDS = ['name', 'phone', 'delivery', 'address', 'comment', 'consent'];

	function radioValue(name, fallback) {
		var r = form && $('input[name="' + name + '"]:checked', form);
		return r ? r.value : fallback;
	}

	function deliveryValue() { return radioValue('delivery', 'pickup'); }
	function paymentValue() { return radioValue('payment', ''); }

	function renderTotals() {
		if (!form) return;
		var sub = Cart.subtotal();
		var d = deliveryValue();
		$('[data-co-subtotal]').textContent = money(sub);
		$('[data-co-delivery]').textContent = feeLabel(d);
		$('[data-co-total]').textContent = money(sub + fee(d));
	}

	function renderCart(detail) {
		var items = Cart.items();
		var n = Cart.count();
		var sub = Cart.subtotal();

		countEls.forEach(function (el) {
			el.textContent = n > 99 ? '99+' : String(n);
			el.hidden = n === 0;
			if (detail && detail.action === 'add') {
				el.classList.remove('is-bump');
				void el.offsetWidth;
				el.classList.add('is-bump');
			}
		});
		cartBtns.forEach(function (b) {
			var base = b.getAttribute('data-label') || t('cart.title', 'Корзина');
			b.setAttribute('aria-label', n ? base + ', ' + n + ' ' + plural(n) : base);
		});

		if (list) {
			renderList(items);
			empty.hidden = items.length > 0;
			list.hidden = items.length === 0;
			cartFoot.hidden = items.length === 0;
			subtotalEl.textContent = money(sub);
		}

		if (mbarLabel) {
			if (n > 0) {
				mbarLabel.innerHTML = esc(t('bar.cart', 'Корзина')) +
					' <span class="mbar__sep" aria-hidden="true">·</span> ' + n + ' ' + esc(plural(n)) +
					' <span class="mbar__sep" aria-hidden="true">·</span> ' + esc(money(sub));
			} else {
				mbarLabel.textContent = t('bar.order', 'Заказать');
			}
		}

		renderTotals();
		if (currentView === 'checkout' && items.length === 0) showView('cart');
	}

	document.addEventListener('knu:cart', function (e) { renderCart(e.detail); });

	// Fees from KNU.delivery override PHP fallbacks.
	$$('[data-fee]').forEach(function (el) {
		var k = el.getAttribute('data-fee');
		if (FEES[k] === undefined) return;
		if (fee(k)) el.textContent = money(fee(k));
	});

	var toCheckout = $('[data-to-checkout]');
	if (toCheckout && form) {
		toCheckout.addEventListener('click', function () {
			if (!Cart.count()) return;
			clearAlert();
			showView('checkout');
			showStep('contact');
			renderTotals();
			var name = $('#co-name');
			if (name) name.focus();
		});
	}

	/* Phone mask: +996 XXX XXX XXX */
	function phoneDigits(v) {
		var d = String(v || '').replace(/\D/g, '');
		if (d.indexOf('996') === 0) d = d.slice(3);
		else if (d.charAt(0) === '0') d = d.slice(1);
		return d.slice(0, 9);
	}

	function formatPhone(d) {
		var out = '+996';
		if (d.length) out += ' ' + d.slice(0, 3);
		if (d.length > 3) out += ' ' + d.slice(3, 6);
		if (d.length > 6) out += ' ' + d.slice(6, 9);
		return out;
	}

	if (phoneInput) {
		phoneInput.addEventListener('focus', function () {
			if (!phoneInput.value) phoneInput.value = '+996 ';
		});
		phoneInput.addEventListener('blur', function () {
			if (!phoneDigits(phoneInput.value).length) phoneInput.value = '';
		});
		phoneInput.addEventListener('input', function () {
			var d = phoneDigits(phoneInput.value);
			phoneInput.value = d.length ? formatPhone(d) : '+996 ';
			if (fieldErrorShown('phone') && d.length === 9) setFieldError('phone', '');
		});
	}

	function fieldErrorShown(name) {
		var el = form && $('[data-error="' + name + '"]', form);
		return !!(el && el.textContent);
	}

	function setFieldError(name, msg) {
		if (!form) return;
		var err = $('[data-error="' + name + '"]', form);
		var input = form.elements[name];
		if (err) err.textContent = msg || '';
		if (input && input.setAttribute) {
			if (msg) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
		}
	}

	function clearErrors() {
		CONTACT_FIELDS.concat(['payment']).forEach(function (n) { setFieldError(n, ''); });
	}

	function clearAlert() {
		if (!alertBox) return;
		alertBox.hidden = true;
		alertText.textContent = '';
		waFallback.hidden = true;
	}

	function showAlert(msg, withWhatsApp) {
		alertText.textContent = msg;
		alertBox.hidden = false;
		if (withWhatsApp && CONTACT.whatsapp) {
			waFallback.href = waLink(draftText());
			waFallback.hidden = false;
		} else {
			waFallback.hidden = true;
		}
		var body = $('.drawer__body', form);
		if (body) body.scrollTop = 0;
	}

	function syncAddress() {
		if (!form) return;
		var need = deliveryValue() !== 'pickup';
		addressField.hidden = !need;
		form.elements.address.required = need;
		if (!need) setFieldError('address', '');
	}

	if (form) {
		form.addEventListener('change', function (e) {
			var n = e.target.name;
			if (n === 'delivery') {
				syncAddress();
				renderTotals();
			}
			if (n === 'consent' && e.target.checked) setFieldError('consent', '');
			if (n === 'payment') setFieldError('payment', '');
		});
		form.addEventListener('input', function (e) {
			var n = e.target.name;
			if (n === 'name' && fieldErrorShown('name') && e.target.value.trim().length >= 2) setFieldError('name', '');
			if (n === 'address' && fieldErrorShown('address') && e.target.value.trim().length >= 3) setFieldError('address', '');
		});
		syncAddress();
	}

	function collect() {
		var f = form.elements;
		var digits = phoneDigits(f.phone.value);
		return {
			name: f.name.value.trim(),
			phone: digits.length ? formatPhone(digits) : '',
			delivery: deliveryValue(),
			address: deliveryValue() === 'pickup' ? '' : f.address.value.trim(),
			comment: f.comment.value.trim(),
			payment: paymentValue(),
			consent: !!f.consent.checked,
			lang: K.lang || 'kg',
			website: f.website.value,
			items: Cart.items().map(function (it) {
				return { slug: it.slug, color: it.color, size: it.size, qty: it.qty };
			})
		};
	}

	function validateContact(data) {
		var errors = {};
		if (data.name.length < 2 || data.name.length > 60) errors.name = t('err.name', 'Введите имя');
		if (phoneDigits(data.phone).length !== 9) errors.phone = t('err.phone', 'Введите номер телефона');
		if (data.delivery !== 'pickup' && data.address.length < 3) errors.address = t('err.address', 'Укажите адрес');
		if (!data.consent) errors.consent = t('err.consent', 'Подтвердите согласие');
		return errors;
	}

	function applyErrors(errors) {
		var first = null;
		var order = CONTACT_FIELDS.concat(['payment']);
		Object.keys(errors).sort(function (a, b) {
			var ia = order.indexOf(a), ib = order.indexOf(b);
			return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib);
		}).forEach(function (k) {
			setFieldError(k, errors[k]);
			if (!first && form.elements[k]) first = form.elements[k];
		});
		if (first) {
			if (!first.focus && first.length) first = first[0];
			if (first.focus) first.focus();
		}
	}

	function deliveryName(key) { return t('delivery.' + key, key); }
	function paymentName(key) { return t('payment.' + key, key); }

	function renderReview() {
		var box = $('[data-review]', form);
		if (!box) return;
		var d = collect();
		var rows = [
			[t('wa.delivery', 'Получение'), deliveryName(d.delivery) + (d.address ? ', ' + d.address : '')],
			[t('wa.name', 'Имя'), d.name],
			[t('wa.phone', 'Телефон'), d.phone]
		];
		box.innerHTML = rows.map(function (r) {
			return '<div><dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd></div>';
		}).join('');
	}

	function lineText(title, colorName, size, qty, sum) {
		var opts = [colorName, size].filter(Boolean).join(', ');
		return '— ' + title + (opts ? ' (' + opts + ')' : '') + ' × ' + qty + ' = ' + money(sum);
	}

	function draftText() {
		var data = form ? collect() : { items: Cart.items(), delivery: 'pickup', payment: '', name: '', phone: '', address: '', comment: '' };
		var lines = [t('wa.new', 'Здравствуйте! Хочу оформить заказ:')];
		data.items.forEach(function (it) {
			var p = product(it.slug) || {};
			var c = colorInfo(it.slug, it.color) || {};
			lines.push(lineText(p.title || it.slug, c.name, it.size, it.qty, Cart.price(it.slug) * it.qty));
		});
		lines.push(t('wa.delivery', 'Получение') + ': ' + deliveryName(data.delivery));
		if (data.address) lines.push(t('wa.address', 'Адрес') + ': ' + data.address);
		if (data.payment) lines.push(t('wa.payment', 'Оплата') + ': ' + paymentName(data.payment));
		if (data.name) lines.push(t('wa.name', 'Имя') + ': ' + data.name);
		if (data.phone) lines.push(t('wa.phone', 'Телефон') + ': ' + data.phone);
		if (data.comment) lines.push(t('wa.comment', 'Комментарий') + ': ' + data.comment);
		lines.push(t('wa.total', 'Итого') + ': ' + money(Cart.subtotal() + fee(data.delivery)));
		return lines.join('\n');
	}

	function orderText(res, data) {
		var lines = [t('wa.hello', 'Здравствуйте! Мой заказ') + ' ' + res.number];
		(res.items || []).forEach(function (it) {
			lines.push(lineText(it.title, it.color, it.size, it.qty, it.sum));
		});
		lines.push(t('wa.delivery', 'Получение') + ': ' + deliveryName(data.delivery));
		if (data.address) lines.push(t('wa.address', 'Адрес') + ': ' + data.address);
		lines.push(t('wa.payment', 'Оплата') + ': ' + paymentName(data.payment));
		lines.push(t('wa.name', 'Имя') + ': ' + data.name);
		lines.push(t('wa.phone', 'Телефон') + ': ' + data.phone);
		lines.push(t('wa.total', 'Итого') + ': ' + money(res.total));
		return lines.join('\n');
	}

	function waLink(text) {
		return 'https://wa.me/' + String(CONTACT.whatsapp || '').replace(/\D/g, '') + '?text=' + encodeURIComponent(text);
	}

	function setBusy(btn, on, busyLabel, idleLabel) {
		btn.disabled = on;
		btn.textContent = on ? busyLabel : idleLabel;
	}

	if (nextBtn) {
		nextBtn.addEventListener('click', goToPayment);
	}

	function goToPayment() {
		clearAlert();
		['name', 'phone', 'address', 'consent'].forEach(function (n) { setFieldError(n, ''); });
		if (!Cart.count()) { showAlert(t('err.empty', 'Корзина пуста'), false); return; }
		var errors = validateContact(collect());
		if (Object.keys(errors).length) { applyErrors(errors); return; }
		showStep('payment');
		var checked = $('input[name="payment"]:checked', form) || $('input[name="payment"]', form);
		if (checked) checked.focus();
	}

	/* ---------- QR payment ---------- */

	var qrLoader = null;

	function loadQrLib() {
		if (window.qrcode) return Promise.resolve(window.qrcode);
		if (qrLoader) return qrLoader;
		qrLoader = new Promise(function (resolve, reject) {
			if (!QR_SRC) { reject(new Error('no src')); return; }
			var s = document.createElement('script');
			s.src = QR_SRC;
			s.async = true;
			s.onload = function () { window.qrcode ? resolve(window.qrcode) : reject(new Error('no lib')); };
			s.onerror = function () { qrLoader = null; reject(new Error('load failed')); };
			document.head.appendChild(s);
		});
		return qrLoader;
	}

	function renderQr(text) {
		var box = $('[data-qr]');
		var status = $('[data-qr-status]');
		$$('svg', box).forEach(function (n) { n.remove(); });
		status.hidden = false;
		status.textContent = t('pay.loading', '…');
		loadQrLib().then(function (lib) {
			var qr = lib(0, 'M');
			qr.addData(text);
			qr.make();
			status.hidden = true;
			box.insertAdjacentHTML('beforeend', qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true }));
			var svg = $('svg', box);
			if (svg) { svg.setAttribute('aria-hidden', 'true'); svg.setAttribute('focusable', 'false'); }
		}).catch(function () {
			status.textContent = t('pay.no_qr', 'QR-код не загрузился');
		});
	}

	function showPay(order) {
		pending = order;
		$('[data-pay-amount]').textContent = money(order.total);
		$('[data-pay-number]').textContent = order.number;
		var recipient = K.qrRecipient || '';
		$('[data-pay-recipient]').textContent = recipient;
		$('[data-pay-recipient-row]').hidden = !recipient;
		$('[data-pay-alert]').hidden = true;
		renderQr('KNU-DEMO|' + order.number + '|' + Math.round(order.total) + 'KGS');
		showView('pay');
		var btn = $('[data-paid]');
		if (btn) btn.focus();
	}

	var paidBtn = $('[data-paid]');
	if (paidBtn) {
		paidBtn.addEventListener('click', function () {
			if (!pending) return;
			var base = K.paidUrl || (String(K.rest || '').replace(/\/?$/, '/'));
			var alert = $('[data-pay-alert]');
			alert.hidden = true;
			setBusy(paidBtn, true, t('pay.checking', '…'), t('pay.done', 'Я оплатил'));
			fetch(base + encodeURIComponent(pending.number) + '/paid', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-WP-Nonce': K.nonce || '' },
				body: JSON.stringify({ token: pending.token || '' })
			}).then(function (res) {
				return res.json().catch(function () { return {}; }).then(function (body) { return { ok: res.ok, body: body || {} }; });
			}).then(function (r) {
				setBusy(paidBtn, false, '', t('pay.done', 'Я оплатил'));
				if (r.ok && r.body.ok) {
					var order = pending;
					pending = null;
					showSuccess(order.res, order.data);
					return;
				}
				payError(r.body.message);
			}).catch(function () {
				setBusy(paidBtn, false, '', t('pay.done', 'Я оплатил'));
				payError('');
			});
		});
	}

	function payError(message) {
		var alert = $('[data-pay-alert]');
		$('[data-pay-alert-text]').textContent = (message ? message + ' ' : '') + t('pay.error', 'Не удалось отметить оплату.');
		var wa = $('[data-pay-wa]');
		wa.hidden = !CONTACT.whatsapp;
		if (CONTACT.whatsapp && pending) wa.href = waLink(orderText(pending.res, pending.data));
		alert.hidden = false;
	}

	/* ---------- success ---------- */

	function showSuccess(res, data) {
		$('[data-success-number]').textContent = res.number || '';
		$('[data-success-total]').textContent = money(res.total != null ? res.total : 0);
		$('[data-success-text]').textContent = data.payment === 'qr' ? t('success.text_qr', '') : t('success.text', '');
		var wa = $('[data-success-wa]');
		wa.hidden = !CONTACT.whatsapp;
		if (CONTACT.whatsapp) wa.href = waLink(orderText(res, data));
		var tg = $('[data-success-tg]');
		tg.hidden = !CONTACT.telegram;
		if (CONTACT.telegram) tg.href = 'https://t.me/' + String(CONTACT.telegram).replace(/^@/, '');
		showView('success');
		var title = $('[data-success-title]');
		if (title) title.focus();
	}

	function resetCheckout() {
		form.reset();
		syncAddress();
		clearErrors();
		clearAlert();
		showStep('contact');
	}

	/* ---------- submit ---------- */

	if (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			// Enter on step 2 moves to step 3 instead of submitting.
			if (checkoutStep !== 'payment') { goToPayment(); return; }

			clearAlert();
			clearErrors();

			if (!Cart.count()) {
				showAlert(t('err.empty', 'Корзина пуста'), false);
				return;
			}

			var data = collect();
			var errors = validateContact(data);
			if (Object.keys(errors).length) {
				showStep('contact');
				applyErrors(errors);
				return;
			}
			if (data.payment !== 'qr' && data.payment !== 'cash') {
				applyErrors({ payment: t('err.payment', 'Выберите способ оплаты') });
				return;
			}
			if (!K.rest) {
				showAlert(t('err.network', 'Не удалось отправить заказ'), true);
				return;
			}

			setBusy(submitBtn, true, t('form.sending', 'Отправляем…'), t('form.submit', 'Оформить заказ'));
			form.setAttribute('aria-busy', 'true');

			fetch(K.rest, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'Accept': 'application/json',
					'X-WP-Nonce': K.nonce || ''
				},
				body: JSON.stringify(data)
			}).then(function (res) {
				return res.json().catch(function () { return {}; }).then(function (body) {
					return { ok: res.ok, status: res.status, body: body || {} };
				});
			}).then(function (r) {
				setBusy(submitBtn, false, '', t('form.submit', 'Оформить заказ'));
				form.setAttribute('aria-busy', 'false');

				if (r.ok && r.body.ok) {
					var res = r.body;
					var payment = res.payment || data.payment;
					Cart.clear();
					resetCheckout();
					if (payment === 'qr' && res.number) {
						showPay({ number: res.number, total: res.total, token: res.token, res: res, data: data });
					} else {
						showSuccess(res, data);
					}
					return;
				}

				var fields = r.body.data && r.body.data.fields;
				if (fields && typeof fields === 'object') {
					var known = {};
					var other = [];
					Object.keys(fields).forEach(function (k) {
						if (form.elements[k] && k !== 'website') known[k] = fields[k]; else other.push(fields[k]);
					});
					var onContact = Object.keys(known).some(function (k) { return CONTACT_FIELDS.indexOf(k) !== -1; });
					if (onContact) showStep('contact');
					applyErrors(known);
					showAlert(other.length ? other.join(' ') : (r.body.message || t('err.generic', 'Проверьте поля формы')), other.length > 0);
					return;
				}
				showAlert((r.body.message ? r.body.message + ' ' : '') + t('err.server', 'Сервер не принял заказ'), true);
			}).catch(function () {
				setBusy(submitBtn, false, '', t('form.submit', 'Оформить заказ'));
				form.setAttribute('aria-busy', 'false');
				showAlert(t('err.network', 'Нет связи с сервером'), true);
			});
		});
	}

	/* ---------- init ---------- */
	if (form) showStep('contact');
	showView('cart');
	renderCart();
})();
