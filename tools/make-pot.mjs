/**
 * Extracteur .pot pour Infinity Migrate Pro (exécution locale, hors runtime).
 * Usage : node tools/make-pot.mjs
 */
import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = fileURLToPath(new URL('..', import.meta.url));
const DOMAIN = 'infinity-migratex-pro';

function walk(dir, out = []) {
	for (const entry of readdirSync(dir)) {
		if (entry === 'node_modules' || entry === '.git' || entry === 'languages') continue;
		const full = join(dir, entry);
		const stat = statSync(full);
		if (stat.isDirectory()) walk(full, out);
		else if (entry.endsWith('.php')) out.push(full);
	}
	return out;
}

const entries = new Map(); // key: msgid \0 plural

function add(msgid, file, line, plural = null) {
	const key = msgid + '\0' + (plural ?? '');
	if (!entries.has(key)) {
		entries.set(key, { msgid, plural, refs: [] });
	}
	entries.get(key).refs.push(`${relative(ROOT, file).replace(/\\/g, '/')}:${line}`);
}

const patterns = [
	{ re: /(?<![a-zA-Z_])__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'infinity-migratex-pro'/g, plural: false },
	{ re: /(?<![a-zA-Z_])_e\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'infinity-migratex-pro'/g, plural: false },
	{ re: /esc_html__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'infinity-migratex-pro'/g, plural: false },
	{ re: /esc_attr__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'infinity-migratex-pro'/g, plural: false },
	{ re: /esc_html_e\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'infinity-migratex-pro'/g, plural: false },
	{ re: /esc_attr_e\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'infinity-migratex-pro'/g, plural: false },
];

const pluralRe = /_n\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'((?:[^'\\]|\\.)*)'\s*,[^,]+,\s*'infinity-migratex-pro'/g;

const doubleQuoteRe = /__\(\s*"((?:[^"\\]|\\.)*)"\s*,\s*'infinity-migratex-pro'/g;

for (const file of walk(ROOT)) {
	const source = readFileSync(file, 'utf8');
	const lines = source.split('\n');
	lines.forEach((line, index) => {
		for (const { re, plural } of patterns) {
			re.lastIndex = 0;
			let match;
			while ((match = re.exec(line)) !== null) {
				add(match[1].replace(/\\'/g, "'"), file, index + 1);
			}
		}
		pluralRe.lastIndex = 0;
		let match;
		while ((match = pluralRe.exec(line)) !== null) {
			add(match[1].replace(/\\'/g, "'"), file, index + 1, match[2].replace(/\\'/g, "'"));
		}
		doubleQuoteRe.lastIndex = 0;
		while ((match = doubleQuoteRe.exec(line)) !== null) {
			add(match[1], file, index + 1);
		}
	});
}

function escapePot(text) {
	return text.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n');
}

let pot = `# Copyright (C) 2026 Derouiche Oussama — Infinity Coder
# This file is distributed under the GPL v2 or later.
msgid ""
msgstr ""
"Project-Id-Version: Infinity Migrate Pro 1.0.0\\n"
"Report-Msgid-Bugs-To: https://github.com/derouicheoussama/infinity-migratex-pro/issues\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"X-Generator: InfinityMigratePro-tools\\n"
"Language-Team: Derouiche Oussama\\n"
`;

const sorted = [...entries.values()].sort((a, b) => a.msgid.localeCompare(b.msgid));
for (const entry of sorted) {
	pot += `\n#: ${entry.refs.slice(0, 8).join(' ')}\n`;
	pot += `msgid "${escapePot(entry.msgid)}"\n`;
	if (entry.plural !== null) {
		pot += `msgid_plural "${escapePot(entry.plural)}"\n`;
		pot += `msgstr[0] ""\nmsgstr[1] ""\n`;
	} else {
		pot += `msgstr ""\n`;
	}
}

writeFileSync(join(ROOT, 'languages', `${DOMAIN}.pot`), pot, 'utf8');
console.log(`POT généré : ${sorted.length} chaînes.`);
