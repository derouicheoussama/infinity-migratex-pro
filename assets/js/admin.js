/**
 * ∞ INFINITY CODER — création originale de Derouiche Oussama
 * Plugin : Infinity MigrateX Pro · https://www.derouicheoussama.com
 * Copyright © 2026 Derouiche Oussama. Licence GPL v2+ —
 * toute copie ou modification doit conserver cette signature.
 *
 * Infinity MigrateX Pro — interface d'administration.
 * Vanilla JS, aucune dépendance. Le runner de jobs interroge le serveur
 * pas à pas : la progression affichée correspond au travail réellement
 * effectué (fichiers / octets / lignes traités côté serveur).
 */
/* global IMP_Admin, wp, tb_remove */
(function () {
	'use strict';

	if (typeof IMP_Admin === 'undefined') {
		return;
	}

	var cfg = IMP_Admin;
	var i18n = cfg.i18n || {};

	/* Thème auto : suit le système quand le réglage est sur "auto". */
	(function themeApplier() {
		if (cfg.theme !== 'auto') { return; }
		var mq = window.matchMedia('(prefers-color-scheme: dark)');
		var apply = function () {
			document.body.classList.toggle('imp-dark', mq.matches);
		};
		if (mq.addEventListener) { mq.addEventListener('change', apply); }
		apply();
	})();

	/* Barre de progression persistante : rafraîchit toutes les 8 s tant
	 * qu'un job tourne, sur n'importe quelle page du plugin. Ré-interroge
	 * le DOM à chaque tick (survit aux navigations SPA). */
	(function topProgressPoller() {
		if (window.__impProgressPoller) { return; }
		window.__impProgressPoller = true;
		var timer = window.setInterval(function () {
			var freshCfg = window.IMP_Admin || cfg;
			var wrap = document.querySelector('[data-imp-top-progress]');
			var pct = wrap ? wrap.querySelector('[data-imp-top-pct]') : null;
			if (!wrap || !pct || !freshCfg.job || freshCfg.job.status !== 'running') { return; }
			ajax('imp_job_status').then(function (json) {
				var s = json && json.data && json.data.status;
				if (!s) { return; }
				if (s.status !== 'running') {
					window.clearInterval(timer);
					window.location.reload();
					return;
				}
				var fill = wrap.querySelector('.imp-top-progress-fill');
				if (fill) { fill.style.width = Math.max(0, Math.min(100, s.percent)) + '%'; }
				pct.textContent = (Math.round(s.percent * 10) / 10) + '%';
			}).catch(function () { /* silencieux */ });
		}, 8000);
	})();

	/* Compteurs animés du dashboard. */
	(function counters() {
		if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
		$$('[data-imp-count]').forEach(function (el) {
			var target = parseInt(el.getAttribute('data-imp-count'), 10) || 0;
			if (!target) { el.textContent = target.toLocaleString(); return; }
			var start = null;
			var dur = 900;
			var step = function (ts) {
				if (!start) { start = ts; }
				var p = Math.min(1, (ts - start) / dur);
				var eased = 1 - Math.pow(1 - p, 3);
				el.textContent = Math.round(target * eased).toLocaleString();
				if (p < 1) { window.requestAnimationFrame(step); }
			};
			window.requestAnimationFrame(step);
		});
	})();

	/* Confettis discrets à la réussite d'un job (une fois par job). */
	function impConfetti(jobId) {
		try {
			var key = 'imp_confetti_' + jobId;
			if (sessionStorage.getItem(key)) { return; }
			sessionStorage.setItem(key, '1');
			if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
			var colors = ['#2f5fe0', '#6d3ae8', '#34d399', '#fbbf24'];
			for (var i = 0; i < 36; i++) {
				var p = document.createElement('div');
				p.style.cssText = 'position:fixed;z-index:100002;width:9px;height:9px;border-radius:2px;' +
					'left:' + (40 + Math.random() * 20) + 'vw;top:-12px;pointer-events:none;' +
					'background:' + colors[i % colors.length] + ';opacity:1;';
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

	/* ------------------------------------------------------------------ *
	 * Petits utilitaires
	 * ------------------------------------------------------------------ */

	function $(sel, ctx) {
		return (ctx || document).querySelector(sel);
	}

	function $$(sel, ctx) {
		return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
	}

	function esc(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function fmtBytes(bytes) {
		bytes = Number(bytes) || 0;
		if (bytes < 1) { return '0 B'; }
		var units = ['B', 'KB', 'MB', 'GB', 'TB'];
		var i = 0;
		while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
		return (i === 0 ? Math.round(bytes) : bytes.toFixed(1)) + ' ' + units[i];
	}

	var toastBox = null;

	function ensureToastBox() {
		if (!toastBox) {
			toastBox = document.createElement('div');
			toastBox.id = 'imp-toast';
			document.body.appendChild(toastBox);
		}
		return toastBox;
	}

	function toast(message, kind) {
		var box = ensureToastBox();
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

	/**
	 * POST admin-ajax avec nonce + FormData.
	 */
	function ajax(action, data) {
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
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: form
		}).then(function (response) {
			return response.json().catch(function () {
				throw new Error('bad-json');
			});
		});
	}

	/**
	 * Confirmation (respecte le réglage "confirmation obligatoire").
	 */
	function askConfirm(kind) {
		if (!cfg.confirm) { return true; }
		var messages = {
			delete: i18n.confirmDelete,
			restore: i18n.confirmRestore,
			import: i18n.confirmImport,
			replace: i18n.confirmReplace
		};
		var message = messages[kind] || i18n.confirmDelete;
		return window.confirm(message);
	}

	function busy(button, isBusy) {
		if (!button) { return; }
		if (isBusy) {
			button.dataset.impLabel = button.innerHTML;
			button.classList.add('is-busy');
			button.innerHTML = esc(i18n.working);
		} else if (button.dataset.impLabel) {
			button.classList.remove('is-busy');
			button.innerHTML = button.dataset.impLabel;
			delete button.dataset.impLabel;
		}
	}

	/* ------------------------------------------------------------------ *
	 * Runner de jobs : progression réelle, pause / reprise / annulation
	 * ------------------------------------------------------------------ */

	function JobRunner(box) {
		this.box = box;
		this.timer = null;
		this.stopped = true;
		this.onDone = null;
		this.consecutiveErrors = 0;
	}

	JobRunner.prototype.render = function (status) {
		var html = '';
		var i;
		var phase;

		// ETA : extrapolée sur la vitesse réelle des derniers lots.
		if (status.status === 'running' && !this.samples) { this.samples = []; }
		if (status.status === 'running') {
			this.samples.push({ p: Number(status.percent) || 0, t: Date.now() });
			if (this.samples.length > 12) { this.samples.shift(); }
			var first = this.samples[0];
			var last = this.samples[this.samples.length - 1];
			var span = (last.t - first.t) / 1000;
			var etaTxt = '';
			if (span > 4 && last.p > first.p) {
				var rate = (last.p - first.p) / span;
				if (rate > 0.02 && last.p < 99.5) {
					var secs = Math.round((100 - last.p) / rate);
					etaTxt = secs >= 90 ? ' · ≈ ' + Math.round(secs / 60) + ' min' : ' · ≈ ' + secs + ' s';
				}
			}
			this._eta = etaTxt;
		}

		if (status.status === 'none') {
			this.box.innerHTML = '';
			return;
		}

		var pct = Math.max(0, Math.min(100, Number(status.percent) || 0));

		html += '<div class="imp-progress-global">';
		html += '<div class="imp-progress-topline"><span>' + esc(status.message || status.status) + '</span><span class="imp-progress-percent">' + pct.toFixed(1) + '%' + (this._eta ? '<small style="font-size:12px;font-weight:600;">' + esc(this._eta) + '</small>' : '') + '</span></div>';
		html += '<div class="imp-bar"><div class="imp-bar-fill' + (status.status === 'running' ? ' is-active' : '') + '" style="width:' + pct + '%"></div></div>';
		html += '</div>';

		if (status.phases && status.phases.length) {
			html += '<div class="imp-phases">';
			for (i = 0; i < status.phases.length; i++) {
				phase = status.phases[i];
				var ppct = Math.max(0, Math.min(100, Number(phase.percent) || 0));
				html += '<div class="imp-phase' + (phase.current ? ' is-current' : '') + '">';
				html += '<div class="imp-phase-label"><span>' + (phase.current ? '▸ ' : '') + esc(phase.label) + '</span><span>' + ppct.toFixed(0) + '%</span></div>';
				html += '<div class="imp-bar"><div class="imp-bar-fill' + (phase.current ? ' is-active' : '') + '" style="width:' + ppct + '%"></div></div>';
				html += '</div>';
			}
			html += '</div>';
		}

		html += '<div class="imp-job-statusline"><span>' + esc(status.elapsed ? status.elapsed + 's' : '') + '</span>';
		html += '<span class="imp-job-status-badge imp-badge imp-badge-' + (status.status === 'completed' ? 'pass' : (status.status === 'failed' ? 'fail' : (status.status === 'canceled' ? 'neutral' : 'info'))) + '">' + esc(status.status) + '</span></div>';

		if (status.message) {
			html += '<div class="imp-job-current">' + esc(status.message) + '</div>';
		}

		if (status.messages && status.messages.length) {
			html += '<div class="imp-job-log">';
			for (i = status.messages.length - 1; i >= 0 && i >= status.messages.length - 8; i--) {
				html += '<div>' + esc(status.messages[i]) + '</div>';
			}
			html += '</div>';
		}

		if (status.status === 'running') {
			html += '<div class="imp-job-actions">';
			html += '<button type="button" class="imp-btn imp-btn-ghost imp-btn-small" data-imp-job="pause">' + esc(i18n.pause) + '</button>';
			html += '<button type="button" class="imp-btn imp-btn-danger imp-btn-small" data-imp-job="cancel">' + esc(i18n.cancel) + '</button>';
			html += '</div>';
		}

		if (status.status === 'completed' && status.result) {
			html += '<div class="imp-job-result">' + renderResult(status.type, status.result) + '</div>';
		}

		if (status.status === 'failed') {
			html += '<div class="imp-job-result imp-job-result-failed"><strong>' + esc(status.error || 'IMP-299') + '</strong><br>' + esc(status.error_text || '') + '</div>';
		}

		if (status.status === 'canceled') {
			html += '<div class="imp-notice imp-notice-warning" style="margin:0">' + esc(i18n.paused) + ' — <button type="button" class="imp-btn imp-btn-small imp-btn-primary" data-imp-job="resume">' + esc(i18n.resume) + '</button></div>';
		}

		this.box.innerHTML = html;

		// Publie le statut pour les pages qui affichent un pipeline vivant.
		window.dispatchEvent(new CustomEvent('imp:jobupdate', { detail: status }));

		var self = this;
		$$('[data-imp-job]', this.box).forEach(function (btn) {
			btn.addEventListener('click', function () {
				var what = this.getAttribute('data-imp-job');
				if (what === 'pause') {
					self.pause();
					toast(i18n.paused, 'warning');
				} else if (what === 'cancel') {
					if (askConfirm('delete')) {
						ajax('imp_job_cancel').then(function () {
							self.pause();
							self.refreshOnce();
						});
					}
				} else if (what === 'resume') {
					self.startLoop();
				}
			});
		});
	};

	function renderResult(type, result) {
		var lines = [];
		var key;

		if (!result) { return ''; }

		if (result.counts) {
			if (result.counts.files) { lines.push(impT('Files: %s', result.counts.files)); }
			if (result.counts.db_tables) { lines.push(impT('Tables: %s', result.counts.db_tables)); }
			if (result.counts.db_rows) { lines.push(impT('Rows: %s', result.counts.db_rows)); }
		}
		if (result.sizes && result.sizes.total) {
			lines.push(impT('Size: %s', fmtBytes(result.sizes.total)));
		}
		if (result.integrity) {
			key = result.integrity;
			if (key.restored_files) { lines.push(impT('Files restored: %s', key.restored_files)); }
			if (key.restored_statements) { lines.push(impT('SQL statements: %s', key.restored_statements)); }
			if (key.checked) { lines.push(impT('Integrity verified: %s / %s files identical', key.ok, key.checked)); }
		}
		if (typeof result.values_changed !== 'undefined') {
			lines.push(impT('Values changed: %s', result.values_changed));
			lines.push(impT('Rows affected: %s', result.rows_changed));
			if (result.dry_run) { lines.push(impT('(dry run — nothing written)')); }
		}
		if (result.new_url) { lines.push(impT('New URL: %s', result.new_url)); }
		if (result.dest) { lines.push(impT('Destination: %s', result.dest)); }
		if (result.elementor) { lines.push(impT('Tip: regenerate Elementor CSS from the Integrations page.')); }

		var html = '';
		for (var i = 0; i < lines.length; i++) {
			html += '<div>' + esc(lines[i]) + '</div>';
		}
		return html;
	}

	function impT(text) {
		// Traduction minimale : le serveur fournit déjà les messages finaux ;
		// ici on construit des lignes de synthèse lisibles.
		var args = Array.prototype.slice.call(arguments, 1);
		var out = text;
		args.forEach(function (value, index) {
			out = out.replace('%s', String(value));
		});
		return out;
	}

	JobRunner.prototype.startLoop = function () {
		var self = this;
		this.stopped = false;
		this.box.dispatchEvent(new CustomEvent('imp:jobstart'));

		var step = function () {
			if (self.stopped) { return; }
			ajax('imp_job_step').then(function (json) {
				if (self.stopped) { return; }
				self.consecutiveErrors = 0;

				if (json && json.success && json.data && json.data.status) {
					self.render(json.data.status);
					var status = json.data.status;

					if (status.status === 'running') {
						self.timer = window.setTimeout(step, 700);
					} else {
						self.stopped = true;
						self.box.dispatchEvent(new CustomEvent('imp:jobdone', { detail: status }));
						if (self.onDone) { self.onDone(status); }
						if (status.status === 'completed') {
							impConfetti(status.job_id || 'job');
							window.setTimeout(function () { window.location.reload(); }, 2500);
						}
					}
				} else {
					self.fail(json);
				}
			}).catch(function () {
				self.fail(null);
			});
		};

		step();
	};

	JobRunner.prototype.fail = function (json) {
		var self = this;
		this.consecutiveErrors++;

		if (json && json.data && json.data.message) {
			toast(json.data.message, 'error');
			this.stopped = true;
			if (this.onDone) { this.onDone(json.data); }
			return;
		}

		if (this.consecutiveErrors >= 4) {
			this.stopped = true;
			this.pause();
			toast(i18n.networkError, 'warning');
		} else {
			this.timer = window.setTimeout(function () {
				if (!self.stopped) { self.startLoop(); }
			}, 2500);
		}
	};

	JobRunner.prototype.pause = function () {
		this.stopped = true;
		if (this.timer) {
			window.clearTimeout(this.timer);
			this.timer = null;
		}
	};

	JobRunner.prototype.refreshOnce = function () {
		var self = this;
		ajax('imp_job_status').then(function (json) {
			if (json && json.success && json.data && json.data.status) {
				self.render(json.data.status);
			}
		});
	};

	/**
	 * Démarre un job puis lance la boucle — avec file d'attente
	 * (ex: backup de sécurité PUIS migration).
	 */
	function runJobs(queue, box, onAllDone) {
		var index = 0;
		var results = [];

		var next = function () {
			if (index >= queue.length) {
				if (onAllDone) { onAllDone(results); }
				return;
			}
			var jobSpec = queue[index++];
			var runner = new JobRunner(box);
			runner.onDone = function (status) {
				results.push(status);
				if (status.status === 'completed' || status.status === 'canceled') {
					if (status.status === 'canceled') { return; }
					next();
				} else if (status.status === 'failed' && status.error) {
					// Arrêt de la file : le suivant ne peut pas s'exécuter.
					return;
				}
			};

			if (jobSpec.endpoint) {
				ajax(jobSpec.endpoint, jobSpec.data).then(function (json) {
					if (json && json.success) {
						runner.startLoop();
					} else {
						var message = (json && json.data && json.data.message) || i18n.error;
						toast(message, 'error');
						if (json && json.data && json.data.checks) {
							renderPreflightInline(box, json.data.checks);
						}
					}
				}).catch(function () {
					toast(i18n.networkError, 'error');
				});
			} else {
				ajax('imp_job_start', {
					type: jobSpec.type,
					data: JSON.stringify(jobSpec.data || {})
				}).then(function (json) {
					if (json && json.success) {
						runner.startLoop();
					} else {
						toast((json && json.data && json.data.message) || i18n.error, 'error');
					}
				}).catch(function () {
					toast(i18n.networkError, 'error');
				});
			}
		};

		next();
	}

	function jobboxFor(type) {
		var box = $('[data-imp-jobbox="' + type + '"]');
		if (!box) {
			box = document.createElement('div');
			box.setAttribute('data-imp-jobbox', type);
			box.className = 'imp-jobbox';
			var target = $('.imp-content') || document.body;
			var panel = document.createElement('section');
			panel.className = 'imp-panel';
			panel.innerHTML = '<div class="imp-panel-head"><h3></h3></div>';
			panel.appendChild(box);
			target.insertBefore(panel, target.firstChild);
		}
		return box;
	}

	/* ------------------------------------------------------------------ *
	 * Reprise automatique d'un job en cours (toutes pages)
	 * ------------------------------------------------------------------ */

	/* ------------------------------------------------------------------ *
	 * Menu : indicateur coulissant + drawer mobile
	 * ------------------------------------------------------------------ */

	(function navIndicator() {
		var nav = $('.imp-nav');
		if (!nav) { return; }

		var active = $('.imp-nav-item.is-active', nav);
		var indicator = document.createElement('span');
		indicator.className = 'imp-nav-indicator';
		nav.appendChild(indicator);

		var moveTo = function (item) {
			if (!item) { indicator.style.opacity = '0'; return; }
			indicator.style.opacity = '1';
			indicator.style.height = item.offsetHeight + 'px';
			indicator.style.transform = 'translateY(' + item.offsetTop + 'px)';
		};

		moveTo(active);

		$$('.imp-nav-item', nav).forEach(function (item) {
			item.addEventListener('mouseenter', function () { moveTo(item); });
		});
		nav.addEventListener('mouseleave', function () { moveTo(active); });
		window.addEventListener('resize', function () {
			active = $('.imp-nav-item.is-active', nav);
			moveTo(active);
		});
	})();

	(function navDrawer() {
		var sidebar = $('.imp-sidebar');
		var toggle = $('[data-imp-nav-toggle]');
		var backdrop = $('[data-imp-backdrop]');
		if (!sidebar || !toggle) { return; }

		var setOpen = function (open) {
			sidebar.classList.toggle('is-open', open);
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (backdrop) { backdrop.classList.toggle('is-visible', open); }
		};

		toggle.addEventListener('click', function () {
			setOpen(!sidebar.classList.contains('is-open'));
		});
		if (backdrop) {
			backdrop.addEventListener('click', function () { setOpen(false); });
		}
		$$('.imp-nav-item', sidebar).forEach(function (item) {
			item.addEventListener('click', function () { setOpen(false); });
		});
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') { setOpen(false); }
		});
	})();

	/* ------------------------------------------------------------------ *
	 * Navigation INSTANTANÉE entre les pages du plugin (SPA maison).
	 *
	 * Survol → la page cible est récupérée et parsée EN ARRIÈRE-PLAN
	 * (garde en mémoire JS : les en-têtes no-cache de l'admin WP rendaient
	 * <link rel=prefetch> inutile). Clic → seul le contenu du plugin est
	 * échangé (le chrome WP reste), puis localize + admin.js sont
	 * ré-exécutés pour relier la nouvelle page. Fallback complet si le
	 * fetch échoue. Bouton retour du navigateur géré via history.
	 * ------------------------------------------------------------------ */

	(function spaNav() {
		if (!document.querySelector('.imp-wrap')) { return; }
		if (window.__impSpaBound) { return; }
		window.__impSpaBound = true;

		var cache = window.__impNavCache = window.__impNavCache || new Map();
		var MAX_CACHE = 8;
		var FRESH_MS = 90000;

		function isSpaLink(a) {
			if (!a || a.target === '_blank' || a.hasAttribute('download')) { return false; }
			if (a.hostname !== window.location.hostname || a.protocol !== window.location.protocol) { return false; }
			return a.pathname.indexOf('admin.php') !== -1 && a.search.indexOf('page=infinity-migratex-pro') !== -1;
		}

		function prune() {
			while (cache.size > MAX_CACHE) {
				var oldest = cache.keys().next().value;
				cache.delete(oldest);
			}
		}

		function fetchPage(url) {
			/* XHR : same-origin PAR CONSTRUCTION (un XHR ne peut pas lire
			 * une autre origine sans CORS) — la navigation ne peut charger
			 * que la propre admin de ce site. */
			return new Promise(function (resolve, reject) {
				var xhr = new XMLHttpRequest();
				xhr.open('GET', url, true);
				xhr.setRequestHeader('X-Requested-With', 'imp-spa');
				xhr.onload = function () {
					if (xhr.status < 200 || xhr.status >= 300) { reject(new Error('HTTP ' + xhr.status)); return; }
					var doc = new DOMParser().parseFromString(xhr.responseText, 'text/html');
					if (!doc.querySelector('.imp-wrap')) { reject(new Error('unexpected')); return; }
					cache.set(url, { doc: doc, at: Date.now() });
					prune();
					resolve(doc);
				};
				xhr.onerror = function () { reject(new Error('network')); };
				xhr.send();
			});
		}

		function swap(doc, url, push) {
			var newWrap = doc.querySelector('.imp-wrap');
			var cur = document.querySelector('.imp-wrap');
			if (!newWrap || !cur) { window.location.href = url; return; }

			/* Éléments body-level périmés (FAB des vagues précédentes). */
			Array.prototype.forEach.call(document.querySelectorAll('body > .imp-fab'), function (el) { el.remove(); });

			cur.replaceWith(newWrap);
			if (doc.title) { document.title = doc.title; }
			window.scrollTo(0, 0);

			/* Re-lie la nouvelle page : les scripts du document récupéré
			 * (localize IMP_Admin + admin.js, .min ou source) sont
			 * ré-exécutés — chaque module se rattache aux éléments frais. */
			Array.prototype.forEach.call(doc.querySelectorAll('script'), function (s) {
				var isLocalize = !s.src && s.textContent.indexOf('var IMP_Admin') !== -1;
				var isAdminJs = s.src && s.src.indexOf('assets/js/admin') !== -1;
				if (!isLocalize && !isAdminJs) { return; }
				var fresh = document.createElement('script');
				if (s.src) { fresh.src = s.src; } else { fresh.textContent = s.textContent; }
				document.body.appendChild(fresh);
			});

			if (push && window.history && window.history.pushState) {
				window.history.pushState({ impSpa: true }, '', url);
			}
		}

		function navigate(url, push) {
			var hit = cache.get(url);
			if (hit && Date.now() - hit.at < FRESH_MS) {
				swap(hit.doc, url, push);
				return;
			}
			document.body.classList.add('imp-nav-loading');
			fetchPage(url).then(function (doc) {
				swap(doc, url, push);
			}).catch(function () {
				window.location.href = url;
			}).then(function () {
				document.body.classList.remove('imp-nav-loading');
			});
		}

		document.addEventListener('click', function (event) {
			if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }
			var link = event.target.closest ? event.target.closest('a.imp-nav-item') : null;
			if (!link || !isSpaLink(link)) { return; }
			event.preventDefault();
			navigate(link.href, true);
		});

		var hoverTimer = null;
		document.addEventListener('mouseover', function (event) {
			var link = event.target.closest ? event.target.closest('a.imp-nav-item') : null;
			if (!link || !isSpaLink(link) || cache.has(link.href)) { return; }
			if (hoverTimer) { window.clearTimeout(hoverTimer); }
			hoverTimer = window.setTimeout(function () {
				fetchPage(link.href).catch(function () { /* silencieux */ });
			}, 80);
		}, { passive: true });

		window.addEventListener('popstate', function () {
			if (window.location.search.indexOf('page=infinity-migratex-pro') !== -1) {
				navigate(window.location.href, false);
			}
		});

		/* Préchauffe paresseux : 3 s après l'arrivée sur une page du
		 * plugin, récupère Dashboard + Réglages (les deux destinations
		 * les plus fréquentes) — une seule fois par session d'admin, les
		 * pages suivantes se préchargent déjà au survol. */
		try {
			if (!window.sessionStorage.getItem('imp_spa_warm')) {
				window.sessionStorage.setItem('imp_spa_warm', '1');
				window.setTimeout(function () {
					var base = window.location.pathname + '?page=infinity-migratex-pro';
					['', '-settings'].forEach(function (suffix) {
						var url = base + suffix;
						if (url === window.location.pathname + window.location.search) { return; }
						fetchPage(url).catch(function () { /* silencieux */ });
					});
				}, 3000);
			}
		} catch (e) { /* sessionStorage indisponible */ }
	})();

	(function autoResume() {
		var job = cfg.job;
		if (!job || job.status !== 'running') { return; }

		var box = $('[data-imp-jobbox="' + job.type + '"]');
		var resumedBox = $('[data-imp-jobbox="resumed"]');

		if (box) {
			var runner = new JobRunner(box);
			runner.render(job);
			runner.startLoop();
			return;
		}

		if (resumedBox && (cfg.page === 'dashboard' || cfg.page === job.type)) {
			var msg = $('[data-imp-jobbox="resumed"]');
			if (job.message && msg) {
				var target = $('#imp-resumed-job-message');
				if (target) { target.textContent = job.message; }
			}
		}
	})();

	/* ------------------------------------------------------------------ *
	 * Actions globales (boutons data-imp-action)
	 * ------------------------------------------------------------------ */

	document.addEventListener('click', function (event) {
		var btn = event.target.closest ? event.target.closest('[data-imp-action]') : null;
		if (!btn) { return; }

		var action = btn.getAttribute('data-imp-action');

		/* ---- Actions job ---- */
		if (action === 'job-resume') {
			var resumeBox = jobboxFor(cfg.job ? cfg.job.type : 'backup');
			btn.closest('.imp-panel').style.display = 'none';
			var runner = new JobRunner(resumeBox);
			runner.render(cfg.job || {});
			runner.startLoop();
			return;
		}

		if (action === 'job-cancel') {
			if (!askConfirm('delete')) { return; }
			ajax('imp_job_cancel').then(function () { window.location.reload(); });
			return;
		}

		/* ---- Dashboard ---- */
		if (action === 'quick-backup') {
			runJobs([{
				type: 'backup',
				data: { name: i18n.working ? 'Quick backup' : 'backup', components: 'full' }
			}], jobboxFor('backup'));
			return;
		}

		if (action === 'quick-scan') {
			runJobs([{ type: 'scan', data: {} }], jobboxFor('scan'));
			return;
		}

		/* ---- WooCommerce : export depuis→vers ---- */
		if (action === 'wc-export-snapshot' || action === 'wc-export-package') {
			var what = action === 'wc-export-package' ? 'full site package (.infinitymigrate)' : 'database snapshot (all tables)';
			if (!window.confirm('Start the WooCommerce export — ' + what + '? The job runs in the background.')) { return; }
			busy(btn, true);
			ajax('imp_wc_action', { do: action.replace('wc-export-', 'export_') }).then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					toast('Export started — follow it on the Backups page.', 'success');
					window.setTimeout(function () {
						window.location.href = cfg.ajaxUrl.replace('admin-ajax.php', 'admin.php') + '?page=infinity-migratex-pro-backups';
					}, 900);
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			}).catch(function () {
				busy(btn, false);
				toast(i18n.networkError, 'error');
			});
			return;
		}

		if (action === 'health-refresh') {
			busy(btn, true);
			ajax('imp_health_refresh').then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					refreshHealth(json.data.checks);
					toast(json.data.overall === 'healthy' ? 'System healthy' : 'System reviewed', json.data.overall === 'healthy' ? 'success' : 'warning');
				}
			});
			return;
		}

		/* ---- Backups ---- */
		if (action === 'backup-cloud') {
			busy(btn, true);
			ajax('imp_backups_action', { do: 'cloud_send', id: btn.dataset.id }).then(function (json) {
				busy(btn, false);
				if (json && json.success && json.data.status) {
					var box = $('[data-imp-jobbox="backup"]') || jobboxFor('backup');
					var runner = new JobRunner(box);
					runner.render(json.data.status);
					runner.startLoop();
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			}).catch(function () {
				busy(btn, false);
				toast(i18n.networkError, 'error');
			});
			return;
		}

		if (action === 'cloud-test') {
			var providerSelect = $('[data-imp-cloud-provider]');
			var result = $('[data-imp-cloud-test-result]');
			var provider = providerSelect ? providerSelect.value : '';
			if (!provider) {
				toast('Pick a destination first.', 'warning');
				return;
			}
			var form = btn.closest('form');
			var payload = { provider: provider };
			if (form) {
				$$('[name^="imp_settings[cloud_"]', form).forEach(function (input) {
					var m = input.name.match(/imp_settings\[([a-z_]+)\]/);
					if (!m) { return; }
					payload[m[1]] = input.type === 'checkbox' ? (input.checked ? 1 : 0) : input.value;
				});
			}
			busy(btn, true);
			if (result) { result.textContent = '…'; }
			ajax('imp_cloud_test', payload).then(function (json) {
				busy(btn, false);
				var msg = (json && json.data && json.data.message) || i18n.error;
				if (result) {
					result.textContent = msg;
					result.style.color = (json && json.success) ? '#0f7a45' : '#c9281e';
				}
				if (json && json.success) { toast(msg, 'success'); } else { toast(msg, 'error'); }
			}).catch(function () {
				busy(btn, false);
				if (result) { result.textContent = i18n.networkError; }
			});
			return;
		}

		if (action === 'backup-verify') {
			busy(btn, true);
			var cell = btn.closest('tr').querySelector('[data-imp-checksum-state]');
			ajax('imp_backups_action', { do: 'verify', id: btn.dataset.id }).then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					if (cell) {
						cell.textContent = json.data.ok ? 'OK (sha256)' : 'FAILED';
						cell.style.color = json.data.ok ? '#0f7a45' : '#c9281e';
					}
					toast(json.data.ok ? 'Checksums verified.' : 'Checksum mismatch: backup corrupted.', json.data.ok ? 'success' : 'error');
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			});
			return;
		}

		if (action === 'backup-export') {
			busy(btn, true);
			ajax('imp_backups_action', { do: 'export', id: btn.dataset.id }).then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					toast(json.data.message, 'success');
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			});
			return;
		}

		if (action === 'backup-rename') {
			var name = window.prompt('New name:', btn.dataset.name || '');
			if (name === null || name.trim() === '') { return; }
			ajax('imp_backups_action', { do: 'rename', id: btn.dataset.id, name: name.trim() }).then(function (json) {
				if (json && json.success) {
					var label = btn.closest('tr').querySelector('.imp-backup-name');
					if (label) { label.textContent = json.data.name; }
					btn.dataset.name = json.data.name;
					toast('Backup renamed.', 'success');
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			});
			return;
		}

		if (action === 'backup-delete') {
			if (!askConfirm('delete')) { return; }
			busy(btn, true);
			ajax('imp_backups_action', { do: 'delete', id: btn.dataset.id }).then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					btn.closest('tr').remove();
					toast('Backup deleted.', 'success');
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			});
			return;
		}

		/* ---- Packages ---- */
		if (action === 'package-scan') {
			scanPackage({ server_file: btn.dataset.file });
			return;
		}

		if (action === 'package-delete') {
			if (!askConfirm('delete')) { return; }
			ajax('imp_package_action', { do: 'delete', file: btn.dataset.file }).then(function (json) {
				if (json && json.success) {
					btn.closest('tr').remove();
					toast('Package deleted.', 'success');
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			});
			return;
		}

		if (action === 'package-import-confirm') {
			var ack = $('[data-imp-import-ack]');
			if (ack && !ack.checked) {
				toast('Tick the confirmation checkbox first.', 'warning');
				return;
			}
			if (!askConfirm('import')) { return; }
			runJobs([{
				endpoint: 'imp_package_start_import',
				data: { file: window.impPendingPackage }
			}], $('[data-imp-jobbox="package_import"]') || jobboxFor('package_import'));
			return;
		}

		/* ---- URL Replace ---- */
		if (action === 'replace-preview') {
			var from = $('#imp_replace_from').value.trim();
			var to = $('#imp_replace_to').value.trim();
			busy(btn, true);
			ajax('imp_replace_preview', { from: from, to: to }).then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					$('[data-imp-replace-preview-empty]').style.display = 'none';
					var box = $('[data-imp-replace-preview]');
					box.hidden = false;
					$('[data-imp-stat="tables"]').textContent = json.data.tables;
					$('[data-imp-stat="rows"]').textContent = json.data.rows;
					$('[data-imp-stat="matches"]').textContent = json.data.matches;
					$('[data-imp-stat="errors"]').textContent = json.data.errors || 0;
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			});
			return;
		}

		/* ---- Database ---- */
		if (action === 'db-export') {
			runJobs([{
				endpoint: 'imp_db_action',
				data: { do: 'export' }
			}], $('[data-imp-jobbox="backup"]') || jobboxFor('backup'));
			return;
		}

		if (action === 'db-refresh') {
			busy(btn, true);
			ajax('imp_db_tables').then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					$('[data-imp-db-tables]').innerHTML = json.data.html;
					$('[data-imp-db-summary]').innerHTML = json.data.summary;
				}
			});
			return;
		}

		if (action === 'db-maint') {
			var tables = $$('[data-imp-db-table]:checked').map(function (input) { return input.value; });
			if (!tables.length) {
				toast('Select at least one table.', 'warning');
				return;
			}
			if (!askConfirm('replace')) { return; }
			busy(btn, true);
			ajax('imp_db_action', { do: 'maint', mode: btn.dataset.mode, tables: tables }).then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					var okCount = 0;
					var failCount = 0;
					json.data.results.forEach(function (item) {
						if (item.status === 'error') { failCount++; } else if (item.status === 'ok') { okCount++; }
					});
					toast(btn.dataset.mode + ': ' + okCount + ' OK, ' + failCount + ' issue(s)', failCount ? 'warning' : 'success');
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			});
			return;
		}

		/* ---- Tools ---- */
		if (action === 'tool-cleanup' || action === 'tool-clearcache' || action === 'tool-flush' || action === 'tool-elementor') {
			var map = {
				'tool-cleanup': { do: 'cleanup' },
				'tool-clearcache': { do: 'clearcache' },
				'tool-flush': { do: 'flush' },
				'tool-elementor': { do: 'elementor' }
			};
			busy(btn, true);
			ajax('imp_tools_action', map[action]).then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					toast(json.data.message, 'success');
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			});
			return;
		}

		if (action === 'tool-permissions') {
			busy(btn, true);
			ajax('imp_tools_action', { do: 'permissions' }).then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					var lines = json.data.results.map(function (item) {
						return (item.ok ? '✓ ' : '✗ ') + item.msg;
					});
					toast(lines.join('\n'), json.data.results.every(function (item) { return item.ok; }) ? 'success' : 'warning');
				}
			});
			return;
		}

		if (action === 'system-check') {
			busy(btn, true);
			ajax('imp_system_check').then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					renderChecksTable($('[data-imp-systemcheck-body]'), json.data.checks);
					$('[data-imp-systemcheck]').hidden = false;
					toast('System check complete: ' + json.data.overall, json.data.overall === 'healthy' ? 'success' : 'warning');
				}
			});
			return;
		}

		/* ---- Security ---- */
		if (action === 'security-resecure') {
			busy(btn, true);
			ajax('imp_tools_action', { do: 'resecure' }).then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					toast(json.data.message, 'success');
					window.location.reload();
				}
			});
			return;
		}

		if (action === 'security-integrity') {
			busy(btn, true);
			ajax('imp_security_action', { do: 'integrity' }).then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					var d = json.data;
					toast(d.ok
						? 'Source code verified: ' + d.count + ' files match the sealed baseline.'
						: 'INTEGRITY ALERT — ' + d.changed + ' modified, ' + d.missing + ' missing, ' + d.added + ' unexpected.', d.ok ? 'success' : 'error');
					window.location.reload();
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			});
			return;
		}

		if (action === 'security-reseal') {
			if (!window.confirm('Re-sealing overwrites the integrity baseline with the CURRENT files. Only do this after an official update or your own verified change. Continue?')) { return; }
			busy(btn, true);
			ajax('imp_security_action', { do: 'reseal' }).then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					toast(json.data.message, 'success');
					window.location.reload();
				}
			});
			return;
		}

		if (action === 'security-cleanup') {
			ajax('imp_tools_action', { do: 'cleanup' }).then(function (json) {
				if (json && json.success) { toast(json.data.message, 'success'); }
			});
			return;
		}

		if (action === 'wc-tool') {
			var tool = btn.dataset.tool;
			if (tool === 'sessions' && !askConfirm('delete')) { return; }
			busy(btn, true);
			ajax('imp_wc_action', { do: 'tool', tool: tool }).then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					toast(json.data.message, 'success');
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			}).catch(function () {
				busy(btn, false);
				toast(i18n.networkError, 'error');
			});
			return;
		}

		/* ---- Integrations ---- */
		if (action === 'integration-elementor-regen' || action === 'integration-wc-transients' || action === 'integration-clear-cache' || action === 'integration-flush') {
			var imap = {
				'integration-elementor-regen': 'elementor_regen',
				'integration-wc-transients': 'wc_transients',
				'integration-clear-cache': 'clear_cache',
				'integration-flush': 'flush'
			};
			busy(btn, true);
			ajax('imp_integrations_action', { do: imap[action] }).then(function (json) {
				busy(btn, false);
				if (json && json.success) {
					toast(json.data.message, 'success');
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			});
			return;
		}

		/* ---- Logs ---- */
		if (action === 'log-delete') {
			if (!askConfirm('delete')) { return; }
			ajax('imp_logs_action', { do: 'delete', id: btn.dataset.id }).then(function (json) {
				if (json && json.success) {
					btn.closest('tr').remove();
					toast('Entry deleted.', 'success');
				}
			});
			return;
		}

		if (action === 'logs-clear') {
			if (!askConfirm('delete')) { return; }
			ajax('imp_logs_action', { do: 'clear' }).then(function (json) {
				if (json && json.success) {
					toast('Logs cleared.', 'success');
					window.setTimeout(function () { window.location.reload(); }, 800);
				}
			});
			return;
		}

		if (action === 'log-details') {
			ajax('imp_logs_action', { do: 'details', id: btn.dataset.id }).then(function (json) {
				if (json && json.success) {
					showDetailsDialog(json.data);
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			});
			return;
		}

		/* ---- Scanner ---- */
		if (action === 'scan-start') {
			runJobs([{ type: 'scan', data: {} }], $('[data-imp-jobbox="scan"]') || jobboxFor('scan'));
			return;
		}
	});

	/* ------------------------------------------------------------------ *
	 * Formulaires
	 * ------------------------------------------------------------------ */

	/* Backup : création */
	var backupForm = $('[data-imp-backup-form]');
	if (backupForm) {
		backupForm.addEventListener('submit', function (event) {
			event.preventDefault();
			var cloudBox = $('#imp_backup_cloud');
			var speed = backupForm.querySelector('[name="backup_speed"]:checked');
			var data = {
				name: backupForm.querySelector('[name="backup_name"]').value.trim(),
				components: backupForm.querySelector('[name="backup_components"]:checked').value,
				exclusions: backupForm.querySelector('[name="backup_exclusions"]').value,
				speed: speed ? speed.value : 'balanced'
			};
			if ('turbo' === data.speed && !cfg.isPro) {
				data.speed = 'balanced';
				toast('Turbo speed is a Pro feature — upgrade to unlock 30-50% faster operations.', 'warning');
				openCheckout('');
			}
			var encBox = $('#imp_backup_encrypt');
			if (encBox && encBox.checked) {
				var encPass = $('#imp_backup_encpass');
				if (!encPass || encPass.value.length < 6) {
					toast('Enter an encryption password (6+ characters).', 'warning');
					return;
				}
				data.encrypt = 1;
				data.enc_pass = encPass.value;
			}
			if (cloudBox && cloudBox.checked) {
				data.remote_after = 1;
			}
			runJobs([{ type: 'backup', data: data }], $('[data-imp-jobbox="backup"]'));
		});
	}

	/* Chiffrement : afficher le champ mot de passe quand coché. */
	var encBox = $('#imp_backup_encrypt');
	if (encBox) {
		encBox.addEventListener('change', function () {
			var fields = $('#imp_backup_encrypt_fields');
			if (fields) { fields.hidden = !encBox.checked; }
		});
	}

	/* Restore : backup de sécurité optionnel puis restore */
	var restoreForm = $('[data-imp-restore-form]');
	if (restoreForm) {
		var backupSelect = $('#imp_restore_backup');
		var precheckBox = $('[data-imp-restore-precheck]');

		var runPrecheck = function () {
			if (!backupSelect.value) { return; }
			precheckBox.innerHTML = '<p class="imp-muted">…</p>';
			ajax('imp_backups_action', { do: 'verify', id: backupSelect.value }).then(function (json) {
				if (json && json.success) {
					if (json.data.ok) {
						precheckBox.innerHTML = '<div class="imp-notice imp-notice-success" style="margin:0">Integrity verified — checksums match, backup is safe to restore.</div>';
					} else {
						precheckBox.innerHTML = '<div class="imp-notice imp-notice-error" style="margin:0">Checksum mismatch — this backup is corrupted or incomplete. Restore is not recommended.</div>';
					}
				}
			});
		};

		backupSelect.addEventListener('change', runPrecheck);
		if (backupSelect.value) { runPrecheck(); }

		restoreForm.addEventListener('submit', function (event) {
			event.preventDefault();
			if (!backupSelect.value) { return; }
			if (!askConfirm('restore')) { return; }

			var components = $$('[data-imp-restore="components"] input:checked').map(function (input) { return input.value; });
			var queue = [];

			if ($('#imp_restore_safety').checked) {
				queue.push({
					type: 'backup',
					data: { name: 'Safety backup (before restore)', components: 'full', origin: 'safety' }
				});
			}
			var maint = $('#imp_restore_maintenance');
			var reurl = $('#imp_restore_reurl');
			var encPass = $('#imp_restore_encpass');
			queue.push({
				type: 'restore',
				data: {
					backup: backupSelect.value,
					restore_components: components,
					maintenance: (maint && maint.checked) ? 1 : 0,
					reurl: (reurl && reurl.checked) ? 1 : 0,
					enc_pass: (encPass && encPass.value) ? encPass.value : ''
				}
			});

			runJobs(queue, $('[data-imp-jobbox="restore"]'));
		});
	}

	/* Packages : upload + scan */
	var packageForm = $('#imp-package-upload-form');
	if (packageForm) {
		packageForm.addEventListener('submit', function (event) {
			event.preventDefault();
			var input = $('#imp_package_file');
			if (!input.files || !input.files.length) {
				toast('Choose a file first.', 'warning');
				return;
			}
			var form = new FormData();
			form.append('action', 'imp_package_scan');
			form.append('nonce', cfg.nonce);
			form.append('package_file', input.files[0]);

			var submit = packageForm.querySelector('button[type="submit"]');
			busy(submit, true);
			fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form })
				.then(function (response) { return response.json(); })
				.then(function (json) {
					busy(submit, false);
					if (json && json.success) {
						renderPackagePreview(json.data);
					} else {
						toast((json && json.data && json.data.message) || i18n.error, 'error');
					}
				})
				.catch(function () {
					busy(submit, false);
					toast(i18n.networkError, 'error');
				});
		});
	}

	var serverSelect = $('[data-imp-package-server]');
	if (serverSelect) {
		serverSelect.addEventListener('change', function () {
			if (serverSelect.value) { scanPackage({ server_file: serverSelect.value }); }
		});
	}

	function scanPackage(data) {
		ajax('imp_package_scan', data).then(function (json) {
			if (json && json.success) {
				renderPackagePreview(json.data);
			} else {
				toast((json && json.data && json.data.message) || i18n.error, 'error');
			}
		});
	}

	function renderPackagePreview(data) {
		window.impPendingPackage = String(data.file || '').replace(/^tmp:/, '');

		var panel = $('[data-imp-package-preview]');
		var body = $('[data-imp-package-preview-body]');
		var actions = $('[data-imp-package-preview-actions]');
		panel.hidden = false;
		actions.hidden = false;

		var html = '<table class="imp-table imp-table-info"><tbody>';
		html += '<tr><th scope="row">Name</th><td>' + esc(data.name) + '</td></tr>';
		html += '<tr><th scope="row">Created</th><td>' + esc(data.created) + '</td></tr>';
		html += '<tr><th scope="row">Source site</th><td>' + esc(data.site_url) + '</td></tr>';
		html += '<tr><th scope="row">WordPress / PHP</th><td>' + esc(data.wp_version) + ' / ' + esc(data.php_version) + '</td></tr>';
		html += '<tr><th scope="row">Files</th><td>' + (data.counts ? esc(data.counts.files) : '—') + '</td></tr>';
		html += '<tr><th scope="row">Database rows</th><td>' + (data.counts ? esc(data.counts.db_rows) : '—') + '</td></tr>';
		html += '<tr><th scope="row">Checksums</th><td><span class="imp-badge imp-badge-pass">VERIFIED</span></td></tr>';
		html += '</tbody></table>';

		if (data.alerts && data.alerts.length) {
			data.alerts.forEach(function (alert) {
				html += '<div class="imp-notice imp-notice-' + (alert.level === 'critical' ? 'error' : 'warning') + '">' + esc(alert.text) + '</div>';
			});
		} else {
			html += '<div class="imp-notice imp-notice-success" style="margin:0">Content scan clean: no unsafe entries, no unexpected PHP in uploads.</div>';
		}

		body.innerHTML = html;
		panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	/* Database : import SQL */
	var dbImportForm = $('[data-imp-db-import]');
	if (dbImportForm) {
		dbImportForm.addEventListener('submit', function (event) {
			event.preventDefault();
			var input = $('#imp_db_sql_file');
			if (!input.files || !input.files.length) {
				toast('Choose a .sql or .sql.gz file.', 'warning');
				return;
			}
			if (!askConfirm('import')) { return; }

			var form = new FormData();
			form.append('action', 'imp_db_action');
			form.append('nonce', cfg.nonce);
			form.append('do', 'import_upload');
			form.append('sql_file', input.files[0]);

			var submit = dbImportForm.querySelector('button[type="submit"]');
			busy(submit, true);
			fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form })
				.then(function (response) { return response.json(); })
				.then(function (json) {
					busy(submit, false);
					if (json && json.success) {
						runJobs([{ type: 'db_import', data: { file: json.data.file } }], $('[data-imp-jobbox="db_import"]') || jobboxFor('db_import'));
					} else {
						toast((json && json.data && json.data.message) || i18n.error, 'error');
					}
				})
				.catch(function () {
					busy(submit, false);
					toast(i18n.networkError, 'error');
				});
		});
	}

	/* Table DB : tout sélectionner */
	var selectAll = $('[data-imp-db-selectall]');
	if (selectAll) {
		selectAll.addEventListener('change', function () {
			$$('[data-imp-db-table]').forEach(function (input) {
				input.checked = selectAll.checked;
			});
		});
	}

	/* URL Replace : exécution */
	var replaceForm = $('[data-imp-replace-form]');
	if (replaceForm) {
		replaceForm.addEventListener('submit', function (event) {
			event.preventDefault();
			if (!askConfirm('replace')) { return; }
			var data = {
				from: $('#imp_replace_from').value.trim(),
				to: $('#imp_replace_to').value.trim(),
				json: $('#imp_replace_json').checked ? 1 : 0,
				dry_run: 0
			};
			runJobs([{ type: 'replace', data: data }], $('[data-imp-jobbox="replace"]'));
		});
	}

	/* Settings : licence — couvert par le binding global [data-imp-license-form]. */


	/* Onglet Cloud : afficher les champs du provider choisi + son hint. */
	var cloudProvider = $('[data-imp-cloud-provider]');
	if (cloudProvider) {
		var refreshCloudFields = function () {
			var value = cloudProvider.value;
			$$('[data-imp-cloud-fields]').forEach(function (zone) {
				zone.hidden = zone.getAttribute('data-imp-cloud-fields') !== value;
			});
			var active = $('[data-imp-cloud-hint="' + value + '"]');
			var target = $('[data-imp-cloud-hint-active]');
			if (target) { target.textContent = active ? active.textContent : ''; }
		};
		cloudProvider.addEventListener('change', refreshCloudFields);
		refreshCloudFields();
	}

	/* Page Import : dropzone glisser-déposer + scan + démarrage. */
	(function importDropzone() {
		var zone = $('[data-imp-dropzone]');
		if (!zone) { return; }
		var input = $('[data-imp-dropfile]', zone);

		zone.addEventListener('click', function () { input.click(); });
		zone.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
		});
		['dragover', 'dragenter'].forEach(function (evt) {
			zone.addEventListener(evt, function (e) { e.preventDefault(); zone.classList.add('is-drag'); }, { passive: true });
		});
		['dragleave', 'drop'].forEach(function (evt) {
			zone.addEventListener(evt, function (e) { e.preventDefault(); zone.classList.remove('is-drag'); });
		});
		zone.addEventListener('drop', function (e) {
			if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
				try {
					// DataTransfer : compatible partout (Safari inclus).
					var dt = new DataTransfer();
					dt.items.add(e.dataTransfer.files[0]);
					input.files = dt.files;
				} catch (err) {
					input.files = e.dataTransfer.files;
				}
				importUpload(input.files[0]);
			}
		});
		input.addEventListener('change', function () {
			if (input.files && input.files.length) { importUpload(input.files[0]); }
		});

		function importUpload(file) {
			var form = new FormData();
			form.append('action', 'imp_import_upload');
			form.append('nonce', cfg.nonce);
			form.append('package_file', file);
			toast(i18n.starting, 'info');
			fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form })
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json && json.success) {
						window.impImportToken = json.data.token;
						renderImportPreview(json.data);
						toast('Package verified — review the options, then start the import.', 'success');
					} else {
						toast((json && json.data && json.data.message) || i18n.error, 'error');
					}
				})
				.catch(function () { toast(i18n.networkError, 'error'); });
		}

		function renderImportPreview(data) {
			var preview = $('[data-imp-import-preview]');
			var options = $('[data-imp-import-options]');
			if (!preview) { return; }
			preview.hidden = false;
			$('[data-imp-imp="name"]', preview).textContent = data.name || '—';
			$('[data-imp-imp="created"]', preview).textContent = data.created || '—';
			$('[data-imp-imp="site"]', preview).textContent = data.site_url || '—';
			var counts = data.counts || {};
			$('[data-imp-imp="counts"]', preview).textContent = (counts.files || 0) + ' files · ' + (counts.db_rows || 0) + ' rows';
			if (options) { options.hidden = false; }
			var start = $('[data-imp-import-start]');
			if (start) { start.disabled = false; }
		}

		var startBtn = $('[data-imp-import-start]');
		if (startBtn) {
			startBtn.addEventListener('click', function () {
				var components = $$('[data-imp-opt]').filter(function (i) { return i.checked; }).map(function (i) { return i.getAttribute('data-imp-opt'); });
				var maint = $('[data-imp-opt="maintenance"]');
				ajax('imp_import_start', {
					token: window.impImportToken || '',
					components: components,
					maintenance: (maint && maint.checked) ? 1 : 0
				}).then(function (json) {
					if (json && json.success && json.data.status) {
						var runner = new JobRunner($('[data-imp-jobbox="package_import"]') || jobboxFor('package_import'));
						runner.render(json.data.status);
						runner.startLoop();
					} else {
						toast((json && json.data && json.data.message) || i18n.error, 'error');
					}
				}).catch(function () { toast(i18n.networkError, 'error'); });
			});
		}
	})();

	/* Cartes Pro floutées : clic => tunnel d'achat intégré (4 étapes). */
	document.addEventListener('click', function (event) {
		var card = event.target.closest ? event.target.closest('.imp-pro-card') : null;
		if (!card || event.target.closest('.imp-btn')) { return; }
		openCheckout('');
	});

	/* FAB mobile : backup rapide en un tap (mobile, pages du plugin uniquement).
	 * Garde : un seul FAB — il survit aux navigations SPA (niveau body). */
	(function quickFab() {
		if (window.innerWidth > 782 || !cfg.nonce || !document.querySelector('.imp-wrap')) { return; }
		if (document.querySelector('.imp-fab')) { return; }
		var fab = document.createElement('button');
		fab.type = 'button';
		fab.className = 'imp-fab';
		fab.innerHTML = '<span class="dashicons dashicons-shield" aria-hidden="true"></span> Backup';
		fab.addEventListener('click', function () {
			if (!window.confirm('Start a full backup now?')) { return; }
			fab.disabled = true;
			ajax('imp_job_start', {
				type: 'backup',
				data: JSON.stringify({ name: 'Quick backup', components: 'full' })
			}).then(function (json) {
				if (json && json.success) {
					toast('Backup started — follow it on the Backups page.', 'success');
					window.setTimeout(function () { window.location.href = cfg.ajaxUrl.replace('admin-ajax.php', 'admin.php') + '?page=infinity-migratex-pro-backups'; }, 900);
				} else {
					fab.disabled = false;
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			}).catch(function () { fab.disabled = false; toast(i18n.networkError, 'error'); });
		});
		document.body.appendChild(fab);
	})();

	/* Dépôt plein écran sur la page Import : glisser n'importe où.
	 * Garde : l'overlay vit au niveau body et survit aux navigations SPA. */
	(function fullscreenDrop() {
		var zone = $('[data-imp-dropzone]');
		if (!zone) { return; }
		if (window.__impDropOverlay) { return; }
		window.__impDropOverlay = true;
		var depth = 0;
		var overlay = document.createElement('div');
		overlay.style.cssText = 'position:fixed;inset:0;z-index:9995;background:rgba(47,95,224,.16);border:4px dashed var(--imp-accent, #2f5fe0);display:none;align-items:center;justify-content:center;font-size:26px;font-weight:800;color:#2f5fe0;pointer-events:none;';
		overlay.textContent = '⬇ Drop to import';
		document.body.appendChild(overlay);

		window.addEventListener('dragenter', function (e) {
			if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1) {
				depth++;
				overlay.style.display = 'flex';
			}
		});
		window.addEventListener('dragleave', function () {
			depth = Math.max(0, depth - 1);
			if (!depth) { overlay.style.display = 'none'; }
		});
		window.addEventListener('dragover', function (e) { e.preventDefault(); });
		window.addEventListener('drop', function (e) {
			e.preventDefault();
			depth = 0;
			overlay.style.display = 'none';
			if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
				var input = $('[data-imp-dropfile]');
				if (input) {
					try {
						var dt = new DataTransfer();
						dt.items.add(e.dataTransfer.files[0]);
						input.files = dt.files;
					} catch (err) {
						input.files = e.dataTransfer.files;
					}
					input.dispatchEvent(new Event('change', { bubbles: true }));
					zone.scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
			}
		});
	})();

	/* Pipeline d'import vivant : coche les étapes à mesure. */
	window.addEventListener('imp:jobupdate', function (e) {
		var status = e.detail;
		if (!status || status.type !== 'package_import') { return; }
		var pipeMap = { prepare: 'validation', files: 'files', database: 'database', integrity: 'links', finalize: 'cleanup' };
		var currentIdx = -1;
		var doneKeys = {};
		(status.phases || []).forEach(function (p, i) {
			if (i < status.phase) {
				doneKeys[pipeMap[p.key] || p.key] = true;
			} else if (i === status.phase) {
				currentIdx = i;
			}
		});
		var currentKey = pipeMap[(status.phases[status.phase] || {}).key] || '';
		$$('.imp-pipeline [data-pipe]').forEach(function (li) {
			var key = li.getAttribute('data-pipe');
			li.classList.toggle('is-done', !!doneKeys[key] || (status.status === 'completed' && status.percent >= 100));
			li.classList.toggle('is-current', status.status === 'running' && key === currentKey);
		});
	});

	/* Options WooCommerce : sauvegarde instantanée (checkbox). */
	$$('[data-imp-wc-option]').forEach(function (input) {
		input.addEventListener('change', function () {
			ajax('imp_wc_action', {
				do: 'option',
				key: input.getAttribute('data-imp-wc-option'),
				value: input.checked ? 1 : 0
			}).then(function (json) {
				if (json && json.success) {
					toast(json.data.message, 'success');
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
					input.checked = !input.checked;
				}
			}).catch(function () {
				input.checked = !input.checked;
				toast(i18n.networkError, 'error');
			});
		});
	});

	/* ------------------------------------------------------------------ *
	 * Tunnel d'achat Pro intégré — 4 étapes dans le plugin :
	 * 1 Plan (Personal / Business, annuel ou à vie) · 2 Coordonnées ·
	 * 3 Paiement (récapitulatif, checkout en ligne, copie commande) ·
	 * 4 Activation (clé → Pro actif immédiatement, sans recharger).
	 * ------------------------------------------------------------------ */

	var coRoot = document.getElementById('imp-checkout');
	var coState = { step: 1, plan: 'personal', billing: 'yearly' };
	var coPrices = { personal: { yearly: '39€', lifetime: '79€' }, business: { yearly: '89€', lifetime: '179€' } };

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
		if (n === 2) {
			var first = co$('[name="imp_co_name"]');
			if (first) { window.setTimeout(function () { first.focus(); }, 60); }
		}
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

	function coOrderText() {
		var f = coFields();
		var site = co$('[data-imp-co-site]');
		return [
			'Infinity MigrateX Pro — order',
			'Plan: ' + (coState.plan === 'business' ? 'Business (5 sites)' : 'Personal (1 site)'),
			'Billing: ' + (coState.billing === 'lifetime' ? 'Lifetime' : 'Yearly'),
			'Total: ' + coPrices[coState.plan][coState.billing],
			'Name: ' + (f.name || '—'),
			'E-mail: ' + (f.email || '—'),
			f.whatsapp ? 'WhatsApp: ' + f.whatsapp : '',
			'Site: ' + (site ? site.value : '')
		].filter(Boolean).join('\n');
	}

	function coCopy(text) {
		var done = function () { toast('Order details copied — paste them into your e-mail or WhatsApp message.', 'success'); };
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done, function () { coCopyFallback(text); done(); });
		} else {
			coCopyFallback(text);
			done();
		}
	}

	function coCopyFallback(text) {
		try {
			var area = document.createElement('textarea');
			area.value = text;
			area.style.cssText = 'position:fixed;left:-9999px;top:0;';
			document.body.appendChild(area);
			area.select();
			document.execCommand('copy');
			area.remove();
		} catch (e) { /* silencieux */ }
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
			copyBtn.addEventListener('click', function () { coCopy(coOrderText()); });
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

	/* Déclencheur global : tout [data-imp-checkout] (About, Import, page
	 * Extensions…) ouvre le tunnel — la valeur présélectionne le plan. */
	document.addEventListener('click', function (event) {
		var btn = event.target.closest ? event.target.closest('[data-imp-checkout]') : null;
		if (!btn) { return; }
		event.preventDefault();
		var plan = btn.getAttribute('data-imp-checkout');
		openCheckout(plan === 'business' ? 'business' : (plan === 'personal' ? 'personal' : ''));
	});

	/* Activation de licence : tous les formulaires [data-imp-license-form]
	 * (Settings → Advanced et panneau Upgrade de la page About). */
	$$('form[data-imp-license-form]').forEach(function (form) {
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			var input = form.querySelector('[name="license_key"]');
			ajax('imp_settings_save', { license_key: input.value.trim() }).then(function (json) {
				if (json && json.success) {
					toast(json.data.message, 'success');
					window.location.reload();
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			}).catch(function () {
				toast(i18n.networkError, 'error');
			});
		});
	});

	/* Boutons [data-imp-copy] : copient leur attribut (guide d'activation,
	 * extraits de code…). Délégation globale, garde idempotence SPA. */
	if (!window.__impCopyBound) {
		window.__impCopyBound = true;
		document.addEventListener('click', function (event) {
			var btn = event.target.closest ? event.target.closest('[data-imp-copy]') : null;
			if (!btn) { return; }
			var text = btn.getAttribute('data-imp-copy') || '';
			var done = function () { toast('Copied to clipboard.', 'success'); };
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
		});
	}

	/* Centre de mises à jour : vérification automatique à l'ouverture +
	 * bouton manuel + « Update now » réel (upgrader WordPress côté serveur).
	 * Présent sur la page À propos. */
	(function updateCenter() {
		var btn = $('[data-imp-update-check]');
		if (!btn) { return; }
		var wpEl = $('[data-imp-upd-wporg]');
		var ghEl = $('[data-imp-upd-github]');
		var runBtn = $('[data-imp-update-run]');
		var original = btn.textContent;
		var checking = false;

		function offer(d) {
			return (d.wporg && d.wporg.available) || (d.github && d.github.available);
		}

		function render(d) {
			if (wpEl) {
				wpEl.textContent = d.wporg && d.wporg.available
					? '✦ Version ' + d.wporg.latest + ' — official channel.'
					: '✓ Up to date.';
			}
			if (ghEl) {
				/* États « guide » / « erreur » rendus côté serveur (guide
				 * d'activation, repo+asset attendu) : ne PAS les écraser —
				 * seuls les résultats nets mettent la ligne à jour. */
				if (d.github && d.github.available) {
					ghEl.textContent = '✦ Version ' + d.github.latest + ' — GitHub channel.';
				} else if (d.github && d.github.active && !d.github.error) {
					ghEl.textContent = '✓ Up to date.';
				} else if (d.github && d.github.error && !ghEl.querySelector('.imp-notice')) {
					ghEl.textContent = '⚠ ' + d.github.error;
				}
			}
			if (runBtn) {
				var has = !!offer(d);
				runBtn.hidden = !has;
				if (has) {
					var version = d.wporg && d.wporg.available ? d.wporg.latest : (d.github ? d.github.latest : '');
					runBtn.textContent = '↑ Update to ' + version;
				}
			}
			return offer(d);
		}

		function check(silent) {
			if (checking) { return; }
			checking = true;
			btn.disabled = true;
			if (!silent) { btn.textContent = 'Checking…'; }
			if (wpEl && !silent) { wpEl.textContent = '…'; }
			if (ghEl && !silent) { ghEl.textContent = '…'; }
			ajax('imp_update_check', {}).then(function (json) {
				checking = false;
				btn.disabled = false;
				btn.textContent = original;
				var d = json && json.data;
				if (!json || !json.success || !d) {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
					return;
				}
				var found = render(d);
				if (!silent) {
					toast(found ? 'An update is available — “Update now” is ready.' : 'Everything is up to date.', found ? 'info' : 'success');
				}
			}).catch(function () {
				checking = false;
				btn.disabled = false;
				btn.textContent = original;
				if (!silent) { toast(i18n.networkError, 'error'); }
			});
		}

		btn.addEventListener('click', function () { check(false); });

		/* Vérification automatique silencieuse à l'ouverture de la page :
		 * les statuts affichés viennent du cache, celui-ci les rafraîchit. */
		window.setTimeout(function () { check(true); }, 700);

		if (runBtn) {
			runBtn.addEventListener('click', function () {
				runBtn.disabled = true;
				var label = runBtn.textContent;
				runBtn.textContent = 'Updating…';
				ajax('imp_update_run', {}).then(function (json) {
					if (json && json.success) {
						toast(json.data.message, 'success');
						window.setTimeout(function () { window.location.reload(); }, 1200);
					} else {
						runBtn.disabled = false;
						runBtn.textContent = label;
						toast((json && json.data && json.data.message) || i18n.error, 'error');
					}
				}).catch(function () {
					runBtn.disabled = false;
					runBtn.textContent = label;
					toast(i18n.networkError, 'error');
				});
			});
		}
	})();

	/* ------------------------------------------------------------------ *
	 * Réglages — UN SEUL ÉCRAN : onglets instantanés (sans rechargement),
	 * indicateur de modifications non enregistrées, sauvegarde AJAX depuis
	 * la barre fixe, garde de sortie. Sans JS : les liens ?tab= et le
	 * POST options.php continuent de fonctionner.
	 * ------------------------------------------------------------------ */

	(function settingsHub() {
		var form = $('[data-imp-settings-form]');
		if (!form) { return; }
		var nav = $('[data-imp-tabs]');
		var tabs = $$('[data-imp-tab]');
		var panels = $$('[data-imp-tab-panel]');
		var savebar = $('[data-imp-savebar]');
		var statePill = $('[data-imp-savebar-state]');
		var dirty = false;
		var currentTab = 'general';
		var themeInput = form.querySelector('[name="imp_settings[ui_theme]"]:checked') || form.querySelector('[name="imp_settings[ui_theme]"]');

		function showTab(key, updateHash) {
			var known = false;
			tabs.forEach(function (btn) {
				var on = btn.getAttribute('data-imp-tab') === key;
				btn.classList.toggle('is-active', on);
				if (on) { known = true; }
			});
			currentTab = known ? key : 'general';
			panels.forEach(function (panel) {
				panel.hidden = panel.getAttribute('data-imp-tab-panel') !== currentTab;
			});
			if (known && updateHash && window.history && window.history.replaceState) {
				window.history.replaceState(null, '', '#' + key);
			}
		}

		tabs.forEach(function (btn) {
			btn.addEventListener('click', function () {
				showTab(btn.getAttribute('data-imp-tab'), true);
			});
		});

		/* État initial : ancre #section prioritaire, sinon l'onglet actif
		 * rendu par le serveur (?tab= pour compat sans JS). */
		var initial = (window.location.hash || '').replace('#', '');
		showTab(initial, false);

		function markDirty() {
			if (dirty) { return; }
			dirty = true;
			if (statePill) { statePill.hidden = false; }
			if (savebar) { savebar.classList.add('is-dirty'); }
		}
		form.addEventListener('input', markDirty);
		form.addEventListener('change', markDirty);

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			var fields = {};
			new FormData(form).forEach(function (value, name) {
				var match = /\[([^\]]+)\]/.exec(name);
				if (match && 'imp_settings' === name.slice(0, 12)) {
					fields[match[1]] = String(value);
				}
			});
			var btn = savebar ? savebar.querySelector('button[type="submit"]') : null;
			if (btn) { btn.disabled = true; }
			ajax('imp_settings_save_all', { imp_settings_json: JSON.stringify(fields) }).then(function (json) {
				if (btn) { btn.disabled = false; }
				if (json && json.success) {
					dirty = false;
					if (statePill) { statePill.hidden = true; }
					if (savebar) { savebar.classList.remove('is-dirty'); }
					var savedEl = $('[data-imp-savebar-saved]');
					if (savedEl) { savedEl.textContent = 'Last saved: just now.'; }
					toast(json.data.message, 'success');
					/* Le thème est appliqué côté serveur (classe du wrap) :
					 * un changement de thème demande un rendu — rechargement
					 * unique, uniquement dans ce cas. */
					var newTheme = fields.ui_theme || '';
					if (themeInput && newTheme && newTheme !== cfg.theme) {
						window.location.reload();
					}
				} else {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
				}
			}).catch(function () {
				if (btn) { btn.disabled = false; }
				toast(i18n.networkError, 'error');
			});
		});

		window.addEventListener('beforeunload', function (event) {
			if (!dirty) { return; }
			event.preventDefault();
			event.returnValue = '';
			return '';
		});

		/* Ctrl+S / Cmd+S sauvegarde les réglages (comportement standard
		 * des éditeurs — ici déclenche le submit intercepté en AJAX). */
		document.addEventListener('keydown', function (event) {
			if (!dirty || event.key !== 's' || !(event.ctrlKey || event.metaKey)) { return; }
			event.preventDefault();
			form.requestSubmit ? form.requestSubmit() : $( '[data-imp-savebar-submit]', form ).click();
		});

		/* Recherche de réglages : filtre les lignes de TOUS les onglets,
		 * bascule automatiquement vers le premier onglet avec résultats. */
		var searchInput = $('[data-imp-settings-search]');
		var searchHint = $('[data-imp-settings-search-hint]');
		if (searchInput) {
			searchInput.addEventListener('input', function () {
				var q = searchInput.value.trim().toLowerCase();
				var panelsAll = $$('[data-imp-tab-panel]');
				if (q.length < 2) {
					panelsAll.forEach(function (panel) {
						panel.hidden = panel.getAttribute('data-imp-tab-panel') !== currentTab;
						$$('tr[hidden]', panel).forEach(function (row) { row.hidden = false; });
					});
					if (searchHint) { searchHint.hidden = true; }
					showTab(currentTab, false);
					return;
				}
				var firstMatchTab = '';
				var totalMatches = 0;
				panelsAll.forEach(function (panel) {
					var matches = 0;
					$$('tbody tr, table tr', panel).forEach(function (row) {
						var hit = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
						row.hidden = q ? !hit : false;
						if (hit) { matches++; }
					});
					panel.hidden = q ? matches === 0 : panel.getAttribute('data-imp-tab-panel') !== currentTab;
					if (matches > 0 && !firstMatchTab) {
						firstMatchTab = panel.getAttribute('data-imp-tab-panel');
					}
					totalMatches += matches;
				});
				if (q && firstMatchTab) { showTab(firstMatchTab, false); }
				if (searchHint) {
					searchHint.hidden = false;
					searchHint.textContent = totalMatches
						? totalMatches + (totalMatches === 1 ? ' match' : ' matches')
						: 'No matching setting.';
				}
			});
		}

		/* Export de configuration : JSON téléchargé (secrets exclus côté
		 * serveur). */
		var exportBtn = $('[data-imp-config-export]');
		if (exportBtn) {
			exportBtn.addEventListener('click', function () {
				ajax('imp_settings_export', {}).then(function (json) {
					if (json && json.success && json.data.payload) {
						var blob = new Blob([json.data.payload], { type: 'application/json' });
						var link = document.createElement('a');
						link.href = URL.createObjectURL(blob);
						link.download = 'infinity-migratex-settings-' + new Date().toISOString().slice(0, 10) + '.json';
						document.body.appendChild(link);
						link.click();
						link.remove();
						toast('Configuration exported.', 'success');
					} else {
						toast((json && json.data && json.data.message) || i18n.error, 'error');
					}
				}).catch(function () { toast(i18n.networkError, 'error'); });
			});
		}

		/* Import de configuration : fichier JSON → fusion sanitisée. */
		var importBtn = $('[data-imp-config-import]');
		var importFile = $('[data-imp-config-file]');
		if (importBtn && importFile) {
			importBtn.addEventListener('click', function () {
				if (!importFile.files || !importFile.files[0]) {
					toast('Choose a configuration file first.', 'warning');
					return;
				}
				if (!window.confirm('Import this configuration? Current values it defines will be replaced (secrets are kept).')) { return; }
				var reader = new FileReader();
				reader.onload = function () {
					ajax('imp_settings_import', { imp_settings_json: String(reader.result) }).then(function (json) {
						if (json && json.success) {
							toast(json.data.message, 'success');
							window.setTimeout(function () { window.location.reload(); }, 900);
						} else {
							toast((json && json.data && json.data.message) || i18n.error, 'error');
						}
					}).catch(function () { toast(i18n.networkError, 'error'); });
				};
				reader.onerror = function () { toast('Unable to read this file.', 'error'); };
				reader.readAsText(importFile.files[0]);
			});
		}

		/* Restauration des valeurs d'usine (double confirmation). */
		var resetBtn = $('[data-imp-config-reset]');
		if (resetBtn) {
			resetBtn.addEventListener('click', function () {
				if (!window.confirm('Restore ALL default settings? Your license, backups and logs are kept.')) { return; }
				if (!window.confirm('Last confirmation: every setting returns to its factory value. Continue?')) { return; }
				ajax('imp_settings_reset', {}).then(function (json) {
					if (json && json.success) {
						toast(json.data.message, 'success');
						window.setTimeout(function () { window.location.reload(); }, 900);
					} else {
						toast((json && json.data && json.data.message) || i18n.error, 'error');
					}
				}).catch(function () { toast(i18n.networkError, 'error'); });
			});
		}
	})();

	/* ------------------------------------------------------------------ *
	 * Assistant migration
	 * ------------------------------------------------------------------ */

	var wizard = $('[data-imp-wizard="migration"]');
	if (wizard) {
		var stepsNav = $$('[data-imp-steps] li');
		var panels = $$('[data-imp-step-panel]', wizard);
		var prevBtn = $('[data-imp-wizard-prev]');
		var nextBtn = $('[data-imp-wizard-next]');
		var runBtn = $('[data-imp-wizard-run]');
		var current = 1;
		var preflightRan = false;

		function showStep(n) {
			current = n;
			panels.forEach(function (panel) {
				panel.hidden = parseInt(panel.getAttribute('data-imp-step-panel'), 10) !== n;
			});
			stepsNav.forEach(function (item) {
				var step = parseInt(item.getAttribute('data-imp-step'), 10);
				item.classList.toggle('is-active', step === n);
				item.classList.toggle('is-done', step < n);
			});
			prevBtn.hidden = n === 1;
			nextBtn.hidden = n >= 5;
			runBtn.hidden = n !== 5;
		}

		function migrationType() {
			var checked = wizard.querySelector('[name="imp_mig_type"]:checked');
			return checked ? checked.value : 'domain';
		}

		/* Affichage des champs selon le type choisi. */
		$$('[name="imp_mig_type"]', wizard).forEach(function (radio) {
			radio.addEventListener('change', function () {
				var type = migrationType();
				$$('[data-imp-mig-fields]', wizard).forEach(function (zone) {
					zone.hidden = zone.getAttribute('data-imp-mig-fields') !== type;
				});
			});
		});

		function collectConfig() {
			var type = migrationType();
			var config = {
				type: type,
				new_url: '',
				speed: (function () {
					var checked = $('[name="imp_mig_speed"]:checked');
					return checked ? checked.value : 'balanced';
				})(),
				selection: $$('[data-imp-mig="components"] input:checked').map(function (input) { return input.value; }).filter(function (v) { return v !== 'database'; })
			};
			var database = $('[data-imp-mig="components"] input[value="database"]');

			if ('turbo' === config.speed && !cfg.isPro) {
				config.speed = 'balanced';
				toast('Turbo speed is a Pro feature — upgrade to unlock 30-50% faster migrations.', 'warning');
				openCheckout('');
			}

			if (type === 'domain') {
				config.new_url = $('#imp_mig_new_domain').value.trim();
			} else if (type === 'clone') {
				config.new_url = $('#imp_mig_clone_url').value.trim();
				config.dest_path = $('#imp_mig_dest').value.trim();
				config.db_prefix = $('#imp_mig_db_prefix').value.trim() || 'wp_';
				config.db_overwrite = $('#imp_mig_db_overwrite').checked ? 1 : 0;
				config.db_host = $('#imp_mig_db_host').value.trim();
				config.db_name = $('#imp_mig_db_name').value.trim();
				config.db_user = $('#imp_mig_db_user').value.trim();
				config.db_pass = $('#imp_mig_db_pass').value;
			} else {
				config.new_url = '';
				config.name = $('#imp_mig_package_name').value.trim();
			}
			config.with_database = database ? database.checked : true;
			return config;
		}

		function validateStep(n) {
			var type = migrationType();
			if (n === 2) {
				if (type === 'domain' && !$('#imp_mig_new_domain').value.trim()) {
					toast('Enter the new URL.', 'warning');
					return false;
				}
				if (type === 'clone') {
					if (!$('#imp_mig_dest').value.trim() || !$('#imp_mig_clone_url').value.trim() ||
						!$('#imp_mig_db_host').value.trim() || !$('#imp_mig_db_name').value.trim() || !$('#imp_mig_db_user').value.trim()) {
						toast('Destination path, clone URL and database credentials are required.', 'warning');
						return false;
					}
				}
			}
			return true;
		}

		prevBtn.addEventListener('click', function () {
			if (current > 1) { showStep(current - 1); }
		});

		nextBtn.addEventListener('click', function () {
			if (!validateStep(current)) { return; }
			showStep(Math.min(5, current + 1));
		});

		runBtn.addEventListener('click', function () {
			if (current !== 5) { return; }

			/* Premier clic : exécute le preflight. Clics suivants : avance
			 * vers l'étape 6 si les erreurs critiques sont résolues. */
			if (!preflightRan) {
				if (!validateStep(2)) { showStep(2); return; }
				runPreflight();
				return;
			}

			var ack = $('[data-imp-ack-warnings]');
			var ackBox = $('[data-imp-preflight-ack]');
			if (!ackBox.hidden && ack && !ack.checked) {
				toast('Tick the acknowledgement checkbox to continue despite warnings.', 'warning');
				return;
			}
			showStep(6);
			runBtn.hidden = true;
			nextBtn.hidden = true;
		});

		function runPreflight() {
			var wait = $('[data-imp-preflight-wait]');
			var results = $('[data-imp-preflight-results]');
			wait.hidden = false;
			results.hidden = true;
			runBtn.disabled = true;

			ajax('imp_migration_preflight', { config: JSON.stringify(collectConfig()) }).then(function (json) {
				wait.hidden = true;
				runBtn.disabled = false;
				if (!json || !json.success) {
					toast((json && json.data && json.data.message) || i18n.error, 'error');
					return;
				}
				renderChecksTable($('[data-imp-preflight-body]'), json.data.checks);
				var ack = $('[data-imp-preflight-ack]');
				ack.hidden = json.data.summary.warn === 0;
				var ackInput = $('[data-imp-ack-warnings]');
				if (ackInput) { ackInput.checked = false; }
				results.hidden = false;
				preflightRan = true;

				/* Le bouton devient "Go to migration" (ou reste bloqué). */
				runBtn.textContent = json.data.summary.error > 0 ? 'Fix critical issues first' : 'Go to migration';
				runBtn.disabled = json.data.summary.error > 0;
			}).catch(function () {
				wait.hidden = true;
				runBtn.disabled = false;
				toast(i18n.networkError, 'error');
			});
		}

		/* Lancement : backup de sécurité optionnel + job migration/export. */
		var launchHandled = false;
		$$('[data-imp-step-panel="6"] button, form[data-imp-wizard] button[type="submit"]').forEach(function () { });

		var launchBtn = document.createElement('button');
		launchBtn.type = 'button';
		launchBtn.className = 'imp-btn imp-btn-primary';
		launchBtn.textContent = 'Start migration';
		launchBtn.addEventListener('click', function () {
			if (launchHandled) { return; }
			launchHandled = true;

			var config = collectConfig();
			var queue = [];

			if ($('#imp_mig_safety').checked) {
				queue.push({
					type: 'backup',
					data: { name: 'Safety backup (before migration)', components: 'full', origin: 'safety' }
				});
			}

			if (config.type === 'export') {
				queue.push({
					type: 'backup',
					data: {
						name: config.name || 'Migration package',
						components: 'full',
						package: 1,
						selection: config.selection,
						exclusions: $('#imp_mig_exclusions').value
					}
				});
			} else {
				queue.push({
					endpoint: 'imp_migration_start',
					data: { config: JSON.stringify(collectConfig()) }
				});
			}

			runJobs(queue, $('[data-imp-jobbox="migration"]') || jobboxFor('migration'));
			launchBtn.disabled = true;
		});
		var step6nav = $('[data-imp-step-panel="6"]');
		if (step6nav) { step6nav.appendChild(launchBtn); }
	}

	/* ------------------------------------------------------------------ *
	 * Rendus partagés
	 * ------------------------------------------------------------------ */

	function renderChecksTable(body, checks) {
		if (!body) { return; }
		var labels = { pass: 'PASS', warn: 'WARNING', error: 'ERROR' };
		var html = '';
		(checks || []).forEach(function (check) {
			html += '<tr>';
			html += '<th scope="row">' + esc(check.label) + '</th>';
			html += '<td><span class="imp-badge imp-badge-' + (check.status === 'error' ? 'fail' : check.status) + '">' + esc(labels[check.status] || check.status) + '</span> ';
			html += '<span class="imp-health-value">' + esc(check.value) + '</span>';
			html += '<span class="imp-health-hint">' + esc(check.hint) + '</span></td>';
			html += '</tr>';
		});
		body.innerHTML = html;
	}

	function renderPreflightInline(box, checks) {
		var existing = box.querySelector('[data-imp-preflight-body]');
		if (existing) { renderChecksTable(existing, checks); }
	}

	function refreshHealth(checks) {
		var body = $('#imp-health-panel tbody');
		if (!body) { return; }
		var labels = { pass: 'PASS', warn: 'WARNING', error: 'ERROR' };
		var html = '';
		(checks || []).forEach(function (check) {
			html += '<tr data-imp-check="' + esc(check.id) + '">';
			html += '<th scope="row">' + esc(check.label) + '</th>';
			html += '<td><span class="imp-badge imp-badge-' + (check.status === 'error' ? 'fail' : check.status) + '">' + esc(labels[check.status]) + '</span>';
			html += '<span class="imp-health-value">' + esc(check.value) + '</span>';
			html += '<span class="imp-health-hint">' + esc(check.hint) + '</span></td>';
			html += '</tr>';
		});
		body.innerHTML = html;
	}

	function showDetailsDialog(data) {
		var overlay = document.createElement('div');
		overlay.style.cssText = 'position:fixed;inset:0;background:rgba(30,42,68,.45);z-index:100001;display:flex;align-items:center;justify-content:center;padding:20px;';
		var entry = data.entry || {};
		var details = data.details || {};

		var rows = '';
		Object.keys(entry).forEach(function (key) {
			rows += '<tr><th scope="row" style="color:#5c688a;text-align:left;padding:6px 10px;">' + esc(key) + '</th><td style="padding:6px 10px;">' + esc(typeof entry[key] === 'object' ? JSON.stringify(entry[key]) : entry[key]) + '</td></tr>';
		});
		var detailRows = '';
		if (details && typeof details === 'object') {
			Object.keys(details).forEach(function (key) {
				detailRows += '<tr><th scope="row" style="color:#5c688a;text-align:left;padding:6px 10px;">' + esc(key) + '</th><td style="padding:6px 10px;">' + esc(typeof details[key] === 'object' ? JSON.stringify(details[key]) : details[key]) + '</td></tr>';
			});
		}

		var dialog = document.createElement('div');
		dialog.style.cssText = 'background:#ffffff;color:#1e2a44;border:1px solid #d9dfee;border-radius:14px;max-width:720px;width:100%;max-height:85vh;overflow:auto;padding:22px;font-size:13px;';
		dialog.innerHTML = '<h3 style="margin:0 0 6px;">' + esc(entry.action || 'Log entry') + '</h3>' +
			'<p style="color:#5c688a;margin:0 0 14px;">#' + esc(entry.id) + ' · ' + esc(entry.created) + ' · ' + esc(data.user || '') + '</p>' +
			'<table style="border-collapse:collapse;width:100%;">' + rows + '</table>' +
			(detailRows ? '<h4 style="margin:16px 0 8px;">Details</h4><table style="border-collapse:collapse;width:100%;">' + detailRows + '</table>' : '') +
			'<div style="margin-top:18px;text-align:right;"><button type="button" style="background:linear-gradient(120deg,#4f7cff,#8a5cff);border:none;border-radius:10px;color:#fff;padding:9px 18px;font-size:13px;font-weight:700;cursor:pointer;">' + esc(i18n.close) + '</button></div>';

		overlay.appendChild(dialog);
		document.body.appendChild(overlay);
		overlay.addEventListener('click', function (event) {
			if (event.target === overlay || event.target.closest('button')) {
				overlay.remove();
			}
		});
	}
})();
