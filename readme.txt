=== Infinity MigrateX Pro ===
Contributors: derouicheoussama
Tags: backup, clone, export-import, migrate, move
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Migrate, back up and restore any WordPress site without timeouts: resumable chunked engine, serialized-safe URL rewriting, scheduled backups, scanner.

== Description ==

Infinity MigrateX Pro is a complete **WordPress migration, backup and restore suite** built for real-world hosting: every long operation runs in small resumable chunks, pauses itself and resumes exactly where it stopped. No PHP timeouts, no lost data, no fake progress bars.

**Why it is different**

* **No size limits, ever** — export and import sites of any size on the free edition; no paid add-on needed to unlock imports.
* **Resumable everything** — a backup or migration interrupted by a timeout, network cut or closed tab resumes where it stopped.
* **Serialized-safe URL rewriting included** — PHP serialized data and JSON are decoded, replaced and re-encoded (Elementor, WooCommerce, options, meta).
* **Real progress** — bars reflect files, rows and bytes actually processed.
* **Security-first** — nonce + dedicated capabilities on every action, protected storage, Zip-Slip-proof package imports, AES-256-GCM backup encryption (Pro).

**Migration assistant (6 steps)**

1. **Domain change** — every URL in the database is rewritten without breaking serialized PHP or JSON data, with a dry-run preview.
2. **Clone to a folder or subdomain** — full copy with its own database and a generated wp-config.php, perfect for staging.
3. **.infinitymigrate package export** — one file (files + database + SHA-256 manifest) to carry to any host.

**Backups & restore**

* Full, files-only or database-only backups with SHA-256 manifests
* Scheduled daily / weekly / monthly backups with automatic retention
* Guided restore: integrity verified before anything is written, component selection, optional safety backup
* Verified package import: zip signature, manifest, checksums, content scan, explicit confirmation

**Tools included**

* Smart URL replacer with dry-run (serialized PHP + JSON aware)
* Security scanner: WordPress.org core checksums, PHP in uploads, suspicious patterns
* Database manager: tables, export, verified SQL import, optimize & repair
* Detailed activity logs with filters, search and CSV/JSON export
* Dedicated WooCommerce page: live compatibility check (HPOS, lookup tables), sessions, transients and lookup-table tools

**Security & compatibility**

* Dedicated `infinity_migratex_*` capabilities, nonces on every action, .htaccess-protected storage
* AES-256-GCM backup encryption with restore password (Pro)
* WooCommerce (classic & HPOS) and Elementor detected, with post-migration maintenance tools
* No telemetry, no obfuscated code, no unsolicited remote requests
* Works on shared hosting: PHP 7.4+, WordPress 5.8+, ZipArchive

== Installation ==

1. In your WordPress admin: Plugins → Add New → Upload Plugin, then pick `infinity-migratex-pro.zip`.
2. Activate the plugin.
3. Open the **Infinity MigrateX Pro** menu.
4. Tip: run a full backup before your first migration.

You can also copy the `infinity-migratex-pro` folder into `/wp-content/plugins/` via FTP.

== Frequently Asked Questions ==

= How do I import a backup from an old site into a new WordPress site? =

1. On the old site: Backups → Create Backup (full), then Download.
2. On the new site: install Infinity MigrateX Pro (same database table prefix, `wp_` by default) and load the backup.
3. Restore → select the backup → the "rewrite old-site URLs" option detects the old URL in the imported database and rewrites it to the new address (posts, Elementor, WooCommerce, options).
4. Finish with Flush rewrite rules (Tools). Media files transfer via FTP or a .infinitymigrate package.

= How do I change my WordPress domain without breaking links? =

Use Migration → "Change domain / URL". The replacer decodes serialized PHP and JSON, rewrites URLs and re-encodes them — string lengths stay correct and Elementor pages, WooCommerce settings and widgets keep working. A dry-run shows what will change before anything is written.

= Can my host's PHP timeout interrupt a backup? =

No. The engine works in time-boxed chunks; every request advances a few seconds and saves its state. Operations pause themselves and resume with one click — even after a network cut or server restart.

= Is the progress bar real? =

Yes. Every step reports the work actually done server-side (files added, bytes copied, rows processed). No simulated progress, ever.

= Can URL replacement corrupt my serialized data? =

No — that is its core design. Serialized values are decoded, replaced recursively and re-serialized with correct lengths. Unreadable serialized data is never touched. JSON follows the same decode → replace → encode path.

= Are my target database credentials stored? =

No. For a clone they are encrypted (AES-256-GCM when OpenSSL is available) for the duration of the operation and then destroyed. They never appear in logs or packages.

= Does uninstalling delete my backups? =

Never. Even the opt-in "Delete plugin data on uninstall" setting (off by default) only removes settings, logs and reports — the archives stay on disk.

= Does the plugin send data to external servers? =

No telemetry. The only external requests are core checksums from the official WordPress.org API (scanner) and update checks against GitHub Releases (optional GitHub-edition channel).

== Changelog ==

= 3.1.0 =

* Performance: update detection is now targeted — a single API request for this plugin instead of purging the global update transient (which forced a re-scan of EVERY installed plugin). Other plugins' update data is preserved.
* New: passive detection — the daily maintenance cron runs the targeted check, so an available update shows a persistent "Update X.Y.Z" badge in the plugin topbar without opening any page.
* New: WooCommerce Import & Export panel with a from → to flow — export a database snapshot or a full .infinitymigrate package from this site, import a package into this site, with a detailed live inventory (products + variations, orders with HPOS mode, customers, coupons, webhooks, tables).

= 3.0.0 =

* Simplified: the backup form now shows just a name, the three content cards and one button — exclusions, speed, cloud sending and encryption live in a collapsible "Advanced options" section (default exclusions still apply when collapsed).
* Fixed: renaming a backup could show the old name for up to 60 seconds on the Backups page and Dashboard (list cache was not invalidated).
* Fixed (security hardening): requesting an encrypted backup without a password is now refused with a clear error (IMP-258) instead of silently producing an archive locked with an empty key — the JS already prevented it, the server now enforces it too.

= 2.9.1 =

* Improved: the "Channel inactive" GitHub state now shows a full activation guide — the exact wp-config.php line with a Copy button, the file location, and the note that the official WordPress.org edition never uses this channel (by design, per Plugin Check rules).
* Improved: a GitHub channel error now displays the configured repo and the expected release asset name, so a missing/misnamed release is obvious.

= 2.9.0 =

* New: settings search — type two letters to filter every setting across all tabs and jump to the matching section instantly.
* New: configuration tools — export your setup as JSON and import it on another site in seconds (cloud secrets are never exported; they stay encrypted per site), and one-click "Restore default settings" that keeps your license, backups and logs.
* New: adjustable smart reminders — choose after how many days without a backup or a security scan the dashboard should remind you (or never).
* New: Ctrl+S / Cmd+S saves the settings; the save bar shows when settings were last saved.

= 2.8.1 =

* Fixed: "Update now" could fail with "No update is currently offered" even though the check had just found one — the check now persists its offer (12 h, wp.org priority) and the button re-injects it if the core transient was cleared in between.

= 2.8.0 =

* Performance: shipped assets are now minified (JS −41%, CSS −23%) — faster download AND much faster re-execution on every instant navigation. Sources are served with SCRIPT_DEBUG=true (WordPress standard).
* Performance: Dashboard and Settings are pre-warmed once per admin session 3 seconds after arrival, so switching to them is instant even on first click.
* Performance: the locked Cloud tab renders a light teaser only (the provider configuration blocks are no longer rendered when Pro is inactive).

= 2.7.0 =

* New: the dual-channel Updates panel is now a real Update Center — statuses are pre-filled from cache when the page opens, refreshed automatically in the background, and an "Update to X" button applies the actual update without leaving the page (full WordPress upgrader: compatibility checks, filesystem, automatic source-code re-seal).
* If WordPress needs FTP credentials, the button explains it once and points to the Plugins screen.

= 2.6.0 =

* Performance: INSTANT navigation — switching between plugin pages no longer reloads the whole admin: the target page is fetched while you hover the menu and only the plugin content is swapped (SPA-style), with the browser back button fully working and a full fallback if anything fails.
* Performance: the plugin no longer loads ANY of its code on the public-facing site (front-end visits pay zero) — it now only runs in admin, AJAX, cron and WP-CLI contexts.
* Performance: the 16 admin page classes load only when actually serving a plugin page (a regular admin page like Posts loads just the shell) — the Extensions page keeps its enriched banner.
* Performance: the backups list (dashboard, Backups and Restore pages) is cached 60 s instead of re-scanning the whole storage directory on every view — invalidated instantly on create/delete/retention.

= 2.5.0 =

* New: dual update system — WordPress.org (official, native) and GitHub Releases (direct/pro channel) shown together on the About page with a one-click "Check for updates now" button hitting both systems.
* New (GitHub channel): conditional ETag requests (304 responses no longer consume API quota), compatibility metadata (WP/PHP) sent with every update offer, plugin icons in the update dialog, structured error surfacing, force-check.
* Fixed: a redundant unconditional require could fatal the WordPress.org package (updater module excluded there) — the class now loads only when present and all call sites are guarded.
* The official WordPress.org channel always has priority over GitHub when both offer an update.

= 2.4.2 =

* Fixed: on the Database page, the "Repair" action actually ran "Optimize" due to an operator-precedence mistake — Repair now repairs.
* Quality: full wp.org-style compliance audit (escaping, nonces, i18n translators comments, timezone functions, PHP 8-only functions, version consistency) — 0 errors across all 52 PHP files; every flagged pattern verified or documented.

= 2.4.1 =

* Fixed: "1 character of unexpected output" at activation — a stray byte before the opening PHP tag of the main file (encoding-repair leftover) is removed; the plugin now activates silently.
* Fixed: the WordPress admin menu icon and the sidebar logo are now constrained to their proper sizes (20 px menu, 26 px sidebar) on every admin theme.

= 2.4.0 =

* New: Settings in ONE screen — all 8 sections rendered in a single form; tabs switch instantly without page reloads (hash deep links kept, ?tab= links still work without JavaScript).
* New: live saving — a sticky save bar with an "Unsaved changes" indicator; settings save over AJAX (same sanitization pipeline) and a warning guards against leaving with unsaved edits. Theme changes still apply via a single reload.
* Performance: ThickBox no longer loaded on plugin pages (only the Plugins screen uses it).

= 2.3.1 =

* Fixed: the source-code seal no longer raises a false "SECURITY ALERT" after a legitimate update — the baseline is rebuilt automatically whenever the sealed version differs from the installed version (manual upload, FTP copy or WP upgrader), while alterations WITHOUT a version change still trigger the alert.
* Fixed: the integrity alert used the old plugin name.

= 2.3.0 =

* New: in-plugin purchase wizard — Go Pro in 4 steps (plan, details, payment, activation) without leaving WordPress.
* New: Personal / Business plans with yearly & lifetime pricing, order summary, copyable order details, and instant license activation with success feedback.
* Improved: every Go Pro button (About, Import, Plugins page, blurred cards, Turbo upsells) now opens the same unified wizard.
* Fixed: duplicate 2.1.1 changelog entry merged.

= 2.2.0 =

* UX: dashboard cockpit — at-a-glance status, latest backup with actions, smart reminders, animated counters.
* UX: persistent progress bar across all plugin pages, real-time ETA, success confetti (respects reduced motion).
* UX: optional dark mode (light / dark / auto), live import pipeline, full-screen drop overlay, loading skeletons, mobile quick-backup button.
* A11y: keyboard skip link, larger touch targets.

= 2.1.1 =
* Fixed: the "last operation failed" alert no longer appears on every WordPress admin page — it is now shown only inside Infinity MigrateX Pro screens (it still auto-expires after 24 hours)
* Improved: automatic recovery from error IMP-203 — if the temporary file list is removed by the server between two steps, the index is rebuilt and the operation resumes by itself (backup and migration, before any file is processed)
* Branding: official Infinity MigrateX Pro logo everywhere — admin menu icon, sidebar, About hero, and regenerated WordPress.org assets (banners + icons).

= 2.1.0 =

* Nouveau : page Import dédiée — glisser-déposer de l'archive, vérification en direct (signature, manifest, checksums, scan), options et démarrage guidé.
* Nouveau : cartes Pro floutées (import cloud, Turbo, réécriture auto) avec popup Go Pro au clic.
* Pipeline d'import visible : Validation → Fichiers → Base de données → Liens & cache → Cleanup.

= 2.0.1 =

* Plugin Check pass: English readme, updater moved to the optional GitHub edition, filesystem/database sniffs addressed with documented justifications, escaping and translators comments fixed.

= 2.0.0 =

* New (Pro): **Secure Backup Encryption** — archives protected with AES-256-GCM (per-chunk authentication tags); a restore password is required and never stored inside the backup.
* One-click Delete action on the Plugins page (deactivates + deletes; backups are always kept).
* Note: the plugin folder is now `infinity-migratex-pro` — remove the old copy before installing this one (your backups remain in place).

= 1.9.0 =

* New identity: Infinity MigrateX Pro — official neon logo in the plugin and WordPress.org assets generated (banners + icons).

= 1.8.2 =

* Upgrade to Pro panel first on the About page with in-plugin checkout modal and on-the-spot license activation.
* Contrast fix: CTA links keep readable text in every state.

= 1.8.1 =

* Diagnostics: real fatal errors now displayed in the admin via an auto-installed diagnostic mu-plugin; official WooCommerce icon in the menu.

= 1.8.0 =

* New dedicated WooCommerce page: live compatibility check (HPOS, lookup tables), session/transient/lookup tools and migration options (exclude sessions and Action Scheduler logs from dumps).

= 1.7.0 =

* New Safe / Balanced / Turbo speed presets — Turbo (Pro) uses 3x bigger batches for 30-50% faster operations.
* Pro sharpened: scheduled backups, e-mail alerts and cross-site URL rewrite are server-enforced Pro features.

= 1.6.1 =

* Fix: duplicate admin initialization; SAFE_MODE bissection constant.

= 1.6.0 =

* New cross-site restore: backups from another site are restored with automatic old-URL rewriting (serialized-safe, resumable).

= 1.5.0 =

* Reliability: SQL import failures are detected, reported and stop the import; comment-aware SQL parsing; disk watchdog; zip self-verification; maintenance mode; e-mail notifications.

= 1.4.0 =

* Multi-layer hardening: rate limiting, security journal, source-code seal, HMAC-signed licenses with domain binding, private GitHub update channel.

= 1.3.0 =

* Cloud backup destinations (Pro): Google Drive, Dropbox, FTP/FTPS with resumable uploads and connection test.

= 1.2.0 =

* Enriched Plugins page: version status, details & history modal, Go Pro banner.

= 1.1.0 =

* Instant navigation (menu prefetch), redesigned About page, SEO/GEO readme.

= 1.0.0 =

* Initial release: migration assistant (domain change, folder clone, package export), chunked resumable engine, backups with checksums and retention, guided verified restore, .infinitymigrate packages with Zip-Slip protection, serialized-safe URL replacement with dry-run, security scanner, database manager, detailed logs, WooCommerce & Elementor integrations.

== Upgrade Notice ==

= 2.0.0 =

AES-256 backup encryption, one-click delete on the Plugins page, full Infinity MigrateX Pro branding. Remove the old copy before installing (your backups are kept).
