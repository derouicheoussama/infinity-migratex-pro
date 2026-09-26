<?php

/**
 * ∞ INFINITY CODER — création originale de Derouiche Oussama
 *
 * Plugin   : Infinity Migrate Pro – WordPress Migration, Backup & Deployment Suite
 * Auteur   : Derouiche Oussama  ·  https://www.derouicheoussama.com
 * Copyright © 2026 Derouiche Oussama. Tous droits réservés.
 * Licence  : GPL v2 ou ultérieure — signature et mentions à conserver.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Assistant de migration : Source → Destination → Components →
 * Exclusions → Preflight → Migration (progression réelle par phases).
 */
final class IMP_Admin_Migration {

	/**
	 * Rendu.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'manage' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'migration' );

		$stats  = IMP_Site_Stats::get();
		$job    = IMP_Job::public_status();
		?>
		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Migration assistant', 'infinity-migratex-pro' ); ?></h3>
			</div>
			<div class="imp-panel-body">
				<p class="imp-lead"><?php esc_html_e( 'Migrate the whole site or only part of it: change domain, clone to another folder on this server, or export a ready-to-import package for a new host. Everything runs in chunks — interruptions are resumable.', 'infinity-migratex-pro' ); ?></p>

				<ol class="imp-steps" data-imp-steps>
					<li class="is-active" data-imp-step="1"><?php esc_html_e( 'Source', 'infinity-migratex-pro' ); ?></li>
					<li data-imp-step="2"><?php esc_html_e( 'Destination', 'infinity-migratex-pro' ); ?></li>
					<li data-imp-step="3"><?php esc_html_e( 'Components', 'infinity-migratex-pro' ); ?></li>
					<li data-imp-step="4"><?php esc_html_e( 'Exclusions', 'infinity-migratex-pro' ); ?></li>
					<li data-imp-step="5"><?php esc_html_e( 'Preflight', 'infinity-migratex-pro' ); ?></li>
					<li data-imp-step="6"><?php esc_html_e( 'Migration', 'infinity-migratex-pro' ); ?></li>
				</ol>

				<form id="imp-migration-form" data-imp-wizard="migration">
					<!-- Étape 1 : source -->
					<div class="imp-step" data-imp-step-panel="1">
						<h4><?php esc_html_e( 'Current site (source)', 'infinity-migratex-pro' ); ?></h4>
						<table class="imp-table imp-table-info">
							<tbody>
								<tr><th scope="row"><?php esc_html_e( 'Site URL', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( home_url() ); ?></td></tr>
								<tr><th scope="row"><?php esc_html_e( 'Root path', 'infinity-migratex-pro' ); ?></th><td><code><?php echo esc_html( imp_normalize_path( ABSPATH ) ); ?></code></td></tr>
								<tr><th scope="row"><?php esc_html_e( 'Files', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( number_format_i18n( $stats['files'] ) ); ?></td></tr>
								<tr><th scope="row"><?php esc_html_e( 'Files size', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( imp_format_bytes( $stats['bytes'] ) ); ?></td></tr>
								<tr><th scope="row"><?php esc_html_e( 'Database', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( sprintf( /* translators: 1: tables 2: size */ __( '%1$s tables — %2$s', 'infinity-migratex-pro' ), number_format_i18n( $stats['db_tables'] ), imp_format_bytes( $stats['db_bytes'] ) ) ); ?></td></tr>
							</tbody>
						</table>
					</div>

					<!-- Étape 2 : destination -->
					<div class="imp-step" data-imp-step-panel="2" hidden>
						<h4><?php esc_html_e( 'Where do you want to migrate?', 'infinity-migratex-pro' ); ?></h4>

						<div class="imp-choice-grid" role="radiogroup" aria-label="<?php esc_attr_e( 'Migration type', 'infinity-migratex-pro' ); ?>">
							<label class="imp-choice">
								<input type="radio" name="imp_mig_type" value="domain" checked>
								<strong><?php esc_html_e( 'Change domain / URL', 'infinity-migratex-pro' ); ?></strong>
								<em><?php esc_html_e( 'Same site, new address: URLs rewritten everywhere, serialized-safe.', 'infinity-migratex-pro' ); ?></em>
							</label>
							<label class="imp-choice">
								<input type="radio" name="imp_mig_type" value="clone">
								<strong><?php esc_html_e( 'Clone to another folder (same server)', 'infinity-migratex-pro' ); ?></strong>
								<em><?php esc_html_e( 'Full copy with its own database and fresh wp-config.php.', 'infinity-migratex-pro' ); ?></em>
							</label>
							<label class="imp-choice">
								<input type="radio" name="imp_mig_type" value="export">
								<strong><?php esc_html_e( 'Export a migration package', 'infinity-migratex-pro' ); ?></strong>
								<em><?php esc_html_e( 'One .infinitymigrate file to import on the new host.', 'infinity-migratex-pro' ); ?></em>
							</label>
						</div>

						<div data-imp-mig-fields="domain">
							<div class="imp-field">
								<label for="imp_mig_old_domain"><?php esc_html_e( 'Old URL', 'infinity-migratex-pro' ); ?></label>
								<input type="url" id="imp_mig_old_domain" name="imp_mig_old_domain" value="<?php echo esc_attr( home_url() ); ?>" readonly>
								<p class="imp-hint"><?php esc_html_e( 'Current address of the site (read-only).', 'infinity-migratex-pro' ); ?></p>
							</div>
							<div class="imp-field">
								<label for="imp_mig_new_domain"><?php esc_html_e( 'New URL', 'infinity-migratex-pro' ); ?> *</label>
								<input type="url" id="imp_mig_new_domain" name="imp_mig_new_domain" placeholder="https://newsite.com" required>
								<p class="imp-hint"><?php esc_html_e( 'Full address including http:// or https://, without trailing slash.', 'infinity-migratex-pro' ); ?></p>
							</div>
						</div>

						<div data-imp-mig-fields="clone" hidden>
							<div class="imp-field">
								<label for="imp_mig_dest"><?php esc_html_e( 'Destination folder (absolute path)', 'infinity-migratex-pro' ); ?> *</label>
								<input type="text" id="imp_mig_dest" name="imp_mig_dest" placeholder="/home/user/public_html/newsite" autocomplete="off">
								<p class="imp-hint"><?php esc_html_e( 'Will be created if missing. Must be outside the current site root (a clean subfolder is fine).', 'infinity-migratex-pro' ); ?></p>
							</div>
							<div class="imp-field">
								<label for="imp_mig_clone_url"><?php esc_html_e( 'URL of the clone', 'infinity-migratex-pro' ); ?> *</label>
								<input type="url" id="imp_mig_clone_url" name="imp_mig_clone_url" placeholder="https://newsite.com" required>
							</div>
							<div class="imp-field-row">
								<div class="imp-field">
									<label for="imp_mig_db_host"><?php esc_html_e( 'Target DB host', 'infinity-migratex-pro' ); ?> *</label>
									<input type="text" id="imp_mig_db_host" name="imp_mig_db_host" placeholder="localhost" autocomplete="off">
								</div>
								<div class="imp-field">
									<label for="imp_mig_db_name"><?php esc_html_e( 'Target DB name', 'infinity-migratex-pro' ); ?> *</label>
									<input type="text" id="imp_mig_db_name" name="imp_mig_db_name" autocomplete="off">
								</div>
							</div>
							<div class="imp-field-row">
								<div class="imp-field">
									<label for="imp_mig_db_user"><?php esc_html_e( 'Target DB user', 'infinity-migratex-pro' ); ?> *</label>
									<input type="text" id="imp_mig_db_user" name="imp_mig_db_user" autocomplete="off">
								</div>
								<div class="imp-field">
									<label for="imp_mig_db_pass"><?php esc_html_e( 'Target DB password', 'infinity-migratex-pro' ); ?></label>
									<input type="password" id="imp_mig_db_pass" name="imp_mig_db_pass" autocomplete="new-password">
								</div>
							</div>
							<div class="imp-field-row">
								<div class="imp-field">
									<label for="imp_mig_db_prefix"><?php esc_html_e( 'Table prefix for the clone', 'infinity-migratex-pro' ); ?></label>
									<input type="text" id="imp_mig_db_prefix" name="imp_mig_db_prefix" value="wp_" autocomplete="off">
								</div>
								<div class="imp-field imp-field-check">
									<label><input type="checkbox" id="imp_mig_db_overwrite" name="imp_mig_db_overwrite" value="1">
									<?php esc_html_e( 'Allow overwriting existing tables with this prefix', 'infinity-migratex-pro' ); ?></label>
								</div>
							</div>
							<p class="imp-hint imp-hint-security"><?php esc_html_e( 'Credentials are encrypted (AES-256-GCM when available) for the duration of the migration and destroyed at the end. They never appear in logs or packages.', 'infinity-migratex-pro' ); ?></p>
						</div>

						<div data-imp-mig-fields="export" hidden>
							<div class="imp-field">
								<label for="imp_mig_package_name"><?php esc_html_e( 'Package name', 'infinity-migratex-pro' ); ?></label>
								<input type="text" id="imp_mig_package_name" name="imp_mig_package_name" placeholder="<?php esc_attr_e( 'site-migration', 'infinity-migratex-pro' ); ?>">
								<p class="imp-hint"><?php esc_html_e( 'A .infinitymigrate archive will be built (files + database + manifest with checksums). Import it on the destination from Packages → Import.', 'infinity-migratex-pro' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Étape 3 : composants -->
					<div class="imp-step" data-imp-step-panel="3" hidden>
						<h4><?php esc_html_e( 'What should be migrated?', 'infinity-migratex-pro' ); ?></h4>
						<p class="imp-hint"><?php esc_html_e( 'The database is always migrated except for pure file exports.', 'infinity-migratex-pro' ); ?></p>
						<div class="imp-checks" data-imp-mig="components">
							<label class="imp-check"><input type="checkbox" value="core" checked> <?php esc_html_e( 'WordPress core (wp-admin, wp-includes, root files)', 'infinity-migratex-pro' ); ?></label>
							<label class="imp-check"><input type="checkbox" value="plugins" checked> <?php esc_html_e( 'Plugins', 'infinity-migratex-pro' ); ?></label>
							<label class="imp-check"><input type="checkbox" value="themes" checked> <?php esc_html_e( 'Themes', 'infinity-migratex-pro' ); ?></label>
							<label class="imp-check"><input type="checkbox" value="uploads" checked> <?php esc_html_e( 'Uploads (media)', 'infinity-migratex-pro' ); ?></label>
							<label class="imp-check"><input type="checkbox" value="mu-plugins" checked> <?php esc_html_e( 'mu-plugins', 'infinity-migratex-pro' ); ?></label>
							<label class="imp-check"><input type="checkbox" value="wpcontent" checked> <?php esc_html_e( 'Other wp-content (dropins, custom folders)', 'infinity-migratex-pro' ); ?></label>
							<label class="imp-check imp-check-db"><input type="checkbox" value="database" checked> <?php esc_html_e( 'Database (options, users, posts, pages, WooCommerce & Elementor data)', 'infinity-migratex-pro' ); ?></label>
						</div>

						<h4 style="margin:16px 0 10px;"><?php esc_html_e( 'Migration speed', 'infinity-migratex-pro' ); ?></h4>
						<div class="imp-choice-grid" role="radiogroup" aria-label="<?php esc_attr_e( 'Migration speed', 'infinity-migratex-pro' ); ?>">
							<label class="imp-choice"><input type="radio" name="imp_mig_speed" value="safe"> <strong><?php esc_html_e( 'Safe (shared hosting)', 'infinity-migratex-pro' ); ?></strong> <em><?php esc_html_e( 'Small batches, very low server load.', 'infinity-migratex-pro' ); ?></em></label>
							<label class="imp-choice"><input type="radio" name="imp_mig_speed" value="balanced" checked> <strong><?php esc_html_e( 'Balanced (default)', 'infinity-migratex-pro' ); ?></strong> <em><?php esc_html_e( 'Good speed on most hosts.', 'infinity-migratex-pro' ); ?></em></label>
							<label class="imp-choice"><input type="radio" name="imp_mig_speed" value="turbo"> <strong><?php esc_html_e( 'Turbo', 'infinity-migratex-pro' ); ?> <span class="imp-edition imp-edition-pro">PRO</span></strong> <em><?php esc_html_e( '3× bigger batches, up to 30–50% faster.', 'infinity-migratex-pro' ); ?></em></label>
						</div>
					</div>

					<!-- Étape 4 : exclusions -->
					<div class="imp-step" data-imp-step-panel="4" hidden>
						<h4><?php esc_html_e( 'Exclusions', 'infinity-migratex-pro' ); ?></h4>
						<p class="imp-hint"><?php esc_html_e( 'One pattern per line. Cache folders, logs and other backup plugins are excluded by default.', 'infinity-migratex-pro' ); ?></p>
						<div class="imp-field">
							<label for="imp_mig_exclusions"><?php esc_html_e( 'Files and folders to exclude', 'infinity-migratex-pro' ); ?></label>
							<textarea id="imp_mig_exclusions" name="imp_mig_exclusions" rows="8" class="imp-textarea"><?php echo esc_textarea( imp_setting( 'default_exclusions', imp_default_exclusions_text() ) ); ?></textarea>
						</div>
						<p class="imp-hint"><?php esc_html_e( 'Examples: wp-content/cache/ · wp-content/uploads/cache/ · *.log · *.tmp', 'infinity-migratex-pro' ); ?></p>
					</div>

					<!-- Étape 5 : preflight -->
					<div class="imp-step" data-imp-step-panel="5" hidden>
						<h4><?php esc_html_e( 'Preflight checks', 'infinity-migratex-pro' ); ?></h4>
						<p class="imp-muted" data-imp-preflight-wait><?php esc_html_e( 'Running real checks on this server…', 'infinity-migratex-pro' ); ?></p>
						<div data-imp-preflight-results hidden>
							<table class="imp-table imp-table-health">
								<tbody data-imp-preflight-body></tbody>
							</table>
							<label class="imp-check imp-check-danger" data-imp-preflight-ack hidden>
								<input type="checkbox" data-imp-ack-warnings>
								<?php esc_html_e( 'I understand the warnings above and want to continue anyway.', 'infinity-migratex-pro' ); ?>
							</label>
						</div>
					</div>

					<!-- Étape 6 : exécution -->
					<div class="imp-step" data-imp-step-panel="6" hidden>
						<h4 data-imp-mig-ready><?php esc_html_e( 'Ready to migrate', 'infinity-migratex-pro' ); ?></h4>
						<label class="imp-check">
							<input type="checkbox" id="imp_mig_safety" checked>
							<?php esc_html_e( 'Create a full safety backup before migrating (recommended)', 'infinity-migratex-pro' ); ?>
						</label>
						<div data-imp-jobbox="migration" class="imp-jobbox"></div>
					</div>

					<div class="imp-wizard-nav">
						<button type="button" class="imp-btn imp-btn-ghost" data-imp-wizard-prev hidden><?php esc_html_e( 'Previous', 'infinity-migratex-pro' ); ?></button>
						<button type="button" class="imp-btn imp-btn-primary" data-imp-wizard-next><?php esc_html_e( 'Next', 'infinity-migratex-pro' ); ?></button>
						<button type="button" class="imp-btn imp-btn-primary" data-imp-wizard-run hidden><?php esc_html_e( 'Run preflight', 'infinity-migratex-pro' ); ?></button>
					</div>
				</form>
			</div>
		</section>
		<?php
		if ( 'running' === $job['status'] && in_array( $job['type'], array( 'migration', 'backup' ), true ) ) {
			// Le JS reprend automatiquement le job en cours (reprise).
		}
		IMP_Admin::page_close();
	}

	/**
	 * AJAX : preflight complet.
	 */
	public static function ajax_preflight() {
		IMP_Security::ajax_guard( 'manage' );
		// Nonce explicite pour les analyseurs statiques (ajax_guard ci-dessus le vérifie déjà).
		check_ajax_referer( 'imp-admin', 'nonce', false );


		$config = self::collect_config();
		$result = IMP_Migrator::preflight( $config );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX : démarrage de la migration (identifiants chiffrés immédiatement).
	 */
	public static function ajax_start() {
		IMP_Security::ajax_guard( 'manage' );
		// Nonce explicite pour les analyseurs statiques (ajax_guard ci-dessus le vérifie déjà).
		check_ajax_referer( 'imp-admin', 'nonce', false );


		$config = self::collect_config();

		// Dernière barrière : bloquer si erreurs critiques non confirmées.
		$preflight = IMP_Migrator::preflight( $config );
		if ( $preflight['summary']['error'] > 0 ) {
			wp_send_json_error(
				array(
					'code'    => 'IMP-225',
					'message' => __( 'Preflight still reports critical issues — fix them (or adjust the destination) before running the migration.', 'infinity-migratex-pro' ),
					'checks'  => $preflight['checks'],
				),
				422
			);
		}

		$data = array(
			'type'    => $config['type'],
			'old_url' => $config['old_url'],
			'new_url' => $config['new_url'],
			'name'    => __( 'Migration', 'infinity-migratex-pro' ),
		);

		if ( 'clone' === $config['type'] ) {
			$ref = wp_generate_password( 20, false, false );
			set_transient(
				'imp_migration_creds_' . $ref,
				IMP_Security::encrypt( (string) wp_json_encode( $config['db'] ) ),
				6 * HOUR_IN_SECONDS
			);
			$data['creds_ref']    = $ref;
			$data['dest_path']    = $config['dest_path'];
			$data['db_prefix']    = $config['db_prefix'];
			$data['db_overwrite'] = $config['db_overwrite'];
		}
		if ( ! empty( $config['selection'] ) ) {
			$data['selection'] = $config['selection'];
		}

		$result = IMP_Job::start( 'migration', $data );

		if ( ! $result['ok'] ) {
			wp_send_json_error(
				array(
					'code'    => $result['error'],
					'message' => IMP_Job::error_text( $result['error'] ),
				),
				409
			);
		}

		wp_send_json_success( array( 'status' => IMP_Job::public_status() ) );
	}

	/**
	 * Collecte et nettoie la configuration du wizard depuis POST.
	 *
	 * @return array
	 */
	private static function collect_config() {
		$raw = isset( $_POST['config'] ) ? json_decode( wp_unslash( (string) $_POST['config'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- nettoyage ci-dessous.
		$raw = is_array( $raw ) ? $raw : array();

		$type = isset( $raw['type'] ) ? sanitize_key( (string) $raw['type'] ) : 'domain';
		if ( ! in_array( $type, array( 'domain', 'clone', 'export' ), true ) ) {
			$type = 'domain';
		}

		$config = array(
			'type'    => $type,
			'old_url' => home_url(),
			'new_url' => isset( $raw['new_url'] ) ? esc_url_raw( trim( (string) $raw['new_url'] ) ) : '',
		);

		if ( isset( $raw['selection'] ) && is_array( $raw['selection'] ) ) {
			$config['selection'] = array_values( array_filter( array_map( 'sanitize_key', $raw['selection'] ) ) );
		}

		if ( 'clone' === $type ) {
			$config['dest_path']    = imp_normalize_path( isset( $raw['dest_path'] ) ? (string) $raw['dest_path'] : '' );
			$config['db_prefix']    = preg_replace( '/[^A-Za-z0-9_]/', '', isset( $raw['db_prefix'] ) ? (string) $raw['db_prefix'] : 'wp_' );
			if ( '' === $config['db_prefix'] ) {
				$config['db_prefix'] = 'wp_';
			}
			$config['db_overwrite'] = empty( $raw['db_overwrite'] ) ? 0 : 1;
			$config['db']           = array(
				'host' => isset( $raw['db_host'] ) ? sanitize_text_field( (string) $raw['db_host'] ) : '',
				'name' => isset( $raw['db_name'] ) ? sanitize_text_field( (string) $raw['db_name'] ) : '',
				'user' => isset( $raw['db_user'] ) ? sanitize_text_field( (string) $raw['db_user'] ) : '',
				'pass' => isset( $raw['db_pass'] ) ? (string) $raw['db_pass'] : '',
			);
		}

		return $config;
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
