/**
 * Minifie les assets du plugin pour la distribution :
 *   assets/js/admin.js  → assets/js/admin.min.js  (terser)
 *   assets/css/admin.css → assets/css/admin.min.css (minification sûre)
 *
 * Les versions .min sont COMMITÉES (le zip est construit depuis l'arbre
 * git). Re-lancer après toute modification des sources :
 *   node tools/minify-assets.mjs
 * SCRIPT_DEBUG=true côté WP sert les sources lisibles (GPL : les sources
 * restent dans le dépôt et le paquet source).
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

/* ---- JS (terser) ---- */
const jsPath = join(root, 'assets/js/admin.js');
const js = readFileSync(jsPath, 'utf8');
const out = await minify(js, {
	compress: { passes: 2 },
	mangle: true,
	format: { comments: false, preamble: banner },
});
writeFileSync(join(root, 'assets/js/admin.min.js'), out.code, 'utf8');
console.log('admin.min.js :', (js.length / 1024).toFixed(1), 'Ko →', (out.code.length / 1024).toFixed(1), 'Ko');

/* ---- CSS (minification sûre : commentaires + blancs) ---- */
const cssPath = join(root, 'assets/css/admin.css');
const css = readFileSync(cssPath, 'utf8');
const minCss = css
	.replace(/\/\*[\s\S]*?\*\//g, '')
	.replace(/\s+/g, ' ')
	.replace(/\s*([{}:;,>~])\s*/g, '$1')
	.replace(/;}/g, '}')
	.trim();
writeFileSync(join(root, 'assets/css/admin.min.css'), banner + minCss, 'utf8');
console.log('admin.min.css :', (css.length / 1024).toFixed(1), 'Ko →', (minCss.length / 1024).toFixed(1), 'Ko');
