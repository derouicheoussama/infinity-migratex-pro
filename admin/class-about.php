<?php

/**
 * ∞ INFINITY CODER — création originale de Derouiche Oussama
 *
 * Plugin   : Infinity MigrateX Pro – WordPress Migration, Backup & Deployment Suite
 * Auteur   : Derouiche Oussama  ·  https://www.derouicheoussama.com
 * Copyright © 2026 Derouiche Oussama. Tous droits réservés.
 * Licence  : GPL v2 ou ultérieure — signature et mentions à conserver.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Page About : présentation claire du produit, argumentaire de valeur,
 * appel au téléchargement, liens officiels de l'auteur (aucun lien
 * inventé), matrice fonctionnalités Free/Pro, changelog.
 */
final class IMP_Admin_About {

	/**
	 * Lien réel vers la dernière release téléchargeable.
	 *
	 * @return string
	 */
	private static function download_url() {
		return 'https://github.com/derouicheoussama/infinity-migratex-pro/releases/latest';
	}

	/**
	 * Rendu.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'about' );
		?>
		<section class="imp-panel imp-about-hero">
			<div class="imp-panel-body">
				<img class="imp-about-logo" src="<?php echo esc_url( IMP_URL . 'assets/images/logo-migratex-256.png' ); ?>" alt="Infinity MigrateX Pro" />
				<h2>Infinity MigrateX Pro</h2>
				<p class="imp-about-tagline"><?php esc_html_e( 'Migration & Backup Suite for WordPress', 'infinity-migratex-pro' ); ?></p>
				<p class="imp-about-pitch"><?php esc_html_e( 'Move, back up and restore a complete WordPress site — without timeouts and without breaking anything. Every operation runs in small resumable chunks: a domain change, a full clone or a daily backup survives interruptions and always shows real progress. Serialized data, WooCommerce orders and Elementor pages come out intact on the other side.', 'infinity-migratex-pro' ); ?></p>

				<div class="imp-hero-cta">
					<a class="imp-btn imp-btn-primary" href="<?php echo esc_url( self::download_url() ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Download the latest version (free)', 'infinity-migratex-pro' ); ?>
					</a>
					<a class="imp-btn" href="https://www.derouicheoussama.com" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
						<?php esc_html_e( 'Visit the author’s website', 'infinity-migratex-pro' ); ?>
					</a>
					<a class="imp-btn imp-btn-ghost" href="https://profiles.wordpress.org/derouicheoussama/" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-wordpress" aria-hidden="true"></span>
						<?php esc_html_e( 'WordPress.org profile', 'infinity-migratex-pro' ); ?>
					</a>
				</div>
				<p class="imp-cta-note"><?php esc_html_e( 'GPL v2 or later — 100% of the features listed below work in this version. Updates are delivered automatically from official GitHub releases.', 'infinity-migratex-pro' ); ?></p>
			</div>
		</section>

		<?php /* ⚠️ NOTE POUR DEROUICHE : panneau principal d'upsell — affiché
		       * EN PREMIER volontairement. Prix modifiables ci-dessous. */ ?>
		<section class="imp-panel" style="border:2px solid #ddccf8;">
			<div class="imp-panel-head"><h3>★ <?php esc_html_e( 'Upgrade to Pro', 'infinity-migratex-pro' ); ?></h3></div>
			<div class="imp-panel-body">
				<div class="imp-grid imp-grid-2">
					<div class="imp-feature-card" style="flex-direction:column;">
						<strong><?php esc_html_e( 'Personal — 1 site', 'infinity-migratex-pro' ); ?></strong>
						<p style="font-size:22px;font-weight:800;color:#1e2a44;margin:4px 0;">39€ / <span style="font-size:13px;color:#5c688a;"><?php esc_html_e( 'year', 'infinity-migratex-pro' ); ?></span> · 79€ <?php esc_html_e( 'lifetime', 'infinity-migratex-pro' ); ?></p>
						<p><?php esc_html_e( 'Cloud backups (Drive, Dropbox, FTP), scheduled backups with e-mail alerts, Turbo speed, cross-site URL rewrite, priority support.', 'infinity-migratex-pro' ); ?></p>
						<p><a class="imp-btn imp-btn-primary" style="margin-top:8px;" data-imp-checkout="personal" href="<?php echo esc_url( IMP_License::checkout_url() ); ?>"><?php esc_html_e( 'Buy Personal — pay here', 'infinity-migratex-pro' ); ?></a></p>
					</div>
					<div class="imp-feature-card" style="flex-direction:column;border-color:#ddccf8;">
						<strong style="color:#5a2bbf;"><?php esc_html_e( 'Business — 5 sites', 'infinity-migratex-pro' ); ?> ⭐</strong>
						<p style="font-size:22px;font-weight:800;color:#1e2a44;margin:4px 0;">89€ / <span style="font-size:13px;color:#5c688a;"><?php esc_html_e( 'year', 'infinity-migratex-pro' ); ?></span> · 179€ <?php esc_html_e( 'lifetime', 'infinity-migratex-pro' ); ?></p>
						<p><?php esc_html_e( 'Everything in Personal, for 5 sites — the best value for freelancers and agencies managing several WordPress installs.', 'infinity-migratex-pro' ); ?></p>
						<p><a class="imp-btn" style="margin-top:8px;background:linear-gradient(120deg,#7b2ff7,#a45cff);border-color:transparent;color:#fff;" data-imp-checkout="business" href="<?php echo esc_url( IMP_License::checkout_url() ); ?>"><?php esc_html_e( 'Buy Business — pay here', 'infinity-migratex-pro' ); ?></a></p>
					</div>
				</div>

				<?php /* Achat ET activation dans le plugin : le checkout s'ouvre
				       * dans une fenêtre modale, la clé s'active ici même. */ ?>
				<div style="margin-top:14px;border-top:1px solid #e7ebf5;padding-top:14px;">
					<strong><?php esc_html_e( 'Already paid? Activate your license now', 'infinity-migratex-pro' ); ?></strong>
					<form data-imp-license-form style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
						<input type="text" name="license_key" placeholder="<?php esc_attr_e( 'Paste your license key…', 'infinity-migratex-pro' ); ?>" autocomplete="off" style="flex:1 1 260px;">
						<button type="submit" class="imp-btn imp-btn-primary"><?php esc_html_e( 'Activate', 'infinity-migratex-pro' ); ?></button>
					</form>
					<p class="imp-hint"><?php esc_html_e( 'The secure checkout opens in a window inside this plugin — you never leave WordPress.', 'infinity-migratex-pro' ); ?></p>
				</div>
			</div>
		</section>

		<?php /* ⚠️ NOTE POUR DEROUICHE : centre de mises à jour dual-canal —
		       * wp.org natif (le paquet officiel) + GitHub direct (édition
		       * hors wp.org). Statuts pré-remplis depuis le cache, vérif
		       * automatique à l'ouverture, bouton « Update now » réel. */
		$upd_gh    = class_exists( 'IMP_Updater' ) ? IMP_Updater::cached_status() : null;
		$upd_wporg = null;
		$upd_transient = get_site_transient( 'update_plugins' );
		$upd_basename  = plugin_basename( IMP_FILE );
		if ( is_object( $upd_transient ) && ! empty( $upd_transient->response[ $upd_basename ] ) ) {
			$upd_wporg = array(
				'available' => true,
				'latest'    => (string) $upd_transient->response[ $upd_basename ]->new_version,
			);
		}
		?>
		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Updates — dual channel', 'infinity-migratex-pro' ); ?></h3>
				<p><?php esc_html_e( 'WordPress.org (official) and GitHub Releases (direct) — checked automatically when this page opens.', 'infinity-migratex-pro' ); ?></p>
			</div>
			<div class="imp-panel-body">
				<table class="imp-table imp-upd-table">
					<tbody>
						<tr>
							<th scope="row">WordPress.org</th>
							<td data-imp-upd-wporg>
								<?php
								echo esc_html(
									$upd_wporg
										/* translators: %s: version */
										? sprintf( __( 'Version %s available.', 'infinity-migratex-pro' ), $upd_wporg['latest'] )
										: __( 'Up to date (cached).', 'infinity-migratex-pro' )
								);
								?>
							</td>
						</tr>
						<tr>
							<th scope="row">GitHub Releases</th>
							<td data-imp-upd-github>
								<?php if ( null === $upd_gh ) : ?>
									<?php esc_html_e( 'Channel inactive on this edition (official WordPress.org channel).', 'infinity-migratex-pro' ); ?>
								<?php elseif ( empty( $upd_gh['active'] ) ) : ?>
									<?php /* Désactivé explicitement (le défaut de cette
									 * édition est ACTIF — règle Plugin Check respectée
									 * par exclusion du module du paquet wp.org). */ ?>
									<div class="imp-notice imp-notice-info" style="margin:0;">
										<strong><?php esc_html_e( 'Channel disabled — re-enable it in one line:', 'infinity-migratex-pro' ); ?></strong><br>
										<?php esc_html_e( 'Remove this line from wp-config.php (or set it to true), just above “/* That’s all, stop editing! */” :', 'infinity-migratex-pro' ); ?><br>
										<code>define( 'INFINITY_MIGRATEX_PRO_GH_UPDATES', false );</code>
										<button type="button" class="imp-btn imp-btn-ghost imp-btn-small" style="margin-left:6px;" data-imp-copy="define( 'INFINITY_MIGRATEX_PRO_GH_UPDATES', true );">⧉ <?php esc_html_e( 'Copy true version', 'infinity-migratex-pro' ); ?></button><br>
										<span class="description">
											<?php
											echo esc_html( sprintf(
												/* translators: %s: wp-config path */
												__( 'File: %s — then click “Check for updates now”. The official WordPress.org edition never uses this channel (its updates come from WordPress.org).', 'infinity-migratex-pro' ),
												ABSPATH . 'wp-config.php'
											) );
											?>
										</span>
									</div>
								<?php elseif ( '' !== $upd_gh['error'] ) : ?>
									<?php /* Erreur + contexte actionnable : repo et asset attendus. */ ?>
									⚠ <?php echo esc_html( $upd_gh['error'] ); ?><br>
									<span class="description">
										<?php
										echo esc_html( sprintf(
											/* translators: 1: repo 2: asset */
											__( 'Configured repo: %1$s — expected asset: %2$s. Publish a GitHub release containing this asset, then check again.', 'infinity-migratex-pro' ),
											INFINITY_MIGRATEX_PRO_GH_REPO,
											INFINITY_MIGRATEX_PRO_GH_ASSET
										) );
										?>
									</span>
								<?php elseif ( '' === $upd_gh['latest'] ) : ?>
									<?php esc_html_e( 'Not checked yet.', 'infinity-migratex-pro' ); ?>
								<?php else : ?>
									<?php
									echo esc_html(
										$upd_gh['available']
											/* translators: %s: version */
											? sprintf( __( 'Version %s available.', 'infinity-migratex-pro' ), $upd_gh['latest'] )
											: __( 'Up to date (cached).', 'infinity-migratex-pro' )
									);
									?>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>
				<p style="margin:12px 0 0;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
					<button type="button" class="imp-btn imp-btn-primary" data-imp-update-check>⟳ <?php esc_html_e( 'Check for updates now', 'infinity-migratex-pro' ); ?></button>
					<button type="button" class="imp-btn imp-btn-primary" data-imp-update-run hidden>↑ <?php esc_html_e( 'Update now', 'infinity-migratex-pro' ); ?></button>
					<span class="imp-hint" style="margin:0;" data-imp-upd-installed>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: version */
								__( 'Installed version: %s.', 'infinity-migratex-pro' ),
								IMP_VERSION
							)
						);
						?>
					</span>
				</p>
			</div>
		</section>

		<?php /* ⚠️ NOTE POUR DEROUICHE : la section ci-dessous explique le
		       * fonctionnement en 3 étapes + le scénario « ancien site vers
		       * nouveau site ». Personnalise librement les textes entre
		       * esc_html_e( '…' ) ci-dessous. */ ?>
		<section class="imp-panel">
			<div class="imp-panel-head"><h3><?php esc_html_e( 'How it works — three simple workflows', 'infinity-migratex-pro' ); ?></h3></div>
			<div class="imp-panel-body">
				<div class="imp-feature-cards">
					<div class="imp-feature-card">
						<span class="dashicons dashicons-database-export" aria-hidden="true"></span>
						<div>
							<strong><?php esc_html_e( '1 · Back up', 'infinity-migratex-pro' ); ?></strong>
							<p><?php esc_html_e( 'Backups → Create Backup. Files, database or both, with SHA-256 checksums. Schedule it daily and let retention keep the last N copies automatically — optionally uploaded to Google Drive, Dropbox or FTP (Pro).', 'infinity-migratex-pro' ); ?></p>
						</div>
					</div>
					<div class="imp-feature-card">
						<span class="dashicons dashicons-migrate" aria-hidden="true"></span>
						<div>
							<strong><?php esc_html_e( '2 · Migrate', 'infinity-migratex-pro' ); ?></strong>
							<p><?php esc_html_e( 'Migration assistant: change the domain (URLs rewritten serialized-safe), clone the site to a staging folder, or export a single .infinitymigrate package to carry to a new host.', 'infinity-migratex-pro' ); ?></p>
						</div>
					</div>
					<div class="imp-feature-card">
						<span class="dashicons dashicons-database-import" aria-hidden="true"></span>
						<div>
							<strong><?php esc_html_e( '3 · Restore', 'infinity-migratex-pro' ); ?></strong>
							<p><?php esc_html_e( 'Pick a backup, integrity is verified before anything is written, choose the components, optional maintenance mode and safety backup — then restore with live progress.', 'infinity-migratex-pro' ); ?></p>
						</div>
					</div>
					<div class="imp-feature-card">
						<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
						<div>
							<strong><?php esc_html_e( 'Cross-site restore (old site → new site)', 'infinity-migratex-pro' ); ?></strong>
							<p><?php esc_html_e( 'Backing up site A and restoring it on site B? Restore → pick the backup → the old URLs detected in the database are automatically rewritten to the new site’s address (posts, Elementor, WooCommerce). Condition: both sites use the same table prefix (wp_ by default).', 'infinity-migratex-pro' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</section>

		<?php /* ⚠️ NOTE POUR DEROUICHE : zone de notes personnelles — remplace
		       * les textes « [Note] » ci-dessous par tes propres informations
		       * (annonces, conseils, liens de support…). */ ?>
		<section class="imp-panel">
			<div class="imp-panel-head"><h3><?php esc_html_e( 'Author’s notes', 'infinity-migratex-pro' ); ?></h3></div>
			<div class="imp-panel-body">
				<ul class="imp-usp-list">
					<li><span class="dashicons dashicons-edit" aria-hidden="true"></span><?php esc_html_e( '[Note — place here your announcement: new release, launch promotion, tutorial video…]', 'infinity-migratex-pro' ); ?></li>
					<li><span class="dashicons dashicons-edit" aria-hidden="true"></span><?php esc_html_e( '[Note — place here your support channel: e-mail, contact form URL, response time…]', 'infinity-migratex-pro' ); ?></li>
					<li><span class="dashicons dashicons-edit" aria-hidden="true"></span><?php esc_html_e( '[Note — place here your tip of the day: always run a full backup before a migration, test on staging first…]', 'infinity-migratex-pro' ); ?></li>
				</ul>
			</div>
		</section>

		<section class="imp-panel">
			<div class="imp-panel-head"><h3><?php esc_html_e( 'What it does for you', 'infinity-migratex-pro' ); ?></h3></div>
			<div class="imp-panel-body">
				<div class="imp-feature-cards">
					<div class="imp-feature-card">
						<span class="dashicons dashicons-migrate" aria-hidden="true"></span>
						<div>
							<strong><?php esc_html_e( 'Migrate or clone any site', 'infinity-migratex-pro' ); ?></strong>
							<p><?php esc_html_e( 'Change domain with a full URL rewrite, clone the site to another folder with its own database, or export a single .infinitymigrate package for a new host. Partial migrations (only plugins, only uploads…) included.', 'infinity-migratex-pro' ); ?></p>
						</div>
					</div>
					<div class="imp-feature-card">
						<span class="dashicons dashicons-database-export" aria-hidden="true"></span>
						<div>
							<strong><?php esc_html_e( 'Backups that actually finish', 'infinity-migratex-pro' ); ?></strong>
							<p><?php esc_html_e( 'Full, files-only or database backups with SHA-256 checksums, scheduled daily/weekly/monthly runs and automatic retention — no PHP timeout can interrupt them.', 'infinity-migratex-pro' ); ?></p>
						</div>
					</div>
					<div class="imp-feature-card">
						<span class="dashicons dashicons-database-import" aria-hidden="true"></span>
						<div>
							<strong><?php esc_html_e( 'Guided, verified restore', 'infinity-migratex-pro' ); ?></strong>
							<p><?php esc_html_e( 'Integrity is verified before anything is written, you choose exactly what gets restored, and an optional safety backup protects the current site first.', 'infinity-migratex-pro' ); ?></p>
						</div>
					</div>
					<div class="imp-feature-card">
						<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
						<div>
							<strong><?php esc_html_e( 'Security built in', 'infinity-migratex-pro' ); ?></strong>
							<p><?php esc_html_e( 'Core checksums against WordPress.org, detection of PHP files in uploads, suspicious-code patterns, protected storage, Zip-Slip-proof package imports — and zero telemetry.', 'infinity-migratex-pro' ); ?></p>
						</div>
					</div>
				</div>
			</div>
		</section>

		<section class="imp-panel">
			<div class="imp-panel-head"><h3><?php esc_html_e( 'Why site owners pick Infinity MigrateX Pro', 'infinity-migratex-pro' ); ?></h3></div>
			<div class="imp-panel-body">
				<ul class="imp-usp-list">
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Chunked engine: works on shared hosting with small time limits.', 'infinity-migratex-pro' ); ?></li>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Pause and resume every operation exactly where it stopped.', 'infinity-migratex-pro' ); ?></li>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'URL replacement that never breaks serialized PHP or JSON data.', 'infinity-migratex-pro' ); ?></li>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Progress bars reflect real work — files, rows, bytes, no fakes.', 'infinity-migratex-pro' ); ?></li>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'WooCommerce orders and Elementor pages migrate intact.', 'infinity-migratex-pro' ); ?></li>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Detailed logs for every operation, exportable in one click.', 'infinity-migratex-pro' ); ?></li>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'No hidden tracking, no obfuscated code, no phoning home.', 'infinity-migratex-pro' ); ?></li>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Your backups are never deleted — not even on uninstall.', 'infinity-migratex-pro' ); ?></li>
				</ul>
			</div>
		</section>

		<div class="imp-grid imp-grid-2">
			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Links', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body">
					<ul class="imp-links">
						<li><span class="dashicons dashicons-download" aria-hidden="true"></span> <a href="<?php echo esc_url( self::download_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Download the latest release — GitHub', 'infinity-migratex-pro' ); ?></a></li>
						<li><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span> <a href="https://www.derouicheoussama.com" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Website — derouicheoussama.com', 'infinity-migratex-pro' ); ?></a></li>
						<li><span class="dashicons dashicons-github" aria-hidden="true"></span> <a href="https://github.com/derouicheoussama/infinity-migratex-pro" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Source code & issues — GitHub', 'infinity-migratex-pro' ); ?></a></li>
						<li><span class="dashicons dashicons-wordpress" aria-hidden="true"></span> <a href="https://profiles.wordpress.org/derouicheoussama/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'WordPress.org profile', 'infinity-migratex-pro' ); ?></a></li>
						<li><span class="dashicons dashicons-instagram" aria-hidden="true"></span> <a href="https://www.instagram.com/derouiche.oussama/" target="_blank" rel="noopener noreferrer">Instagram — @derouiche.oussama</a></li>
						<li><span class="dashicons dashicons-facebook-alt" aria-hidden="true"></span> <a href="https://www.facebook.com/derouiche.oussama" target="_blank" rel="noopener noreferrer">Facebook — Derouiche Oussama</a></li>
						<li><span class="dashicons dashicons-video-alt3" aria-hidden="true"></span> <a href="https://www.tiktok.com/@derouiche.oussama" target="_blank" rel="noopener noreferrer">TikTok — @derouiche.oussama</a></li>
					</ul>
				</div>
			</section>
		</div>

		<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Features — Free & Pro editions', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body">
					<table class="imp-table imp-table-list">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Feature', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Edition', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'infinity-migratex-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( IMP_License::feature_matrix() as $feature ) : ?>
								<tr>
									<td><?php echo esc_html( $feature['feature'] ); ?><?php echo isset( $feature['note'] ) ? ' <em class="imp-muted">' . esc_html( $feature['note'] ) . '</em>' : ''; ?></td>
									<td><span class="imp-edition imp-edition-<?php echo esc_attr( $feature['tier'] ); ?>"><?php echo esc_html( 'pro' === $feature['tier'] ? 'PRO' : 'FREE' ); ?></span></td>
									<td>
										<?php
										echo $feature['available']
											? IMP_Admin::badge( 'pass', __( 'Available', 'infinity-migratex-pro' ) ) // phpcs:ignore WordPress.Security.EscapeOutput
											: IMP_Admin::badge( 'neutral', __( 'Planned', 'infinity-migratex-pro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
						</table>
					</div>
				</section>

			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Changelog', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body">
					<h4>3.13.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'New: every Go Pro button opens the in-plugin purchase wizard — backup encryption, scheduled backups, cloud, WooCommerce tools.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.12.1</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Hardening: all Plugin Check errors fixed — ordered placeholders, explicit nonces on every AJAX handler, sanitized inputs throughout.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.12.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Robustness: executable author watermark in all 46 code files, verified from the Security page; defensive Google Sheets logging.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.11.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'New (Pro): Google Sheets tracking — every operation logged as a row in your spreadsheet, native service-account API, encrypted credentials.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.10.1</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'UI: centered footer with author credit, useful links and social icons on every screen.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.10.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Commercialization hardening: unguessable license activation, order-by-e-mail/WhatsApp channels, ready-to-deploy license server.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.9.1</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Fixed: after an update you always land on a valid page (About or Plugins list) — never the "not allowed" error.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.9.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Performance (ultra fast): micro-bundle on the Plugins screen, cached dashboard counters, adaptive job polling.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.8.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Fixed (important): no more automatic deactivation after updates — folder rename filter repaired + guaranteed automatic re-activation with duplicate cleanup.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.7.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Fixed: a connection-lost pause now self-resumes — the runner probes every 45 s and continues the operation conservatively once the connection returns.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.6.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Fixed: self-healing operations on slow hosting — automatic request-throttling and exponential-backoff retries instead of "Connection lost" pauses.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.5.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'New (Pro): safety backup before every WordPress update; new 14-day free PRO trial; license state memoized per request.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.4.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Fixed: updates now detected automatically — the GitHub channel is active by default on this edition and both channels are checked passively every day.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.3.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Performance (major): the dashboard never waits on the full-site scan anymore — stale-while-revalidate statistics, cached health checks and cached packages list.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.2.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'New: clear edition identity — golden PRO pill and tags everywhere, dashboard edition banner, persistent upgrade entry points on the Free edition.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.1.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Performance: targeted update detection (one API call) + passive daily cron detection with a topbar update badge.', 'infinity-migratex-pro' ); ?></li>
						<li><?php esc_html_e( 'New: WooCommerce Import & Export panel — from → to flow with a detailed live inventory.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>3.0.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Simplified backup form: name + contents + one button, everything else in a collapsible Advanced options section.', 'infinity-migratex-pro' ); ?></li>
						<li><?php esc_html_e( 'Fixed: stale backup name after rename; encrypted-backup-without-password is now refused server-side (IMP-258).', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>2.9.1</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Improved: the inactive GitHub channel now shows a full activation guide with a Copy button; channel errors display the repo and expected asset.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>2.9.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'New: settings search, JSON export/import of the configuration, default restore, adjustable smart reminders, Ctrl+S to save.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>2.8.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Performance: minified assets (JS −41%), session pre-warm of Dashboard & Settings, lighter locked Cloud tab.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>2.7.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'New: Update Center — automatic background check, cached statuses on open, and one-click "Update to X" without leaving the page.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>2.6.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Performance: instant page-to-page navigation (SPA) — no more full admin reloads; zero plugin code on public pages; backups list cached.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>2.5.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'New: dual update system — official WordPress.org channel and direct GitHub Releases channel, with a one-click check on the About page.', 'infinity-migratex-pro' ); ?></li>
						<li><?php esc_html_e( 'New (GitHub): ETag conditional requests, compatibility metadata, icons in the update dialog, structured errors.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>2.4.2</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Fixed: the Database "Repair" action ran "Optimize" (operator precedence) — Repair repairs again.', 'infinity-migratex-pro' ); ?></li>
						<li><?php esc_html_e( 'Quality: full wp.org-style compliance audit — 0 errors across all PHP files.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>2.4.1</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Fixed: no more "unexpected output" at activation; admin menu icon and sidebar logo sized correctly on every admin theme.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>2.4.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'New: Settings in ONE screen — instant tabs, sticky save bar, AJAX saving with unsaved-changes protection.', 'infinity-migratex-pro' ); ?></li>
						<li><?php esc_html_e( 'Performance: ThickBox dropped from plugin pages; lighter admin assets.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>2.3.1</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'Fixed: no more false "SECURITY ALERT" after a legitimate update — the source seal rebuilds itself automatically when the sealed version differs from the installed one.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>2.3.0</h4>
					<ul class="imp-changelog">
						<li><?php esc_html_e( 'New: in-plugin purchase wizard — Go Pro in 4 steps (plan, details, payment, activation) without leaving WordPress.', 'infinity-migratex-pro' ); ?></li>
						<li><?php esc_html_e( 'New: Personal / Business with yearly & lifetime pricing, order summary and instant license activation.', 'infinity-migratex-pro' ); ?></li>
						<li><?php esc_html_e( 'Improved: every Go Pro button opens the same unified wizard — About, Import, Plugins page and Turbo upsells.', 'infinity-migratex-pro' ); ?></li>
					</ul>
					<h4>1.9.0</h4>
				<ul class="imp-changelog">
					<li><?php esc_html_e( 'New identity: Infinity MigrateX Pro — official neon logo in the plugin and WordPress.org assets generated.', 'infinity-migratex-pro' ); ?></li>
				</ul>
				<h4>1.7.0</h4>
				<ul class="imp-changelog">
					<li><?php esc_html_e( 'New: Safe / Balanced / Turbo speed presets — Turbo (Pro) uses 3× bigger batches for 30-50% faster operations.', 'infinity-migratex-pro' ); ?></li>
					<li><?php esc_html_e( 'Pro sharpened: scheduled backups, e-mail alerts and cross-site URL rewrite are now Pro features (server-enforced).', 'infinity-migratex-pro' ); ?></li>
					<li><?php esc_html_e( 'New pricing panel in About: Personal (1 site) and Business (5 sites) plans.', 'infinity-migratex-pro' ); ?></li>
				</ul>
				<h4>1.5.0</h4>
				<ul class="imp-changelog">
					<li><?php esc_html_e( 'Reliability: SQL import failures are now detected, reported and stop the import to protect data.', 'infinity-migratex-pro' ); ?></li>
					<li><?php esc_html_e( 'Reliability: comment-aware SQL parsing with strict verb allowlist, disk watchdog, zip self-verification, backup ID collision guard.', 'infinity-migratex-pro' ); ?></li>
					<li><?php esc_html_e( 'Restore: optional maintenance mode, WP version mismatch warning, stale cache drop-in detection.', 'infinity-migratex-pro' ); ?></li>
					<li><?php esc_html_e( 'Operations keep progressing when the browser tab is closed; scheduled backup e-mail notifications.', 'infinity-migratex-pro' ); ?></li>
				</ul>
				<h4>1.4.0</h4>
				<ul class="imp-changelog">
					<li><?php esc_html_e( 'New: multi-layer hardening — rate limiting, security journal, source code seal with daily integrity checks.', 'infinity-migratex-pro' ); ?></li>
					<li><?php esc_html_e( 'New (Pro): HMAC-signed license with domain binding, private GitHub update channel with authenticated proxy.', 'infinity-migratex-pro' ); ?></li>
				</ul>
				<h4>1.3.0</h4>
				<ul class="imp-changelog">
					<li><?php esc_html_e( 'New (Pro): cloud backup destinations — Google Drive, Dropbox, FTP/FTPS, with resumable uploads and connection test.', 'infinity-migratex-pro' ); ?></li>
					<li><?php esc_html_e( 'New: automatic cloud sending after every backup, or one-click per backup.', 'infinity-migratex-pro' ); ?></li>
				</ul>
				<h4>1.2.0</h4>
				<ul class="imp-changelog">
					<li><?php esc_html_e( 'New: enriched Plugins page — version status, full details & history modal, Go Pro banner.', 'infinity-migratex-pro' ); ?></li>
				</ul>
				<h4>1.1.0</h4>
				<ul class="imp-changelog">
					<li><?php esc_html_e( 'New: About page with full product tour and direct download links.', 'infinity-migratex-pro' ); ?></li>
					<li><?php esc_html_e( 'New: instant navigation — pages are prefetched while you hover the menu.', 'infinity-migratex-pro' ); ?></li>
					<li><?php esc_html_e( 'New: smooth page transitions (respects reduced-motion preferences).', 'infinity-migratex-pro' ); ?></li>
				</ul>
				<h4>1.0.2</h4>
				<ul class="imp-changelog">
					<li><?php esc_html_e( 'Fixed a fatal error at installation on PHP 7.4/8.0 (octal literal).', 'infinity-migratex-pro' ); ?></li>
					<li><?php esc_html_e( 'New clean light dashboard with reinforced contrast.', 'infinity-migratex-pro' ); ?></li>
				</ul>
				<h4>1.0.0</h4>
				<ul class="imp-changelog">
					<li><?php esc_html_e( 'Initial release: full & partial migration assistant (domain, folder clone, package export).', 'infinity-migratex-pro' ); ?></li>
					<li><?php esc_html_e( 'Chunked, resumable engine — no PHP timeouts, pause/resume/cancel on every operation.', 'infinity-migratex-pro' ); ?></li>
					<li><?php esc_html_e( 'Backups with manifest, SHA-256 checksums, scheduled backups and retention.', 'infinity-migratex-pro' ); ?></li>
					<li><?php esc_html_e( 'Guided restore with pre-restore integrity verification.', 'infinity-migratex-pro' ); ?></li>
					<li><?php esc_html_e( 'Verified .infinitymigrate packages (Zip Slip protection) and serialized-safe URL replacement.', 'infinity-migratex-pro' ); ?></li>
					<li><?php esc_html_e( 'Security scanner, database manager, detailed logs, WooCommerce & Elementor integrations.', 'infinity-migratex-pro' ); ?></li>
				</ul>
			</div>
		</section>
		<?php

		IMP_Admin::page_close();
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
