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
 * Page Import : glisser-déposer d'une archive .infinitymigrate/.zip,
 * vérification en direct (signature, manifest, checksums, scan de
 * contenu) puis import guidé. Remplace le flux manuel de Duplicator :
 * tu déposes le fichier, le plugin fait le reste — sans limite de taille.
 */
final class IMP_Admin_Import {

	/**
	 * Enregistre les endpoints AJAX.
	 */
	public static function init() {
		add_action( 'wp_ajax_imp_import_upload', array( __CLASS__, 'ajax_upload' ) );
		add_action( 'wp_ajax_imp_import_start', array( __CLASS__, 'ajax_start' ) );
	}

	/**
	 * Rendu de la page.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'restore' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'import' );
		?>
		<section class="imp-panel">
			<div class="imp-panel-head"><h3><?php esc_html_e( 'Import a site — drag & drop', 'infinity-migratex-pro' ); ?></h3></div>
			<div class="imp-panel-body">
				<div class="imp-dropzone" data-imp-dropzone tabindex="0" role="button" aria-label="<?php esc_attr_e( 'Drop your archive here or click to browse', 'infinity-migratex-pro' ); ?>">
					<span class="dashicons dashicons-upload" aria-hidden="true"></span>
					<strong><?php esc_html_e( 'Drop your .infinitymigrate or .zip archive here', 'infinity-migratex-pro' ); ?></strong>
					<em><?php esc_html_e( '… or click to browse. No size limit — huge archives are fine.', 'infinity-migratex-pro' ); ?></em>
					<input type="file" accept=".infinitymigrate,.zip" data-imp-dropfile hidden>
				</div>

				<div class="imp-import-preview" data-imp-import-preview hidden>
					<h4><?php esc_html_e( 'Package verified', 'infinity-migratex-pro' ); ?></h4>
					<table class="imp-table imp-table-info">
						<tbody>
							<tr><th scope="row"><?php esc_html_e( 'Name', 'infinity-migratex-pro' ); ?></th><td data-imp-imp="name">—</td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Created', 'infinity-migratex-pro' ); ?></th><td data-imp-imp="created">—</td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Source site', 'infinity-migratex-pro' ); ?></th><td data-imp-imp="site">—</td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Files / DB rows', 'infinity-migratex-pro' ); ?></th><td data-imp-imp="counts">—</td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Checksums', 'infinity-migratex-pro' ); ?></th><td><span class="imp-badge imp-badge-pass"><?php esc_html_e( 'VERIFIED', 'infinity-migratex-pro' ); ?></span></td></tr>
						</tbody>
					</table>
				</div>

				<div class="imp-import-options" data-imp-import-options hidden>
					<h4><?php esc_html_e( 'Import options', 'infinity-migratex-pro' ); ?></h4>
					<label class="imp-check"><input type="checkbox" data-imp-opt="files" checked> <?php esc_html_e( 'Restore files (themes, plugins, uploads…)', 'infinity-migratex-pro' ); ?></label>
					<label class="imp-check"><input type="checkbox" data-imp-opt="database" checked> <?php esc_html_e( 'Import database', 'infinity-migratex-pro' ); ?></label>
					<label class="imp-check"><input type="checkbox" data-imp-opt="maintenance" checked> 🚧 <?php esc_html_e( 'Maintenance mode during import', 'infinity-migratex-pro' ); ?></label>

					<div class="imp-notice imp-notice-warning" style="margin-top:12px;">
						<strong><?php esc_html_e( 'Careful:', 'infinity-migratex-pro' ); ?></strong>
						<?php esc_html_e( 'importing replaces the selected files and database tables on this site. wp-config.php is never touched.', 'infinity-migratex-pro' ); ?>
					</div>

					<button type="button" class="imp-btn imp-btn-danger" data-imp-import-start disabled><?php esc_html_e( 'Start import', 'infinity-migratex-pro' ); ?></button>
				</div>
			</div>
		</section>

		<section class="imp-panel">
			<div class="imp-panel-head"><h3><?php esc_html_e( 'What happens during the import', 'infinity-migratex-pro' ); ?></h3></div>
			<div class="imp-panel-body">
				<ol class="imp-pipeline" data-imp-pipeline>
					<li data-pipe="validation"><strong><?php esc_html_e( 'Validation', 'infinity-migratex-pro' ); ?></strong><?php esc_html_e( 'zip signature, manifest, SHA-256 checksums and content scan (Zip-Slip protection).', 'infinity-migratex-pro' ); ?></li>
					<li data-pipe="files"><strong><?php esc_html_e( 'Files', 'infinity-migratex-pro' ); ?></strong><?php esc_html_e( 'themes, plugins and uploads extracted with path validation.', 'infinity-migratex-pro' ); ?></li>
					<li data-pipe="database"><strong><?php esc_html_e( 'Database', 'infinity-migratex-pro' ); ?></strong><?php esc_html_e( 'tables imported statement by statement, failures stop everything.', 'infinity-migratex-pro' ); ?></li>
					<li data-pipe="links"><strong><?php esc_html_e( 'Links & cache', 'infinity-migratex-pro' ); ?></strong><?php esc_html_e( 'caches cleared, rewrite rules flushed.', 'infinity-migratex-pro' ); ?></li>
					<li data-pipe="cleanup"><strong><?php esc_html_e( 'Cleanup', 'infinity-migratex-pro' ); ?></strong><?php esc_html_e( 'temporary data removed, integrity summary displayed.', 'infinity-migratex-pro' ); ?></li>
				</ol>
				<p class="imp-hint"><?php esc_html_e( 'Every step is resumable: if anything interrupts the import, it resumes exactly where it stopped.', 'infinity-migratex-pro' ); ?></p>
			</div>
		</section>

		<div class="imp-grid imp-grid-2">
			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Pro import features', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body">
					<div class="imp-pro-card">
						<div class="imp-blur">
							<strong><?php esc_html_e( 'Import from Google Drive / Dropbox', 'infinity-migratex-pro' ); ?></strong>
							<p><?php esc_html_e( 'Pick a package straight from your cloud storage — no manual download.', 'infinity-migratex-pro' ); ?></p>
						</div>
						<button type="button" class="imp-btn imp-btn-primary" data-imp-checkout href="<?php echo esc_url( IMP_License::checkout_url() ); ?>">★ <?php esc_html_e( 'Go Pro', 'infinity-migratex-pro' ); ?></button>
					</div>
					<div class="imp-pro-card">
						<div class="imp-blur">
							<strong><?php esc_html_e( 'Turbo import', 'infinity-migratex-pro' ); ?></strong>
							<p><?php esc_html_e( '3× bigger batches — imports finish 30-50% faster on dedicated hosting.', 'infinity-migratex-pro' ); ?></p>
						</div>
						<button type="button" class="imp-btn imp-btn-primary" data-imp-checkout href="<?php echo esc_url( IMP_License::checkout_url() ); ?>">★ <?php esc_html_e( 'Go Pro', 'infinity-migratex-pro' ); ?></button>
					</div>
					<div class="imp-pro-card">
						<div class="imp-blur">
							<strong><?php esc_html_e( 'Automatic URL rewriting on import', 'infinity-migratex-pro' ); ?></strong>
							<p><?php esc_html_e( 'Old-site URLs detected and rewritten to this address automatically during the import.', 'infinity-migratex-pro' ); ?></p>
						</div>
						<button type="button" class="imp-btn imp-btn-primary" data-imp-checkout href="<?php echo esc_url( IMP_License::checkout_url() ); ?>">★ <?php esc_html_e( 'Go Pro', 'infinity-migratex-pro' ); ?></button>
					</div>
				</div>
			</section>

			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Included free', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body">
					<ul class="imp-usp-list">
						<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Unlimited import size — no paid unlock, ever.', 'infinity-migratex-pro' ); ?></li>
						<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Resumable import — interruptions never restart from zero.', 'infinity-migratex-pro' ); ?></li>
						<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Zip-Slip and path-traversal protection on every entry.', 'infinity-migratex-pro' ); ?></li>
						<li><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php esc_html_e( 'Maintenance mode and safety options built in.', 'infinity-migratex-pro' ); ?></li>
					</ul>
				</div>
			</section>
		</div>

		<div data-imp-jobbox="package_import" class="imp-jobbox"></div>
		<?php

		IMP_Admin::page_close();
	}

	/**
	 * AJAX : upload + scan complet du package (aucune extraction ici).
	 */
	public static function ajax_upload() {
		IMP_Security::ajax_guard( 'restore' );

		if ( empty( $_FILES['package_file']['name'] ) ) {
			wp_send_json_error( array( 'code' => 'IMP-301', 'message' => IMP_Job::error_text( 'IMP-301' ) ), 400 );
		}

		$uploaded = IMP_Security::handle_upload( 'package_file', array( 'infinitymigrate', 'zip' ) );
		if ( is_string( $uploaded ) ) {
			wp_send_json_error( array( 'code' => $uploaded, 'message' => IMP_Job::error_text( $uploaded ) ), 422 );
		}

		$path = (string) $uploaded['file'];
		$scan = IMP_Package::scan( $path );
		if ( empty( $scan['ok'] ) ) {
			wp_delete_file( $path );
			$error = isset( $scan['error'] ) ? $scan['error'] : 'IMP-217';
			wp_send_json_error( array( 'code' => $error, 'message' => IMP_Job::error_text( $error ) ), 422 );
		}

		// Jeton de session : le chemin du package n'est jamais envoyé au client.
		$token = wp_generate_password( 24, false, false );
		set_transient(
			'imp_import_' . $token,
			array( 'path' => $path ),
			HOUR_IN_SECONDS
		);

		$manifest = $scan['manifest'];
		wp_send_json_success(
			array(
				'token'      => $token,
				'name'       => isset( $manifest['name'] ) ? $manifest['name'] : basename( $path ),
				'created'    => isset( $manifest['created'] ) ? $manifest['created'] : '',
				'site_url'   => isset( $manifest['site_url'] ) ? $manifest['site_url'] : '',
				'counts'     => isset( $manifest['counts'] ) ? $manifest['counts'] : array(),
				'wp_version' => isset( $manifest['wp_version'] ) ? $manifest['wp_version'] : '',
			)
		);
	}

	/**
	 * AJAX : démarre l'import (package_import) après re-vérification.
	 */
	public static function ajax_start() {
		IMP_Security::ajax_guard( 'restore' );

		$token = isset( $_POST['token'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', (string) wp_unslash( $_POST['token'] ) ) : '';
		$info  = $token ? get_transient( 'imp_import_' . $token ) : false;
		if ( ! is_array( $info ) || empty( $info['path'] ) || ! is_file( $info['path'] ) ) {
			wp_send_json_error( array( 'code' => 'IMP-218', 'message' => __( 'Uploaded package expired — drop it again.', 'infinity-migratex-pro' ) ), 422 );
		}

		$scan = IMP_Package::scan( $info['path'] );
		if ( empty( $scan['ok'] ) ) {
			wp_send_json_error( array( 'code' => 'IMP-217', 'message' => __( 'Package verification failed — import refused.', 'infinity-migratex-pro' ) ), 422 );
		}

		$components = isset( $_POST['components'] ) && is_array( $_POST['components'] )
			? array_values( array_filter( array_map( 'sanitize_key', wp_unslash( $_POST['components'] ) ) ) )
			: array( 'core', 'plugins', 'themes', 'uploads', 'mu-plugins', 'wpcontent', 'database' );
		if ( empty( $components ) ) {
			$components = array( 'core', 'plugins', 'themes', 'uploads', 'mu-plugins', 'wpcontent', 'database' );
		}

		$result = IMP_Job::start(
			'package_import',
			array(
				'package'            => basename( $info['path'] ),
				'package_path'       => $info['path'],
				'restore_components' => $components,
				'maintenance'        => ! empty( $_POST['maintenance'] ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				'name'               => __( 'Drag & drop import', 'infinity-migratex-pro' ),
			)
		);

		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'code' => $result['error'], 'message' => IMP_Job::error_text( $result['error'] ) ), 409 );
		}

		delete_transient( 'imp_import_' . $token );
		wp_send_json_success( array( 'status' => IMP_Job::public_status() ) );
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
