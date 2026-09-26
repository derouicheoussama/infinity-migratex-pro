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
 * Page Packages : inventaire .infinitymigrate, import vérifié
 * (upload → scan → confirmation explicite → import), téléchargement.
 */
final class IMP_Admin_Packages {

	/**
	 * Rendu.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'restore' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'packages' );

		$packages = IMP_Package::all();
		?>
		<div class="imp-grid imp-grid-2">
			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Import a package', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body">
					<p class="imp-muted"><?php esc_html_e( 'Packages are verified before anything is written: signature, manifest, checksums and a content scan. Nothing is extracted without your explicit confirmation.', 'infinity-migratex-pro' ); ?></p>

					<form id="imp-package-upload-form" data-imp-package-upload enctype="multipart/form-data">
						<div class="imp-field">
							<label for="imp_package_file"><?php esc_html_e( 'Upload a .infinitymigrate / .zip package', 'infinity-migratex-pro' ); ?></label>
							<input type="file" id="imp_package_file" name="package_file" accept=".infinitymigrate,.zip">
							<p class="imp-hint"><?php echo esc_html( sprintf( /* translators: %s: max size */ __( 'Max upload size: %s (server limit).', 'infinity-migratex-pro' ), ini_get( 'upload_max_filesize' ) ?: '—' ) ); ?></p>
						</div>
						<button type="submit" class="imp-btn imp-btn-primary"><?php esc_html_e( 'Upload & scan', 'infinity-migratex-pro' ); ?></button>
					</form>

					<?php if ( ! empty( $packages ) ) : ?>
						<div class="imp-field">
							<label for="imp_package_server"><?php esc_html_e( '… or scan a package already on this server', 'infinity-migratex-pro' ); ?></label>
							<select id="imp_package_server" data-imp-package-server>
								<option value=""><?php esc_html_e( '— Choose a stored package —', 'infinity-migratex-pro' ); ?></option>
								<?php foreach ( $packages as $package ) : ?>
									<option value="<?php echo esc_attr( $package['file'] ); ?>"><?php echo esc_html( $package['name'] . ' — ' . imp_format_bytes( $package['size'] ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<section class="imp-panel" data-imp-package-preview hidden>
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Package preview', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body" data-imp-package-preview-body></div>
				<div class="imp-panel-actions" data-imp-package-preview-actions hidden>
					<label class="imp-check"><input type="checkbox" data-imp-import-ack> <?php esc_html_e( 'I have reviewed the content above and confirm the import (existing data will be overwritten).', 'infinity-migratex-pro' ); ?></label>
					<button type="button" class="imp-btn imp-btn-danger" data-imp-action="package-import-confirm" data-imp-confirm="import"><?php esc_html_e( 'Import package', 'infinity-migratex-pro' ); ?></button>
				</div>
				<div data-imp-jobbox="package_import" class="imp-jobbox"></div>
			</section>
		</div>

		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Stored packages', 'infinity-migratex-pro' ); ?></h3>
				<span class="imp-muted"><?php echo esc_html( sprintf( /* translators: %s */ _n( '%s package', '%s packages', count( $packages ), 'infinity-migratex-pro' ), number_format_i18n( count( $packages ) ) ) ); ?></span>
			</div>
			<div class="imp-panel-body">
				<?php if ( empty( $packages ) ) : ?>
					<p class="imp-muted"><?php esc_html_e( 'No packages yet. Create one from a backup (Backups → Export) or from the Migration assistant (export mode).', 'infinity-migratex-pro' ); ?></p>
				<?php else : ?>
					<table class="imp-table imp-table-list">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Name', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Created', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Size', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Contents', 'infinity-migratex-pro' ); ?></th>
								<th scope="col" class="imp-col-actions"><?php esc_html_e( 'Actions', 'infinity-migratex-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $packages as $package ) : ?>
								<?php
								$download_url = wp_nonce_url(
									add_query_arg(
										array(
											'action' => 'imp_download_package',
											'file'   => rawurlencode( $package['file'] ),
										),
										admin_url( 'admin-post.php' )
									),
									'imp-download'
								);
								$manifest = $package['manifest'];
								?>
								<tr data-imp-package-file="<?php echo esc_attr( $package['file'] ); ?>">
									<td><strong><?php echo esc_html( $package['name'] ); ?></strong><br><code><?php echo esc_html( $package['file'] ); ?></code></td>
									<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', gmdate( 'Y-m-d H:i:s', $package['modified'] ) ) ); ?></td>
									<td><?php echo esc_html( imp_format_bytes( $package['size'] ) ); ?></td>
									<td>
										<?php
										if ( is_array( $manifest ) ) {
											$counts = isset( $manifest['counts'] ) ? $manifest['counts'] : array();
											echo esc_html(
												sprintf(
													/* translators: 1: files 2: rows */
													__( '%1$s files · %2$s rows', 'infinity-migratex-pro' ),
													number_format_i18n( (int) ( $counts['files'] ?? 0 ) ),
													number_format_i18n( (int) ( $counts['db_rows'] ?? 0 ) )
												)
											);
										} else {
											esc_html_e( 'Manifest unreadable', 'infinity-migratex-pro' );
										}
										?>
									</td>
									<td class="imp-col-actions">
										<div class="imp-row-actions">
											<a class="imp-btn imp-btn-small imp-btn-ghost" href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Download', 'infinity-migratex-pro' ); ?></a>
											<button type="button" class="imp-btn imp-btn-small imp-btn-ghost" data-imp-action="package-scan" data-file="<?php echo esc_attr( $package['file'] ); ?>"><?php esc_html_e( 'Scan', 'infinity-migratex-pro' ); ?></button>
											<button type="button" class="imp-btn imp-btn-small imp-btn-danger" data-imp-action="package-delete" data-file="<?php echo esc_attr( $package['file'] ); ?>"><?php esc_html_e( 'Delete', 'infinity-migratex-pro' ); ?></button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</section>
		<?php

		IMP_Admin::page_close();
	}

	/**
	 * AJAX : upload + scan d'un package (aucune extraction ici).
	 */
	public static function ajax_scan() {
		IMP_Security::ajax_guard( 'restore' );
		// Nonce explicite pour les analyseurs statiques (ajax_guard ci-dessus le vérifie déjà).
		check_ajax_referer( 'imp-admin', 'nonce', false );


		$upload_ok = false;

		// Cas 1 : upload.
		if ( ! empty( $_FILES['package_file'] ) && ! empty( $_FILES['package_file']['name'] ) ) {
			$uploaded = IMP_Security::handle_upload( 'package_file', array( 'infinitymigrate', 'zip' ) );
			if ( is_string( $uploaded ) ) {
				wp_send_json_error( array( 'code' => $uploaded, 'message' => IMP_Job::error_text( $uploaded ) ), 422 );
			}
			$file     = (string) $uploaded['file'];
			$upload_ok = true;
		} elseif ( isset( $_POST['server_file'] ) ) {
			// Cas 2 : fichier déjà présent dans packages/.
			$name = basename( sanitize_file_name( wp_unslash( (string) $_POST['server_file'] ) ) );
			$file = IMP_Package::dir() . $name;
		} else {
			wp_send_json_error( array( 'code' => 'IMP-301', 'message' => IMP_Job::error_text( 'IMP-301' ) ), 400 );
		}

		if ( empty( $file ) || ! is_file( $file ) ) {
			wp_send_json_error( array( 'code' => 'IMP-218', 'message' => IMP_Job::error_text( 'IMP-218' ) ), 404 );
		}

		$scan = IMP_Package::scan( $file );
		if ( ! $scan['ok'] ) {
			$error = isset( $scan['error'] ) ? $scan['error'] : 'IMP-217';
			wp_send_json_error(
				array(
					'code'    => $error,
					'message' => IMP_Job::error_text( $error ),
					'alerts'  => $scan['alerts'],
					'stats'   => $scan['stats'],
				),
				422
			);
		}

		$manifest = $scan['manifest'];
		$stats    = $scan['stats'];

		wp_send_json_success(
			array(
				'file'       => ( $upload_ok ? 'tmp:' : '' ) . basename( $file ),
				'name'       => isset( $manifest['name'] ) ? $manifest['name'] : basename( $file ),
				'created'    => isset( $manifest['created'] ) ? $manifest['created'] : '',
				'site_url'   => isset( $manifest['site_url'] ) ? $manifest['site_url'] : '',
				'wp_version' => isset( $manifest['wp_version'] ) ? $manifest['wp_version'] : '',
				'php_version' => isset( $manifest['php_version'] ) ? $manifest['php_version'] : '',
				'counts'     => isset( $manifest['counts'] ) ? $manifest['counts'] : array(),
				'sizes'      => isset( $manifest['sizes'] ) ? $manifest['sizes'] : array(),
				'stats'      => $stats,
				'alerts'     => $scan['alerts'],
				'checksums_ok' => true,
			)
		);
	}

	/**
	 * AJAX : démarrage de l'import après confirmation explicite.
	 */
	public static function ajax_start_import() {
		IMP_Security::ajax_guard( 'restore' );
		// Nonce explicite pour les analyseurs statiques (ajax_guard ci-dessus le vérifie déjà).
		check_ajax_referer( 'imp-admin', 'nonce', false );


		$file = isset( $_POST['file'] ) ? basename( sanitize_file_name( wp_unslash( (string) $_POST['file'] ) ) ) : '';
		if ( '' === $file ) {
			wp_send_json_error( array( 'code' => 'IMP-218', 'message' => IMP_Job::error_text( 'IMP-218' ) ), 400 );
		}

		$components = isset( $_POST['components'] ) && is_array( $_POST['components'] )
			? array_values( array_filter( array_map( 'sanitize_key', wp_unslash( $_POST['components'] ) ) ) )
			: array( 'core', 'plugins', 'themes', 'uploads', 'mu-plugins', 'wpcontent', 'database' );

		$result = IMP_Job::start(
			'package_import',
			array(
				'package'            => $file,
				'restore_components' => $components,
				'name'               => __( 'Package import', 'infinity-migratex-pro' ),
			)
		);

		if ( ! $result['ok'] ) {
			wp_send_json_error( array( 'code' => $result['error'], 'message' => IMP_Job::error_text( $result['error'] ) ), 409 );
		}

		wp_send_json_success( array( 'status' => IMP_Job::public_status() ) );
	}

	/**
	 * AJAX : suppression d'un package.
	 */
	public static function ajax_action() {
		IMP_Security::ajax_guard( 'restore' );
		// Nonce explicite pour les analyseurs statiques (ajax_guard ci-dessus le vérifie déjà).
		check_ajax_referer( 'imp-admin', 'nonce', false );


		$do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$file = isset( $_POST['file'] ) ? basename( sanitize_file_name( wp_unslash( (string) $_POST['file'] ) ) ) : '';

		if ( 'delete' === $do ) {
			if ( '' === $file || ! IMP_Package::delete( $file ) ) {
				wp_send_json_error( array( 'code' => 'IMP-218', 'message' => __( 'Package not found or delete failed.', 'infinity-migratex-pro' ) ), 404 );
			}
			wp_send_json_success( array( 'deleted' => $file ) );
		}

		wp_send_json_error( array( 'code' => 'IMP-224', 'message' => IMP_Job::error_text( 'IMP-224' ) ), 400 );
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
