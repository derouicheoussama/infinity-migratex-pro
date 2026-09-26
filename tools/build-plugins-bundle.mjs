/**
 * Construit le micro-bundle de la page Extensions (plugins.php) :
 *   assets/css/imp-checkout.min.css — uniquement les règles du tunnel
 *   d'achat (.imp-co*, #imp-checkout, #imp-toast, body.imp-co-lock),
 *   extraites AUTOMATIQUEMENT de admin.css (zéro dérive).
 *   assets/js/imp-checkout.min.js — minification de imp-checkout.js.
 * Usage : node tools/build-plugins-bundle.mjs  (après minify-assets.mjs)
 */
import { readFileSync, writeFileSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';
import { createRequire } from 'module';

const require = createRequire(import.meta.url);
const { minify } = require('terser');

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const banner =
	'/*! Infinity MigrateX Pro — (c) Derouiche Oussama, GPL v2+ — https://www.derouicheoussama.com */\n';

/* ---------- 1. Extraction des règles checkout depuis admin.css ---------- */
const css = readFileSync(join(root, 'assets/css/admin.css'), 'utf8');

function keepSelector(sel) {
	/* Lookahead : `imp-co` suivi d'autre chose qu'une lettre — garde
	 * .imp-co-* / .imp-co-dialog, exclut .imp-content / .imp-col-*. */
	return /(^|[\s,])body\.imp-co-lock(?![a-z])/.test(sel)
		|| /(^|[\s,])#imp-checkout(?![a-z])/.test(sel)
		|| /(^|[\s,])\.imp-checkout(?![a-z])/.test(sel)
		|| /(^|[\s,])\.imp-co(?![a-z])/.test(sel)
		|| /(^|[\s,])\.imp-toast(?![a-z])/.test(sel)
		|| /(^|[\s,])#imp-toast(?![a-z])/.test(sel);
}

/* Tokenizer à niveau d'accolades : retourne les blocs top-level. */
function topLevelBlocks(text) {
	const blocks = [];
	let depth = 0;
	let start = 0;
	for (let i = 0; i < text.length; i++) {
		const ch = text[i];
		if (ch === '{') { depth++; }
		else if (ch === '}') {
			depth--;
			if (depth === 0) {
				blocks.push(text.slice(start, i + 1));
				start = i + 1;
				while (text[start] === '\n' || text[start] === ' ') { start++; }
				i = start;
			}
		}
	}
	return blocks;
}

function minifyRules(body) {
	return body
		.replace(/\/\*[\s\S]*?\*\//g, '')
		.replace(/\s+/g, ' ')
		.replace(/\s*([{}:;,>~])\s*/g, '$1')
		.replace(/;}/g, '}')
		.trim();
}

const outRules = [];
for (const block of topLevelBlocks(css)) {
	const brace = block.indexOf('{');
	if (brace === -1) { continue; }
	const selector = block.slice(0, brace).trim();
	const body = block.slice(brace + 1, block.lastIndexOf('}'));

	if (selector.startsWith('@media')) {
		const inner = topLevelBlocks(body)
			.filter((b) => {
				const s = b.slice(0, b.indexOf('{')).trim();
				return keepSelector(s);
			})
			.map((b) => {
				const bi = b.indexOf('{');
				return b.slice(0, bi).trim() + '{' + minifyRules(b.slice(bi + 1, b.lastIndexOf('}'))) + '}';
			});
		if (inner.length) {
			outRules.push(selector + '{' + inner.join('') + '}');
		}
	} else if (selector.startsWith('@keyframes') && /imp-co/.test(selector)) {
		outRules.push(selector + '{' + minifyRules(body) + '}');
	} else if (keepSelector(selector)) {
		outRules.push(selector + '{' + minifyRules(body) + '}');
	}
}

const minCss = banner + outRules.join('');
writeFileSync(join(root, 'assets/css/imp-checkout.min.css'), minCss, 'utf8');
console.log('imp-checkout.min.css :', outRules.length, 'règles,', (minCss.length / 1024).toFixed(1), 'Ko');

/* ---------- 2. Minification du JS standalone ---------- */
const jsPath = join(root, 'assets/js/imp-checkout.js');
const js = readFileSync(jsPath, 'utf8');
const out = await minify(js, { compress: { passes: 2 }, mangle: true, format: { comments: false, preamble: banner } });
writeFileSync(join(root, 'assets/js/imp-checkout.min.js'), out.code, 'utf8');
console.log('imp-checkout.min.js :', (js.length / 1024).toFixed(1), 'Ko →', (out.code.length / 1024).toFixed(1), 'Ko');
