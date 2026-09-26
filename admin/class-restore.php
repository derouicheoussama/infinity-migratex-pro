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
 * Page Restore : assistant Select Backup → Pre-Restore Check →
 * Components → Restore → Integrity → Complete, avec avertissement clair
 * et backup de sécurité automatique optionnel.
 */
final class IMP_Admin_Restore {

	/**
	 * Rendu.
	 */
	public static function render() {
		if ( ! IMP_Capabilities::user_can( 'restore' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'infinity-migratex-pro' ) );
		}

		IMP_Admin::page_open( 'restore' );

		$backups = IMP_Backup_Engine::all();
		$preselect = isset( $_GET['backup'] ) ? IMP_Backup_Engine::sanitize_id( wp_unslash( $_GET['backup'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation.
		?>
		<section class="imp-panel">
			<div class="imp-panel-head"><h3><?php esc_html_e( 'Restore assistant', 'infinity-migratex-pro' ); ?></h3></div>
			<div class="imp-panel-body">
				<div class="imp-notice imp-notice-warning">
					<strong><?php esc_html_e( 'Careful: restoring overwrites data.', 'infinity-migratex-pro' ); ?></strong>
					<?php esc_html_e( 'Selected files and database tables will be replaced by the backup content. A safety backup is strongly recommended and enabled by default below.', 'infinity-migratex-pro' ); ?>
				</div>

				<?php if ( empty( $backups ) ) : ?>
					<p class="imp-muted"><?php esc_html_e( 'No backups available yet — create one on the Backups page first.', 'infinity-migratex-pro' ); ?></p>
				<?php else : ?>
					<form id="imp-restore-form" data-imp-restore-form>
						<div class="imp-field">
							<label for="imp_restore_backup"><?php esc_html_e( '1. Select a backup', 'infinity-migratex-pro' ); ?> *</label>
							<select id="imp_restore_backup" name="restore_backup" required>
								<option value=""><?php esc_html_e( '— Choose a backup —', 'infinity-migratex-pro' ); ?></option>
								<?php foreach ( $backups as $backup ) : ?>
									<?php
									$size = 0;
									foreach ( (array) $backup['sizes'] as $value ) {
										$size += (int) $value;
									}
									$parts = array();
									if ( ! empty( $backup['components']['files'] ) ) {
										$parts[] = __( 'Files', 'infinity-migratex-pro' );
									}
									if ( ! empty( $backup['components']['database'] ) ) {
										$parts[] = __( 'DB', 'infinity-migratex-pro' );
									}
									?>
									<option value="<?php echo esc_attr( $backup['id'] ); ?>" <?php selected( $backup['id'], $preselect ); ?>>
										<?php
										echo esc_html(
											sprintf(
												/* translators: 1: name 2: date 3: parts 4: size */
												'%1$s — %2$s (%3$s, %4$s)',
												$backup['name'],
												mysql2date( get_option( 'date_format' ) . ' H:i', $backup['created'] ),
												implode( '+', $parts ),
												imp_format_bytes( $size )
											)
										);
										?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="imp-field">
							<label><?php esc_html_e( '2. Pre-restore check', 'infinity-migratex-pro' ); ?></label>
							<div class="imp-restore-precheck" data-imp-restore-precheck>
								<p class="imp-muted"><?php esc_html_e( 'Select a backup to run the integrity verification (manifest + checksums) automatically.', 'infinity-migratex-pro' ); ?></p>
							</div>
						</div>

						<div class="imp-field">
							<label><?php esc_html_e( '3. Components to restore', 'infinity-migratex-pro' ); ?></label>
							<div class="imp-checks" data-imp-restore="components">
								<label class="imp-check"><input type="checkbox" value="core" checked> <?php esc_html_e( 'WordPress files (core, root)', 'infinity-migratex-pro' ); ?></label>
								<label class="imp-check"><input type="checkbox" value="plugins" checked> <?php esc_html_e( 'Plugins', 'infinity-migratex-pro' ); ?></label>
								<label class="imp-check"><input type="checkbox" value="themes" checked> <?php esc_html_e( 'Themes', 'infinity-migratex-pro' ); ?></label>
								<label class="imp-check"><input type="checkbox" value="uploads" checked> <?php esc_html_e( 'Uploads', 'infinity-migratex-pro' ); ?></label>
								<label class="imp-check"><input type="checkbox" value="mu-plugins" checked> <?php esc_html_e( 'mu-plugins', 'infinity-migratex-pro' ); ?></label>
								<label class="imp-check"><input type="checkbox" value="wpcontent" checked> <?php esc_html_e( 'Other wp-content', 'infinity-migratex-pro' ); ?></label>
								<label class="imp-check"><input type="checkbox" value="database" checked> <?php esc_html_e( 'Database', 'infinity-migratex-pro' ); ?></label>
							</div>
							<p class="imp-hint"><?php esc_html_e( 'wp-config.php is never restored: your current database connection stays untouched.', 'infinity-migratex-pro' ); ?></p>
						</div>

						<div class="imp-field">
							<label for="imp_restore_encpass"><?php esc_html_e( 'Decryption password (only if the backup was encrypted)', 'infinity-migratex-pro' ); ?></label>
							<input type="password" id="imp_restore_encpass" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Leave empty for non-encrypted backups', 'infinity-migratex-pro' ); ?>">
						</div>

						<div class="imp-field imp-field-check">
							<label class="imp-check">
								<input type="checkbox" id="imp_restore_safety" checked>
								<?php esc_html_e( 'Create a full safety backup before restoring (recommended)', 'infinity-migratex-pro' ); ?>
							</label>
							<label class="imp-check">
								<input type="checkbox" id="imp_restore_maintenance" checked>
								🚧 <?php esc_html_e( 'Put the site in maintenance mode during the restore (recommended)', 'infinity-migratex-pro' ); ?>
							</label>
							<label class="imp-check">
								<input type="checkbox" id="imp_restore_reurl" <?php checked( IMP_License::is_pro() ); ?> <?php disabled( ! IMP_License::is_pro() ); ?>>
								🔗 <?php esc_html_e( 'Rewrite old-site URLs to this site’s URL after import', 'infinity-migratex-pro' ); ?>
								<?php echo IMP_License::is_pro() ? '' : '<span class="imp-edition imp-edition-pro">PRO</span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?>
							</label>
							<p class="description" style="margin-top:6px;"><?php esc_html_e( 'Cross-site condition: both sites must use the same database table prefix (wp_ by default) — then the restore replaces the tables and rewrites every old URL (posts, Elementor, WooCommerce) to this site’s address.', 'infinity-migratex-pro' ); ?></p>
						</div>

						<button type="submit" class="imp-btn imp-btn-danger" data-imp-confirm="restore"><?php esc_html_e( 'Restore now', 'infinity-migratex-pro' ); ?></button>
						<div data-imp-jobbox="restore" class="imp-jobbox"></div>
					</form>
				<?php endif; ?>
			</div>
		</section>
		<?php

		IMP_Admin::page_close();
	}
}

// ∞ INFINITY CODER — Derouiche Oussama · https://www.derouicheoussama.com
