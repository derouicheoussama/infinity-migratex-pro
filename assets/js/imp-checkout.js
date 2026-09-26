/**
 * ∞ INFINITY CODER — création originale de Derouiche Oussama
 * Plugin : Infinity MigrateX Pro · https://www.derouicheoussama.com
 * Copyright © 2026 Derouiche Oussama. Licence GPL v2+ —
 * toute copie ou modification doit conserver cette signature.
 *
 * Tunnel d'achat Pro — STANDALONE pour la page Extensions (plugins.php).
 * Charge ~10 Ko au lieu de tout admin.js : contient uniquement les
 * helpers nécessaires + le wizard 4 étapes + les déclencheurs.
 * ⚠️ À garder en synchronisation avec le module wizard de admin.js
 * (mêmes attributs data-imp-co-* rendus par admin/class-checkout.php).
 */
/* global IMP_Admin */
(function () {
	'use strict';

	if (typeof IMP_Admin === 'undefined') { return; }
	window.__impCheckoutStandalone = true;

	var cfg = IMP_Admin;
	var i18n = cfg.i18n || {};
	var coPrices = { personal: { yearly: '39€', lifetime: '79€' }, business: { yearly: '89€', lifetime: '179€' } };

	function $(sel, ctx) { return (ctx || document).querySelector(sel); }
	function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

	function toast(message, kind) {
		var box = $('#imp-toast');
		if (!box) {
			box = document.createElement('div');
			box.id = 'imp-toast';
			document.body.appendChild(box);
		}
		var item = document.createElement('div');
		item.className = 'imp-toast' + (kind ? ' imp-toast-' + kind : '');
		item.textContent = message;
		box.appendChild(item);
		window.setTimeout(function () {
			item.style.opacity = '0';
			item.style.transition = 'opacity .4s ease';
			window.setTimeout(function () { item.remove(); }, 450);
		}, kind === 'error' ? 9000 : 5000);
	}

	function ajax(action, data, timeoutMs) {
		var form = new FormData();
		form.append('action', action);
		form.append('nonce', cfg.nonce);
		if (data) {
			Object.keys(data).forEach(function (key) {
				var value = data[key];
				if (value !== undefined && value !== null && typeof value !== 'function') {
					form.append(key, value);
				}
			});
		}
		var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
		var tid = controller ? window.setTimeout(function () { controller.abort(); }, timeoutMs || 120000) : null;
		var cleanup = function () { if (tid) { window.clearTimeout(tid); } };
		return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form, signal: controller ? controller.signal : undefined })
			.then(function (response) { cleanup(); return response.json().catch(function () { throw new Error('bad-json'); }); },
				function (err) { cleanup(); throw err; });
	}

	function impConfetti(jobId) {
		try {
			var key = 'imp_confetti_' + jobId;
			if (sessionStorage.getItem(key)) { return; }
			sessionStorage.setItem(key, '1');
			if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
			var colors = ['#2f5fe0', '#6d3ae8', '#34d399', '#fbbf24'];
			for (var i = 0; i < 36; i++) {
				var p = document.createElement('div');
				p.style.cssText = 'position:fixed;z-index:100002;width:9px;height:9px;border-radius:2px;left:' + (40 + Math.random() * 20) + 'vw;top:-12px;pointer-events:none;background:' + colors[i % colors.length] + ';opacity:1;';
				document.body.appendChild(p);
				(function (piece, delay, drift) {
					var t0 = null;
					var anim = function (ts) {
						if (!t0) { t0 = ts; }
						var t = (ts - t0) / 1400;
						if (t >= 1) { piece.remove(); return; }
						piece.style.transform = 'translate(' + (drift * t) + 'px,' + (t * t * window.innerHeight) + 'px) rotate(' + (t * 540) + 'deg)';
						piece.style.opacity = 1 - t;
						window.requestAnimationFrame(anim);
					};
					window.setTimeout(function () { window.requestAnimationFrame(anim); }, delay);
				})(p, i * 24, (Math.random() - 0.5) * 320);
			}
		} catch (e) { /* silencieux */ }
	}

	/* ---------- Wizard 4 étapes ---------- */

	var coRoot = document.getElementById('imp-checkout');
	var coState = { step: 1, plan: 'personal', billing: 'yearly' };

	function co$(sel) { return coRoot ? coRoot.querySelector(sel) : null; }
	function co$$(sel) { return coRoot ? Array.prototype.slice.call(coRoot.querySelectorAll(sel)) : []; }

	function coGo(n) {
		if (!coRoot) { return; }
		coState.step = n;
		co$$('[data-imp-co-step]').forEach(function (section) {
			section.hidden = parseInt(section.getAttribute('data-imp-co-step'), 10) !== n;
		});
		co$$('[data-imp-co-dot]').forEach(function (dot) {
			var k = parseInt(dot.getAttribute('data-imp-co-dot'), 10);
			dot.classList.toggle('is-active', k === n);
			dot.classList.toggle('is-done', k < n);
		});
		var back = co$('[data-imp-co-back]');
		var next = co$('[data-imp-co-next]');
		if (back) { back.hidden = n === 1; }
		if (next) { next.hidden = n === 4; }
		if (n === 3) { coSummary(); }
		if (n === 4) {
			var err = co$('[data-imp-co-error]');
			if (err) { err.hidden = true; }
		}
	}

	function coSyncPrices() {
		if (!coRoot) { return; }
		var life = coState.billing === 'lifetime';
		co$$('.imp-co-plan').forEach(function (card) {
			var plan = card.getAttribute('data-imp-co-plan');
			var price = card.querySelector('[data-imp-co-price]');
			var per = card.querySelector('[data-imp-co-per]');
			if (price && coPrices[plan]) { price.textContent = coPrices[plan][coState.billing]; }
			if (per) { per.textContent = life ? 'one-time' : '/ year'; }
		});
	}

	function coSyncPlans() {
		co$$('.imp-co-plan').forEach(function (card) {
			var input = card.querySelector('input');
			if (!input) { return; }
			card.classList.toggle('is-picked', input.checked);
			if (input.checked) { coState.plan = input.value; }
		});
	}

	function coFields() {
		var name = co$('[name="imp_co_name"]');
		var email = co$('[name="imp_co_email"]');
		var whats = co$('[name="imp_co_whatsapp"]');
		return {
			name: name ? name.value.trim() : '',
			email: email ? email.value.trim() : '',
			whatsapp: whats ? whats.value.trim() : ''
		};
	}

	function coValidate() {
		var f = coFields();
		var nameInput = co$('[name="imp_co_name"]');
		var emailInput = co$('[name="imp_co_email"]');
		var ok = true;
		if (nameInput) { nameInput.classList.toggle('is-bad', !f.name); }
		if (emailInput) { emailInput.classList.toggle('is-bad', !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email)); }
		if (!f.name || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email)) {
			toast('Enter your name and a valid e-mail — the license key is sent there.', 'warning');
			ok = false;
		}
		return ok;
	}

	function coSetText(sel, text) {
		var el = co$(sel);
		if (el) { el.textContent = text; }
	}

	function coSummary() {
		var f = coFields();
		var life = coState.billing === 'lifetime';
		coSetText('[data-imp-co-sum-plan]', coState.plan === 'business' ? 'Business — 5 sites' : 'Personal — 1 site');
		coSetText('[data-imp-co-sum-billing]', life ? 'Lifetime (one-time)' : 'Yearly');
		coSetText('[data-imp-co-sum-sites]', coState.plan === 'business' ? '5' : '1');
		coSetText('[data-imp-co-sum-name]', f.name || '—');
		coSetText('[data-imp-co-sum-email]', f.email || '—');
		coSetText('[data-imp-co-sum-total]', coPrices[coState.plan][coState.billing]);
	}

	function coCopy(text) {
		var done = function () { toast('Order details copied — paste them into your e-mail or WhatsApp message.', 'success'); };
		var fallback = function () {
			try {
				var area = document.createElement('textarea');
				area.value = text;
				area.style.cssText = 'position:fixed;left:-9999px;top:0;';
				document.body.appendChild(area);
				area.select();
				document.execCommand('copy');
				area.remove();
			} catch (e) { /* silencieux */ }
		};
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done, function () { fallback(); done(); });
		} else {
			fallback();
			done();
		}
	}

	function coError(message) {
		var box = co$('[data-imp-co-error]');
		if (!box) { toast(message, 'error'); return; }
		box.textContent = message;
		box.hidden = false;
	}

	function coShowSuccess() {
		var act = co$('[data-imp-co-act]');
		var ok = co$('[data-imp-co-success]');
		if (act) { act.hidden = true; }
		if (ok) { ok.hidden = false; }
		coGo(4);
	}

	function openCheckout(plan) {
		if (!coRoot) {
			window.open(cfg.proUrl, '_blank');
			return;
		}
		if (plan === 'business' || plan === 'personal') {
			var input = coRoot.querySelector('.imp-co-plan[data-imp-co-plan="' + plan + '"] input');
			if (input) {
				input.checked = true;
				coSyncPlans();
			}
		}
		coSyncPrices();
		coRoot.hidden = false;
		document.body.classList.add('imp-co-lock');
		if (cfg.isPro) {
			coShowSuccess();
		} else {
			var act = co$('[data-imp-co-act]');
			var ok = co$('[data-imp-co-success]');
			if (act) { act.hidden = false; }
			if (ok) { ok.hidden = true; }
			coGo(1);
		}
	}

	function closeCheckout() {
		if (!coRoot) { return; }
		coRoot.hidden = true;
		document.body.classList.remove('imp-co-lock');
	}

	if (coRoot) {
		co$('[data-imp-co-billing="yearly"]').addEventListener('click', function () {
			coState.billing = 'yearly';
			co$$('[data-imp-co-billing]').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-imp-co-billing') === 'yearly'); });
			coSyncPrices();
		});
		co$('[data-imp-co-billing="lifetime"]').addEventListener('click', function () {
			coState.billing = 'lifetime';
			co$$('[data-imp-co-billing]').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-imp-co-billing') === 'lifetime'); });
			coSyncPrices();
		});

		co$$('.imp-co-plan input').forEach(function (input) {
			input.addEventListener('change', coSyncPlans);
		});

		var cmpLink = co$('[data-imp-co-compare]');
		var cmpBox = coRoot.querySelector('.imp-co-compare');
		if (cmpLink && cmpBox) {
			cmpLink.addEventListener('click', function () {
				cmpBox.hidden = !cmpBox.hidden;
				cmpLink.textContent = cmpBox.hidden ? 'Compare Free vs Pro ▾' : 'Hide comparison ▴';
			});
		}

		co$('[data-imp-co-next]').addEventListener('click', function () {
			if (coState.step === 2 && !coValidate()) { return; }
			coGo(Math.min(4, coState.step + 1));
		});
		co$('[data-imp-co-back]').addEventListener('click', function () {
			coGo(Math.max(1, coState.step - 1));
		});

		co$$('[data-imp-co-close]').forEach(function (el) {
			el.addEventListener('click', closeCheckout);
		});
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !coRoot.hidden) { closeCheckout(); }
		});

		var copyBtn = co$('[data-imp-co-copy]');
		if (copyBtn) {
			copyBtn.addEventListener('click', function () {
				var f = coFields();
				var site = co$('[data-imp-co-site]');
				var text = [
					'Infinity MigrateX Pro — order',
					'Plan: ' + (coState.plan === 'business' ? 'Business (5 sites)' : 'Personal (1 site)'),
					'Billing: ' + (coState.billing === 'lifetime' ? 'Lifetime' : 'Yearly'),
					'Total: ' + coPrices[coState.plan][coState.billing],
					'Name: ' + (f.name || '—'),
					'E-mail: ' + (f.email || '—'),
					f.whatsapp ? 'WhatsApp: ' + f.whatsapp : '',
					'Site: ' + (site ? site.value : '')
				].filter(Boolean).join('\n');
				coCopy(text);
			});
		}

		co$('[data-imp-co-activate]').addEventListener('click', function () {
			var btn = this;
			var input = co$('[name="imp_co_key"]');
			var key = input ? input.value.trim() : '';
			var err = co$('[data-imp-co-error]');
			if (err) { err.hidden = true; }
			if (!key) {
				coError('Paste the license key you received by e-mail.');
				return;
			}
			btn.disabled = true;
			btn.textContent = 'Verifying…';
			ajax('imp_settings_save', { license_key: key }).then(function (json) {
				btn.disabled = false;
				btn.textContent = 'Activate Pro';
				if (json && json.success) {
					toast(json.data.message, 'success');
					impConfetti('checkout-pro');
					coShowSuccess();
				} else {
					coError((json && json.data && json.data.message) || i18n.error);
				}
			}).catch(function () {
				btn.disabled = false;
				btn.textContent = 'Activate Pro';
				coError(i18n.networkError || 'Connection lost — try again.');
			});
		});

		var finishBtn = co$('[data-imp-co-finish]');
		if (finishBtn) {
			finishBtn.addEventListener('click', function () {
				window.location.reload();
			});
		}

		coSyncPlans();
	}

	/* Déclencheurs : boutons d'achat + cartes Pro floutées. */
	document.addEventListener('click', function (event) {
		var btn = event.target.closest ? event.target.closest('[data-imp-checkout]') : null;
		if (btn) {
			event.preventDefault();
			var plan = btn.getAttribute('data-imp-checkout');
			openCheckout(plan === 'business' ? 'business' : (plan === 'personal' ? 'personal' : ''));
			return;
		}
		var card = event.target.closest ? event.target.closest('.imp-pro-card') : null;
		if (card && !event.target.closest('.imp-btn')) {
			openCheckout('');
		}
	});
})();
