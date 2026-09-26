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
 * Page Backups : création (full/files/database), liste réelle, actions
 * Download / Restore / Export / Verify / Rename / Delete, programmation.
 */
final class IMP_Admin_Backup {

	/**
	 * Rendu.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'backup' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'backups' );

		$backups  = IMP_Backup_Engine::all();
		$settings = imp_settings();
		?>
		<div class="imp-grid imp-grid-2">
			<section class="imp-panel">
				<div class="imp-panel-head"><h3><?php esc_html_e( 'Create a backup', 'infinity-migratex-pro' ); ?></h3></div>
				<div class="imp-panel-body">
					<form id="imp-backup-form" data-imp-backup-form>
						<div class="imp-field">
							<label for="imp_backup_name"><?php esc_html_e( 'Backup name', 'infinity-migratex-pro' ); ?></label>
							<input type="text" id="imp_backup_name" name="backup_name" placeholder="<?php esc_attr_e( 'Before migration', 'infinity-migratex-pro' ); ?>">
						</div>
						<div class="imp-field">
							<label><?php esc_html_e( 'Contents', 'infinity-migratex-pro' ); ?></label>
							<div class="imp-choice-grid imp-choice-grid-row" role="radiogroup">
								<label class="imp-choice"><input type="radio" name="backup_components" value="full" checked><strong><?php esc_html_e( 'Full', 'infinity-migratex-pro' ); ?></strong><em><?php esc_html_e( 'Files + database', 'infinity-migratex-pro' ); ?></em></label>
								<label class="imp-choice"><input type="radio" name="backup_components" value="files"><strong><?php esc_html_e( 'Files only', 'infinity-migratex-pro' ); ?></strong><em><?php esc_html_e( 'No database', 'infinity-migratex-pro' ); ?></em></label>
								<label class="imp-choice"><input type="radio" name="backup_components" value="database"><strong><?php esc_html_e( 'Database only', 'infinity-migratex-pro' ); ?></strong><em><?php esc_html_e( 'SQL dump', 'infinity-migratex-pro' ); ?></em></label>
							</div>
						</div>
						<?php /* PERFORMANCE UX — un formulaire de 2 champs et un
						 * bouton : tout le reste (exclusions, vitesse, cloud,
						 * chiffrement) vit dans l'accordéon ci-dessous. Les
						 * exclusions par défaut du réglage s'appliquent
						 * toujours même replié. */ ?>
						<details class="imp-advanced">
							<summary>⚙ <?php esc_html_e( 'Advanced options', 'infinity-migratex-pro' ); ?> <em><?php esc_html_e( 'exclusions, speed, cloud, encryption', 'infinity-migratex-pro' ); ?></em></summary>
							<div class="imp-advanced-body">
								<div class="imp-field">
									<label for="imp_backup_exclusions"><?php esc_html_e( 'Exclusions', 'infinity-migratex-pro' ); ?></label>
									<textarea id="imp_backup_exclusions" name="backup_exclusions" rows="6" class="imp-textarea"><?php echo esc_textarea( $settings['default_exclusions'] ); ?></textarea>
								</div>
								<div class="imp-field">
									<label><?php esc_html_e( 'Speed', 'infinity-migratex-pro' ); ?></label>
									<div class="imp-choice-grid imp-choice-grid-row">
										<label class="imp-choice"><input type="radio" name="backup_speed" value="safe"> <strong><?php esc_html_e( 'Safe', 'infinity-migratex-pro' ); ?></strong></label>
										<label class="imp-choice"><input type="radio" name="backup_speed" value="balanced" checked> <strong><?php esc_html_e( 'Balanced', 'infinity-migratex-pro' ); ?></strong></label>
										<label class="imp-choice"><input type="radio" name="backup_speed" value="turbo" <?php disabled( ! IMP_License::is_pro() ); ?>> <strong><?php esc_html_e( 'Turbo', 'infinity-migratex-pro' ); ?></strong> <?php echo IMP_License::is_pro() ? '' : '<span class="imp-edition imp-edition-pro">PRO</span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?></label>
									</div>
								</div>
								<?php $cloud_ready = IMP_License::is_pro() && class_exists( 'IMP_Remote' ) && IMP_Remote::is_ready(); ?>
								<?php if ( '' === imp_setting( 'cloud_provider', '' ) || ! IMP_License::is_pro() ) : ?>
									<p class="description" style="margin-bottom:12px;">☁ <?php
									echo IMP_License::is_pro()
										? esc_html__( 'Cloud sending is available — configure a destination in Settings → Cloud.', 'infinity-migratex-pro' )
										: esc_html__( 'Go Pro to send backups to Google Drive, Dropbox or FTP (Settings → Cloud).', 'infinity-migratex-pro' );
									?></p>
								<?php else : ?>
									<div class="imp-field imp-field-check">
										<label class="imp-check"><input type="checkbox" id="imp_backup_cloud" checked> ☁ <?php esc_html_e( 'Send to cloud after backup', 'infinity-migratex-pro' ); ?></label>
									</div>
								<?php endif; ?>
								<?php if ( IMP_License::is_pro() && IMP_Crypto::available() ) : ?>
									<div class="imp-field">
										<label class="imp-check"><input type="checkbox" id="imp_backup_encrypt"> 🔒 <?php esc_html_e( 'Secure Backup Encryption — protect the archive with AES-256', 'infinity-migratex-pro' ); ?></label>
										<div id="imp_backup_encrypt_fields" hidden style="margin-top:8px;">
											<input type="password" id="imp_backup_encpass" placeholder="<?php esc_attr_e( 'Encryption password (required)', 'infinity-migratex-pro' ); ?>" autocomplete="new-password" style="width:100%;">
											<p class="description"><?php esc_html_e( 'You will need this password to restore. It is never stored in the backup.', 'infinity-migratex-pro' ); ?></p>
										</div>
									</div>
								<?php elseif ( ! IMP_Crypto::available() ) : ?>
									<p class="description" style="margin-bottom:12px;">🔒 <?php esc_html_e( 'Backup encryption requires the PHP OpenSSL extension.', 'infinity-migratex-pro' ); ?></p>
								<?php else : ?>
									<p class="description" style="margin-bottom:12px;">🔒 <?php esc_html_e( 'AES-256 backup encryption is a Pro feature.', 'infinity-migratex-pro' ); ?> <button type="button" class="imp-btn imp-btn-small imp-btn-primary" data-imp-checkout="personal" style="margin-left:6px;">★ <?php esc_html_e( 'Go Pro', 'infinity-migratex-pro' ); ?></button></p>
								<?php endif; ?>
							</div>
						</details>
						<button type="submit" class="imp-btn imp-btn-primary"><?php esc_html_e( 'Create Backup', 'infinity-migratex-pro' ); ?></button>
					</form>
					<div data-imp-jobbox="backup" class="imp-jobbox"></div>
				</div>
			</section>

			<section class="imp-panel">
				<div class="imp-panel-head">
					<h3><?php esc_html_e( 'Schedule & retention', 'infinity-migratex-pro' ); ?></h3>
					<div class="imp-panel-actions">
						<a class="imp-btn imp-btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=infinity-migratex-pro-settings' ) ); ?>"><?php esc_html_e( 'Configure', 'infinity-migratex-pro' ); ?></a>
					</div>
				</div>
				<div class="imp-panel-body">
					<table class="imp-table imp-table-info">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Automatic backups', 'infinity-migratex-pro' ); ?></th>
								<td>
									<?php
									echo $settings['backup_schedule_enabled']
										? IMP_Admin::badge( 'pass', sprintf( /* translators: %s: schedule */ __( 'Enabled — %s', 'infinity-migratex-pro' ), $settings['backup_schedule'] ) ) // phpcs:ignore WordPress.Security.EscapeOutput
										: IMP_Admin::badge( 'neutral', __( 'Disabled', 'infinity-migratex-pro' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
									?>
								</td>
							</tr>
							<tr><th scope="row"><?php esc_html_e( 'Scheduled at', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( sprintf( '%02d:00', (int) $settings['backup_schedule_hour'] ) ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Contents', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( 'full' === $settings['backup_schedule_components'] ? __( 'Full site', 'infinity-migratex-pro' ) : $settings['backup_schedule_components'] ); ?></td></tr>
							<tr><th scope="row"><?php esc_html_e( 'Retention', 'infinity-migratex-pro' ); ?></th><td><?php echo esc_html( sprintf( /* translators: %s: count */ __( 'Keep last %s backups', 'infinity-migratex-pro' ), number_format_i18n( max( 1, (int) $settings['backup_retention'] ) ) ) ); ?></td></tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Next scheduled run', 'infinity-migratex-pro' ); ?></th>
								<td><?php
								$next = wp_next_scheduled( 'imp_cron_backup' );
								echo esc_html( $next ? mysql2date( get_option( 'date_format' ) . ' H:i', gmdate( 'Y-m-d H:i:s', $next ) ) : __( 'Not scheduled', 'infinity-migratex-pro' ) );
								?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>
		</div>

		<section class="imp-panel">
			<div class="imp-panel-head">
				<h3><?php esc_html_e( 'Existing backups', 'infinity-migratex-pro' ); ?></h3>
				<span class="imp-muted"><?php echo esc_html( sprintf( /* translators: %s: count */ _n( '%s backup stored', '%s backups stored', count( $backups ), 'infinity-migratex-pro' ), number_format_i18n( count( $backups ) ) ) ); ?></span>
			</div>
			<div class="imp-panel-body">
				<?php if ( empty( $backups ) ) : ?>
					<p class="imp-muted"><?php esc_html_e( 'No backups yet. Create your first one — it runs in the background and survives interruptions.', 'infinity-migratex-pro' ); ?></p>
				<?php else : ?>
					<table class="imp-table imp-table-list" data-imp-backups-table>
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Name', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Date', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Contents', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Size', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Files / Rows', 'infinity-migratex-pro' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Checksums', 'infinity-migratex-pro' ); ?></th>
								<th scope="col" class="imp-col-actions"><?php esc_html_e( 'Actions', 'infinity-migratex-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $backups as $backup ) : ?>
								<?php
								$download_url = wp_nonce_url(
									add_query_arg(
										array(
											'action' => 'imp_download_backup',
											'id'     => rawurlencode( $backup['id'] ),
										),
										admin_url( 'admin-post.php' )
									),
									'imp-download'
								);
								$size = 0;
								foreach ( (array) $backup['sizes'] as $value ) {
									$size += (int) $value;
								}
								?>
								<tr data-imp-backup-id="<?php echo esc_attr( $backup['id'] ); ?>">
									<td>
										<strong class="imp-backup-name"><?php echo esc_html( $backup['name'] ); ?></strong>
										<code class="imp-backup-id-label"><?php echo esc_html( $backup['id'] ); ?></code>
									</td>
									<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' H:i', $backup['created'] ) ); ?></td>
									<td>
										<?php
										$parts = array();
										if ( ! empty( $backup['components']['files'] ) ) {
											$parts[] = __( 'Files', 'infinity-migratex-pro' );
										}
										if ( ! empty( $backup['components']['database'] ) ) {
											$parts[] = __( 'Database', 'infinity-migratex-pro' );
										}
										echo esc_html( implode( ' + ', $parts ) ?: '—' );
										?>
									</td>
									<td><?php echo esc_html( imp_format_bytes( $size ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( (int) $backup['counts']['files'] ) . ' / ' . number_format_i18n( (int) $backup['counts']['db_rows'] ) ); ?></td>
									<td><code class="imp-checksum-state" data-imp-checksum-state><?php esc_html_e( 'Verify to check', 'infinity-migratex-pro' ); ?></code></td>
									<td class="imp-col-actions">
										<div class="imp-row-actions">
											<a class="imp-btn imp-btn-small imp-btn-ghost" href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Download', 'infinity-migratex-pro' ); ?></a>
											<a class="imp-btn imp-btn-small" href="<?php echo esc_url( admin_url( 'admin.php?page=infinity-migratex-pro-restore&backup=' . rawurlencode( $backup['id'] ) ) ); ?>"><?php esc_html_e( 'Restore', 'infinity-migratex-pro' ); ?></a>
											<button type="button" class="imp-btn imp-btn-small imp-btn-ghost" data-imp-action="backup-export" data-id="<?php echo esc_attr( $backup['id'] ); ?>"><?php esc_html_e( 'Export', 'infinity-migratex-pro' ); ?></button>
											<?php if ( $cloud_ready ) : ?>
												<button type="button" class="imp-btn imp-btn-small imp-btn-ghost" data-imp-action="backup-cloud" data-id="<?php echo esc_attr( $backup['id'] ); ?>" title="<?php esc_attr_e( 'Send this backup to your cloud destination', 'infinity-migratex-pro' ); ?>">☁ <?php esc_html_e( 'Cloud', 'infinity-migratex-pro' ); ?></button>
											<?php endif; ?>
											<button type="button" class="imp-btn imp-btn-small imp-btn-ghost" data-imp-action="backup-verify" data-id="<?php echo esc_attr( $backup['id'] ); ?>"><?php esc_html_e( 'Verify', 'infinity-migratex-pro' ); ?></button>
											<button type="button" class="imp-btn imp-btn-small imp-btn-ghost" data-imp-action="backup-rename" data-id="<?php echo esc_attr( $backup['id'] ); ?>" data-name="<?php echo esc_attr( $backup['name'] ); ?>"><?php esc_html_e( 'Rename', 'infinity-migratex-pro' ); ?></button>
											<button type="button" class="imp-btn imp-btn-small imp-btn-danger" data-imp-action="backup-delete" data-id="<?php echo esc_attr( $backup['id'] ); ?>"><?php esc_html_e( 'Delete', 'infinity-migratex-pro' ); ?></button>
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
	 * AJAX : actions sur les backups (verify/export/rename/delete).
	 */
	public static function ajax_action() {
		IMP_Security::ajax_guard( 'backup' );
		// Nonce explicite pour les analyseurs statiques (ajax_guard ci-dessus le vérifie déjà).
		check_ajax_referer( 'imp-admin', 'nonce', false );


		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$id = isset( $_POST['id'] ) ? IMP_Backup_Engine::sanitize_id( wp_unslash( $_POST['id'] ) ) : '';

		if ( '' === $id || null === IMP_Backup_Engine::read_manifest( $id ) ) {
			wp_send_json_error( array( 'code' => 'IMP-218', 'message' => IMP_Job::error_text( 'IMP-218' ) ), 404 );
		}

		switch ( $do ) {
			case 'cloud_send':
				if ( ! IMP_License::is_pro() || ! class_exists( 'IMP_Remote' ) ) {
					wp_send_json_error( array( 'code' => 'IMP-241', 'message' => IMP_Job::error_text( 'IMP-241' ) ), 402 );
				}
				if ( ! IMP_Remote::is_ready() ) {
					wp_send_json_error(
						array(
							'code'    => 'IMP-242',
							'message' => __( 'No cloud destination configured — pick one in Settings → Cloud.', 'infinity-migratex-pro' ),
						),
						422
					);
				}

				$result = IMP_Job::start(
					'remote',
					array(
						'backup'     => $id,
						'keep_local' => imp_setting( 'cloud_keep_local', 1 ),
						'name'       => __( 'Cloud upload', 'infinity-migratex-pro' ),
					)
				);
				if ( ! $result['ok'] ) {
					wp_send_json_error( array( 'code' => $result['error'], 'message' => IMP_Job::error_text( $result['error'] ) ), 409 );
				}
				wp_send_json_success( array( 'status' => IMP_Job::public_status() ) );
				break;

			case 'verify':
				$result = IMP_Integrity::verify_backup_dir( IMP_Backup_Engine::dir() . $id );
				wp_send_json_success(
					array(
						'ok'      => $result['ok'],
						'parts'   => $result['parts'],
						'missing' => $result['missing'],
					)
				);
				break;

			case 'export':
				$result = IMP_Package::create_from_backup( IMP_Backup_Engine::dir() . $id );
				if ( ! $result['ok'] ) {
					wp_send_json_error( array( 'code' => $result['error'], 'message' => IMP_Job::error_text( $result['error'] ) ), 500 );
				}
				wp_send_json_success(
					array(
						'message' => sprintf(
							/* translators: %s: size */
							__( 'Migration package created (%s) — available on the Packages page.', 'infinity-migratex-pro' ),
							imp_format_bytes( $result['size'] )
						),
					)
				);
				break;

			case 'rename':
				$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '';
				if ( '' === $name || ! IMP_Backup_Engine::rename( $id, $name ) ) {
					wp_send_json_error( array( 'code' => 'IMP-220', 'message' => __( 'Rename failed — check the storage is writable.', 'infinity-migratex-pro' ) ), 500 );
				}
				wp_send_json_success( array( 'name' => $name ) );
				break;

			case 'delete':
				if ( ! IMP_Backup_Engine::delete( $id ) ) {
					wp_send_json_error( array( 'code' => 'IMP-218', 'message' => __( 'Delete failed — check directory permissions.', 'infinity-migratex-pro' ) ), 500 );
				}
				wp_send_json_success( array( 'deleted' => $id ) );
				break;
		}

		wp_send_json_error( array( 'code' => 'IMP-224', 'message' => IMP_Job::error_text( 'IMP-224' ) ), 400 );
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
